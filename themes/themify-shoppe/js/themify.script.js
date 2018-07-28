/* Themify Theme Scripts */

// Declar object literals and variables
var FixedHeader = {}, LayoutAndFilter = {};

// throttledresize
!function ($) {
    var e = $.event, t, n = {_: 0}, r = 0, s, i;
    t = e.special.throttledresize = {setup: function () {
            $(this).on("resize", t.handler)
        }, teardown: function () {
            $(this).off("resize", t.handler)
        }, handler: function (h, o) {
            var a = this, l = arguments;
            s = !0, i || (setInterval(function () {
                r++, (r > t.threshold && s || o) && (h.type = "throttledresize", e.dispatch.apply(a, l), s = !1, r = 0), r > 9 && ($(n).stop(), i = !1, r = 0)
            }, 30), i = !0)
        }, threshold: 0}
}(jQuery);

(function ($) {

// Fixed Header /////////////////////////
    FixedHeader = {
        headerHeight: 0,
        hasHeaderSlider: false,
        headerSlider: false,
        init: function () {
            if ($('body').hasClass('fixed-header')) {
                this.headerHeight = $('#headerwrap').outerHeight(true);
                this.activate();
                $(window).on('scroll touchstart.touchScroll touchmove.touchScroll', this.activate);
            }
            $(window).on('throttledresize', function () {
                $('#pagewrap').css('paddingTop', Math.floor($('#headerwrap').outerHeight(true)));
            });
            if ($('#gallery-controller').length > 0) {
                this.hasHeaderSlider = true;
            }
			
			// Sticky header logo customizer
			if(themifyScript.sticky_header) {
				var img = '<img id="sticky_header_logo" src="' + themifyScript.sticky_header.src + '"';
					if(themifyScript.sticky_header.imgwidth){
						img+=' width="'+themifyScript.sticky_header.imgwidth+'"';
					}
					if(themifyScript.sticky_header.imgheight){
						img+=' height="'+themifyScript.sticky_header.imgheight+'"';
					}
					img+='/>';
				$('#site-logo a').prepend(img);
			}

        },
        activate: function () {
            var $window = $(window),
                    scrollTop = $window.scrollTop(),
                    $headerWrap = $('#headerwrap');
            if (scrollTop >= FixedHeader.headerHeight) {
                if (!$headerWrap.hasClass('fixed-header')) {
                    FixedHeader.scrollEnabled();
                }
            } else {
                if ($headerWrap.hasClass('fixed-header')) {
                    FixedHeader.scrollDisabled();
                }
            }
        },
        scrollDisabled: function () {
            $('#headerwrap').removeClass('fixed-header');
            $('#header').removeClass('header-on-scroll');
            $('body').removeClass('fixed-header-on');
			/**
			 * force redraw the header
			 * required in order to calculate header height properly after removing fixed-header classes
			 */
			$('#headerwrap').hide();
			$('#headerwrap')[0].offsetHeight;
			$('#headerwrap').show();

			FixedHeader.headerHeight = $('#headerwrap').outerHeight(true);
			$('#pagewrap').css('paddingTop', Math.floor( FixedHeader.headerHeight ));
        },
        scrollEnabled: function () {
            $('#headerwrap').addClass('fixed-header');
            $('#header').addClass('header-on-scroll');
            $('body').addClass('fixed-header-on');
        }
    };

// Entry Filter /////////////////////////
    LayoutAndFilter = {
        init: function () {
            $('.loops-wrapper.masonry').prepend('<div class="grid-sizer"></div><div class="gutter-sizer"></div>');
			this.layout();
        },
        layout: function () {
                        var is_rtl = !$('body').hasClass('rtl');
			$('.loops-wrapper.masonry').each(function(){
				var $item = $(this),
					itemDisplay = $item.css( 'display' );
				
				if( itemDisplay !== 'block' ) {
					$item.css( 'display', 'block' );
				}
				
				$item.imagesLoaded().always(function(){
					$item.addClass('masonry-done').isotope({
						fitRows: {
							columnWidth: '.grid-sizer',
							gutter: '.gutter-sizer'
						},
						layoutMode: 'fitRows',
						itemSelector: '.loops-wrapper > .post,.loops-wrapper > .product',
						isOriginLeft: is_rtl
					});
				});
			});
        }
    };


// Infinite Scroll ///////////////////////////////
    function doInfinite($container, selector) {

		// Get max pages for regular category pages and home
		var scrollMaxPages = parseInt(themifyScript.maxPages);

		// Get max pages for Query Category pages
		if (typeof qp_max_pages !== 'undefined') {
			scrollMaxPages = qp_max_pages;
		}

		// infinite scroll
		$container.infinitescroll({
			navSelector: '#load-more a:last', // selector for the paged navigation
			nextSelector: '#load-more a:last', // selector for the NEXT link (to page 2)
			itemSelector: selector, // selector for all items you'll retrieve
			loadingText: '',
			donetext: '',
			loading: {img: false, msg: $('<div class="themify_spinner" id="infscr-loading"></div>')},
			maxPage: scrollMaxPages,
			behavior: 'auto' !== themifyScript.autoInfinite ? 'twitter' : '',
			pathParse: function (path) {
				return path.match(/^(.*?)\b2\b(?!.*\b2\b)(.*?$)/).slice(1);
			},
			bufferPx: 50,
			pixelsFromNavToBottom:  $('#sidebar').length>0 && $(window).width()<680?$('#sidebar').height()+$('#footerwrap').height():$('#footerwrap').height()
		}, function (newElements, instance, url) {
			// call Isotope for new elements
			var $newElems = $(newElements);

			// Mark new items: remove newItems from already loaded items and add it to loaded items
			$('.newItems').removeClass('newItems');
			$newElems.removeClass('first last').first().addClass('newItems');


			$newElems.hide().imagesLoaded().always(function () {

				$newElems.fadeIn();

				$('.wp-audio-shortcode, .wp-video-shortcode').not('div').each(function () {
					var $self = $(this);
					if ($self.closest('.mejs-audio').length === 0) {
						ThemifyMediaElement.init($self);
					}
				});

				// Apply lightbox/fullscreen gallery to new items
				Themify.InitGallery();
				if ('object' === typeof $container.data('isotope')) {
					$container.isotope('appended', $newElems);
				}

				if ($container.hasClass('auto_tiles') && $('body').hasClass('tile_enable')) {
					$container.trigger('infiniteloaded.themify', [$newElems]);
				}

				if (history.pushState && !+themifyScript.infiniteURL) {
					history.pushState(null, null, url);
				}

				$('#infscr-loading').fadeOut('normal');
				if (1 === scrollMaxPages) {
					$('#load-more, #infscr-loading').remove();
				}

				/**
				 * Fires event after the elements and its images are loaded.
				 *
				 * @event infiniteloaded.themify
				 * @param {object} $newElems The elements that were loaded.
				 */

				$('body').trigger('infiniteloaded.themify', [$newElems]);

				//	$(window).trigger( 'resize' );
			});

			scrollMaxPages = scrollMaxPages - 1;
			if (1 < scrollMaxPages && 'auto' !== themifyScript.autoInfinite) {
				$('.load-more-button').show();
			}
		});

		// disable auto infinite scroll based on user selection
		if ('auto' === themifyScript.autoInfinite) {
			$('#load-more, #load-more a').hide();
		}
    }


// Test if this is a touch device /////////
    function is_touch_device() {
        return $('body').hasClass('touch');
    }

// DOCUMENT READY /////////////////////////
    $(document).ready(function ($) {

        var $body = $('body');

        FixedHeader.init();

        if ( $('.has-mega-sub-menu').length ) {
            Themify.LoadAsync(themifyScript.theme_url + '/themify/megamenu/js/themify.mega-menu.js', null,
            null,
            null,
            function () {
                return ('undefined' !== typeof $.fn.ThemifyMegaMenu);
            });
        }
        /////////////////////////////////////////////
        // Entry Filter Layout
        /////////////////////////////////////////////
		if($('.loops-wrapper.masonry').length>0){
			if($.fn.isotope){
				LayoutAndFilter.init();
			}
			else{
				
				Themify.LoadAsync(themifyScript.theme_url + '/js/jquery.isotope.min.js', function () {
					LayoutAndFilter.init();
				},
				null,
				null,
				function () {
					return ('undefined' !== typeof $.fn.isotope);
				});
			}
		}
        


        ///////////////////////////////////////////
        // Initialize infinite scroll
        ///////////////////////////////////////////
        
		if($('.loops-wrapper.infinite').length>0){
			if($.fn.infinitescroll){
				doInfinite($('.loops-wrapper.infinite'), '.loops-wrapper.infinite .post');
			}
			else{
				Themify.LoadAsync(themifyScript.theme_url + '/js/jquery.infinitescroll.min.js', function () {
					 doInfinite($('.loops-wrapper.infinite'), '.loops-wrapper.infinite .post');
				},
				null,
				null,
				function () {
					return ('undefined' !== typeof $.fn.infinitescroll);
				});
			}
		}
		
        function ThemifyTiles(container) {
            var dummy = $('<div class="post-tiled tiled-square-small" style="visibility: hidden !important; opacity: 0;" />').appendTo(container.first()),
                    $gutter = themifyScript.tiledata['padding'],
                    $small = parseFloat(dummy.width());
					dummy.remove();

            container.each(function () {
                var $this = $(this);
                $(this).imagesLoaded().always(function (instance) {
                    $this.children('.product').addClass('post');
                    var $post = $this.children('.post');
                    themifyScript.tiledata['padding'] = $this.hasClass('no-gutter') ? 0 : $gutter;
                    $this.themify_tiles(themifyScript.tiledata, $small);
                    setClasses($post, $small);
                });
            });

        }
        ;
        function AjaxThemifyTiles(container) {

            $(document).ajaxComplete(function (e, request, settings) {
                if (settings.type === 'POST' && settings.url.indexOf('wpf_search')) {
                    ThemifyTiles($('.loops-wrapper.auto_tiles'))
                }
            });
        }
        var container = $('.auto_tiles');
        if (container.length > 0 && $body.hasClass('tile_enable')) {
            if ('undefined' === typeof Tiles) {
                Themify.LoadAsync(themifyScript.theme_url + '/js/tiles.min.js', function () {
                    if (!$.fn.themify_tiles) {
                        Themify.LoadAsync(themifyScript.theme_url + '/js/themify-tiles.js', function () {
                            ThemifyTiles(container);
                            AjaxThemifyTiles(container);
                        },
                                null,
                                null,
                                function () {
                                    return ('undefined' !== typeof $.fn.themify_tiles);
                                });
                    }
                    else {
                        ThemifyTiles(container);
                        AjaxThemifyTiles(container);
                    }
                }
                , null,
                        null,
                        function () {
                            return ('undefined' !== typeof Tiles);
                        });
            }
            else {
                ThemifyTiles(container);
                AjaxThemifyTiles(container);
            }

        }

        /////////////////////////////////////////////
        // Search Form							
        /////////////////////////////////////////////
        var $search = $('#search-lightbox-wrap');
        if ($search.length > 0) {
            var cache = [],
                    xhr,
                    $input = $search.find('#searchform input'),
                    $result_wrapper = $search.find('.search-results-wrap');
            $('.search-button, #close-search-box').click(function (e) {
                e.preventDefault();
                if ($(this).hasClass('search-button')) {
                    $search.fadeIn(function () {
                        $input.focus();
                        $body.css('overflow-y', 'hidden');
                    });
                    $('body').addClass('searchform-slidedown');
                }
                else {
                    if (xhr) {
                        xhr.abort();
                    }
                    $search.fadeOut();
                    $body.css('overflow-y', 'visible');
                    $('body').removeClass('searchform-slidedown');
                }
            });

            $result_wrapper.delegate('.search-option-tab a', 'click', function (e) {
                e.preventDefault();
                var $href = $(this).attr('href').replace('#', '');
                if ($href === 'all') {
                    $href = 'item';
                }
                else {
                    $result_wrapper.find('.result-item').stop().fadeOut();
                }
                if ($('#result-link-' + $href).length > 0) {
                    $('.view-all-button').hide();
                    $('#result-link-' + $href).show();
                }
                $result_wrapper.find('.result-' + $href).stop().fadeIn();
                $(this).closest('ul').children('li.active').removeClass('active');
                $(this).closest('li').addClass('active');
            });

            $input.prop('autocomplete', 'off').keyup(function (e) {
                function set_active_tab(index) {
                    if (index < 0) {
                        index = 0;
                    }
                    $result_wrapper.find('.search-option-tab li').eq(index).children('a').trigger('click');
                    $result_wrapper.show();
                }
                if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 65 && e.keyCode <= 90) || e.keyCode === 8) {
                    var $v = $.trim($(this).val());
                    if ($v) {
                        if (cache[$v]) {
                            var $tab = $result_wrapper.find('.search-option-tab li.active').index();
                            $result_wrapper.hide().html(cache[$v]);
                            set_active_tab($tab);
                            return;
                        }
                        setTimeout(function () {
                            $v = $.trim($input.val());
                            if (xhr) {
                                xhr.abort();
                            }
                            if (!$v) {
                                $result_wrapper.html('');
                                return;
                            }

                            xhr = $.ajax({
                                url: themifyScript.ajax_url,
                                type: 'POST',
                                data: {'action': 'themify_search_autocomplete', 'term': $v},
                                beforeSend: function () {
                                    $search.addClass('themify-loading');
                                    $result_wrapper.html('<span class="themify_spinner"></span>');
                                },
                                complete: function () {
                                    $search.removeClass('themify-loading');
                                },
                                success: function (resp) {
                                    if (!$v) {
                                        $result_wrapper.html('');
                                    }
                                    else if (resp) {
                                        var $tab = $result_wrapper.find('.search-option-tab li.active').index();
                                        $result_wrapper.hide().html(resp);
                                        set_active_tab($tab);
                                        $result_wrapper.find('.search-option-tab li.active')
                                        cache[$v] = resp;
                                    }
                                }
                            });
                        }, 100);
                    }
                    else {
                        $result_wrapper.html('');
                    }
                }
            });
        }
        /////////////////////////////////////////////
        // Scroll to top 							
        /////////////////////////////////////////////
        $('.back-top a').click(function () {
            $('body,html').animate({
                scrollTop: 0
            }, 800);
            return false;
        });

        /////////////////////////////////////////////
        // Toggle main nav on mobile 							
        /////////////////////////////////////////////
        // touch dropdown menu
        if ($body.hasClass('touch')) {
            if (!$.fn.themifyDropdown) {
                Themify.LoadAsync(themify_vars.url + '/js/themify.dropdown.js', function () {
                    $('#main-nav').themifyDropdown();
                },
                        null,
                        null,
                        function () {
                            return ('undefined' !== typeof $.fn.themifyDropdown);
                        });
            }
            else {
                $('#main-nav').themifyDropdown();
            }
        }

        // Set Slide Menu /////////////////////////
        if ($body.hasClass('header-minbar-left') || $body.hasClass('header-left-pane') || $body.hasClass('header-slide-left')) {
            $('#menu-icon').themifySideMenu({
                close: '#menu-icon-close',
                side: 'left'
            });
        }
        else {
            $('#menu-icon').themifySideMenu({
                close: '#menu-icon-close'
            });
        }
		 if ($body.hasClass('no-touch')) {
			 var $niceScrollTarget = $('.top-icon-wrap #cart-list'),
				 $niceScrollMenu = $body.is('.header-minbar-left,.header-minbar-right,.header-overlay,.header-slide-right,.header-slide-left')?
									  $('#mobile-menu'):
									  ($body.is('.header-left-pane,.header-right-pane')?$('#headerwrap'):false);
			if(($niceScrollMenu && $niceScrollMenu.length>0) || $niceScrollTarget.length>0){
				Themify.LoadAsync(themifyScript.theme_url + '/js/jquery.nicescroll.min.js', function () {
					  
					if($niceScrollTarget.length>0){
						$niceScrollTarget.niceScroll();
						setTimeout(function () {
							$niceScrollTarget.getNiceScroll().resize();
						}, 200);
					 }
					
					if($niceScrollMenu){
						$niceScrollMenu.niceScroll();
						$body.on('sidemenushow.themify', function () {
							setTimeout(function () {
								$niceScrollMenu.getNiceScroll().resize();
							}, 200);
						});
					}
				}, 
				null,
				null,
				function () {
					return ('undefined' !== typeof $.fn.niceScroll);
				});
            }
        }
        // Set Body Overlay Show/Hide /////////////////////////
        var $overlay = $('<div class="body-overlay">');
        $body.append($overlay).on('sidemenushow.themify', function () {
            $overlay.addClass('body-overlay-on');
        }).on('sidemenuhide.themify', function () {
            $overlay.removeClass('body-overlay-on');
        }).on('click.themify touchend.themify', '.body-overlay', function () {
            $('#menu-icon').themifySideMenu('hide');
            $('#cart-link').themifySideMenu('hide');
        });

        // Set Body Overlay Resize /////////////////////////
        $(window).on('debouncedresize', function () {
            if ($('#menu-icon').is(':visible') && $('#mobile-menu').hasClass('sidemenu-on')) {
                $overlay.addClass('body-overlay-on');
            }
            else {
                $overlay.removeClass('body-overlay-on');
            }
        });

        // Set Dropdown Arrow /////////////////////////
        $("#main-nav li.menu-item-has-children > a, #main-nav li.page_item_has_children > a").after(
                "<span class='child-arrow'></span>"
                );
        $('#main-nav li.menu-item-has-children > .child-arrow, #main-nav li.page_item_has_children > .child-arrow').click(function () {
            $(this).toggleClass('toggle-on');
            return true;
        });

       
        //For Builder Modules

        $('body').delegate('#themify_builder_lightbox_parent #layout_post a, #themify_builder_lightbox_parent #layout_products a', 'click', function () {
            var $masonary = $('#themify_builder_lightbox_parent .masonry_post'),
                    $content_layout = $('#themify_builder_lightbox_parent .content_layout'),
                    $post_gutter = $('#themify_builder_lightbox_parent .post_gutter'),
                    $val = $(this).prop('id');
            if ($val === 'grid3' || $val === 'grid2' || $val === 'grid4') {
                $masonary.show();
            }
            else {
                $masonary.hide();
                if ($val === 'auto_tiles') {
                    $content_layout.hide();
                }
                else {
                    $content_layout.show();
                }
            }
        });
        $('body').on('editing_module_option', function (e, settings) {

            if ($('#themify_builder_lightbox_parent .masonry_post').length > 0) {
                setTimeout(function () {
                    var $cl = $('#themify_builder_lightbox_parent #layout_post').length > 0 ? '#layout_post' : '#layout_products',
                            $layout = $('#themify_builder_lightbox_parent ' + $cl + ' a.selected');

                    if ($layout.length === 0) {
                        $layout = $('#themify_builder_lightbox_parent ' + $cl + ' a').first();
                    }
                    $layout.trigger('click');
                }, 600);
            }
        });

		if ($body.hasClass('header-left-pane') || $body.hasClass('header-right-pane') ) {
			 var $HLicons = $('.top-icon-wrap, .search-button'),
				 $HLiconswrapper = $('#mobile-menu');
			$( $HLiconswrapper ).prepend( $( '<div class="header-icons"></div>' ) );
			$( '.header-icons' ).append( $HLicons );
        }
        if ($body.hasClass('header-overlay')) {
			$('.search-button').appendTo('.top-icon-wrap');
		}

		// Mobile cart
		( function( $cart, $mobMenu ) {
			if( $cart.length && $mobMenu.length && ! $body.is( '.header-left-pane, .header-right-pane, .header-minbar-left, .header-minbar-right' ) ) {
				var $cloneCart = $cart.clone();

				$cloneCart.attr( 'id', 'cart-link-mobile' );
				$cloneCart.addClass( 'icon-menu' );

				$cloneCart.insertBefore( $mobMenu );
				$cloneCart.find( '.tooltip' ).remove();

				if( $cart.is( '#cart-link' ) ) {
					$cloneCart.on( 'click', function( e ) {
						e.preventDefault();
						$cart.trigger( 'click' );
					} );
				}
			}

		} )( $( '#cart-icon-count > a' ), $( '#menu-icon' ) );
    });

