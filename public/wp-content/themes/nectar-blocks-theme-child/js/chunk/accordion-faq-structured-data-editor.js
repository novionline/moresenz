(function () {
  'use strict'

  const BLOCK_NAME = 'nectar-blocks/accordion'
  const ATTR_NAME = 'faqStructuredDataEnabled'

  function addAccordionFaqAttribute(settings, blockName) {
    if (blockName !== BLOCK_NAME) {
      return settings
    }
    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        [ATTR_NAME]: {
          type: 'boolean',
          default: true
        }
      }
    }
  }

  if (typeof wp !== 'undefined' && wp.hooks) {
    wp.hooks.addFilter(
      'blocks.registerBlockType',
      'novionline/accordion-faq-structured-data-attributes',
      addAccordionFaqAttribute
    )
  }

  function withAccordionFaqControls(BlockEdit) {
    return function AccordionFaqEdit(props) {
      if (props.name !== BLOCK_NAME) {
        return wp.element.createElement(BlockEdit, props)
      }

      const InspectorControls = wp.blockEditor?.InspectorControls
      const PanelBody = wp.components?.PanelBody
      const ToggleControl = wp.components?.ToggleControl

      if (!InspectorControls || !PanelBody || !ToggleControl) {
        return wp.element.createElement(BlockEdit, props)
      }

      const enabled = props.attributes[ATTR_NAME] !== false
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
              title: wp.i18n?.__('Structured data', 'novionline') || 'Structured data',
              initialOpen: true
            },
            wp.element.createElement(ToggleControl, {
              label:
                wp.i18n?.__('Enable FAQ structured data for this Accordion', 'novionline') ||
                'Enable FAQ structured data for this Accordion',
              help:
                wp.i18n?.__('When enabled, all accordion items in this block are added to a single FAQPage schema for the page.', 'novionline') ||
                'When enabled, all accordion items in this block are added to a single FAQPage schema for the page.',
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
      'novionline/accordion-faq-structured-data-controls',
      wp.compose.createHigherOrderComponent(withAccordionFaqControls, 'withAccordionFaqControls')
    )
  }
})()
