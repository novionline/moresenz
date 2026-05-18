(function () {
  const RESUME_DELAY_MS = 300
  const DRAG_THRESHOLD_PX = 8
  const CARD_HOLD_MS = 400
  const CARD_HOLD_MOVE_CANCEL_PX = 10

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
  }

  function prefersTouchHold() {
    return window.matchMedia('(hover: none), (pointer: coarse)').matches
  }

  function deactivateAllCardHovers(slider) {
    slider.querySelectorAll('.project-marquee-slider__link.is-card-active').forEach(function (link) {
      link.classList.remove('is-card-active', 'is-video-active', 'is-video-loading')
      const video = link.querySelector('.project-marquee-slider__video')
      if (video) {
        video.pause()
      }
    })
  }

  function bindCardTouchHold(link, onActivate, onDeactivate) {
    let holdTimer = null
    let isActive = false
    let suppressClick = false
    let startX = 0
    let startY = 0
    let activePointerId = null

    const cancelHold = function () {
      if (holdTimer) {
        clearTimeout(holdTimer)
        holdTimer = null
      }
    }

    const cleanupDocumentListeners = function () {
      document.removeEventListener('pointermove', onDocumentPointerMove)
      document.removeEventListener('pointerup', onDocumentPointerUp)
      document.removeEventListener('pointercancel', onDocumentPointerUp)
    }

    const deactivate = function () {
      cancelHold()
      cleanupDocumentListeners()
      if (!isActive) {
        return
      }
      isActive = false
      onDeactivate()
    }

    const activate = function () {
      if (isActive) {
        return
      }
      isActive = true
      suppressClick = true
      onActivate()
    }

    const onDocumentPointerMove = function (e) {
      if (activePointerId === null || e.pointerId !== activePointerId) {
        return
      }

      const deltaX = Math.abs(e.clientX - startX)
      const deltaY = Math.abs(e.clientY - startY)
      if (deltaX < CARD_HOLD_MOVE_CANCEL_PX && deltaY < CARD_HOLD_MOVE_CANCEL_PX) {
        return
      }

      if (!isActive) {
        cancelHold()
      }
    }

    const onDocumentPointerUp = function (e) {
      if (activePointerId === null || e.pointerId !== activePointerId) {
        return
      }

      cancelHold()
      if (isActive) {
        deactivate()
      } else {
        cleanupDocumentListeners()
      }

      activePointerId = null
    }

    link.addEventListener('pointerdown', function (e) {
      if (e.button !== 0) {
        return
      }

      activePointerId = e.pointerId
      startX = e.clientX
      startY = e.clientY
      cancelHold()

      cleanupDocumentListeners()
      document.addEventListener('pointermove', onDocumentPointerMove, { passive: true })
      document.addEventListener('pointerup', onDocumentPointerUp)
      document.addEventListener('pointercancel', onDocumentPointerUp)

      holdTimer = setTimeout(activate, CARD_HOLD_MS)
    })

    link.addEventListener('click', function (e) {
      if (!suppressClick) {
        return
      }
      e.preventDefault()
      suppressClick = false
    }, true)
  }

  function getInners(track) {
    return track.querySelectorAll('.nectar-blocks-marquee__inner')
  }

  function parseDurationSeconds(duration) {
    if (!duration || duration === '0s') return 0
    const match = String(duration).match(/^([\d.]+)s$/)
    return match ? parseFloat(match[1]) : 35
  }

  function getTranslateXFromMatrix(element) {
    const transform = window.getComputedStyle(element).transform
    if (!transform || transform === 'none') return 0

    if (typeof DOMMatrixReadOnly !== 'undefined') {
      return new DOMMatrixReadOnly(transform).m41
    }

    const matrix = transform.match(/^matrix\(([^)]+)\)$/)
    if (matrix) {
      const values = matrix[1].split(',').map(function (value) {
        return parseFloat(value.trim())
      })
      return values[4] || 0
    }

    return 0
  }

  function getTranslateXFromWAAPI(inner, track) {
    const loopDistance = getLoopDistance(inner, track)
    if (!loopDistance) {
      return null
    }

    const animations = inner.getAnimations()
    for (let i = 0; i < animations.length; i++) {
      const anim = animations[i]
      const name = anim.animationName || ''
      if (name.indexOf('marquee') === -1) {
        continue
      }

      const effect = anim.effect
      if (!effect) {
        continue
      }

      const timing = effect.getComputedTiming()
      const duration = timing.duration
      if (typeof duration !== 'number' || !isFinite(duration) || duration <= 0) {
        continue
      }

      let currentTime = anim.currentTime
      if (currentTime === null || currentTime === undefined) {
        currentTime = 0
      }

      const progress = (currentTime % duration) / duration
      return -progress * loopDistance
    }

    return null
  }

  function getTranslateXFromAnimationDelay(inner, track) {
    const durationSeconds = parseDurationSeconds(window.getComputedStyle(inner).animationDuration)
    if (!durationSeconds) {
      return null
    }

    const delayRaw = window.getComputedStyle(inner).animationDelay || ''
    const firstDelay = delayRaw.split(',')[0].trim()
    if (!firstDelay || firstDelay === '0s') {
      return null
    }

    const delaySeconds = parseFloat(firstDelay)
    if (!Number.isFinite(delaySeconds) || delaySeconds === 0) {
      return null
    }

    const loopDistance = getLoopDistance(inner, track)
    if (!loopDistance) {
      return null
    }

    const progress = ((-delaySeconds / durationSeconds) % 1 + 1) % 1
    return -progress * loopDistance
  }

  function getFrozenTranslateX(inner, track) {
    const matrixX = getTranslateXFromMatrix(inner)
    const fromDelay = getTranslateXFromAnimationDelay(inner, track)
    const fromWaaPI = getTranslateXFromWAAPI(inner, track)

    //computed matrix is authoritative when it shows movement
    if (Math.abs(matrixX) >= 1) {
      return matrixX
    }

    //when matrix reads 0 mid-loop, derive phase from delay or WAAPI (loop offset)
    if (fromDelay !== null && Math.abs(fromDelay) >= 1) {
      return fromDelay
    }

    if (fromWaaPI !== null && Math.abs(fromWaaPI) >= 1) {
      return fromWaaPI
    }

    if (fromDelay !== null) {
      return fromDelay
    }

    if (fromWaaPI !== null) {
      return fromWaaPI
    }

    return matrixX
  }

  function getGapPx(track) {
    const styles = window.getComputedStyle(track)
    const gapValue = styles.columnGap || styles.gap || '0'
    const parsed = parseFloat(gapValue)
    return Number.isFinite(parsed) ? parsed : 0
  }

  function getLoopDistance(inner, track) {
    return inner.offsetWidth + getGapPx(track)
  }

  function progressFromTranslateX(translateX, loopDistance) {
    if (!loopDistance) {
      return 0
    }
    return ((-translateX / loopDistance) % 1 + 1) % 1
  }

  function freezeAnimationAtCurrentPosition(inners, track, translateX) {
    const resolvedTranslateX =
      typeof translateX === 'number'
        ? translateX
        : getFrozenTranslateX(inners[0], track)

    inners.forEach(function (inner) {
      inner.style.animationPlayState = ''
      inner.style.animation = 'none'
      inner.style.animationDelay = ''
      inner.style.transform = 'translate3d(' + resolvedTranslateX + 'px, 0, 0)'
    })

    return [resolvedTranslateX]
  }

  function isTouchPointer(e) {
    return e.pointerType === 'touch' || e.pointerType === 'pen'
  }

  function preventNativeDrag(track) {
    track.addEventListener('dragstart', function (e) {
      e.preventDefault()
    })

    track.querySelectorAll('.project-marquee-slider__link, .project-marquee-slider__image').forEach(function (element) {
      element.setAttribute('draggable', 'false')
      element.addEventListener('dragstart', function (e) {
        e.preventDefault()
      })
    })
  }

  function getVideoSourceUrl(video) {
    const source = video.querySelector('source')
    if (source && source.getAttribute('data-src')) {
      return source.getAttribute('data-src')
    }
    return video.getAttribute('data-src') || ''
  }

  function shouldPreloadVideos() {
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection
    if (connection && connection.saveData) {
      return false
    }
    return true
  }

  function attachVideoSource(video) {
    if (video.dataset.sourceAttached === 'true') {
      return true
    }

    const videoUrl = getVideoSourceUrl(video)
    if (!videoUrl) {
      return false
    }

    const source = video.querySelector('source')
    if (source) {
      source.src = videoUrl
    }
    video.src = videoUrl
    video.preload = 'auto'
    video.load()
    video.dataset.sourceAttached = 'true'
    return true
  }

  function preloadVideo(video) {
    if (video.dataset.preloadReady === 'true') {
      return Promise.resolve()
    }

    if (video._preloadPromise) {
      return video._preloadPromise
    }

    if (!attachVideoSource(video)) {
      return Promise.resolve()
    }

    video.dataset.preloadReady = 'loading'
    video._preloadPromise = new Promise(function (resolve) {
      const finish = function () {
        video.dataset.preloadReady = 'true'
        video.dataset.loaded = 'true'
        resolve()
      }

      if (video.readyState >= HTMLMediaElement.HAVE_FUTURE_DATA) {
        finish()
        return
      }

      video.addEventListener('canplay', finish, { once: true })
      video.addEventListener('error', finish, { once: true })
    })

    return video._preloadPromise
  }

  function initVideoPreload(slider) {
    if (!shouldPreloadVideos()) {
      return
    }

    const links = slider.querySelectorAll('.project-marquee-slider__link--has-video')
    if (!links.length) {
      return
    }

    const preloadLinkVideo = function (link) {
      const video = link.querySelector('.project-marquee-slider__video')
      if (video) {
        preloadVideo(video)
      }
    }

    links.forEach(function (link) {
      link.addEventListener('pointerenter', function () {
        preloadLinkVideo(link)
      }, { passive: true })
    })

    if (!('IntersectionObserver' in window)) {
      links.forEach(preloadLinkVideo)
      return
    }

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return
          }
          preloadLinkVideo(entry.target)
        })
      },
      {
        root: null,
        rootMargin: '400px 0px',
        threshold: 0
      }
    )

    links.forEach(function (link) {
      observer.observe(link)
    })
  }

  function initCardHover(slider) {
    initVideoPreload(slider)

    const videoLinks = slider.querySelectorAll('.project-marquee-slider__link--has-video')
    const imageLinks = slider.querySelectorAll('.project-marquee-slider__link--has-hover-image')
    const useTouchHold = prefersTouchHold()

    videoLinks.forEach(function (link) {
      const video = link.querySelector('.project-marquee-slider__video')
      if (!video) {
        return
      }

      const hideVideoLoader = function () {
        link.classList.remove('is-video-loading')
      }

      const showVideoLoader = function () {
        link.classList.add('is-video-loading')
      }

      const isLinkActive = function () {
        return (
          link.matches(':hover') ||
          link.classList.contains('is-card-active') ||
          link.contains(document.activeElement)
        )
      }

      const activateCard = function () {
        link.classList.add('is-card-active')
        playVideo()
      }

      const deactivateCard = function () {
        link.classList.remove('is-card-active')
        pauseVideo()
      }

      const playVideo = function () {
        if (link.classList.contains('is-video-active')) {
          return
        }

        showVideoLoader()

        preloadVideo(video).then(function () {
          if (!isLinkActive()) {
            hideVideoLoader()
            return
          }

          link.classList.add('is-video-active')

          const onPlaying = function () {
            hideVideoLoader()
          }

          video.addEventListener('playing', onPlaying, { once: true })

          const playPromise = video.play()
          if (playPromise && typeof playPromise.then === 'function') {
            playPromise.catch(function () {
              hideVideoLoader()
            })
            return
          }

          if (!video.paused) {
            hideVideoLoader()
          }
        })
      }

      const pauseVideo = function () {
        link.classList.remove('is-video-active', 'is-video-loading')
        video.pause()
      }

      link.addEventListener('mouseenter', activateCard)
      link.addEventListener('mouseleave', deactivateCard)
      link.addEventListener('focusin', activateCard)
      link.addEventListener('focusout', function (e) {
        if (!link.contains(e.relatedTarget)) {
          deactivateCard()
        }
      })

      if (useTouchHold) {
        bindCardTouchHold(link, activateCard, deactivateCard)
      }
    })

    if (!useTouchHold) {
      return
    }

    imageLinks.forEach(function (link) {
      bindCardTouchHold(
        link,
        function () {
          link.classList.add('is-card-active')
        },
        function () {
          link.classList.remove('is-card-active')
        }
      )
    })
  }

  function isEditorPreview(slider) {
    return (
      slider.classList.contains('is-editor-preview') ||
      slider.dataset.editorPreview === 'true' ||
      !!slider.closest('.acf-block-preview')
    )
  }

  function isPauseOnHoverEnabled(slider) {
    return slider.dataset.pauseOnHover !== '0'
  }

  function isDragEnabled(slider) {
    return slider.dataset.dragEnabled !== '0'
  }

  function initMarqueePause(slider, track, inners) {
    let resumeTimer = null

    const pause = function () {
      slider.classList.add('is-paused')
      track.classList.add('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = 'paused'
      })
    }

    const resume = function () {
      slider.classList.remove('is-paused')
      track.classList.remove('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = ''
      })
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
  }

  function initMarqueeDrag(slider, track, inners, pauseOnHover) {
    let isPaused = false
    let isDragging = false
    let isPointerDown = false
    let suppressClick = false
    let resumeTimer = null
    let dragStartX = 0
    let dragStartY = 0
    let dragOffsetPx = 0
    let dragBaseTranslateX = 0
    let dragSnapshotTranslateX = null
    let activePointerId = null
    let hasPointerCapture = false

    const durationSeconds = parseDurationSeconds(
      window.getComputedStyle(track).getPropertyValue('--speed').trim() ||
        window.getComputedStyle(inners[0]).animationDuration
    )

    const resumeAnimationAtProgress = function (progress) {
      const delay = -(progress * durationSeconds) + 's'
      inners.forEach(function (inner) {
        inner.style.animation = ''
        inner.style.transform = ''
        inner.style.animationPlayState = ''
        inner.style.animationDelay = delay
      })
    }

    const releasePointerHold = function () {
      dragSnapshotTranslateX = null
      const shouldStayPaused =
        pauseOnHover && (slider.matches(':hover') || slider.contains(document.activeElement))
      if (!shouldStayPaused) {
        inners.forEach(function (inner) {
          inner.style.animationPlayState = ''
        })
      }
    }

    const pause = function () {
      if (isDragging) return
      isPaused = true
      slider.classList.add('is-paused')
      track.classList.add('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = 'paused'
      })
    }

    const resume = function () {
      if (isDragging) return
      isPaused = false
      slider.classList.remove('is-paused')
      track.classList.remove('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = ''
        inner.style.transform = ''
      })
    }

    const scheduleResume = function () {
      clearTimeout(resumeTimer)
      resumeTimer = setTimeout(function () {
        if (!slider.matches(':hover') && !slider.contains(document.activeElement)) {
          resume()
        }
      }, RESUME_DELAY_MS)
    }

    const applyDragPosition = function () {
      const translateX = dragBaseTranslateX + dragOffsetPx
      inners.forEach(function (inner) {
        inner.style.transform = 'translate3d(' + translateX + 'px, 0, 0)'
      })
    }

    const beginDrag = function (e) {
      if (isDragging) return

      deactivateAllCardHovers(slider)

      isDragging = true
      suppressClick = false
      slider.classList.add('is-dragging')
      track.classList.add('is-dragging')
      isPaused = true
      slider.classList.add('is-paused')
      track.classList.add('is-paused')

      const translateX =
        dragSnapshotTranslateX !== null
          ? dragSnapshotTranslateX
          : getFrozenTranslateX(inners[0], track)
      dragBaseTranslateX = freezeAnimationAtCurrentPosition(inners, track, translateX)[0]
      applyDragPosition()

      activePointerId = e.pointerId

      if (track.setPointerCapture && !hasPointerCapture) {
        track.setPointerCapture(e.pointerId)
        hasPointerCapture = true
      }

      e.preventDefault()
    }

    const releasePointerCapture = function () {
      if (
        hasPointerCapture &&
        activePointerId !== null &&
        track.releasePointerCapture &&
        track.hasPointerCapture(activePointerId)
      ) {
        track.releasePointerCapture(activePointerId)
      }
      hasPointerCapture = false
    }

    const endDrag = function () {
      if (!isDragging) return

      const finalTranslateX = dragBaseTranslateX + dragOffsetPx
      const loopDistance = getLoopDistance(inners[0], track)
      const newProgress = progressFromTranslateX(finalTranslateX, loopDistance)

      isDragging = false
      slider.classList.remove('is-dragging')
      track.classList.remove('is-dragging')

      releasePointerCapture()
      activePointerId = null
      dragSnapshotTranslateX = null
      resumeAnimationAtProgress(newProgress)

      if (Math.abs(dragOffsetPx) >= DRAG_THRESHOLD_PX) {
        suppressClick = true
      }

      dragOffsetPx = 0
      dragBaseTranslateX = 0

      isPaused = false
      slider.classList.remove('is-paused')
      track.classList.remove('is-paused')
      inners.forEach(function (inner) {
        inner.style.animationPlayState = ''
      })

      if (pauseOnHover && (slider.matches(':hover') || slider.contains(document.activeElement))) {
        pause()
      }
    }

    const onDocumentPointerMove = function (e) {
      if (!isPointerDown) return
      if (activePointerId !== null && e.pointerId !== activePointerId) return

      const deltaX = e.clientX - dragStartX
      const deltaY = e.clientY - dragStartY
      dragOffsetPx = deltaX

      if (!isDragging) {
        if (Math.abs(deltaX) < DRAG_THRESHOLD_PX && Math.abs(deltaY) < DRAG_THRESHOLD_PX) {
          return
        }

        if (Math.abs(deltaX) >= Math.abs(deltaY)) {
          beginDrag(e)
        } else {
          isPointerDown = false
          track.classList.remove('is-pointer-down')
          releasePointerHold()
          releasePointerCapture()
          document.removeEventListener('pointermove', onDocumentPointerMove)
          document.removeEventListener('pointerup', onDocumentPointerUp)
          document.removeEventListener('pointercancel', onDocumentPointerUp)
          return
        }
      }

      if (!isDragging) return

      e.preventDefault()
      applyDragPosition()
    }

    const onDocumentPointerUp = function (e) {
      if (!isPointerDown) return
      if (activePointerId !== null && e.pointerId !== activePointerId) return

      isPointerDown = false
      track.classList.remove('is-pointer-down')
      releasePointerCapture()
      document.removeEventListener('pointermove', onDocumentPointerMove)
      document.removeEventListener('pointerup', onDocumentPointerUp)
      document.removeEventListener('pointercancel', onDocumentPointerUp)

      if (isDragging) {
        endDrag()
        return
      }

      releasePointerHold()
      dragOffsetPx = 0
      activePointerId = null
    }

    if (pauseOnHover) {
      slider.addEventListener('mouseenter', pause)
      slider.addEventListener('mouseleave', scheduleResume)
      slider.addEventListener('focusin', pause)
      slider.addEventListener('focusout', function (e) {
        if (!slider.contains(e.relatedTarget)) {
          scheduleResume()
        }
      })
    }

    track.addEventListener('click', function (e) {
      if (!suppressClick) return
      e.preventDefault()
      e.stopImmediatePropagation()
      suppressClick = false
    }, true)

    track.addEventListener('pointerdown', function (e) {
      if (e.button !== 0) return

      isPointerDown = true
      isDragging = false
      suppressClick = false
      dragStartX = e.clientX
      dragStartY = e.clientY
      dragOffsetPx = 0
      dragBaseTranslateX = 0
      activePointerId = e.pointerId
      track.classList.add('is-pointer-down')

      dragSnapshotTranslateX = getFrozenTranslateX(inners[0], track)
      if (!isPaused) {
        inners.forEach(function (inner) {
          inner.style.animationPlayState = 'paused'
        })
      }

      if (isTouchPointer(e) && track.setPointerCapture) {
        track.setPointerCapture(e.pointerId)
        hasPointerCapture = true
      }

      document.addEventListener('pointermove', onDocumentPointerMove, { passive: false })
      document.addEventListener('pointerup', onDocumentPointerUp)
      document.addEventListener('pointercancel', onDocumentPointerUp)
    }, true)
  }

  function initMarquee(slider) {
    const track = slider.querySelector('.project-marquee-slider__track')
    if (!track) return

    const inners = getInners(track)
    if (!inners.length) return

    const editorPreview = isEditorPreview(slider)
    const pauseOnHover = isPauseOnHoverEnabled(slider)
    const dragEnabled = isDragEnabled(slider)

    if (!editorPreview && dragEnabled) {
      preventNativeDrag(track)
    }

    if (prefersReducedMotion()) {
      return
    }

    if (editorPreview) {
      if (pauseOnHover) {
        initMarqueePause(slider, track, inners)
      }
      return
    }

    if (dragEnabled) {
      initMarqueeDrag(slider, track, inners, pauseOnHover)
      return
    }

    if (pauseOnHover) {
      initMarqueePause(slider, track, inners)
    }
  }

  function initSlider(slider) {
    if (slider.dataset.projectMarqueeInit === '1') return
    slider.dataset.projectMarqueeInit = '1'
    initMarquee(slider)
    initCardHover(slider)
  }

  function initSliders(root) {
    const scope = root && root.querySelectorAll ? root : document
    scope.querySelectorAll('.project-marquee-slider').forEach(initSlider)
  }

  let acfPreviewBound = false

  function bindAcfBlockPreview() {
    if (acfPreviewBound || typeof acf === 'undefined' || !acf.addAction) return
    acfPreviewBound = true

    const blockName = 'acf/block-project-marquee-slider'

    const onPreviewRender = function ($el, block) {
      if (block && block.name && block.name !== blockName) return

      const root = $el && $el[0] ? $el[0] : document
      root.querySelectorAll('.project-marquee-slider').forEach(function (slider) {
        delete slider.dataset.projectMarqueeInit
      })
      initSliders(root)
    }

    acf.addAction('render_block_preview/type=block-project-marquee-slider', onPreviewRender)
    acf.addAction('render_block_preview', onPreviewRender)
  }

  function boot() {
    initSliders(document)
    bindAcfBlockPreview()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot)
  } else {
    boot()
  }

  if (typeof acf !== 'undefined' && acf.addAction) {
    acf.addAction('ready', bindAcfBlockPreview)
  }
})()
