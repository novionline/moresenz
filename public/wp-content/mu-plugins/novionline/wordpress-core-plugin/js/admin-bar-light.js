/**
 * Admin bar light front-end behaviors:
 * - flip dropdown alignment when it would overflow the viewport
 */
const adminBarLight = document.querySelector('.admin-bar-light')

if (adminBarLight) {
    const moreToggle = adminBarLight.querySelector('.admin-bar-light__checkbox-more')
    const listMore = adminBarLight.querySelector('.admin-bar-light__list-more')

    const updateDropdownAlignment = () => {
        if (!listMore) return

        //only measure when the dropdown is visible
        const isOpen = moreToggle && moreToggle.checked
        if (!isOpen) return

        listMore.classList.remove('is-align-left', 'is-align-right')

        const rect = listMore.getBoundingClientRect()

        if (rect.left < 0) {
            listMore.classList.add('is-align-left')
        } else if (rect.right > window.innerWidth) {
            listMore.classList.add('is-align-right')
        }
    }

    let resizeTimer = null
    const debouncedUpdate = () => {
        window.clearTimeout(resizeTimer)
        resizeTimer = window.setTimeout(updateDropdownAlignment, 100)
    }

    if (moreToggle) {
        moreToggle.addEventListener('change', () => {
            //allow layout to settle before measuring
            window.requestAnimationFrame(updateDropdownAlignment)
        })
    }

    window.addEventListener('resize', debouncedUpdate)
}