// WINDOW LOAD /////////////////////////
    $(window).load(function () {

        
        // Lightbox / Fullscreen initialization ///////////
        if (typeof ThemifyGallery !== 'undefined') {
            ThemifyGallery.init({'context': jQuery(themifyScript.lightboxContext)});
        }

        // EDGE MENU /////////////////////////
        $(function ($) {
            $("#main-nav li").on('mouseenter mouseleave', function (e) {
                if ($('ul', this).length) {
                    var elm = $('ul:first', this);
                    var off = elm.offset();
                    var l = off.left;
                    var w = elm.width();
                    var docW = $(window).width();
                    var isEntirelyVisible = (l + w <= docW);

                    if (!isEntirelyVisible) {
                        $(this).addClass('edge');
                    } else {
                        $(this).removeClass('edge');
                    }

                }
            });
        });

    }).on("load debouncedresize ready", function (e) {
        var viewport = $(window).width(),
                $body = $('body');
        if ($body.hasClass('header-logo-center')) {
            if (viewport > 1183) {
                var $HalfWidth = $(window).width() / 2 - $('#site-logo').width() / 2;
                $('#main-nav').css('max-width', $HalfWidth);
            }
            else {
                $('#main-nav').removeAttr('style');
            }
        }
        else if ($body.hasClass('header-slide-right') || $body.hasClass('header-slide-left')) {

            var $swapWrap = $('.top-icon-wrap, .search-button'),
				$sidePanel = $('#mobile-menu'),
				$insertWrapper = $('#main-nav-wrap');

            // Move menu into side panel on small screens /////////////////////////
            if (viewport > tf_mobile_menu_trigger_point) {
                $sidePanel.before($swapWrap);
            } else {
                $insertWrapper.before($swapWrap);
            }

        }

    });

	// Mega menu width
	var MegaMenuWidth = function(){
		
		if ($(window).width() > tf_mobile_menu_trigger_point) { 
			$('#main-nav li.has-mega-column > ul, #main-nav li.has-mega-sub-menu > .mega-sub-menu').css('width',  $('#header').width());
		} else {
			$('#main-nav li.has-mega-column > ul, #main-nav li.has-mega-sub-menu > .mega-sub-menu').removeAttr("style");
		}
	};
	$( document ).on( 'ready',MegaMenuWidth );
	$( window ).on( 'debouncedresize',MegaMenuWidth );

})(jQuery);