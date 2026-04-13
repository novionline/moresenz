/**
 * Novi Login – material input setup on login form, left-panel animation
 */
(function () {
    function initLeftPanelAnimation() {
        const art = document.querySelector('.novi-login .novi-login__background-art')
        if (!art) return

        const driftX = 35 + Math.floor(Math.random() * 36) + 'px'
        const driftY = (Math.random() > 0.5 ? 1 : -1) * (20 + Math.floor(Math.random() * 26)) + 'px'
        const driftRotate = (Math.random() > 0.5 ? 1 : -1) * (2 + Math.random() * 3).toFixed(2) + 'deg'
        const duration = 35 + Math.floor(Math.random() * 36) + 's'

        art.style.setProperty('--drift-x', driftX)
        art.style.setProperty('--drift-y', driftY)
        art.style.setProperty('--drift-rotate', driftRotate)
        art.style.setProperty('--drift-duration', duration)
    }

    function setupMaterialInputContainers() {
        const loginForm = document.querySelector('#login form')
        if (!loginForm) return

        const inputs = loginForm.querySelectorAll('input.input')
        inputs.forEach(function (input) {
            const label = loginForm.querySelector('label[for="' + input.id + '"]')
            if (label) {
                const container = document.createElement('div')
                container.classList.add('material-input')
                if (input.tagName.toLowerCase() === 'textarea') {
                    container.classList.add('type-textarea')
                }
                input.parentNode.insertBefore(container, input)
                container.appendChild(label)
                container.appendChild(input)
            }
        })
    }

    function initMaterialInputs() {
        const containers = document.querySelectorAll('.material-input')
        if (containers.length === 0) return
        if (typeof window.MaterialInput !== 'function') return

        containers.forEach(function (container) {
            const isTextarea = container.classList.contains('type-textarea') || container.classList.contains('type-post_content')
            const label = container.querySelector('label')
            const input = container.querySelector(isTextarea ? 'textarea' : 'input')
            if (label && input) {
                new window.MaterialInput(container, label, input)
            }
        })
    }

    function init() {
        initLeftPanelAnimation()
        setupMaterialInputContainers()
        initMaterialInputs()
    }

    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded', init)
    } else {
        init()
    }
})()
