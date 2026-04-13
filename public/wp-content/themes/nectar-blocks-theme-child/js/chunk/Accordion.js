/**
 * Accordion class
 * Handles the accordion functionality with accessible toggle actions for opening/closing content sections.
 * It manages ARIA attributes and ensures the content visibility state is controlled via a button click.
 */
class Accordion {

    /**
     * Accordion constructor
     * @param {HTMLElement} $rootEl - The HTML element which contains the accordion.
     * Throws an error if required DOM elements are missing.
     */
    constructor($rootEl) {

        //validate root element
        if (!$rootEl || !($rootEl instanceof HTMLElement)) throw new Error('Accordion - Invalid root element passed to constructor')

        //prevent double initialization of this class
        if ($rootEl.classList.contains('novi-accordion--initiated')) return

        //validate button element
        const $buttonEl = $rootEl.querySelector('button[aria-expanded]')
        if (!$buttonEl) throw new Error('Accordion - Accordion button (button[aria-expanded]) is required inside the root element')

        //validate content element
        const controlsId = $buttonEl.getAttribute('aria-controls')
        if (!controlsId) throw new Error('Accordion - Accordion button missing "aria-controls" attribute')

        //get content element
        const $contentEl = $rootEl.querySelector(`#${controlsId}`)
        if (!$contentEl) throw new Error(`Accordion - No element found with id "${controlsId}" for accordion content`)

        this.state = {
            $rootEl,        //the root element containing the accordion
            $buttonEl,      //the button element that toggles the accordion
            $contentEl,     //the content element of the accordion
            controlsId,     //store controlsId for custom events
            wasInitiallyHidden: $contentEl.hasAttribute('hidden'),          //track if the content is initially hidden
            open: $buttonEl.getAttribute('aria-expanded') === 'true',       //track if the accordion is open or closed
            openEvent: new CustomEvent('novi-accordion:open', {detail: {controlsId}}),     //custom open event
            closeEvent: new CustomEvent('novi-accordion:close', {detail: {controlsId}})    //custom close event
        }

        //add event listener for the button click
        this.state.$buttonEl.addEventListener('click', this.onButtonClick.bind(this))

        //add initiated modifier to element to prevent double execution
        this.state.$rootEl.classList.add('novi-accordion--initiated')
    }

    /**
     * Event handler for button click.
     * Toggles the state of the accordion (open/closed).
     * @param {Event} event - The click event
     */
    onButtonClick(event) {
        if (event) event.preventDefault()
        this.state.open ? this.close() : this.open()  // toggle state based on the current state
    }

    /**
     * Open the accordion
     */
    open() {

        //if already open, do nothing
        if (this.state.open) return

        //update state to reflect open status
        this.state.open = true

        //update aria-expanded attribute for accessibility
        Accordion.setAriaExpanded(this.state.$buttonEl, 'true')

        //toggle the hidden attribute based on the accordion's visibility
        if (this.state.wasInitiallyHidden) this.state.$contentEl.removeAttribute('hidden')

        //dispatch open event
        window.dispatchEvent(this.state.openEvent)
    }

    /**
     * Close the accordion
     */
    close() {

        //if already closed, do nothing
        if (!this.state.open) return

        //update state to reflect closed status
        this.state.open = false

        //update aria-expanded attribute for accessibility
        Accordion.setAriaExpanded(this.state.$buttonEl, 'false')

        //toggle the hidden attribute based on the accordion's visibility
        if (this.state.wasInitiallyHidden) this.state.$contentEl.setAttribute('hidden', '')

        //dispatch close event
        window.dispatchEvent(this.state.closeEvent)
    }

    /**
     * Toggle the accordion open or closed.
     * @param {boolean} open - If true, the accordion will be opened, otherwise it will be closed
     */
    toggle(open) {
        open ? this.open() : this.close()
    }

    /**
     * Static helper method for setting aria-expanded.
     * @param {HTMLElement} $buttonEl - The button element to update
     * @param {string} value - The value to set for aria-expanded
     */
    static setAriaExpanded($buttonEl, value) {
        $buttonEl.setAttribute('aria-expanded', value)
    }
}

window.noviAccordion = Accordion