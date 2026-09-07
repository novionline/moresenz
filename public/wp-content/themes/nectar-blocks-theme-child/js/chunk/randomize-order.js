(function () {
  'use strict'

  const BLOCK_CONFIGS = [
    {
      blockType: 'carousel',
      containerSelector: '.swiper-wrapper',
      itemSelector: '.swiper-slide'
    },
    {
      blockType: 'flexbox',
      itemSelector: '.nectar-block',
      resolveContainer(wrapper) {
        if (wrapper.classList.contains('nectar-blocks-flex-box__inner')) {
          return wrapper
        }

        const directInner = wrapper.querySelector(':scope > .nectar-blocks-flex-box__wrapper > .nectar-blocks-flex-box__inner')
        if (directInner) {
          return directInner
        }

        return wrapper
      }
    }
  ]

  const configByBlockType = BLOCK_CONFIGS.reduce(function (map, config) {
    map[config.blockType] = config
    return map
  }, {})

  function shuffleArray(items) {
    const shuffled = items.slice()

    for (let i = shuffled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1))
      const temp = shuffled[i]
      shuffled[i] = shuffled[j]
      shuffled[j] = temp
    }

    return shuffled
  }

  function shuffleChildren(container, itemSelector) {
    const items = Array.from(container.querySelectorAll(':scope > ' + itemSelector))
    if (items.length < 2) {
      return
    }

    const shuffled = shuffleArray(items)
    shuffled.forEach(function (item) {
      container.appendChild(item)
    })
  }

  function getContainer(wrapper, config) {
    if (typeof config.resolveContainer === 'function') {
      return config.resolveContainer(wrapper)
    }

    if (!config.containerSelector) {
      return null
    }

    return wrapper.querySelector(config.containerSelector)
  }

  function randomizeBlock(wrapper) {
    if (!wrapper || wrapper.dataset.noviRandomized === 'true') {
      return
    }

    const blockType = wrapper.dataset.noviRandomizeBlock || ''
    const config = configByBlockType[blockType]
    if (!config) {
      return
    }

    const container = getContainer(wrapper, config)
    if (!container || container === wrapper.ownerDocument) {
      return
    }

    shuffleChildren(container, config.itemSelector)
    wrapper.dataset.noviRandomized = 'true'
  }

  function randomizeInRoot(root) {
    if (!(root instanceof Element || root instanceof Document)) {
      return
    }

    const scope = root instanceof Document ? root : root
    const wrappers = scope.querySelectorAll('[data-novi-randomize-order="true"]')
    wrappers.forEach(randomizeBlock)
  }

  randomizeInRoot(document)

  document.addEventListener('nectar:init-blocks', function (event) {
    const container = event.detail?.container
    if (container instanceof HTMLElement) {
      randomizeInRoot(container)
    }
  }, true)
})()
