;(function () {
  'use strict'

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
  }

  function revealItems(items) {
    items.forEach(function (item) {
      item.classList.add('is-in-view')
    })
  }

  function initScrollReveal(grid) {
    if (grid.dataset.projectArchiveRevealInit === '1') {
      return
    }
    grid.dataset.projectArchiveRevealInit = '1'

    const items = Array.prototype.slice.call(
      grid.querySelectorAll('.project-archive-grid__item')
    )
    if (!items.length) {
      return
    }

    //editor preview: css shows items; skip observer
    if (grid.classList.contains('is-editor-preview')) {
      revealItems(items)
      return
    }

    //reduced motion or no io: show immediately
    if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
      revealItems(items)
      return
    }

    //css adds transition-delay on is-in-view; io waits until more of the card is visible
    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return
          }
          entry.target.classList.add('is-in-view')
          observer.unobserve(entry.target)
        })
      },
      {
        root: null,
        rootMargin: '0px 0px -12% 0px',
        threshold: 0.2
      }
    )

    items.forEach(function (item) {
      observer.observe(item)
    })
  }

  function initCardHover(grid) {
    if (grid.dataset.projectCardHoverInit === '1') {
      return
    }

    //marquee script exposes shared hover (preload + play); retry briefly if script order races
    const tryInit = function (attempt) {
      if (typeof window.noviInitProjectCardHover === 'function') {
        grid.dataset.projectCardHoverInit = '1'
        window.noviInitProjectCardHover(grid)
        return
      }

      if (attempt >= 20) {
        return
      }

      window.setTimeout(function () {
        tryInit(attempt + 1)
      }, 50)
    }

    tryInit(0)
  }

  function initArchiveGrids(root) {
    const scope = root && root.querySelectorAll ? root : document
    scope.querySelectorAll('.project-archive-grid[data-project-card-hover="1"]').forEach(function (grid) {
      initCardHover(grid)
      initScrollReveal(grid)
    })
  }

  function boot() {
    initArchiveGrids(document)
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot)
  } else {
    boot()
  }

  document.addEventListener('nectar:init-blocks', function (event) {
    const container = event.detail && event.detail.container
    if (container instanceof HTMLElement) {
      initArchiveGrids(container)
    }
  })

  if (typeof acf !== 'undefined' && acf.addAction) {
    acf.addAction('render_block_preview/type=block-project-archive-grid', function ($el) {
      const root = $el && $el[0] ? $el[0] : document
      root.querySelectorAll('.project-archive-grid').forEach(function (grid) {
        delete grid.dataset.projectCardHoverInit
        delete grid.dataset.projectArchiveRevealInit
      })
      initArchiveGrids(root)
    })
  }
})()
