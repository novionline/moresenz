//header transparency fix for mega menus
//prevents the header from losing transparency when hovering over mega menus
//leverages nectar theme's existing scroll detection via body classes

(function() {
    'use strict'
    
    //get dom elements
    const nectarNav = document.getElementById('nectar-nav')
    const body = document.body

    //early exit if required elements don't exist
    if (!nectarNav) {
        return
    }

    const hasTransparentHeader = nectarNav.getAttribute('data-transparent-header') === 'true'

    if (!hasTransparentHeader) {
        return
    }

    //track mega menu hover state
    let isMegaMenuHovered = false

    //function to check if we're at top of page using nectar's body classes
    function isAtTop() {
        //use nectar theme's existing scroll detection
        return !body.classList.contains('scrolled-down')
    }

    //monitor mega menu hover state
    const megaMenus = document.querySelectorAll('.sf-menu .megamenu')

    megaMenus.forEach(function(megaMenu) {
        megaMenu.addEventListener('mouseenter', function() {
            isMegaMenuHovered = true
            //restore transparency if we're at the top of the page
            if (isAtTop() && !nectarNav.classList.contains('transparent')) {
                setTimeout(function() {
                    //double-check we're still at the top before adding transparency
                    if (isAtTop()) {
                        nectarNav.classList.add('transparent')
                    }
                }, 20)
            }
        })
        
        megaMenu.addEventListener('mouseleave', function() {
            isMegaMenuHovered = false
        })
    })

    //monitor for scroll changes and remove transparency when scrolled down while hovering
    window.addEventListener('scroll', function() {
        //if we've scrolled down and are hovering a mega menu, remove transparency immediately
        if (!isAtTop() && isMegaMenuHovered && nectarNav.classList.contains('transparent')) {
            nectarNav.classList.remove('transparent')
        }
    })

    //use mutationobserver to restore transparency when removed by mega menu hover
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const target = mutation.target
                    if (target.id === 'nectar-nav' && hasTransparentHeader) {
                        //only restore transparency if:
                        //1. we're hovering a mega menu
                        //2. we're at the top of the page (using nectar's body classes)
                        //3. the transparent class was removed
                        //4. side widget is not open
                        if (isMegaMenuHovered && 
                            isAtTop() && 
                            !target.classList.contains('transparent') && 
                            !target.classList.contains('side-widget-open')) {
                            
                            setTimeout(function() {
                                //final check before restoring transparency
                                if (isAtTop()) {
                                    target.classList.add('transparent')
                                }
                            }, 1)
                        }
                    }
                }
            })
        })
        
        //start observing
        observer.observe(nectarNav, {
            attributes: true,
            attributeFilter: ['class']
        })
    }
})()
