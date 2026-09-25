/**
 * NectarBlocks colorpicker init.
 *
 * @package NectarBlocks
 * @author NectarBlocks
 */
 /* global jQuery */
 
jQuery(document).ready(function ($) {
  "use strict";
  var colorScheme = ['#27CCC0', '#f6653c', '#2ac4ea', '#ae81f9', '#FF4629', '#78cd6e'];

  if( window.nectar_theme_colors ) {
    var values = Array.isArray(window.nectar_theme_colors)
      ? window.nectar_theme_colors.map(function(el){ return el && el.value; })
      : Object.values(window.nectar_theme_colors).map(function(el){
          return el && typeof el === 'object' ? el.value : el;
        });
    values.forEach(function(value, index){
      if(value) {
        colorScheme[index] = value;
      }
    });
  }

  $('[id*="nectar-metabox-"] input.popup-colorpicker:not(.sc-gen), .nectar-term-colorpicker').wpColorPicker({
    palettes: colorScheme
  });
});
