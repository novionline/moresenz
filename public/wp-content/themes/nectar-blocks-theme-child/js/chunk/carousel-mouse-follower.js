(function () {
  'use strict'

  //only run on non-touch devices (pointer: fine)
  function isTouchOnly() {
    if (typeof window.matchMedia !== 'function') return false
    return !window.matchMedia('(pointer: fine)').matches
  }

  if (isTouchOnly()) return

  function initCarouselMouseFollower() {
    var carousels = document.querySelectorAll('.nectar-blocks-carousel[data-novi-mouse-follower="true"]')
    if (!carousels.length) return

    carousels.forEach(function (carousel) {
      var indicator = carousel.querySelector('.novi-drag-indicator, .novi-carousel-mouse-follower')
      if (!indicator) return

      var indicatorSize = 94
      var halfSize = indicatorSize / 2

      function setPosition(clientX, clientY) {
        indicator.style.left = (clientX - halfSize) + 'px'
        indicator.style.top = (clientY - halfSize) + 'px'
      }

      function isNavigationElement(target) {
        if (!target || typeof target.closest !== 'function') return false

        return Boolean(target.closest(
          '.swiper-pagination-wrap, .swiper-pagination, .swiper-button-prev, .swiper-button-next, .swiper-arrow'
        ))
      }

      function show() {
        indicator.classList.add('novi-drag-indicator-visible')
      }

      function hide() {
        indicator.classList.remove('novi-drag-indicator-visible', 'novi-drag-indicator-pointer-down')
      }

      function addPointerDown() {
        indicator.classList.add('novi-drag-indicator-pointer-down')
      }

      function removePointerDown() {
        indicator.classList.remove('novi-drag-indicator-pointer-down')
      }

      carousel.addEventListener('mousemove', function (e) {
        if (isNavigationElement(e.target)) {
          hide()
          return
        }

        setPosition(e.clientX, e.clientY)
        show()
      })

      carousel.addEventListener('mouseleave', hide)

      carousel.addEventListener('pointerdown', function (e) {
        if (isNavigationElement(e.target)) {
          hide()
          return
        }

        if (e.pointerType === 'mouse') {
          addPointerDown()
        }
      })

      document.addEventListener('pointerup', removePointerDown)
      document.addEventListener('pointerleave', removePointerDown)
    })
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCarouselMouseFollower)
  } else {
    initCarouselMouseFollower()
  }
})()
