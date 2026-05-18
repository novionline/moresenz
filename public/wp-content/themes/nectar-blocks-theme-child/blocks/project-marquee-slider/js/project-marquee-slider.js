(function () {
  const RESUME_DELAY_MS = 300

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
  }

  function getInners(track) {
    return track.querySelectorAll('.nectar-blocks-marquee__inner')
  }

  function parseDurationSeconds(duration) {
    if (!duration || duration === '0s') return 0
    const match = String(duration).match(/^([\d.]+)s$/)
    return match ? parseFloat(match[1]) : 35
  }

  function getAnimationProgress(inner) {
    const computed = window.getComputedStyle(inner)
    const duration = parseDurationSeconds(computed.animationDuration)
    if (!duration) return 0
    const delay = parseFloat(computed.animationDelay) || 0
    const elapsed = (Date.now() / 1000 + delay) % duration
    return elapsed / duration
  }

  function setAnimationProgress(inners, progress, durationSeconds) {
    const delay = -(progress * durationSeconds)
    inners.forEach(function (inner) {
      inner.style.animationDelay = delay + 's'
    })
  }

  function initVideos(slider) {
    const links = slider.querySelectorAll('.project-marquee-slider__link--has-video')

    links.forEach(function (link) {
      const video = link.querySelector('.project-marquee-slider__video')
      if (!video) return

      const playVideo = function () {
        const playPromise = video.play()
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(function () {})
        }
      }

      const pauseVideo = function () {
        video.pause()
        video.currentTime = 0
      }

      link.addEventListener('mouseenter', playVideo)
      link.addEventListener('mouseleave', pauseVideo)
      link.addEventListener('focusin', playVideo)
      link.addEventListener('focusout', function (e) {
        if (!link.contains(e.relatedTarget)) {
          pauseVideo()
        }
      })
    })
  }

  function initMarquee(slider) {
    const track = slider.querySelector('.project-marquee-slider__track')
    if (!track) return

    const inners = getInners(track)
    if (!inners.length) return

    if (prefersReducedMotion()) {
      return
    }

    let isPaused = false
    let isDragging = false
    let resumeTimer = null
    let dragStartX = 0
    let dragOffsetPx = 0
    let dragProgress = 0

    const durationSeconds = parseDurationSeconds(
      window.getComputedStyle(track).getPropertyValue('--speed').trim() ||
        window.getComputedStyle(inners[0]).animationDuration
    )

    const pause = function () {
      isPaused = true
      track.classList.add('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = 'paused'
      })
    }

    const resume = function () {
      if (isDragging) return
      isPaused = false
      track.classList.remove('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = ''
        inner.style.transform = ''
      })
      track.style.setProperty('--drag-offset', '0px')
    }

    const scheduleResume = function () {
      clearTimeout(resumeTimer)
      resumeTimer = setTimeout(function () {
        if (!slider.matches(':hover') && !slider.contains(document.activeElement)) {
          resume()
        }
      }, RESUME_DELAY_MS)
    }

    slider.addEventListener('mouseenter', pause)
    slider.addEventListener('mouseleave', scheduleResume)
    slider.addEventListener('focusin', pause)
    slider.addEventListener('focusout', function (e) {
      if (!slider.contains(e.relatedTarget)) {
        scheduleResume()
      }
    })

    track.addEventListener('pointerdown', function (e) {
      if (e.button !== 0) return
      isDragging = true
      track.classList.add('is-dragging')
      pause()
      dragStartX = e.clientX
      dragOffsetPx = 0
      dragProgress = getAnimationProgress(inners[0])
      track.setPointerCapture(e.pointerId)
    })

    track.addEventListener('pointermove', function (e) {
      if (!isDragging) return
      dragOffsetPx = e.clientX - dragStartX
      const offset = dragOffsetPx + 'px'
      track.style.setProperty('--drag-offset', offset)
      inners.forEach(function (inner) {
        inner.style.transform = 'translateX(' + offset + ')'
      })
    })

    const endDrag = function (e) {
      if (!isDragging) return
      isDragging = false
      track.classList.remove('is-dragging')
      if (track.hasPointerCapture(e.pointerId)) {
        track.releasePointerCapture(e.pointerId)
      }

      const innerWidth = inners[0].scrollWidth || 1
      const progressDelta = dragOffsetPx / innerWidth
      let newProgress = dragProgress - progressDelta
      newProgress = ((newProgress % 1) + 1) % 1

      inners.forEach(function (inner) {
        inner.style.transform = ''
      })
      track.style.setProperty('--drag-offset', '0px')
      setAnimationProgress(inners, newProgress, durationSeconds)

      if (!slider.matches(':hover') && !slider.contains(document.activeElement)) {
        scheduleResume()
      } else {
        pause()
      }
    }

    track.addEventListener('pointerup', endDrag)
    track.addEventListener('pointercancel', endDrag)
  }

  function initSliders() {
    document.querySelectorAll('.project-marquee-slider').forEach(function (slider) {
      initMarquee(slider)
      initVideos(slider)
    })
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSliders)
  } else {
    initSliders()
  }
})()
