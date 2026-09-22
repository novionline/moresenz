;(function () {
  'use strict'

  function initArchiveGrids(root) {
    const scope = root && root.querySelectorAll ? root : document
    scope.querySelectorAll('.project-archive-grid[data-project-card-hover="1"]').forEach(function (grid) {
      if (grid.dataset.projectCardHoverInit !== '1') {
        grid.dataset.projectCardHoverInit = '1'

        //marquee script exposes hover init via reusing same link classes;
        //dispatch a custom event the marquee boot also listens for, or call shared init if present
        if (typeof window.noviInitProjectCardHover === 'function') {
          window.noviInitProjectCardHover(grid)
        }
      }
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
      })
      initArchiveGrids(root)
    })
  }
})()
