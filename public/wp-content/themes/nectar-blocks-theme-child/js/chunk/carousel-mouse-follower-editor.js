(function () {
  'use strict'

  function addCarouselMouseFollowerAttribute(settings, blockName) {
    if (blockName !== 'nectar-blocks/carousel') {
      return settings
    }
    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        mouseFollowerEnabled: {
          type: 'boolean',
          default: false
        }
      }
    }
  }

  if (typeof wp !== 'undefined' && wp.hooks) {
    wp.hooks.addFilter(
      'blocks.registerBlockType',
      'novionline/carousel-mouse-follower-attributes',
      addCarouselMouseFollowerAttribute
    )
  }

  function withCarouselMouseFollowerControls(BlockEdit) {
    return function CarouselMouseFollowerEdit(props) {
      if (props.name !== 'nectar-blocks/carousel') {
        return wp.element.createElement(BlockEdit, props)
      }

      const InspectorControls = wp.blockEditor?.InspectorControls
      const PanelBody = wp.components?.PanelBody
      const ToggleControl = wp.components?.ToggleControl

      if (!InspectorControls || !PanelBody || !ToggleControl) {
        return wp.element.createElement(BlockEdit, props)
      }

      const enabled = props.attributes.mouseFollowerEnabled === true
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
              title: wp.i18n?.__('Mouse follower', 'novionline') || 'Mouse follower',
              initialOpen: true
            },
            wp.element.createElement(ToggleControl, {
              label: wp.i18n?.__('Show drag indicator (circle + arrows)', 'novionline') || 'Show drag indicator (circle + arrows)',
              help: wp.i18n?.__('Display a cursor-following indicator on non-touch devices to suggest horizontal dragging.', 'novionline') || 'Display a cursor-following indicator on non-touch devices to suggest horizontal dragging.',
              checked: enabled,
              onChange: function (value) {
                setAttributes({ mouseFollowerEnabled: !!value })
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
      'novionline/carousel-mouse-follower-controls',
      wp.compose.createHigherOrderComponent(withCarouselMouseFollowerControls, 'withCarouselMouseFollowerControls')
    )
  }
})()
