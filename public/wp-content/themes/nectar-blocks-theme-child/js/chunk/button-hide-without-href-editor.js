(function () {
  'use strict'

  const BLOCK_NAME = 'nectar-blocks/button'
  const ATTR_NAME = 'hideWithoutHref'

  function addButtonHideWithoutHrefAttribute(settings, blockName) {
    if (blockName !== BLOCK_NAME) {
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
      'novionline/button-hide-without-href-attributes',
      addButtonHideWithoutHrefAttribute
    )
  }

  function withButtonHideWithoutHrefControls(BlockEdit) {
    return function ButtonHideWithoutHrefEdit(props) {
      if (props.name !== BLOCK_NAME) {
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
              title: wp.i18n?.__('Visibility', 'novionline') || 'Visibility',
              initialOpen: false
            },
            wp.element.createElement(ToggleControl, {
              label:
                wp.i18n?.__('Hide when link URL is empty', 'novionline') ||
                'Hide when link URL is empty',
              help:
                wp.i18n?.__(
                  'On the frontend, this button is not rendered when its link URL resolves to empty (e.g. an unfilled dynamic field).',
                  'novionline'
                ) ||
                'On the frontend, this button is not rendered when its link URL resolves to empty (e.g. an unfilled dynamic field).',
              checked: enabled,
              onChange: function (value) {
                setAttributes({ [ATTR_NAME]: !!value })
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
      'novionline/button-hide-without-href-controls',
      wp.compose.createHigherOrderComponent(withButtonHideWithoutHrefControls, 'withButtonHideWithoutHrefControls')
    )
  }
})()
