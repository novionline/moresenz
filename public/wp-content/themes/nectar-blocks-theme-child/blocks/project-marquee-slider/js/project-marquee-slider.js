(function () {
  const RESUME_DELAY_MS = 300
  const DRAG_THRESHOLD_PX = 8

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

  function getTranslateXFromLayout(inner, track) {
    const trackRect = track.getBoundingClientRect()
    const innerRect = inner.getBoundingClientRect()
    return innerRect.left - trackRect.left
  }

  function getCurrentTranslateX(inner, track) {
    if (inner.style.animation === 'none' && inner.style.transform) {
      const matrixX = getTranslateXFromMatrix(inner)
      if (matrixX !== 0) return matrixX
    }

    return getTranslateXFromLayout(inner, track)
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
    if (!loopDistance) return 0
    let progress = (-translateX / loopDistance) % 1
    if (progress < 0) progress += 1
    return progress
  }

  function setAnimationProgress(inners, progress, durationSeconds) {
    const delay = -(progress * durationSeconds)
    inners.forEach(function (inner) {
      inner.style.animationDelay = delay + 's'
    })
  }

  function freezeAnimationAtCurrentPosition(inners, track) {
    const positions = []
    inners.forEach(function (inner, index) {
      positions[index] = getCurrentTranslateX(inner, track)
      inner.style.animation = 'none'
      inner.style.transform = 'translate3d(' + positions[index] + 'px, 0, 0)'
    })
    return positions
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

  function loadVideoOnDemand(video) {
    if (video.dataset.loaded === 'true') {
      return Promise.resolve()
    }

    const videoUrl = getVideoSourceUrl(video)
    if (!videoUrl) {
      return Promise.resolve()
    }

    const source = video.querySelector('source')
    if (source) {
      source.src = videoUrl
    }
    video.src = videoUrl
    video.load()
    video.dataset.loaded = 'true'

    return new Promise(function (resolve) {
      if (video.readyState >= 2) {
        resolve()
        return
      }

      video.addEventListener('loadeddata', resolve, { once: true })
      video.addEventListener('error', resolve, { once: true })
    })
  }

  function initVideos(slider) {
    const links = slider.querySelectorAll('.project-marquee-slider__link--has-video')

    links.forEach(function (link) {
      const video = link.querySelector('.project-marquee-slider__video')
      if (!video) return

      const playVideo = function () {
        link.classList.add('is-video-active')

        loadVideoOnDemand(video).then(function () {
          const playPromise = video.play()
          if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function () {})
          }
        })
      }

      const pauseVideo = function () {
        link.classList.remove('is-video-active')
        video.pause()
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
    let activePointerId = null
    let hasPointerCapture = false

    const durationSeconds = parseDurationSeconds(
      window.getComputedStyle(track).getPropertyValue('--speed').trim() ||
        window.getComputedStyle(inners[0]).animationDuration
    )

    const clearInlineMotionStyles = function () {
      inners.forEach(function (inner) {
        inner.style.animation = ''
        inner.style.transform = ''
        inner.style.animationPlayState = ''
      })
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

      isDragging = true
      suppressClick = false
      slider.classList.add('is-dragging')
      track.classList.add('is-dragging')
      isPaused = true
      slider.classList.add('is-paused')
      track.classList.add('is-paused')

      dragBaseTranslateX = freezeAnimationAtCurrentPosition(inners, track)[0]
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

      clearInlineMotionStyles()
      setAnimationProgress(inners, newProgress, durationSeconds)

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
    initVideos(slider)
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
