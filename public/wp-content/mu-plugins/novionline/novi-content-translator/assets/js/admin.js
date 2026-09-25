document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.noviContentTranslator === 'undefined' || !window.noviContentTranslator) {
        return
    }

    const button = document.getElementById('novi-create-translations');
    const messages = document.getElementById('novi-translator-messages');
    const checkboxes = document.querySelectorAll('input[name="novi_target_languages[]"]');
    const languageMetaBySlug = {};
    checkboxes.forEach(function (cb) {
        const slug = cb.value || '';
        const row = cb.closest('.novi-language-row');
        const flagImg = row ? row.querySelector('.novi-site-flag img') : null;
        const nameEl = row ? row.querySelector('.novi-site-name-label') : null;
        languageMetaBySlug[slug] = {
            flag: flagImg ? flagImg.getAttribute('src') : '',
            name: nameEl ? nameEl.textContent.trim() : slug
        };
    });
    const selectAllButton = document.querySelector('#novi-content-translator-metabox .novi-select-all');
    const clearAllButton = document.querySelector('#novi-content-translator-metabox .novi-clear-all');

    if (!button) {
        return;
    }

    if (selectAllButton) {
        selectAllButton.addEventListener('click', function (e) {
            e.preventDefault();
            checkboxes.forEach(function (cb) {
                cb.checked = true;
            });
        });
    }

    if (clearAllButton) {
        clearAllButton.addEventListener('click', function (e) {
            e.preventDefault();
            checkboxes.forEach(function (cb) {
                cb.checked = false;
            });
        });
    }

    button.addEventListener('click', function (e) {
        e.preventDefault();

        // Check if post is saved
        if (!noviContentTranslator.postId || noviContentTranslator.postId === 0) {
            showMessage('error', noviContentTranslator.strings.saveFirst);
            return;
        }

        const selectedLanguages = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        if (selectedLanguages.length === 0) {
            showMessage('error', noviContentTranslator.strings.error + ' ' + 'Please select at least one language.');
            return;
        }

        // Check for existing translations first
        checkExistingTranslations(selectedLanguages);
    });

    function checkExistingTranslations(targetLanguages) {
        showMessage('info', noviContentTranslator.strings.creating);

        const formData = new FormData();
        formData.append('action', 'novi_check_existing_translations');
        formData.append('nonce', noviContentTranslator.nonce);
        formData.append('post_id', noviContentTranslator.postId);
        formData.append('target_languages', JSON.stringify(targetLanguages));

        fetch(noviContentTranslator.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.data.has_existing && data.data.existing_translations.length > 0) {
                        showConfirmationDialog(data.data.existing_translations, targetLanguages);
                    } else {
                        createTranslations(targetLanguages, false);
                    }
                } else {
                    showMessage('error', data.data.message || noviContentTranslator.strings.error);
                }
            })
            .catch(() => {
                showMessage('error', noviContentTranslator.strings.error);
            });
    }

    function showConfirmationDialog(existingTranslations, targetLanguages) {
        let message = noviContentTranslator.strings.confirmOverwrite + '\n\n';

        existingTranslations.forEach(function (translation) {
            message += '- ' + translation.post_title;
            if (translation.edit_link) {
                message += ' (' + translation.edit_link + ')';
            }
            message += '\n';
        });

        if (confirm(message)) {
            createTranslations(targetLanguages, true);
        } else {
            clearMessages();
        }
    }

    function createTranslations(targetLanguages, overwriteExisting) {
        button.disabled = true;

        // Immediately show an indeterminate loader so the user sees work is happening
        showTranslationProgressUI(noviContentTranslator.strings.creating);

        // Preflight: get string count for progress message
        const countFormData = new FormData();
        countFormData.append('action', 'novi_count_translation_strings');
        countFormData.append('nonce', noviContentTranslator.nonce);
        countFormData.append('post_id', noviContentTranslator.postId);

        fetch(noviContentTranslator.ajaxUrl, {
            method: 'POST',
            body: countFormData
        })
            .then(response => response.json())
            .then(function (countData) {
                let progressMessage = noviContentTranslator.strings.creating;
                if (countData.success && countData.data.total_strings !== undefined) {
                    const total = countData.data.total_strings;
                    const languageCount = targetLanguages.length;
                    if (languageCount > 1 && noviContentTranslator.strings.translatingStrings) {
                        progressMessage = noviContentTranslator.strings.translatingStrings
                            .replace('%1$d', total)
                            .replace('%2$d', languageCount);
                    } else if (noviContentTranslator.strings.translatingStringsSingle) {
                        progressMessage = noviContentTranslator.strings.translatingStringsSingle.replace('%d', total);
                    }
                }

                // Update the loader message (still indeterminate, since the current flow
                // is a single AJAX request for all languages).
                showTranslationProgressUI(progressMessage);

                const formData = new FormData();
                formData.append('action', 'novi_create_translations');
                formData.append('nonce', noviContentTranslator.nonce);
                formData.append('post_id', noviContentTranslator.postId);
                formData.append('target_languages', JSON.stringify(targetLanguages));
                formData.append('overwrite_existing', overwriteExisting ? '1' : '0');

                return fetch(noviContentTranslator.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
            })
            .then(response => response.json())
            .then(data => {
                button.disabled = false;

                if (data.success) {
                    showSuccessMessage(data.data);

                    // Uncheck all checkboxes
                    checkboxes.forEach(cb => cb.checked = false);
                } else {
                    showMessage('error', data.data.message || noviContentTranslator.strings.error);
                }
            })
            .catch(() => {
                button.disabled = false;
                showMessage('error', noviContentTranslator.strings.error);
            });
    }

    function showSuccessMessage(data) {
        let html = '<div class="novi-success-message">';
        html += '<p><strong>' + noviContentTranslator.strings.success + '</strong></p>';

        if (data.results && data.results.length > 0) {
            html += '<ul class="novi-translator-sites">';
            data.results.forEach(function (result) {
                const meta = result.language ? (languageMetaBySlug[result.language] || {}) : {};
                const langFlag = meta.flag || '';

                html += '<li class="novi-language-row novi-language-row--compact">';
                html += '<div class="novi-language-main">';
                if (langFlag) {
                    html += '<span class="novi-site-flag" aria-hidden="true"><img src="' + langFlag + '" alt=""></span>';
                }
                html += '</div>';

                if (result.edit_link) {
                    html += '<a class="novi-existing-translation-link" href="' + result.edit_link + '" target="_blank" rel="noopener noreferrer">';
                    html += '<span class="novi-existing-translation-link-inner">';
                    html += '<small>' + (result.post_title || '') + '</small>';
                    html += '</span>';
                    html += '</a>';
                } else {
                    html += '<span>' + (result.post_title || '') + '</span>';
                }
                html += '</li>';
            });
            html += '</ul>';
        }

        if (data.errors && data.errors.length > 0) {
            html += '<div class="novi-error-message">';
            html += '<p><strong>Errors:</strong></p>';
            html += '<ul>';
            data.errors.forEach(function (error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }

        html += '</div>';
        messages.innerHTML = html;
    }

    function showTranslationProgressUI(progressMessage) {
        const safeMessage = progressMessage || '';
        messages.innerHTML = '' +
            '<div class="novi-info-message nct-translation-progress">' +
                '<p>' + safeMessage + '</p>' +
                '<div class="nct-translation-progress__bar" role="progressbar" aria-busy="true" aria-label="' + safeMessage + '">' +
                    '<div class="nct-translation-progress__bar-fill"></div>' +
                '</div>' +
            '</div>';
    }

    function showMessage(type, message) {
        const className = type === 'error' ? 'novi-error-message' : 'novi-info-message';
        messages.innerHTML = '<div class="' + className + '"><p>' + message + '</p></div>';
    }

    function clearMessages() {
        messages.innerHTML = '';
    }
});