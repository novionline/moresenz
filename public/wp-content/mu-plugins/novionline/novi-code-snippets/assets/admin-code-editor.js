(function() {
    'use strict'

    if (typeof wp === 'undefined' || !wp.codeEditor || typeof ncsCodeEditorSettings === 'undefined') {
        return
    }

    var settings = ncsCodeEditorSettings
    var codeEditorInstance = null
    var validityDebounceTimer = null
    var VALIDITY_DEBOUNCE_MS = 600
    var beautifyToolbar = null

    function getTypeSelect() {
        return document.querySelector('.acf-field[data-name="snippet_type"] select') || document.querySelector('[data-name="snippet_type"] select')
    }

    function getCodeTextarea() {
        return document.querySelector('.ncs-code-field textarea')
    }

    function getCodeFieldWrapper() {
        var textarea = getCodeTextarea()
        return textarea ? textarea.closest('.ncs-code-field') : null
    }

    function beautifyCode() {
        if (!codeEditorInstance || !codeEditorInstance.codemirror) return
        var typeSelect = getTypeSelect()
        if (!typeSelect) return
        var type = typeSelect.value || 'css'
        var code = codeEditorInstance.codemirror.getValue()
        var formatted
        try {
            if (type === 'js') {
                formatted = typeof window.js_beautify === 'function' ? window.js_beautify(code) : code
            } else {
                formatted = typeof window.css_beautify === 'function' ? window.css_beautify(code) : code
            }
        } catch (e) {
            return
        }
        if (formatted != null) {
            codeEditorInstance.codemirror.setValue(formatted)
            var textarea = getCodeTextarea()
            if (textarea) textarea.value = formatted
        }
    }

    function injectBeautifyToolbar() {
        var wrapper = getCodeFieldWrapper()
        if (!wrapper || wrapper.querySelector('.ncs-beautify-toolbar')) return
        var btnText = (settings.beautifyButtonText || 'Beautify')
        beautifyToolbar = document.createElement('div')
        beautifyToolbar.className = 'ncs-beautify-toolbar'
        beautifyToolbar.innerHTML = '<button type="button" class="button ncs-beautify-btn">' + escapeHtml(btnText) + '</button>'
        wrapper.insertBefore(beautifyToolbar, wrapper.firstChild)
        beautifyToolbar.querySelector('.ncs-beautify-btn').addEventListener('click', beautifyCode)
    }

    function removeBeautifyToolbar() {
        if (beautifyToolbar && beautifyToolbar.parentNode) {
            beautifyToolbar.parentNode.removeChild(beautifyToolbar)
        }
        beautifyToolbar = null
        var existing = document.querySelector('.ncs-beautify-toolbar')
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing)
        }
    }

    function getValidityBlock() {
        return document.getElementById('ncs-validity-feedback')
    }

    function getSettingsForType(type) {
        return type === 'js' ? settings.js : settings.css
    }

    function updateValidityBlock(valid, message, line, column) {
        var block = getValidityBlock()
        if (!block) return
        var loc = []
        if (line != null) loc.push('line ' + line)
        if (column != null) loc.push('column ' + column)
        var locStr = loc.length ? ' (' + loc.join(', ') + ')' : ''
        if (valid) {
            block.innerHTML = '<span class="ncs-validity-valid">' + (settings.validityValidText || 'Valid. This snippet will be output on the front-end.') + '</span>'
        } else {
            block.innerHTML = '<span class="ncs-validity-invalid">' +
                (settings.validityInvalidText || 'Invalid. This snippet will NOT be shown on the front-end.') +
                (message ? ' <span class="ncs-validity-message">' + escapeHtml(message) + '</span>' : '') +
                (locStr ? ' <span class="ncs-validity-location">' + escapeHtml(locStr) + '</span>' : '') +
                '</span>'
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    function validateAndUpdate() {
        if (!codeEditorInstance || !codeEditorInstance.codemirror || !settings.validateUrl || !settings.validateNonce) return
        var typeSelect = getTypeSelect()
        if (!typeSelect) return
        var code = codeEditorInstance.codemirror.getValue()
        var type = typeSelect.value || 'css'
        var xhr = new XMLHttpRequest()
        xhr.open('POST', settings.validateUrl, true)
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return
            var response
            try {
                response = JSON.parse(xhr.responseText)
            } catch (e) {
                return
            }
            var data = response.success && response.data ? response.data : null
            if (data) {
                updateValidityBlock(data.valid, data.message || null, data.line != null ? data.line : null, data.column != null ? data.column : null)
            }
        }
        xhr.send(
            'action=ncs_validate_snippet&nonce=' + encodeURIComponent(settings.validateNonce) +
            '&code=' + encodeURIComponent(code) +
            '&type=' + encodeURIComponent(type)
        )
    }

    function scheduleValidityCheck() {
        if (validityDebounceTimer) clearTimeout(validityDebounceTimer)
        validityDebounceTimer = setTimeout(function() {
            validityDebounceTimer = null
            validateAndUpdate()
        }, VALIDITY_DEBOUNCE_MS)
    }

    function setEditorHeight() {
        if (!codeEditorInstance || !codeEditorInstance.codemirror) return
        var toolbarHeight = 40
        var height = Math.max(300, (window.innerHeight * 0.6) - toolbarHeight)
        codeEditorInstance.codemirror.setSize(null, height)
        codeEditorInstance.codemirror.refresh()
    }

    function initCodeEditor() {
        var textarea = getCodeTextarea()
        if (!textarea) return
        var typeSelect = getTypeSelect()
        var type = typeSelect ? typeSelect.value : 'css'
        var editorSettings = getSettingsForType(type)
        if (!editorSettings) return
        codeEditorInstance = wp.codeEditor.initialize(textarea, editorSettings)
        if (codeEditorInstance && codeEditorInstance.codemirror) {
            codeEditorInstance.codemirror.on('blur', validateAndUpdate)
            codeEditorInstance.codemirror.on('change', scheduleValidityCheck)
            setTimeout(injectBeautifyToolbar, 0)
            setTimeout(setEditorHeight, 0)
            window.addEventListener('resize', setEditorHeight)
        }
    }

    function destroyCodeEditor() {
        if (validityDebounceTimer) {
            clearTimeout(validityDebounceTimer)
            validityDebounceTimer = null
        }
        removeBeautifyToolbar()
        window.removeEventListener('resize', setEditorHeight)
        if (codeEditorInstance && codeEditorInstance.codemirror) {
            codeEditorInstance.codemirror.off('blur', validateAndUpdate)
            codeEditorInstance.codemirror.off('change', scheduleValidityCheck)
            codeEditorInstance.codemirror.toTextArea()
            codeEditorInstance = null
        }
    }

    function reinitCodeEditor() {
        destroyCodeEditor()
        setTimeout(initCodeEditor, 0)
    }

    function bindTypeChange() {
        var typeSelect = getTypeSelect()
        if (typeSelect) {
            typeSelect.addEventListener('change', reinitCodeEditor)
        }
    }

    function bindSaveWarning() {
        var form = document.getElementById('post')
        if (!form || !settings.validateUrl || !settings.validateNonce) return

        form.addEventListener('submit', function onSubmit(event) {
            if (!codeEditorInstance || !codeEditorInstance.codemirror) return

            var textarea = getCodeTextarea()
            var typeSelect = getTypeSelect()
            if (!textarea || !typeSelect) return

            event.preventDefault()
            event.stopImmediatePropagation()

            textarea.value = codeEditorInstance.codemirror.getValue()
            var type = typeSelect.value || 'css'
            var code = textarea.value

            var xhr = new XMLHttpRequest()
            xhr.open('POST', settings.validateUrl, true)
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return
                var response
                try {
                    response = JSON.parse(xhr.responseText)
                } catch (e) {
                    form.removeEventListener('submit', onSubmit)
                    form.submit()
                    return
                }
                var valid = response.success && response.data && response.data.valid
                if (valid) {
                    form.removeEventListener('submit', onSubmit)
                    form.submit()
                    return
                }
                var msg = 'The code may contain syntax errors. Save anyway?'
                if (!window.confirm(msg)) return
                form.removeEventListener('submit', onSubmit)
                form.submit()
            }
            xhr.send(
                'action=ncs_validate_snippet&nonce=' + encodeURIComponent(settings.validateNonce) +
                '&code=' + encodeURIComponent(code) +
                '&type=' + encodeURIComponent(type)
            )
        })
    }

    function disableLeavePrompt() {
        if (typeof window.acf !== 'undefined' && window.acf.unload) {
            window.acf.unload.active = false
        }
    }

    function run() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                disableLeavePrompt()
                initCodeEditor()
                bindTypeChange()
                bindSaveWarning()
            })
        } else {
            disableLeavePrompt()
            initCodeEditor()
            bindTypeChange()
            bindSaveWarning()
        }
        //ACF may init after our script; disable leave prompt again once DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', disableLeavePrompt)
        }
    }

    run()
})()
