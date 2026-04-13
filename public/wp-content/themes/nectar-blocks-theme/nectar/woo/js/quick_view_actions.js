jQuery(document).ready(function($) {
  const NectarQuickViewState = {
    ajaxLoaded: false,
    sizeSet: false
  };
  let resetTimeout = '';

  $('body').on('click', 'ul.products li.product a.nectar_quick_view', function(e) {
    e.preventDefault();

    const $quickViewBox = $('.nectar-quick-view-box');
    const $product_id = $(this).data('product-id');

    clearTimeout(resetTimeout);

    // exit if no ID passed
    if (typeof $product_id === 'undefined') {
      return;
    }

    quickView($(this).parents('li.product'), 'open');

    // empty old product info
    $quickViewBox.find('.inner-content').empty();


    // get product info
    $.ajax({
      type: 'POST',
      url: nectarLove.ajaxurl,
      data: {
        'action': 'nectar_woo_get_product',
        'product_id': $product_id
      },
      success: function(response) {
        NectarQuickViewState.ajaxLoaded = true;

        $quickViewBox.find('.inner-content').html(response);

        // store variation starting attr
        $vari_startingImage = ($quickViewBox.find('.nectar-product-slider div.carousel-cell:first img').length > 0) ? $quickViewBox.find('.nectar-product-slider div.carousel-cell:first img').attr('src') : '';


        // select2
        if ($('body[data-form-select-js="1"]').length > 0) {
          select2Init();

          // z index fix
          $select2_css = '.select2-container { z-index: 99999; }';
          const head = document.head || document.getElementsByTagName('head')[0];
  			const style = document.createElement('style');

  			style.type = 'text/css';
  			if (style.styleSheet) {
  			  style.styleSheet.cssText = $select2_css;
  			} else {
  			  style.appendChild(document.createTextNode($select2_css));
  			}
  			$(style).attr('id', 'quickview-select-2-zindex');
  			head.appendChild(style);
        }

        // variations
        if ( typeof wc_add_to_cart_variation_params !== 'undefined' ) {
  			$( '.variations_form' ).each( function() {
  				$( this ).wc_variation_form();
  			});
  		}

      // quantity
      quantityButtons();

        $('.nectar-quick-view-box').addClass('fully-open');

        $('.nectar-quick-view-box-backdrop').addClass('visible');


      // init flickity
        $pageDots = true;
        if ($('.nectar-quick-view-box .nectar-product-slider .carousel-cell').length == 1) {
          $pageDots = false;
        }
        let themeOptionProductGap = getComputedStyle(document.body).getPropertyValue('--nectar-product-layout-gap');
        themeOptionProductGap = ( themeOptionProductGap ) ? parseInt(themeOptionProductGap) : 10;
  
        $carousel = new Swiper(".nectar-quick-view-box .nectar-product-slider", {
          slidesPerView: 'auto',
          spaceBetween: themeOptionProductGap,
          loopAddBlankSlides: true,
          pagination: {
            el: '.swiper-pagination',
            clickable: true
          },
          touch: true
        });

        // $carousel = $('.nectar-quick-view-box .nectar-product-slider').flickity({
        //   contain: true,
        //   lazyLoad: false,
        //   imagesLoaded: true,
        //   percentPosition: true,
        //   prevNextButtons: false,
        //   pageDots: $pageDots,
        //   resize: true,
        //   setGallerySize: true,
        //   wrapAround: true,
        //   accessibility: false
        // });

        // show quick view content
        $('.nectar-quick-view-box .preview_image').hide();
        $('.nectar-quick-view-box').addClass('add-content');

        // Trigger for custom events.
        $(window).trigger('nectar_quickview_init');
  
      } // success


    }); // ajax
  }); // quick view click


  $('body').on('click', '.nectar-quick-view-box-backdrop, .nectar-quick-view-box .close', function(e) {
    e.preventDefault();
    if ( $('.nectar-quick-view-box.fully-open').length > 0 ) {
      quickView($('.product.open-nectar-quick-view'), 'close');
    }
  });

  let $carousel;

  function quickView(el, state) {
    NectarQuickViewState.ajaxLoaded = false;
    NectarQuickViewState.sizeSet = false;

    if (state === 'open') {
      $('.nectar-quick-view-box').addClass('visible');
      $('.nectar-quick-view-box-backdrop').addClass('visible');

      NectarQuickViewState.sizeSet = true;
    } else {
      // close
      $('.nectar-quick-view-box').removeClass('fully-open');
      resetTimeout = setTimeout(function() {
        $('.nectar-quick-view-box').removeClass('add-content');
      }, 850);
      el.removeClass('no-trans');
      $('.nectar-quick-view-box-backdrop').removeClass('visible');

      if ($('head #quickview-select-2-zindex').length > 0) {
        $('head #quickview-select-2-zindex').remove();
      }
      $startingImage = ($('.nectar-product-slider .carousel-cell:first-child > img').length > 0) ? $('.nectar-product-slider .carousel-cell:first-child > img').attr('src') : '';
      $('.nectar-quick-view-box').removeClass('visible');
    }
  } // quickview function



  let dataThumb = '';
  let prevDataThumb = '';
  $('body').on('change', '.nectar-quick-view-box select[name*="attribute_"]', function() {

    // reset swiper to first slide.
    if ($carousel) {
      dataThumb = $carousel.el.querySelector('.carousel-cell').getAttribute('data-thumb');
      if (dataThumb !== prevDataThumb) {
      // if attr data thumb exists, a variation image is being displayed.
       prevDataThumb = dataThumb;
        $carousel.slideTo(0);
      }
    }

    // keep classes from default hidden btn and full width btn the same
    if ($('.nectar-quick-view-box .product .product > .single_add_to_cart_button_wrap .single_add_to_cart_button').length > 0) {
      setTimeout(function() {
        const addToCartClasses = $('.nectar-quick-view-box .summary-content .single_add_to_cart_button').attr('class');
        $('.nectar-quick-view-box .product .product > .single_add_to_cart_button_wrap .single_add_to_cart_button').attr('class', addToCartClasses);
      }, 290);
    }
  }); // blur variation 2


  function select2Init() {
    $('.nectar-quick-view-box select' ).each( function() {
      $( this ).select2({
        minimumResultsForSearch: 7,
        dropdownParent: $(this).parent(),
 				dropdownAutoWidth: true,
        width: '100%'
      });
    });
  }


  // Quantity buttons
  function quantityButtons() {
    if ($('.nectar-quick-view-box .plus').length == 0) {
      $('.nectar-quick-view-box div.quantity:not(.buttons_added), .nectar-quick-view-box td.quantity:not(.buttons_added)' ).addClass( 'buttons_added' ).append( '<input type="button" value="+" class="plus" />' ).prepend( '<input type="button" value="-" class="minus" />' );
    }

    // also move add to cart button
    setTimeout(function() {
      const addToCartBtnText = $('.nectar-quick-view-box .summary-content .single_add_to_cart_button').text();
      const addToCartBtnClasses = ($('.nectar-quick-view-box .summary-content .single_add_to_cart_button[class]').length > 0) ? $('.nectar-quick-view-box .summary-content .single_add_to_cart_button').attr('class') : '';
      const productViewFullBtn = $('.nectar-quick-view-box .nectar-full-product-link').clone();

      $('.nectar-quick-view-box .product .product').append('<div class="single_add_to_cart_button_wrap" />');

      $('.nectar-quick-view-box .product .product .single_add_to_cart_button_wrap').append('<a class="single_add_to_cart_button button"><span>'+ addToCartBtnText +'</span></a>').append(productViewFullBtn);

      // bind click to original button
      $('.nectar-quick-view-box .product .product .single_add_to_cart_button_wrap > .single_add_to_cart_button').attr('class', addToCartBtnClasses).on('click', function(e) {
        e.preventDefault(e);
        // ensure variations are set.
        if ( $('.nectar-quick-view-box .summary-content .single_add_to_cart_button.wc-variation-selection-needed').length == 0 ) {
          $('.nectar-quick-view-box .summary-content .single_add_to_cart_button').last().trigger('click');
        }
      });
    }, 100);
  }
});
