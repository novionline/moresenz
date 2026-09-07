(function () {
  'use strict'

  const BLOCK_NAME = 'nectar-blocks/image'
  const ATTR_NAME = 'priorityLoading'

  function addImagePriorityAttribute(settings, blockName) {
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
      'novionline/image-priority-attributes',
      addImagePriorityAttribute
    )
  }

  function withImagePriorityControls(BlockEdit) {
    return function ImagePriorityEdit(props) {
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
              title: wp.i18n?.__('Performance', 'novionline') || 'Performance',
              initialOpen: false
            },
            wp.element.createElement(ToggleControl, {
              label:
                wp.i18n?.__('Prioritize loading (LCP)', 'novionline') ||
                'Prioritize loading (LCP)',
              help:
                wp.i18n?.__(
                  'Use for hero / above-the-fold images. Sets loading=eager, fetchpriority=high, and removes decoding=async to improve LCP.',
                  'novionline'
                ) ||
                'Use for hero / above-the-fold images. Sets loading=eager, fetchpriority=high, and removes decoding=async to improve LCP.',
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
      'novionline/image-priority-controls',
      wp.compose.createHigherOrderComponent(withImagePriorityControls, 'withImagePriorityControls')
    )
  }
})()
