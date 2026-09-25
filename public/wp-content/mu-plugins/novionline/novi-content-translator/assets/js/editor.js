/* global wp, noviContentTranslator */

(function () {
    //nct debug: dump parsed nectar-blocks/text attrs in editor (only when WP_DEBUG localizes debug=true)
    function debugDumpNectarTextBlocks() {
        try {
            if (!window.noviContentTranslator || !window.noviContentTranslator.debug) return
            if (!window.wp || !wp.data || !wp.data.select) return
            const store = wp.data.select('core/block-editor')
            if (!store || !store.getBlocks) return

            const blocks = store.getBlocks() || []
            const texts = []
            const walk = (arr) => {
                arr.forEach((b) => {
                    if (!b) return
                    const name = b.name || ''
                    if (name === 'nectar-blocks/text') {
                        texts.push({
                            clientId: b.clientId,
                            blockId: b.attributes && b.attributes.blockId ? b.attributes.blockId : '',
                            customId: b.attributes && b.attributes.customId ? b.attributes.customId : '',
                            textElement: b.attributes && b.attributes.textElement ? b.attributes.textElement : '',
                            className: b.attributes && b.attributes.className ? b.attributes.className : '',
                            content: b.attributes && b.attributes.content ? b.attributes.content : '',
                        })
                    }
                    if (b.innerBlocks && b.innerBlocks.length) {
                        walk(b.innerBlocks)
                    }
                })
            }
            walk(blocks)
            if (texts.length) {
                // eslint-disable-next-line no-console
                console.log('Novi content translator: editor nectar-blocks/text snapshot', texts.slice(0, 10))
            }
        } catch (e) {
            //fail silently
        }
    }

    function bootstrap() {
        if (typeof wp === 'undefined') {
            //nothing else we can do here
            return;
        }

        const { registerPlugin } = wp.plugins || {};
        if (!registerPlugin) {
            return;
        }
        const { PluginSidebar: PostPluginSidebar } = wp.editPost || {};
        const { PluginSidebar: SitePluginSidebar } = wp.editSite || {};
        const {
            Button,
            CheckboxControl,
            Modal,
            Dialog,
            Spinner,
            Notice,
            TabPanel,
            SelectControl,
            TextControl,
            ToggleControl,
            HelpText,
            Icon,
            Tooltip,
            Popover,
        } = wp.components || {};
        const ModalComponent = Modal || Dialog;
        const { useSelect } = wp.data;
        const { useState, useCallback, useEffect, useRef, Fragment } = wp.element;
        const { __, sprintf } = wp.i18n || { __: (s) => s, sprintf: (f, n) => f.replace('%d', n) };


        function getCurrentEntity() {
            const editor = wp.data && wp.data.select ? wp.data.select('core/editor') : null;
            if (editor && editor.getCurrentPostId && editor.getCurrentPostType) {
                const id = editor.getCurrentPostId();
                const type = editor.getCurrentPostType();
                if (id && type) {
                    return { id, type };
                }
            }

            const editSite = wp.data && wp.data.select ? wp.data.select('core/edit-site') : null;
            if (editSite) {
                //newer api: getEditedPostContext
                if (editSite.getEditedPostContext) {
                    const context = editSite.getEditedPostContext();
                    if (context && context.postId && context.postType) {
                        return { id: context.postId, type: context.postType };
                    }
                }
                if (editSite.getEditedPostId && editSite.getEditedPostType) {
                    const id = editSite.getEditedPostId();
                    const type = editSite.getEditedPostType();
                    if (id && type) {
                        return { id, type };
                    }
                }
            }

            // Fallback to localized context (e.g. Site Editor patterns)
            if (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.postId) {
                return {
                    id: noviContentTranslator.postId,
                    type: noviContentTranslator.postType || ''
                };
            }

            return { id: null, type: null };
        }

        function useEntityContext() {
            return useSelect(() => getCurrentEntity(), []);
        }

        function NoviSidebarIcon() {
        return (
            <svg
                className="novi-sidebar-icon"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 48 48"
                aria-hidden="true"
                focusable="false"
            >
                <path
                    fillRule="evenodd"
                    clipRule="evenodd"
                    d="M0.662983 0H10.8095C10.9853 0 11.154 0.0698499 11.2783 0.194183L42.2637 31.1796L46.3994 27.044L36.7217 17.3662C36.5974 17.2419 36.5275 17.0733 36.5275 16.8974V0.662983C36.5275 0.296828 36.8243 0 37.1905 0H47.337C47.7032 0 48 0.296828 48 0.662983V47.337C48 47.7032 47.7032 48 47.337 48H37.1905C37.0147 48 36.846 47.9301 36.7217 47.8058L5.73625 16.8204L1.60058 20.956L11.2783 30.6338C11.4026 30.7581 11.4725 30.9267 11.4725 31.1026V47.337C11.4725 47.7032 11.1757 48 10.8095 48H0.662983C0.296828 48 0 47.7032 0 47.337V0.662983C0 0.296828 0.296828 0 0.662983 0ZM1.32597 2.26357V10.5349L5.73625 14.9452L9.87191 10.8095L1.32597 2.26357ZM10.8095 11.7471L6.67385 15.8828L36.5275 45.7364V37.4651L10.8095 11.7471ZM37.1905 36.2529L11.4725 10.5349L11.4725 2.26357L41.3262 32.1172L37.1905 36.2529ZM37.8535 38.7911V46.674H45.7364L37.8535 38.7911ZM46.674 45.7364V37.4651L42.2637 33.0548L38.1281 37.1905L46.674 45.7364ZM43.2013 32.1172L46.674 35.5899V28.6445L43.2013 32.1172ZM46.674 25.4434L37.8535 16.6228V1.32597H46.674V25.4434ZM10.1465 1.32597L10.1465 9.20893L2.26357 1.32597H10.1465ZM4.79865 15.8828L1.32597 12.4101V19.3555L4.79865 15.8828ZM1.32597 22.5566V46.674H10.1465V31.3772L1.32597 22.5566Z"
                    fill="#945EF0"
                />
            </svg>
        );
    }

        function TranslatorPanel({ deeplStatus, requireSettingsTab }) {
        const { id: postId } = useEntityContext();
        const [languages, setLanguages] = useState(null);
        const [currentLang, setCurrentLang] = useState(null);
        const [selected, setSelected] = useState([]);
        const [loading, setLoading] = useState(false);
        const [translating, setTranslating] = useState(false);
        const [confirmOverwriteOpen, setConfirmOverwriteOpen] = useState(false);
        const [confirmOverwriteExisting, setConfirmOverwriteExisting] = useState([]);
        const [confirmReusableOpen, setConfirmReusableOpen] = useState(false);
        const [confirmReusableMissing, setConfirmReusableMissing] = useState(null);
        const [pendingCreateParams, setPendingCreateParams] = useState(null);
        const [message, setMessage] = useState(null);
        const [messageType, setMessageType] = useState(null);

        const isDeeplValid = !!(deeplStatus && deeplStatus.valid);
        const isQuotaExhausted = !!(deeplStatus && deeplStatus.quota_exhausted);

        const ajaxUrl = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.ajaxUrl)
            ? noviContentTranslator.ajaxUrl
            : (window.ajaxurl || '');

        const nonce = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.nonce)
            ? noviContentTranslator.nonce
            : '';

        const strings = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.strings)
            ? noviContentTranslator.strings
            : {};

        const loadLanguages = useCallback(() => {
            if (!postId || !ajaxUrl || !nonce || languages !== null) {
                return;
            }

            setLoading(true);

            const formData = new window.FormData();
            formData.append('action', 'novi_get_languages');
            formData.append('nonce', nonce);
            formData.append('post_id', postId);

            window.fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data && data.success && data.data) {
                        setLanguages(data.data.target_languages || {});
                        setCurrentLang(data.data.current_language || null);
                    } else {
                        setMessage((data && data.data && data.data.message) || strings.error || __('Failed to load languages.', 'novi-content-translator'));
                        setMessageType('error');
                    }
                })
                .catch(() => {
                    setMessage(strings.error || __('An error occurred while loading languages.', 'novi-content-translator'));
                    setMessageType('error');
                })
                .finally(() => {
                    setLoading(false);
                });
        }, [postId, ajaxUrl, nonce, languages, strings]);

        if (!postId) {
            return (
                <div className="novi-content-translator-panel">
                    <Notice status="warning" isDismissible={false}>
                        {strings.saveFirst || __('Please save this post first before creating translations.', 'novi-content-translator')}
                    </Notice>
                </div>
            );
        }

        if (languages === null && !loading) {
            loadLanguages();
        }

        const onToggleLanguage = (slug) => {
            setSelected((prev) =>
                prev.includes(slug)
                    ? prev.filter((s) => s !== slug)
                    : [...prev, slug]
            );
        };

        const onSelectAll = () => {
            if (!languages) return;
            setSelected(Object.keys(languages));
        };

        const onClearAll = () => {
            setSelected([]);
        };

        const showMessage = (type, content) => {
            setMessage(content);
            setMessageType(type);
        };

        const renderTranslationProgressUI = (progressMessage) => (
            <div className="nct-translation-progress">
                <p>{progressMessage}</p>
                <div
                    className="nct-translation-progress__bar"
                    role="progressbar"
                    aria-busy="true"
                    aria-label={progressMessage || ''}
                >
                    <div className="nct-translation-progress__bar-fill" />
                </div>
            </div>
        );

        const refreshLanguages = () => {
            setLanguages(null);
            setCurrentLang(null);
        };

        const runPreflightCount = (targetLanguages) => {
            const formData = new window.FormData();
            formData.append('action', 'novi_count_translation_strings');
            formData.append('nonce', nonce);
            formData.append('post_id', postId);

            return window.fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
            })
                .then((res) => res.json())
                .then((countData) => {
                    let progressMessage = strings.creating || __('Duplicating to selected languages...', 'novi-content-translator');
                    if (countData && countData.success && countData.data && typeof countData.data.total_strings !== 'undefined') {
                        const total = countData.data.total_strings;
                        const languageCount = targetLanguages.length;
                        if (languageCount > 1 && strings.translatingStrings) {
                            progressMessage = strings.translatingStrings
                                .replace('%1$d', total)
                                .replace('%2$d', languageCount);
                        } else if (strings.translatingStringsSingle) {
                            progressMessage = strings.translatingStringsSingle.replace('%d', total);
                        }
                    }
                    showMessage('info', renderTranslationProgressUI(progressMessage));
                })
                .catch(() => {
                    // fall back to generic creating message
                    showMessage(
                        'info',
                        renderTranslationProgressUI(strings.creating || __('Duplicating to selected languages...', 'novi-content-translator'))
                    );
                });
        };

        const performCreateTranslations = (targetLanguages, overwriteExisting, createMissingReusableBlocks) => {
            return runPreflightCount(targetLanguages).then(() => {
                const formData = new window.FormData();
                formData.append('action', 'novi_create_translations');
                formData.append('nonce', nonce);
                formData.append('post_id', postId);
                formData.append('target_languages', JSON.stringify(targetLanguages));
                formData.append('overwrite_existing', overwriteExisting ? '1' : '0');
                formData.append('create_missing_reusable_blocks', createMissingReusableBlocks ? '1' : '0');

                return window.fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data && data.success && data.data) {
                            const payload = data.data;
                            const results = Array.isArray(payload.results) ? payload.results : [];
                            const errors = Array.isArray(payload.errors) ? payload.errors : [];
                            const reusableBlocks = payload.reusable_blocks || {};
                            const reusableTotals = reusableBlocks.totals || { created: 0, existing: 0, failed: 0 };
                            const hasOnlyErrors = results.length === 0 && errors.length > 0;

                            if (hasOnlyErrors) {
                                showMessage('error', (
                                    <div className="novi-translator-messages-inline">
                                        <div className="novi-error-message">
                                            <p>
                                                <strong>{__('Translation failed. No posts were created.', 'novi-content-translator')}</strong>
                                            </p>
                                            <ul>
                                                {errors.map((err, index) => (
                                                    <li key={index}>{err}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                ));
                                return;
                            }

                            const reusedCount = reusableTotals.existing || 0;
                            const createdCount = reusableTotals.created || 0;
                            const baseSuccessMessageRaw = payload.message || strings.success || __('Posts duplicated successfully!', 'novi-content-translator');
                            const baseSuccessMessage = String(baseSuccessMessageRaw).replace(/[.,;:\s]+$/u, '');
                            let patternSummary = '';
                            if (createdCount > 0 && reusedCount > 0) {
                                patternSummary = sprintf(
                                    __('created %1$d block pattern(s) and reused %2$d block pattern(s)', 'novi-content-translator'),
                                    createdCount,
                                    reusedCount
                                );
                            } else if (createdCount > 0) {
                                patternSummary = sprintf(
                                    __('created %d block pattern(s)', 'novi-content-translator'),
                                    createdCount
                                );
                            } else if (reusedCount > 0) {
                                patternSummary = sprintf(
                                    __('reused %d block pattern(s)', 'novi-content-translator'),
                                    reusedCount
                                );
                            }

                            const fullSuccessMessage = patternSummary !== ''
                                ? `${baseSuccessMessage}, ${patternSummary}`
                                : baseSuccessMessage;

                            showMessage('success', (
                                <div className="novi-translator-messages-inline">
                                    <div className="novi-success-message">
                                    <p>
                                        <strong>
                                            {fullSuccessMessage}
                                        </strong>
                                    </p>
                                    {results.length > 0 && (
                                        <ul className="novi-ct-panel__sites">
                                            {results.map((result) => {
                                                const langSlug = result.language || '';
                                                const langMeta = languages && langSlug && languages[langSlug] ? languages[langSlug] : null;
                                                const flag = langMeta && langMeta.flag ? langMeta.flag : '';

                                                return (
                                                <li
                                                    key={result.post_id || result.language}
                                                    className="novi-ct-panel__language-row novi-ct-panel__language-row--compact"
                                                >
                                                        <div className="novi-ct-panel__language-main">
                                                            {flag ? (
                                                                <span className="novi-ct-panel__site-flag" aria-hidden="true">
                                                                    <img src={flag} alt="" />
                                                                </span>
                                                            ) : null}
                                                        </div>

                                                        {result.edit_link ? (
                                                            <a
                                                                className="novi-ct-panel__translation-link"
                                                                href={result.edit_link}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <span className="novi-ct-panel__translation-link-inner">
                                                                    <small>{result.post_title || ''}</small>
                                                                </span>
                                                            </a>
                                                        ) : (
                                                            <span className="novi-ct-panel__translation-link">
                                                                {result.post_title || ''}
                                                            </span>
                                                        )}
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                    {errors.length > 0 && (
                                        <div className="novi-error-message">
                                            <p>
                                                <strong>{__('Errors:', 'novi-content-translator')}</strong>
                                            </p>
                                            <ul>
                                                {errors.map((err, index) => (
                                                    <li key={index}>{err}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    </div>
                                </div>
                            ));

                            const unsupportedBlocksRaw = Array.isArray(payload.unsupported_blocks) ? payload.unsupported_blocks : [];
                            const unsupportedBlocks = Array.from(
                                new Set(
                                    unsupportedBlocksRaw
                                        .map((name) => String(name || '').trim())
                                        .filter((name) => name !== '')
                                )
                            ).sort((a, b) => a.localeCompare(b));

                            if (unsupportedBlocks.length > 0) {
                                const quotedUnsupported = unsupportedBlocks.map((name) => `"${name}"`).join(', ');
                                const unsupportedMessage = sprintf(
                                    __('Please note: blocks %s were skipped because they are not supported yet. Contact info@novionline.nl to request support.', 'novi-content-translator'),
                                    quotedUnsupported
                                );
                                const noticesStore = wp.data && wp.data.dispatch ? wp.data.dispatch('core/notices') : null;
                                if (noticesStore && typeof noticesStore.createNotice === 'function') {
                                    noticesStore.createNotice('warning', unsupportedMessage, {
                                        type: 'snackbar',
                                        isDismissible: true,
                                        explicitDismiss: true,
                                    });
                                } else {
                                    showMessage('warning', unsupportedMessage);
                                }
                            }

                            // Unselect languages and refresh list so existing translations update.
                            setSelected([]);
                            refreshLanguages();
                        } else {
                            showMessage(
                                'error',
                                (data && data.data && data.data.message) || strings.error || __('An error occurred while duplicating posts.', 'novi-content-translator')
                            );
                        }
                    })
                    .catch(() => {
                        showMessage('error', strings.error || __('An error occurred while duplicating posts.', 'novi-content-translator'));
                    });
            });
        };

        const checkExistingTranslations = (targetLanguages) => {
            const formData = new window.FormData();
            formData.append('action', 'novi_check_existing_translations');
            formData.append('nonce', nonce);
            formData.append('post_id', postId);
            formData.append('target_languages', JSON.stringify(targetLanguages));

            return window.fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
            })
                .then((res) => res.json());
        };

        const closeReusableModal = () => {
            setConfirmReusableOpen(false);
            setConfirmReusableMissing(null);
        };

        const startCreateTranslationsFlow = (targetLanguages, overwriteExisting, createMissingReusableBlocks) => {
            setTranslating(true);
            return performCreateTranslations(targetLanguages, overwriteExisting, createMissingReusableBlocks).finally(() => {
                setLoading(false);
                setTranslating(false);
            });
        };

        const onCreateTranslations = () => {
            if (!selected.length) {
                showMessage('error', strings.error || __('Please select at least one language.', 'novi-content-translator'));
                return;
            }

            if (!isDeeplValid || isQuotaExhausted) {
                showMessage(
                    'error',
                    isQuotaExhausted
                        ? __('DeepL quota is exhausted. Please wait for quota refresh or increase your quota.', 'novi-content-translator')
                        : (strings.deeplInvalid ||
                            __('DeepL API key is missing or invalid. Open Settings to set a valid key.', 'novi-content-translator'))
                );
                if (typeof requireSettingsTab === 'function') {
                    requireSettingsTab();
                }
                return;
            }

            setLoading(true);
            setTranslating(false);
            showMessage('info', strings.creating || __('Duplicating to selected languages...', 'novi-content-translator'));

            checkExistingTranslations(selected)
                .then((data) => {
                    if (!data || !data.success || !data.data) {
                        showMessage('error', (data && data.data && data.data.message) || strings.error || __('An error occurred while duplicating posts.', 'novi-content-translator'));
                        setLoading(false);
                        return;
                    }

                    const existing = data.data.existing_translations || [];
                    const hasExisting = data.data.has_existing && existing.length > 0;
                    const missingReusable = data.data.missing_reusable_blocks || null;
                    const hasMissingReusable = !!(missingReusable && missingReusable.has_missing);

                    if (!hasExisting) {
                        if (hasMissingReusable) {
                            setPendingCreateParams({
                                targetLanguages: selected.slice(),
                                overwriteExisting: false,
                            });
                            setConfirmReusableMissing(missingReusable);
                            setConfirmReusableOpen(true);
                            showMessage('info', '');
                            return null;
                        }

                        return startCreateTranslationsFlow(selected, false, false);
                    }

                    // open overwrite confirmation modal
                    setConfirmOverwriteExisting(existing);
                    setConfirmOverwriteOpen(true);
                    setConfirmReusableMissing(hasMissingReusable ? missingReusable : null);
                    setPendingCreateParams({
                        targetLanguages: selected.slice(),
                        overwriteExisting: true,
                    });

                    // keep loading=true while user decides
                    showMessage('info', '');
                    return null;
                })
                .catch(() => {
                    showMessage('error', strings.error || __('An error occurred while duplicating posts.', 'novi-content-translator'));
                    setLoading(false);
                    setTranslating(false);
                });
        };

        const closeOverwriteModal = () => {
            setConfirmOverwriteOpen(false);
            setConfirmOverwriteExisting([]);
        };

        const onConfirmOverwrite = () => {
            closeOverwriteModal();
            if (confirmReusableMissing && confirmReusableMissing.has_missing) {
                setConfirmReusableOpen(true);
                showMessage('info', '');
                return;
            }

            const params = pendingCreateParams || { targetLanguages: selected.slice(), overwriteExisting: true };
            startCreateTranslationsFlow(params.targetLanguages, params.overwriteExisting, false);
        };

        const onCancelOverwrite = () => {
            closeOverwriteModal();
            closeReusableModal();
            setPendingCreateParams(null);
            setLoading(false);
            setTranslating(false);
            showMessage('info', '');
        };

        const onConfirmReusableCreate = () => {
            const params = pendingCreateParams || { targetLanguages: selected.slice(), overwriteExisting: false };
            closeReusableModal();
            setPendingCreateParams(null);
            startCreateTranslationsFlow(params.targetLanguages, params.overwriteExisting, true);
        };

        const onSkipReusableCreate = () => {
            const params = pendingCreateParams || { targetLanguages: selected.slice(), overwriteExisting: false };
            closeReusableModal();
            setPendingCreateParams(null);
            showMessage('warning', __('Block patterns without translations will keep their original references.', 'novi-content-translator'));
            startCreateTranslationsFlow(params.targetLanguages, params.overwriteExisting, false);
        };

        return (
            <div className="novi-ct-panel">
                {currentLang && (
                    <p className="novi-ct-panel__summary">
                        <span className="novi-ct-panel__source-label">
                            <small className="novi-ct-panel__source-prefix">
                                {__('Source language:', 'novi-content-translator')}
                            </small>
                            {currentLang.flag && (
                                <span className="novi-ct-panel__site-flag">
                                    <img
                                        src={currentLang.flag}
                                        alt={currentLang.name || currentLang.slug}
                                    />
                                </span>
                            )}
                            <small className="novi-ct-panel__site-name">
                                {currentLang.name || currentLang.slug}
                            </small>
                        </span>
                    </p>
                )}

                <p className="novi-ct-panel__description">
                    {__(
                        'Select one or more languages to create or update translations based on this post using automatic DeepL translation.',
                        'novi-content-translator'
                    )}
                </p>

                <div className="novi-ct-panel__actions">
                    <Button
                        isLink
                        className="novi-ct-panel__action novi-ct-panel__action--select-all"
                        onClick={onSelectAll}
                    >
                        <span className="novi-ct-panel__action-label">
                            {Icon && <span className="novi-ct-panel__action-icon" aria-hidden="true"><Icon icon="yes-alt" size={16} /></span>}
                            <span>{__('Select all', 'novi-content-translator')}</span>
                        </span>
                    </Button>
                    <span className="novi-ct-panel__actions-sep">·</span>
                    <Button
                        isLink
                        className="novi-ct-panel__action novi-ct-panel__action--clear"
                        onClick={onClearAll}
                    >
                        <span className="novi-ct-panel__action-label">
                            {Icon && <span className="novi-ct-panel__action-icon" aria-hidden="true"><Icon icon="no-alt" size={16} /></span>}
                            <span>{__('Clear selection', 'novi-content-translator')}</span>
                        </span>
                    </Button>
                </div>

                {loading && !languages && (
                    <div className="novi-ct-panel__sites">
                        <Spinner />
                    </div>
                )}

                {languages && (
                    <div className="novi-ct-panel__sites">
                        {Object.entries(languages).map(([slug, lang]) => {
                            const hasExisting = !!lang.edit_link;
                            return (
                                <div className="novi-ct-panel__language-row" key={slug}>
                                    <div className="novi-ct-panel__language-main">
                                        <CheckboxControl
                                            label={
                                                <span className="novi-ct-panel__site-label">
                                                    {lang.flag && (
                                                        <span className="novi-ct-panel__site-flag">
                                                            <img
                                                                src={lang.flag}
                                                                alt={lang.name || slug}
                                                            />
                                                        </span>
                                                    )}
                                                    <strong className="novi-ct-panel__site-name">
                                                        {lang.name || slug}
                                                    </strong>
                                                </span>
                                            }
                                            checked={selected.includes(slug)}
                                            onChange={() => onToggleLanguage(slug)}
                                        />
                                    </div>
                                    {hasExisting && (
                                        <div className="novi-ct-panel__translation-link-wrap">
                                            <small className="novi-ct-panel__translation-prefix">
                                                {__('Translation:', 'novi-content-translator')}
                                            </small>
                                            <a
                                                className="novi-ct-panel__translation-link"
                                                href={lang.edit_link}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <span className="novi-ct-panel__translation-link-inner">
                                                    <small>{lang.post_title || ''}</small>
                                                    {Icon && <span className="novi-ct-panel__link-icon" aria-hidden="true"><Icon icon="external" size={12} /></span>}
                                                </span>
                                            </a>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                <div className="novi-ct-panel__messages">
                    {message && (
                        <Notice status={messageType || 'info'} isDismissible={false}>
                            {message}
                        </Notice>
                    )}
                </div>

                {confirmOverwriteOpen && (
                    ModalComponent
                        ? (
                            <ModalComponent
                                title={__('Overwrite existing translations?', 'novi-content-translator')}
                                isFullScreen={false}
                                onRequestClose={onCancelOverwrite}
                            >
                                <div className="nct-overwrite-modal">
                                    <Notice status="warning" isDismissible={false}>
                                        {__(
                                            'The languages below already have translations. By clicking "Overwrite", you will overwrite them new automatically translated content.',
                                            'novi-content-translator'
                                        )}
                                    </Notice>

                                    <ul
                                        className="nct-overwrite-modal__list"
                                    >
                                        {confirmOverwriteExisting.map((translation, idx) => {
                                            const langSlug = translation.language || '';
                                            const langMeta = languages && langSlug && languages[langSlug] ? languages[langSlug] : null;
                                            const langLabel = langMeta && langMeta.name ? langMeta.name : langSlug;
                                            const postTitle = translation.post_title || '';
                                            const editLink = translation.edit_link || '';

                                            return (
                                                <li
                                                    key={(translation.post_id || idx) + '_' + langSlug}
                                                    className="nct-overwrite-modal__lang-row"
                                                >
                                                    <div className="nct-overwrite-modal__lang-main">
                                                        {langMeta && langMeta.flag ? (
                                                            <span className="nct-overwrite-modal__lang-flag" aria-hidden="true">
                                                                <img src={langMeta.flag} alt={langLabel} />
                                                            </span>
                                                        ) : null}
                                                        <strong className="nct-overwrite-modal__lang-label">{langLabel}:</strong>
                                                    </div>

                                                    <div className="nct-overwrite-modal__lang-title">
                                                        {editLink ? (
                                                            <a href={editLink} target="_blank" rel="noopener noreferrer">
                                                                <span>{postTitle}</span>{' '}
                                                                {Icon && (
                                                                    <span className="nct-overwrite-modal__link-icon" aria-hidden="true">
                                                                        <Icon icon="external" size={12} />
                                                                    </span>
                                                                )}
                                                            </a>
                                                        ) : (
                                                            postTitle
                                                        )}
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>

                                    <div className="nct-overwrite-modal__actions">
                                        <Button
                                            isLink
                                            className="nct-overwrite-modal__cancel"
                                            onClick={onCancelOverwrite}
                                        >
                                            {__('Cancel', 'novi-content-translator')}
                                        </Button>
                                        <Button
                                            isPrimary
                                            className="nct-overwrite-modal__confirm"
                                            onClick={onConfirmOverwrite}
                                        >
                                            {__('Overwrite', 'novi-content-translator')}
                                        </Button>
                                    </div>
                                </div>
                            </ModalComponent>
                        )
                        : (
                            <div
                                className="nct-overwrite-modal-fallback"
                                role="dialog"
                                aria-modal="true"
                                style={{
                                    position: 'fixed',
                                    inset: 0,
                                    zIndex: 100000,
                                    background: 'rgba(0,0,0,0.4)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    padding: '1rem',
                                }}
                            >
                                <div style={{ width: '100%', maxWidth: '720px', background: 'var(--wp-admin-theme-color, #fff)', padding: '1rem', borderRadius: '8px' }}>
                                    <h2 style={{ marginTop: 0 }}>
                                        {__('Overwrite existing translations?', 'novi-content-translator')}
                                    </h2>
                                    <p style={{ marginTop: 0 }}>
                                        <strong>
                                            {__('The selected languages already have translations. Confirm overwrite to replace them.', 'novi-content-translator')}
                                        </strong>
                                    </p>
                                    <ul className="nct-overwrite-modal__list">
                                        {confirmOverwriteExisting.map((translation, idx) => {
                                            const langSlug = translation.language || '';
                                            const langMeta = languages && langSlug && languages[langSlug] ? languages[langSlug] : null;
                                            const langLabel = langMeta && langMeta.name ? langMeta.name : langSlug;
                                            const postTitle = translation.post_title || '';
                                            const editLink = translation.edit_link || '';

                                            return (
                                                <li
                                                    key={(translation.post_id || idx) + '_' + langSlug}
                                                    className="nct-overwrite-modal__lang-row"
                                                >
                                                    <div className="nct-overwrite-modal__lang-main">
                                                        {langMeta && langMeta.flag ? (
                                                            <span className="nct-overwrite-modal__lang-flag" aria-hidden="true">
                                                                <img src={langMeta.flag} alt={langLabel} />
                                                            </span>
                                                        ) : null}
                                                        <strong className="nct-overwrite-modal__lang-label">{langLabel}:</strong>
                                                    </div>

                                                    <div className="nct-overwrite-modal__lang-title">
                                                        {editLink ? (
                                                            <a href={editLink} target="_blank" rel="noopener noreferrer">
                                                                <span>{postTitle}</span>{' '}
                                                                {Icon && (
                                                                    <span className="nct-overwrite-modal__link-icon" aria-hidden="true">
                                                                        <Icon icon="external" size={12} />
                                                                    </span>
                                                                )}
                                                            </a>
                                                        ) : (
                                                            postTitle
                                                        )}
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                    <div className="nct-overwrite-modal__actions">
                                        <Button isLink className="nct-overwrite-modal__cancel" onClick={onCancelOverwrite}>
                                            {__('Cancel', 'novi-content-translator')}
                                        </Button>
                                        <Button isPrimary className="nct-overwrite-modal__confirm" onClick={onConfirmOverwrite}>
                                            {__('Overwrite', 'novi-content-translator')}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )
                )}

                {confirmReusableOpen && confirmReusableMissing && (
                    ModalComponent
                        ? (
                            <ModalComponent
                                title={__('Translate missing block patterns?', 'novi-content-translator')}
                                isFullScreen={false}
                                onRequestClose={onSkipReusableCreate}
                            >
                                <div className="nct-overwrite-modal nct-reusable-modal">
                                    <Notice status="warning" isDismissible={false}>
                                        {__(
                                            'Some block patterns have no translation yet. Create them now for the selected languages?',
                                            'novi-content-translator'
                                        )}
                                    </Notice>

                                    <ul className="nct-reusable-modal__languages">
                                        {Object.entries(confirmReusableMissing.by_language || {}).map(([langSlug, items]) => {
                                            const langMeta = languages && languages[langSlug] ? languages[langSlug] : null;
                                            const langLabel = (langMeta && langMeta.name) ? langMeta.name : langSlug;
                                            return (
                                                <li key={`reusable-missing-${langSlug}`} className="nct-reusable-modal__language-row">
                                                    <div className="nct-reusable-modal__language-head">
                                                        {langMeta && langMeta.flag ? (
                                                            <span className="nct-overwrite-modal__lang-flag" aria-hidden="true">
                                                                <img src={langMeta.flag} alt={langLabel} />
                                                            </span>
                                                        ) : null}
                                                        <strong className="nct-overwrite-modal__lang-label">
                                                            {langLabel} ({Array.isArray(items) ? items.length : 0})
                                                        </strong>
                                                    </div>
                                                    {Array.isArray(items) && items.length > 0 && (
                                                        <ul className="nct-reusable-modal__patterns">
                                                            {items.map((item) => (
                                                                <li className="nct-reusable-modal__pattern-row" key={`missing-ref-${langSlug}-${item.source_ref}`}>
                                                                    {item.edit_link ? (
                                                                        <a className="nct-reusable-modal__pattern-link" href={item.edit_link} target="_blank" rel="noopener noreferrer">
                                                                            <span>{item.source_title || `#${item.source_ref}`}</span>
                                                                            {Icon && (
                                                                                <span className="nct-overwrite-modal__link-icon" aria-hidden="true">
                                                                                    <Icon icon="external" size={12} />
                                                                                </span>
                                                                            )}
                                                                        </a>
                                                                    ) : (
                                                                        <span>{item.source_title || `#${item.source_ref}`}</span>
                                                                    )}
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>

                                    <div className="nct-overwrite-modal__actions">
                                        <Button isLink className="nct-overwrite-modal__cancel" onClick={onSkipReusableCreate}>
                                            {__('Skip', 'novi-content-translator')}
                                        </Button>
                                        <Button isPrimary className="nct-overwrite-modal__confirm" onClick={onConfirmReusableCreate}>
                                            {__('Create & continue', 'novi-content-translator')}
                                        </Button>
                                    </div>
                                </div>
                            </ModalComponent>
                        )
                        : null
                )}

                <Button
                    isPrimary
                    className="novi-ct-panel__button"
                    onClick={onCreateTranslations}
                    disabled={loading || !selected.length || !isDeeplValid || isQuotaExhausted}
                >
                    {sprintf(__('Duplicate & translate to %d language(s)', 'novi-content-translator'), selected.length)}
                </Button>
            </div>
        );
    }

        function SettingsPanel({ deeplStatus, onDeeplStatusChange }) {
            const [settings, setSettings] = useState(null);
            const [initialFormality, setInitialFormality] = useState(null);
            const [loading, setLoading] = useState(false);
            const [saving, setSaving] = useState(false);
            const [message, setMessage] = useState(null);
            const [messageType, setMessageType] = useState(null);
            const [deepl, setDeepl] = useState(deeplStatus || null);
            const [deeplKey, setDeeplKey] = useState('');
            const [deeplKeyTouched, setDeeplKeyTouched] = useState(false);
            const [showDeeplKey, setShowDeeplKey] = useState(false);
            const [showKeyHelp, setShowKeyHelp] = useState(false);
            const [resettingKey, setResettingKey] = useState(false);
            const [languages, setLanguages] = useState([]);
            const [wordsSourceLang, setWordsSourceLang] = useState('');
            const [wordsSearch, setWordsSearch] = useState('');
            const [wordsPage, setWordsPage] = useState(1);
            const wordsPageSize = 50;

            const ajaxUrl = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.ajaxUrl)
                ? noviContentTranslator.ajaxUrl
                : (window.ajaxurl || '');

            const nonce = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.nonce)
                ? noviContentTranslator.nonce
                : '';

            const loadSettings = useCallback(() => {
                if (!ajaxUrl || !nonce || settings !== null || loading) {
                    return;
                }

                setLoading(true);
                setMessage(null);
                setMessageType(null);

                const formData = new window.FormData();
                formData.append('action', 'novi_get_settings');
                formData.append('nonce', nonce);

                window.fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data && data.success && data.data && data.data.settings) {
                            const loaded = data.data.settings;
                            const langs = Array.isArray(data.data.languages) ? data.data.languages : [];
                            setSettings(loaded);
                            setLanguages(langs);
                            if (!wordsSourceLang && langs.length) {
                                setWordsSourceLang(langs[0].slug);
                            }
                            setDeepl(data.data.deepl || null);
                            if (typeof onDeeplStatusChange === 'function') {
                                onDeeplStatusChange(data.data.deepl || null)
                            }
                            if (initialFormality === null) {
                                setInitialFormality(loaded.formality || 'default');
                            }
                        } else {
                            setMessage((data && data.data && data.data.message) || __('Failed to load settings.', 'novi-content-translator'));
                            setMessageType('error');
                        }
                    })
                    .catch(() => {
                        setMessage(__('An error occurred while loading settings.', 'novi-content-translator'));
                        setMessageType('error');
                    })
                    .finally(() => {
                        setLoading(false);
                    });
            }, [ajaxUrl, nonce, settings, loading]);

            useEffect(() => {
                loadSettings();
            }, [loadSettings]);

            const updateSetting = (key, value) => {
                setSettings((prev) => ({
                    ...(prev || {}),
                    [key]: value,
                }));
            };

            const saveSettingsPayload = (payload, options = {}) => {
                if (!ajaxUrl || !nonce || !settings || saving || resettingKey) {
                    return;
                }

                const isKeyReset = !!options.isKeyReset;
                if (isKeyReset) {
                    setResettingKey(true)
                } else {
                    setSaving(true);
                }
                setMessage(__('Saving settings…', 'novi-content-translator'));
                setMessageType('info');
                const attemptedKeySave = Object.prototype.hasOwnProperty.call(payload, 'deepl_api_key') && !isKeyReset;
                const attemptedKeyValue = attemptedKeySave ? String(payload.deepl_api_key || '') : '';

                const formData = new window.FormData();
                formData.append('action', 'novi_save_settings');
                formData.append('nonce', nonce);
                formData.append('settings', JSON.stringify(payload));

                window.fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data && data.success && data.data) {
                            if (data.data.settings) {
                                setSettings(data.data.settings);
                                setInitialFormality(data.data.settings.formality || 'default');
                                if (Array.isArray(data.data.languages)) {
                                    setLanguages(data.data.languages);
                                    if (!wordsSourceLang && data.data.languages.length) {
                                        setWordsSourceLang(data.data.languages[0].slug);
                                    }
                                }
                                if (typeof data.data.deepl !== 'undefined') {
                                    setDeepl(data.data.deepl || null);
                                    if (typeof onDeeplStatusChange === 'function') {
                                        onDeeplStatusChange(data.data.deepl || null)
                                    }
                                    if (data.data.deepl && data.data.deepl.valid) {
                                        setDeeplKey('');
                                        setDeeplKeyTouched(false);
                                        setShowDeeplKey(false);
                                    } else if (attemptedKeySave && attemptedKeyValue !== '') {
                                        // keep entered key visible so user can quickly correct it
                                        setDeeplKey(attemptedKeyValue);
                                        setDeeplKeyTouched(true);
                                    }
                                }
                            }

                            if (attemptedKeySave && attemptedKeyValue !== '' && (!data.data.deepl || !data.data.deepl.valid)) {
                                setMessage(__('DeepL API key could not be verified. Please check the key and try again.', 'novi-content-translator'));
                                setMessageType('error');
                            } else if (attemptedKeySave && attemptedKeyValue !== '' && data.data.deepl && data.data.deepl.quota_exhausted) {
                                setMessage(__('DeepL key is valid, but quota is exhausted. Translation is blocked until quota refresh.', 'novi-content-translator'));
                                setMessageType('error');
                            } else {
                                setMessage((data.data && data.data.message) || __('Settings saved.', 'novi-content-translator'));
                                setMessageType('success');
                            }
                        } else {
                            setMessage((data && data.data && data.data.message) || __('Failed to save settings.', 'novi-content-translator'));
                            setMessageType('error');
                        }
                    })
                    .catch(() => {
                        setMessage(__('An error occurred while saving settings.', 'novi-content-translator'));
                        setMessageType('error');
                    })
                    .finally(() => {
                        if (isKeyReset) {
                            setResettingKey(false)
                        } else {
                            setSaving(false);
                        }
                    });
            };

            const validateDontTranslateWords = (wordsByLang, langs) => {
                const slugs = (langs || []).map((l) => (l && l.slug ? String(l.slug) : '')).filter(Boolean);
                for (let i = 0; i < slugs.length; i++) {
                    const sourceLang = slugs[i];
                    const rules = Array.isArray(wordsByLang[sourceLang]) ? wordsByLang[sourceLang] : [];
                    const seen = {};
                    for (let r = 0; r < rules.length; r++) {
                        const source = String((rules[r] && rules[r].source) || '').trim();
                        if (!source) {
                            return sprintf(
                                __('Word or phrase is required for every entry (language %s).', 'novi-content-translator'),
                                String(sourceLang).toUpperCase()
                            );
                        }
                        const key = source.toLowerCase();
                        if (seen[key]) {
                            return sprintf(
                                __('Duplicate word "%s" for language %s.', 'novi-content-translator'),
                                source,
                                String(sourceLang).toUpperCase()
                            );
                        }
                        seen[key] = true;
                    }
                }
                return null;
            };

            const getDontTranslateWords = () => {
                const words = settings && settings.dont_translate_words && typeof settings.dont_translate_words === 'object'
                    ? settings.dont_translate_words
                    : {};
                return words;
            };

            const updateDontTranslateWords = (updater) => {
                setSettings((prev) => {
                    const current = prev && prev.dont_translate_words && typeof prev.dont_translate_words === 'object'
                        ? prev.dont_translate_words
                        : {};
                    return {
                        ...(prev || {}),
                        dont_translate_words: updater(current),
                    };
                });
            };

            const onAddDontTranslateWord = () => {
                if (!wordsSourceLang) {
                    return;
                }
                updateDontTranslateWords((current) => {
                    const next = { ...(current || {}) };
                    const list = Array.isArray(next[wordsSourceLang]) ? next[wordsSourceLang].slice() : [];
                    list.push({
                        source: '',
                        targets: {},
                        also_in_sentence: true,
                        ignore_casing: true,
                    });
                    next[wordsSourceLang] = list;
                    return next;
                });
                setWordsSearch('');
                const currentLen = Array.isArray(getDontTranslateWords()[wordsSourceLang])
                    ? getDontTranslateWords()[wordsSourceLang].length
                    : 0;
                setWordsPage(Math.max(1, Math.ceil((currentLen + 1) / wordsPageSize)));
            };

            const onDeleteDontTranslateWord = (ruleIndex) => {
                updateDontTranslateWords((current) => {
                    const next = { ...(current || {}) };
                    const list = Array.isArray(next[wordsSourceLang]) ? next[wordsSourceLang].slice() : [];
                    list.splice(ruleIndex, 1);
                    if (list.length) {
                        next[wordsSourceLang] = list;
                    } else {
                        delete next[wordsSourceLang];
                    }
                    return next;
                });
            };

            const updateDontTranslateWordAt = (ruleIndex, updater) => {
                updateDontTranslateWords((current) => {
                    const next = { ...(current || {}) };
                    const list = Array.isArray(next[wordsSourceLang]) ? next[wordsSourceLang].slice() : [];
                    if (!list[ruleIndex]) {
                        return current;
                    }
                    list[ruleIndex] = updater(list[ruleIndex]);
                    next[wordsSourceLang] = list;
                    return next;
                });
            };

            const onSave = () => {
                const words = getDontTranslateWords();
                const validationError = validateDontTranslateWords(words, languages);
                if (validationError) {
                    setMessage(validationError);
                    setMessageType('error');
                    return;
                }
                const payload = {
                    formality: settings.formality || 'default',
                    dont_translate_words: words,
                };
                if (deeplKeyTouched) {
                    payload.deepl_api_key = deeplKey;
                }
                saveSettingsPayload(payload);
            }

            const onResetApiKey = () => {
                const payload = {
                    formality: settings && settings.formality ? settings.formality : 'default',
                    deepl_api_key: '',
                }
                saveSettingsPayload(payload, { isKeyReset: true })
            }

            if (!ajaxUrl || !nonce) {
                return null;
            }

            const formalityHelp = __(
                'Choose how formal or casual the translation should sound. For example, the English phrase "You\'re welcome" in German can be translated more formally as "Gern geschehen" or more informally as "Kein Problem". DeepL applies this only in languages that support formality (e.g. German, French).',
                'novi-content-translator'
            );
            const browserLocale = (typeof window !== 'undefined' && window.navigator && window.navigator.language)
                ? window.navigator.language
                : undefined;
            const formatUsageFull = (value) => {
                const number = Number(value || 0);
                if (!Number.isFinite(number)) {
                    return '0';
                }

                try {
                    return new Intl.NumberFormat(browserLocale).format(number);
                } catch (e) {
                    return String(Math.round(number));
                }
            };
            const formatUsageCompact = (value) => {
                const number = Number(value || 0);
                if (!Number.isFinite(number)) {
                    return '0';
                }

                try {
                    return new Intl.NumberFormat(browserLocale, {
                        notation: 'compact',
                        maximumFractionDigits: 1,
                    })
                        .format(number)
                        .replace(/\s+/g, '')
                        .toLowerCase();
                } catch (e) {
                    return String(Math.round(number));
                }
            };
            const parseStatusTimestamp = (value) => {
                if (!value) return 0
                if (typeof value === 'number' && Number.isFinite(value) && value > 0) return value

                const numeric = Number(value)
                if (Number.isFinite(numeric) && numeric > 0) return numeric

                const parsedMs = Date.parse(String(value))
                if (!Number.isNaN(parsedMs) && parsedMs > 0) {
                    return Math.floor(parsedMs / 1000)
                }

                return 0
            }

            const periodStartAt = parseStatusTimestamp(deepl && deepl.period_start_at);
            const refreshAt = parseStatusTimestamp((deepl && deepl.refresh_at) || (deepl && deepl.period_end_at));
            const hasPeriodStartAt = Number.isFinite(periodStartAt) && periodStartAt > 0;
            const hasRefreshAt = Number.isFinite(refreshAt) && refreshAt > 0;
            const periodStartLabel = hasPeriodStartAt ? new Date(periodStartAt * 1000).toLocaleString() : '';
            const refreshDateLabel = hasRefreshAt ? new Date(refreshAt * 1000).toLocaleString() : '';

            return (
                <div className="novi-ct-panel">
                    {loading && !settings && (
                        <div className="novi-ct-panel__sites">
                            <Spinner />
                        </div>
                    )}

                    <div className="novi-ct-panel__deepl novi-ct-panel__setting-group novi-ct-panel__setting-group--deepl">
                        <div className="components-base-control">
                            <label className="components-base-control__label">
                                <span className="novi-ct-panel__formality-label-wrap novi-ct-panel__deepl-label-wrap">
                                    {__('DeepL API key', 'novi-content-translator')}
                                    <button
                                        type="button"
                                        className="novi-ct-panel__formality-tooltip-trigger"
                                        onClick={() => setShowKeyHelp((v) => !v)}
                                        aria-expanded={showKeyHelp ? 'true' : 'false'}
                                        aria-label={__('How to find your DeepL API key', 'novi-content-translator')}
                                        title={__('How to find your DeepL API key', 'novi-content-translator')}
                                    >
                                        {Icon && <Icon icon="editor-help" size={16} />}
                                    </button>
                                    {showKeyHelp && Popover && (
                                        <Popover position="middle right" onClose={() => setShowKeyHelp(false)}>
                                            <div className="novi-ct-panel__deepl-help-popover">
                                                <strong>{__('Find your key in 3 steps:', 'novi-content-translator')}</strong>
                                                <ol>
                                                    <li>
                                                        <a href="https://www.deepl.com/en/your-account/keys" target="_blank" rel="noopener noreferrer">
                                                            {__('Open DeepL API Keys & Limits', 'novi-content-translator')}
                                                        </a>
                                                    </li>
                                                    <li>{__('Sign in (or choose your API subscription).', 'novi-content-translator')}</li>
                                                    <li>{__('Click copy on your API key and paste it here.', 'novi-content-translator')}</li>
                                                </ol>
                                                <a href="https://developers.deepl.com/docs/getting-started/managing-api-keys" target="_blank" rel="noopener noreferrer">
                                                    {__('DeepL docs: managing API keys', 'novi-content-translator')}
                                                </a>
                                            </div>
                                        </Popover>
                                    )}
                                </span>
                            </label>
                        </div>

                        {deepl && deepl.valid ? (
                            <Notice status={deepl.quota_exhausted ? 'warning' : 'success'} isDismissible={false}>
                                <div className="novi-ct-panel__deepl-status-row">
                                    <span className="dashicons dashicons-lock" aria-hidden="true" />
                                    <strong>{__('Connected API key:', 'novi-content-translator')}</strong>
                                    <span>{deepl.identifier}</span>
                                </div>
                                {deepl.usage && (
                                    <div className="novi-ct-panel__deepl-status-row">
                                        <span className="dashicons dashicons-chart-area" aria-hidden="true" />
                                        <span>({formatUsageFull(deepl.usage.character_count)} / {formatUsageCompact(deepl.usage.character_limit)} {__('characters', 'novi-content-translator')})</span>
                                    </div>
                                )}
                                {hasRefreshAt && (
                                    <div className="novi-ct-panel__deepl-status-row novi-ct-panel__deepl-status-row--refresh">
                                        <span className="dashicons dashicons-clock" aria-hidden="true" />
                                        <span>{__('Quota refresh:', 'novi-content-translator')} {refreshDateLabel}</span>
                                    </div>
                                )}
                                {!hasRefreshAt && (
                                    <div className="novi-ct-panel__deepl-status-row novi-ct-panel__deepl-status-row--refresh">
                                        <span className="dashicons dashicons-clock" aria-hidden="true" />
                                        <span>{__('Quota refresh: Not available', 'novi-content-translator')}</span>
                                    </div>
                                )}
                                {hasPeriodStartAt && hasRefreshAt && (
                                    <div className="novi-ct-panel__deepl-status-row novi-ct-panel__deepl-status-row--refresh">
                                        <span className="dashicons dashicons-calendar-alt" aria-hidden="true" />
                                        <span>{__('Billing period:', 'novi-content-translator')} {periodStartLabel} - {refreshDateLabel}</span>
                                    </div>
                                )}
                                {deepl.quota_exhausted && (
                                    <div className="novi-ct-panel__deepl-status-row novi-ct-panel__deepl-status-row--warning">
                                        <span className="dashicons dashicons-warning" aria-hidden="true" />
                                        <span>{__('DeepL quota is exhausted. Translation is blocked until quota refresh.', 'novi-content-translator')}</span>
                                    </div>
                                )}
                                <button
                                    type="button"
                                    className="components-button is-link novi-ct-panel__deepl-reset-link"
                                    onClick={onResetApiKey}
                                    disabled={resettingKey || saving}
                                >
                                    {resettingKey
                                        ? __('Removing key…', 'novi-content-translator')
                                        : __('Remove connected key', 'novi-content-translator')}
                                </button>
                            </Notice>
                        ) : (
                            <Notice status="warning" isDismissible={false}>
                                {__('DeepL API key is missing or invalid. Enter a valid key below to enable translations.', 'novi-content-translator')}
                            </Notice>
                        )}

                        {(!deepl || !deepl.valid) && (
                            <div className="novi-ct-panel__deepl-key-row">
                                <div className="novi-ct-panel__deepl-key-input-wrap">
                                    <input
                                        className="novi-ct-panel__deepl-key-input"
                                        type={showDeeplKey ? 'text' : 'password'}
                                        value={deeplKey}
                                        placeholder={__('DeepL API key', 'novi-content-translator')}
                                        onChange={(e) => {
                                            setDeeplKey(e.target.value)
                                            setDeeplKeyTouched(true)
                                        }}
                                        autoComplete="off"
                                    />

                                    <button
                                        type="button"
                                        className="novi-ct-panel__deepl-eye"
                                        onClick={() => setShowDeeplKey((v) => !v)}
                                        aria-label={showDeeplKey ? __('Hide key', 'novi-content-translator') : __('Show key', 'novi-content-translator')}
                                        title={showDeeplKey ? __('Hide key', 'novi-content-translator') : __('Show key', 'novi-content-translator')}
                                    >
                                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            {showDeeplKey ? (
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Zm10 5a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm11.3-10.3L4.7 21.3l-1.4-1.4L21.9 5.3l1.4 1.4Z" fill="currentColor" />
                                            ) : (
                                                <path d="M12 5c-6.5 0-10 7-10 7s3.5 7 10 7 10-7 10-7-3.5-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" fill="currentColor" />
                                            )}
                                        </svg>
                                    </button>
                                </div>
                                <p className="novi-ct-panel__deepl-save-hint">
                                    {__('Click "Save settings" to verify this key.', 'novi-content-translator')}
                                </p>
                            </div>
                        )}
                    </div>

                    {settings && (
                        <div className="novi-ct-panel__setting-group novi-ct-panel__setting-group--formality">
                            {SelectControl ? (
                                <SelectControl
                                    label={
                                        <span className="novi-ct-panel__formality-label-wrap">
                                            {__('Formality', 'novi-content-translator')}
                                            {Tooltip ? (
                                                <Tooltip text={formalityHelp} placement="top" delay={400}>
                                                    <button
                                                        type="button"
                                                        className="novi-ct-panel__formality-tooltip-trigger"
                                                        aria-label={__('More information about formality', 'novi-content-translator')}
                                                        title={formalityHelp}
                                                    >
                                                        {Icon && <Icon icon="editor-help" size={16} />}
                                                    </button>
                                                </Tooltip>
                                            ) : (
                                                <span className="novi-ct-panel__formality-tooltip-trigger" title={formalityHelp}>
                                                    {Icon && <Icon icon="editor-help" size={16} />}
                                                </span>
                                            )}
                                        </span>
                                    }
                                    value={settings.formality || 'default'}
                                    options={[
                                        { label: __('Use DeepL default', 'novi-content-translator'), value: 'default' },
                                        { label: __('Prefer formal tone', 'novi-content-translator'), value: 'more' },
                                        { label: __('Prefer informal tone', 'novi-content-translator'), value: 'less' },
                                    ]}
                                    onChange={(value) => updateSetting('formality', value)}
                                />
                            ) : (
                                <div className="components-base-control">
                                    <label className="components-base-control__label">
                                        <span className="novi-ct-panel__formality-label-wrap">
                                            {__('Formality', 'novi-content-translator')}
                                            {Tooltip ? (
                                                <Tooltip text={formalityHelp} placement="top" delay={400}>
                                                    <button
                                                        type="button"
                                                        className="novi-ct-panel__formality-tooltip-trigger"
                                                        aria-label={__('More information about formality', 'novi-content-translator')}
                                                        title={formalityHelp}
                                                    >
                                                        {Icon && <Icon icon="editor-help" size={16} />}
                                                    </button>
                                                </Tooltip>
                                            ) : (
                                                <span className="novi-ct-panel__formality-tooltip-trigger" title={formalityHelp}>
                                                    {Icon && <Icon icon="editor-help" size={16} />}
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                    <select
                                        className="components-select-control__input"
                                        value={settings.formality || 'default'}
                                        onChange={(e) => updateSetting('formality', e.target.value)}
                                    >
                                        <option value="default">{__('Use DeepL default', 'novi-content-translator')}</option>
                                        <option value="more">{__('Prefer formal tone', 'novi-content-translator')}</option>
                                        <option value="less">{__('Prefer informal tone', 'novi-content-translator')}</option>
                                    </select>
                                    <p className="novi-ct-panel__description">{formalityHelp}</p>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="novi-ct-panel__setting-group novi-ct-dont-translate">
                        <h3 className="novi-ct-dont-translate__title">
                            {__('Don\'t translate / terminology', 'novi-content-translator')}
                        </h3>
                        <p className="novi-ct-panel__description">
                            {__('Words and phrases listed here are protected when translating from the selected source language. Leave the target empty to keep the source unchanged (brand names). Optionally set a target per language to force a glossary term (for example NL “Ketenimpact” → EN “Due diligence”). Team member and project titles are protected automatically at translation time and do not need to be listed here.', 'novi-content-translator')}
                        </p>

                        {languages && languages.length ? (
                            <div className="novi-ct-dont-translate__source-switcher" role="tablist" aria-label={__('Source language', 'novi-content-translator')}>
                                {languages.map((lang) => (
                                    <button
                                        key={lang.slug}
                                        type="button"
                                        role="tab"
                                        aria-selected={wordsSourceLang === lang.slug}
                                        className={'novi-ct-dont-translate__source-tab' + (wordsSourceLang === lang.slug ? ' is-active' : '')}
                                        onClick={() => {
                                            setWordsSourceLang(lang.slug);
                                            setWordsPage(1);
                                        }}
                                    >
                                        {lang.flag ? (
                                            <span className="novi-ct-panel__site-flag" aria-hidden="true">
                                                <img src={lang.flag} alt="" />
                                            </span>
                                        ) : null}
                                        <span>{lang.name || String(lang.slug).toUpperCase()}</span>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <p className="novi-ct-panel__description">
                                {__('No Polylang languages available.', 'novi-content-translator')}
                            </p>
                        )}

                        {wordsSourceLang ? (
                            <>
                                <div className="novi-ct-dont-translate__toolbar">
                                    {TextControl ? (
                                        <TextControl
                                            label={__('Search', 'novi-content-translator')}
                                            value={wordsSearch}
                                            onChange={(value) => {
                                                setWordsSearch(value);
                                                setWordsPage(1);
                                            }}
                                            placeholder={__('Search words…', 'novi-content-translator')}
                                        />
                                    ) : (
                                        <label className="novi-ct-dont-translate__search-fallback">
                                            <span>{__('Search', 'novi-content-translator')}</span>
                                            <input
                                                type="search"
                                                value={wordsSearch}
                                                onChange={(e) => {
                                                    setWordsSearch(e.target.value);
                                                    setWordsPage(1);
                                                }}
                                                placeholder={__('Search words…', 'novi-content-translator')}
                                            />
                                        </label>
                                    )}
                                </div>

                                {(() => {
                                    const wordsMap = getDontTranslateWords();
                                    const currentRules = Array.isArray(wordsMap[wordsSourceLang]) ? wordsMap[wordsSourceLang] : [];
                                    const needle = String(wordsSearch || '').trim().toLowerCase();
                                    const filtered = currentRules
                                        .map((rule, index) => ({ rule, index }))
                                        .filter(({ rule }) => {
                                            if (!needle) return true;
                                            if (String(rule.source || '').toLowerCase().indexOf(needle) !== -1) {
                                                return true;
                                            }
                                            const targets = rule.targets && typeof rule.targets === 'object' ? rule.targets : {};
                                            return Object.keys(targets).some((lang) =>
                                                String(targets[lang] || '').toLowerCase().indexOf(needle) !== -1
                                            );
                                        });
                                    const targetLangs = (languages || []).filter((lang) => lang && lang.slug && lang.slug !== wordsSourceLang);
                                    const totalPages = Math.max(1, Math.ceil(filtered.length / wordsPageSize));
                                    const safePage = Math.min(wordsPage, totalPages);
                                    const pageItems = filtered.slice((safePage - 1) * wordsPageSize, safePage * wordsPageSize);

                                    return (
                                        <>
                                            {!pageItems.length ? (
                                                <p className="novi-ct-panel__description">
                                                    {needle
                                                        ? __('No words match your search.', 'novi-content-translator')
                                                        : __('No words yet for this source language.', 'novi-content-translator')}
                                                </p>
                                            ) : (
                                                <div className="novi-ct-dont-translate__list">
                                                    {pageItems.map(({ rule, index }) => (
                                                        <div className="novi-ct-dont-translate__row" key={'dtw-' + wordsSourceLang + '-' + index}>
                                                            <div className="novi-ct-dont-translate__row-main">
                                                                {TextControl ? (
                                                                    <TextControl
                                                                        label={__('Word or phrase', 'novi-content-translator')}
                                                                        hideLabelFromVision
                                                                        value={rule.source || ''}
                                                                        onChange={(value) => updateDontTranslateWordAt(index, (prev) => ({
                                                                            ...prev,
                                                                            source: value,
                                                                        }))}
                                                                        placeholder={__('Word or phrase (source)', 'novi-content-translator')}
                                                                    />
                                                                ) : (
                                                                    <input
                                                                        type="text"
                                                                        value={rule.source || ''}
                                                                        onChange={(e) => updateDontTranslateWordAt(index, (prev) => ({
                                                                            ...prev,
                                                                            source: e.target.value,
                                                                        }))}
                                                                        placeholder={__('Word or phrase (source)', 'novi-content-translator')}
                                                                    />
                                                                )}
                                                                <Button
                                                                    className="novi-ct-dont-translate__delete"
                                                                    variant="tertiary"
                                                                    isDestructive
                                                                    icon="trash"
                                                                    label={__('Delete', 'novi-content-translator')}
                                                                    onClick={() => onDeleteDontTranslateWord(index)}
                                                                    disabled={saving || resettingKey}
                                                                />
                                                            </div>
                                                            {targetLangs.length ? (
                                                                <div className="novi-ct-dont-translate__targets">
                                                                    {targetLangs.map((lang) => {
                                                                        const targets = rule.targets && typeof rule.targets === 'object' ? rule.targets : {};
                                                                        const targetValue = targets[lang.slug] || '';
                                                                        const targetLabel = sprintf(
                                                                            __('Target (%s) — leave empty to keep source', 'novi-content-translator'),
                                                                            lang.name || String(lang.slug).toUpperCase()
                                                                        );
                                                                        return TextControl ? (
                                                                            <TextControl
                                                                                key={'dtw-target-' + wordsSourceLang + '-' + index + '-' + lang.slug}
                                                                                label={targetLabel}
                                                                                value={targetValue}
                                                                                onChange={(value) => updateDontTranslateWordAt(index, (prev) => {
                                                                                    const nextTargets = {
                                                                                        ...((prev && prev.targets && typeof prev.targets === 'object') ? prev.targets : {}),
                                                                                    };
                                                                                    const trimmed = String(value || '').trim();
                                                                                    if (trimmed) {
                                                                                        nextTargets[lang.slug] = value;
                                                                                    } else {
                                                                                        delete nextTargets[lang.slug];
                                                                                    }
                                                                                    return {
                                                                                        ...prev,
                                                                                        targets: nextTargets,
                                                                                    };
                                                                                })}
                                                                                placeholder={__('Optional forced translation', 'novi-content-translator')}
                                                                            />
                                                                        ) : (
                                                                            <label
                                                                                key={'dtw-target-' + wordsSourceLang + '-' + index + '-' + lang.slug}
                                                                                className="novi-ct-dont-translate__target-fallback"
                                                                            >
                                                                                <span>{targetLabel}</span>
                                                                                <input
                                                                                    type="text"
                                                                                    value={targetValue}
                                                                                    onChange={(e) => updateDontTranslateWordAt(index, (prev) => {
                                                                                        const nextTargets = {
                                                                                            ...((prev && prev.targets && typeof prev.targets === 'object') ? prev.targets : {}),
                                                                                        };
                                                                                        const trimmed = String(e.target.value || '').trim();
                                                                                        if (trimmed) {
                                                                                            nextTargets[lang.slug] = e.target.value;
                                                                                        } else {
                                                                                            delete nextTargets[lang.slug];
                                                                                        }
                                                                                        return {
                                                                                            ...prev,
                                                                                            targets: nextTargets,
                                                                                        };
                                                                                    })}
                                                                                    placeholder={__('Optional forced translation', 'novi-content-translator')}
                                                                                />
                                                                            </label>
                                                                        );
                                                                    })}
                                                                </div>
                                                            ) : null}
                                                            <div className="novi-ct-dont-translate__row-options">
                                                                <CheckboxControl
                                                                    label={__('Also when used in sentence', 'novi-content-translator')}
                                                                    checked={rule.also_in_sentence !== false}
                                                                    onChange={(checked) => updateDontTranslateWordAt(index, (prev) => ({
                                                                        ...prev,
                                                                        also_in_sentence: !!checked,
                                                                    }))}
                                                                />
                                                                <CheckboxControl
                                                                    label={__('Ignore casing', 'novi-content-translator')}
                                                                    checked={rule.ignore_casing !== false}
                                                                    onChange={(checked) => updateDontTranslateWordAt(index, (prev) => ({
                                                                        ...prev,
                                                                        ignore_casing: !!checked,
                                                                    }))}
                                                                />
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            {filtered.length > wordsPageSize ? (
                                                <div className="novi-ct-dont-translate__pagination">
                                                    <Button
                                                        variant="tertiary"
                                                        disabled={safePage <= 1 || saving}
                                                        onClick={() => setWordsPage(Math.max(1, safePage - 1))}
                                                    >
                                                        {__('Previous', 'novi-content-translator')}
                                                    </Button>
                                                    <span>
                                                        {sprintf(
                                                            __('Page %1$d of %2$d', 'novi-content-translator'),
                                                            safePage,
                                                            totalPages
                                                        )}
                                                    </span>
                                                    <Button
                                                        variant="tertiary"
                                                        disabled={safePage >= totalPages || saving}
                                                        onClick={() => setWordsPage(Math.min(totalPages, safePage + 1))}
                                                    >
                                                        {__('Next', 'novi-content-translator')}
                                                    </Button>
                                                </div>
                                            ) : null}

                                            <Button
                                                variant="secondary"
                                                className="novi-ct-dont-translate__add"
                                                onClick={onAddDontTranslateWord}
                                                disabled={!wordsSourceLang || saving || resettingKey}
                                            >
                                                {__('Add word', 'novi-content-translator')}
                                            </Button>
                                        </>
                                    );
                                })()}
                            </>
                        ) : null}
                    </div>

                    <div className="novi-ct-panel__messages">
                        {message && (
                            <Notice status={messageType || 'info'} isDismissible={false}>
                                {message}
                            </Notice>
                        )}
                    </div>

                    <Button
                        isPrimary
                        className="novi-ct-panel__button"
                        onClick={onSave}
                        disabled={saving || resettingKey || !settings}
                    >
                        {saving
                            ? __('Saving settings…', 'novi-content-translator')
                            : (deeplKeyTouched
                                ? __('Save settings & verify key', 'novi-content-translator')
                                : __('Save settings', 'novi-content-translator'))}
                    </Button>
                </div>
            );
        }

        function SidebarTabs() {
            const [activeTab, setActiveTab] = useState('translator');
            const [deeplStatus, setDeeplStatus] = useState(null);
            const [panelInstanceKey, setPanelInstanceKey] = useState(0)
            const { id: entityId, type: entityType } = useEntityContext()
            const regenAttemptedRef = useRef({})

            const ajaxUrl = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.ajaxUrl)
                ? noviContentTranslator.ajaxUrl
                : (window.ajaxurl || '');

            const nonce = (typeof noviContentTranslator !== 'undefined' && noviContentTranslator.nonce)
                ? noviContentTranslator.nonce
                : '';

            const loadDeeplStatus = useCallback(() => {
                if (!ajaxUrl || !nonce || deeplStatus !== null) {
                    return;
                }

                const formData = new window.FormData();
                formData.append('action', 'novi_get_settings');
                formData.append('nonce', nonce);

                window.fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data && data.success && data.data) {
                            setDeeplStatus(data.data.deepl || null);
                        }
                    })
                    .catch(() => {
                        setDeeplStatus(null);
                    });
            }, [ajaxUrl, nonce, deeplStatus]);

            useEffect(() => {
                loadDeeplStatus();
            }, [loadDeeplStatus]);

            useEffect(() => {
                if (deeplStatus && !deeplStatus.valid && activeTab !== 'settings') {
                    setActiveTab('settings')
                }
            }, [deeplStatus, activeTab]);

            useEffect(() => {
                //auto-regenerate nectar-blocks css meta after server-side translations for patterns/global sections
                if (!entityId || !entityType) return
                if (!wp || !wp.data || !wp.data.select || !wp.data.dispatch) return

                const key = `${entityType}:${entityId}`
                if (regenAttemptedRef.current[key]) return

                const coreSelect = wp.data.select('core')
                const coreDispatch = wp.data.dispatch('core')
                if (!coreSelect || !coreDispatch) return

                const record = coreSelect.getEditedEntityRecord
                    ? coreSelect.getEditedEntityRecord('postType', entityType, entityId)
                    : null

                const needsRegen = !!(record && record.meta && record.meta._nct_needs_nb_css_regen)
                if (!needsRegen) return

                regenAttemptedRef.current[key] = true

                try {
                    //clear the flag and save; nectar-blocks hooks into save to generate and persist _nectar_blocks_css
                    coreDispatch.editEntityRecord('postType', entityType, entityId, {
                        meta: {
                            ...(record && record.meta ? record.meta : {}),
                            _nct_needs_nb_css_regen: false,
                        },
                    })

                    if (coreDispatch.saveEditedEntityRecord) {
                        coreDispatch.saveEditedEntityRecord('postType', entityType, entityId)
                    } else if (wp.data.dispatch('core/editor') && wp.data.dispatch('core/editor').savePost) {
                        wp.data.dispatch('core/editor').savePost()
                    }
                } catch (e) {
                    //fail silently; user can still manually save
                }
            }, [entityId, entityType])

            useEffect(() => {
                //fallback: detect SPA navigation (back/forward + pushState) and remount panels when ?post or ?postId changes
                const getPostParam = () => {
                    try {
                        const params = new window.URLSearchParams(window.location.search || '')
                        return params.get('post') || params.get('postId') || params.get('post_id') || ''
                    } catch (e) {
                        return ''
                    }
                }

                let lastPostParam = getPostParam()

                const handleLocationChange = () => {
                    const nextPostParam = getPostParam()
                    if (nextPostParam !== lastPostParam) {
                        lastPostParam = nextPostParam
                        setPanelInstanceKey((k) => k + 1)
                    }
                }

                window.addEventListener('popstate', handleLocationChange)
                window.addEventListener('nct:locationchange', handleLocationChange)

                const originalPushState = window.history && window.history.pushState ? window.history.pushState.bind(window.history) : null
                const originalReplaceState = window.history && window.history.replaceState ? window.history.replaceState.bind(window.history) : null

                if (originalPushState) {
                    window.history.pushState = function (...args) {
                        const result = originalPushState(...args)
                        try {
                            window.dispatchEvent(new window.Event('nct:locationchange'))
                        } catch (e) {}
                        return result
                    }
                }
                if (originalReplaceState) {
                    window.history.replaceState = function (...args) {
                        const result = originalReplaceState(...args)
                        try {
                            window.dispatchEvent(new window.Event('nct:locationchange'))
                        } catch (e) {}
                        return result
                    }
                }

                return () => {
                    window.removeEventListener('popstate', handleLocationChange)
                    window.removeEventListener('nct:locationchange', handleLocationChange)
                    if (originalPushState) window.history.pushState = originalPushState
                    if (originalReplaceState) window.history.replaceState = originalReplaceState
                }
            }, [])

            return (
                <div className="novi-ct">
                    <div className="novi-ct-tabs novi-ct__tabs">
                        <div className="novi-ct-tabs__list" role="tablist">
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeTab === 'translator'}
                            className={'novi-ct-tabs__tab' + (activeTab === 'translator' ? ' novi-ct-tabs__tab--active' : '')}
                            onClick={() => {
                                if (deeplStatus && !deeplStatus.valid) {
                                    setActiveTab('settings')
                                } else {
                                    setActiveTab('translator')
                                }
                            }}
                        >
                            {Icon && <span className="novi-ct-tabs__tab-icon" aria-hidden="true"><Icon icon="translation" size={20} /></span>}
                            {__('Translator', 'novi-content-translator')}
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeTab === 'settings'}
                            className={'novi-ct-tabs__tab' + (activeTab === 'settings' ? ' novi-ct-tabs__tab--active' : '')}
                            onClick={() => setActiveTab('settings')}
                        >
                            {Icon && <span className="novi-ct-tabs__tab-icon" aria-hidden="true"><Icon icon="admin-generic" size={20} /></span>}
                            {__('Settings', 'novi-content-translator')}
                        </button>
                        </div>
                    </div>
                    <div className="novi-ct__panel" role="tabpanel">
                        {activeTab === 'settings'
                            ? <SettingsPanel key={'nct-settings-' + panelInstanceKey} deeplStatus={deeplStatus} onDeeplStatusChange={setDeeplStatus} />
                            : <TranslatorPanel key={'nct-translator-' + panelInstanceKey} deeplStatus={deeplStatus} requireSettingsTab={() => setActiveTab('settings')} />}
                    </div>
                </div>
            );
        }

        function DocumentPanelWrapper() {
            const { id } = useEntityContext();
            if (!id || !PostPluginSidebar) {
                return null;
            }

            return (
                <PostPluginSidebar
                    name="novi-content-translator"
                    title={__('Novi Content Translator', 'novi-content-translator')}
                >
                    <SidebarTabs />
                </PostPluginSidebar>
            );
        }

        function SiteEditorSidebarWrapper() {
            const { id } = useEntityContext();
            if (!id || !SitePluginSidebar) {
                return null;
            }

            return (
                <SitePluginSidebar
                    name="novi-content-translator"
                    title={__('Novi Content Translator', 'novi-content-translator')}
                >
                    <SidebarTabs />
                </SitePluginSidebar>
            );
        }

        function NoviTranslatorPlugin() {
            const { id, type } = useEntityContext();

            const translatedTypes = (typeof noviContentTranslator !== 'undefined' && Array.isArray(noviContentTranslator.translatedPostTypes))
                ? noviContentTranslator.translatedPostTypes
                : null;

            if (!id) {
                return null;
            }

            //respect polylang-translated post types but always allow wp_block patterns,
            //so the panel is available in the site editor for reusable blocks
            if (translatedTypes && type && type !== 'wp_block' && translatedTypes.indexOf(type) === -1) {
                return null;
            }

            // In Site Editor (patterns, templates) use PluginSidebar from wp.editSite so we appear
            // in the same sidebar as Polylang "Languages". For posts/pages/CPTs, use PluginSidebar
            // from wp.editPost.
            const siteEditorPostTypes = ['wp_block', 'wp_template', 'wp_template_part'];
            if (type && siteEditorPostTypes.indexOf(type) !== -1 && SitePluginSidebar) {
                return <SiteEditorSidebarWrapper />;
            }

            if (PostPluginSidebar) {
                return <DocumentPanelWrapper />;
            }

            if (SitePluginSidebar) {
                return <SiteEditorSidebarWrapper />;
            }

            return null;
        }

        registerPlugin('novi-content-translator', {
            icon: <NoviSidebarIcon />,
            render: () => (
                <Fragment>
                    <NoviTranslatorPlugin />
                </Fragment>
            ),
        });

        if (wp && wp.domReady) {
            wp.domReady(debugDumpNectarTextBlocks)
        }
    }

    if (typeof wp !== 'undefined' && wp.domReady) {
        wp.domReady(bootstrap);
    } else {
        document.addEventListener('DOMContentLoaded', bootstrap);
    }
})();

