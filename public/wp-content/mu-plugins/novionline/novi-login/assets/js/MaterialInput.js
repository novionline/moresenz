/**
 * MaterialInput – floating label behavior for login form inputs
 * @param {Element} containerElement
 * @param {HTMLLabelElement} labelElement
 * @param {HTMLInputElement|HTMLTextAreaElement} inputElement
 */
function MaterialInput(containerElement, labelElement, inputElement) {
    const CSS_CLASS_ACTIVE = 'material-input--active'
    const CSS_CLASS_INITIATED = 'material-input--initiated'

    if (containerElement === null || !(containerElement instanceof Element)) {
        throw new Error('Missing required parameter containerElement in MaterialInput or not an Element.')
    }
    if (labelElement === null || !(labelElement instanceof HTMLLabelElement)) {
        throw new Error('Missing required parameter labelElement in MaterialInput or not an HTMLLabelElement.')
    }
    const isInputOrTextarea = inputElement instanceof HTMLInputElement || inputElement instanceof HTMLTextAreaElement
    if (inputElement === null || !isInputOrTextarea) {
        throw new Error('Missing required parameter inputElement in MaterialInput or not an HTMLInputElement or HTMLTextAreaElement.')
    }

    if (containerElement.classList.contains(CSS_CLASS_INITIATED)) return

    this.state = {
        containerElement: containerElement,
        inputElement: inputElement,
        labelElement: labelElement,
        value: inputElement.value,
        active: inputElement.value.length > 0
    }

    if (this.state.active === true) this.activate()

    this.state.inputElement.addEventListener('change', this.handleChange.bind(this))
    this.state.inputElement.addEventListener('input', this.syncFromValue.bind(this))
    this.state.inputElement.addEventListener('focus', this.handleFocus.bind(this))
    this.state.inputElement.addEventListener('blur', this.handleBlur.bind(this))

    // re-check after short delays so we catch browser autofill (no reliable event)
    var self = this
    ;[100, 400, 800].forEach(function (delay) {
        setTimeout(function () {
            self.syncFromValue()
        }, delay)
    })

    this.state.containerElement.classList.add(CSS_CLASS_INITIATED)
}

MaterialInput.prototype.handleChange = function (event) {
    this.state.value = event.currentTarget.value
    this.syncFromValue()
}

/**
 * Sync active state from current input value (handles autofill and programmatic changes)
 */
MaterialInput.prototype.syncFromValue = function () {
    var value = this.state.inputElement.value
    this.state.value = value
    if (value.length > 0) {
        this.activate()
    } else {
        this.deactivate()
    }
}

MaterialInput.prototype.handleFocus = function () {
    this.activate()
}

MaterialInput.prototype.handleBlur = function () {
    this.syncFromValue()
}

MaterialInput.prototype.activate = function () {
    this.state.active = true
    this.state.containerElement.classList.add('material-input--active')
}

MaterialInput.prototype.deactivate = function () {
    this.state.active = false
    this.state.containerElement.classList.remove('material-input--active')
}

window.MaterialInput = MaterialInput
