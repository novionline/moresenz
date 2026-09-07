(function () {
  'use strict'

  const ATTR_NAME = 'noviRandomizeOrder'

  const BLOCK_CONFIGS = [
    {
      blockName: 'nectar-blocks/carousel',
      panelTitle: 'Randomize order',
      toggleLabel: 'Randomize slide order',
      toggleHelp: 'Shuffle slides on each page visit. Editor order stays unchanged.'
    },
    {
      blockName: 'nectar-blocks/flex-box',
      panelTitle: 'Randomize order',
      toggleLabel: 'Randomize item order',
      toggleHelp: 'Shuffle direct child items on each page visit. Editor order stays unchanged.'
    }
  ]

  const blockNames = BLOCK_CONFIGS.map(function (config) {
    return config.blockName
  })

  function getConfigForBlock(blockName) {
    return BLOCK_CONFIGS.find(function (config) {
      return config.blockName === blockName
    }) || null
  }

  function addRandomizeOrderAttribute(settings, blockName) {
    if (blockNames.indexOf(blockName) === -1) {
      return settings
    }

    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        [ATTR_NAME]: {
          type: 'boolean',
          default: false
        }
      }
    }
  }

  if (typeof wp !== 'undefined' && wp.hooks) {
    wp.hooks.addFilter(
      'blocks.registerBlockType',
      'novionline/randomize-order-attributes',
      addRandomizeOrderAttribute
    )
  }

  function withRandomizeOrderControls(BlockEdit) {
    return function RandomizeOrderEdit(props) {
      const config = getConfigForBlock(props.name)
      if (!config) {
        return wp.element.createElement(BlockEdit, props)
      }

      const InspectorControls = wp.blockEditor?.InspectorControls
      const PanelBody = wp.components?.PanelBody
      const ToggleControl = wp.components?.ToggleControl

      if (!InspectorControls || !PanelBody || !ToggleControl) {
        return wp.element.createElement(BlockEdit, props)
      }

      const enabled = props.attributes[ATTR_NAME] === true
      const setAttributes = props.setAttributes || (function () {})

      return wp.element.createElement(
        wp.element.Fragment,
        {},
        wp.element.createElement(BlockEdit, props),
        wp.element.createElement(
          InspectorControls,
          {},
          wp.element.createElement(
            PanelBody,
            {
              title: wp.i18n?.__(config.panelTitle, 'novionline') || config.panelTitle,
              initialOpen: false
            },
            wp.element.createElement(ToggleControl, {
              label: wp.i18n?.__(config.toggleLabel, 'novionline') || config.toggleLabel,
              help: wp.i18n?.__(config.toggleHelp, 'novionline') || config.toggleHelp,
              checked: enabled,
              onChange: function (value) {
                const update = {}
                update[ATTR_NAME] = !!value
                setAttributes(update)
              }
            })
          )
        )
      )
    }
  }

  if (typeof wp !== 'undefined' && wp.hooks && wp.compose && wp.compose.createHigherOrderComponent) {
    wp.hooks.addFilter(
      'editor.BlockEdit',
      'novionline/randomize-order-controls',
      wp.compose.createHigherOrderComponent(withRandomizeOrderControls, 'withRandomizeOrderControls')
    )
  }
})()
