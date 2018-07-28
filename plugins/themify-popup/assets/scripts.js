(function($){

	function setCookie( cname, cvalue, exdays ) {
		var d = new Date();
		d.setTime( d.getTime() + ( exdays * 24 * 60 * 60 * 1000 ) );
		var expires = "expires="+ d.toUTCString();
		document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
	}

	function getCookie( cname ) {
		var name = cname + "=";
		var decodedCookie = decodeURIComponent( document.cookie );
		var ca = decodedCookie.split( ';' );
		for ( var i = 0; i <ca.length; i++ ) {
			var c = ca[i];
			while ( c.charAt(0) == ' ' ) {
				c = c.substring(1);
			}
			if ( c.indexOf( name ) == 0 ) {
				return c.substring( name.length, c.length );
			}
		}
		return "";
	}

	function getScrollPercent() {
		var h = document.documentElement, 
			b = document.body,
			st = 'scrollTop',
			sh = 'scrollHeight';
		return (h[st]||b[st]) / ((h[sh]||b[sh]) - h.clientHeight) * 100;
	}

	function triggerEvent(a, b) {
		var c;
		document.createEvent ? (c = document.createEvent('HTMLEvents'), c.initEvent(b, !0, !0)) : document.createEventObject && (c = document.createEventObject(), c.eventType = b), c.eventName = b, a.dispatchEvent ? a.dispatchEvent(c) : a.fireEvent && htmlEvents["on" + b] ? a.fireEvent("on" + c.eventType, c) : a[b] ? a[b]() : a["on" + b] && a["on" + b]()
	}

	function open( $el ) {
		// keep track of how many times visitor has seen this popup
		var view_count = getCookie( "themify-popup-" + $el.data( 'object-id' ) );
		if ( view_count !== '' && parseInt( view_count ) !== NaN ) {
			view_count = parseInt( view_count );
		} else {
			view_count = 0;
		}

		// visitor has seen this popup already? Bail!
		if ( $el.data( 'limit-count' ) && view_count >= $el.data( 'limit-count' ) ) {
			return;
		}

		setCookie( "themify-popup-" + $el.data( 'object-id' ), view_count + 1, $el.data( 'cookie-expiration' ) );

		var style = $el.data( 'style' );
		$el.show();
		var classes = 'themify-popup-showing themify-popup-style-' + style + ' themify-popup-showing-' + $el.data( 'object-id' ) + ' tf-popup-position-' + $el.data( 'position' );
		if( style == 'classic' || style == 'fullscreen' ) {

			// do not display the popup if one is showing already
			if( $( 'body' ).hasClass( 'themify-popup-showing' ) ) {
				return;
			}

			$.magnificPopup.open({
				closeOnBgClick : ( style == 'fullscreen' || ( style == 'classic' && $el.data( 'close-overlay' ) == 'no' ) ) ? false : true,
				enableEscapeKey : ( style == 'fullscreen' ) ? false : true,
				removalDelay: 1000,
				items: {
					src: $el,
					type: 'inline',
				},
				callbacks : {
					open : function(){
						apply_animation( $( '.mfp-wrap .mfp-content' ), $el.data( 'animation' ) );

						$( 'body' ).addClass( classes );

						// init Builder modules JS
						if( typeof ThemifyBuilderModuleJs == 'object' ) {
							ThemifyBuilderModuleJs.loadOnAjax();
						}

						// move close button to the top-right corner of the screen
						$( '.mfp-close' ).addClass( 'themify-popup-close' ).appendTo( $( '.mfp-content' ) );

						/* force elements inside the popup to respond to the popup being opened */
						triggerEvent( window, 'resize' );
					},
					beforeClose: function(){
						var el = $( '.mfp-wrap .mfp-content' );
						apply_animation( el, $el.data( 'animation-exit' ), function(){
							el.hide();
						} );
					},
					close : function(){
						$( 'body' ).removeClass( classes );
					}
				}
			});
		} else if ( style == 'slide-out' ) {
			slide_out_fix_position( $el );
			$( window ).resize( function(){
				slide_out_fix_position( $el );
			} );
			$el.show();
			apply_animation( $el, $el.data( 'animation' ) );
			$el.append( '<button class="themify-popup-close">x</button>' );
		}

		if( $el.data( 'auto-close' ) ) {
			setTimeout(function() {
				close( $el );
			}, $el.data( 'auto-close' ) * 1000 );
		}
	}

	function apply_animation( $el, name, callback ) {
		$el.addClass( 'animated ' + name )
			.one( 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend', function(){
				// remove the animation classes after animation ends
				// required in order to apply new animation on close
				$( this ).removeClass( 'animated ' + name );
				if( callback ) {
					callback();
				}
			} );
	}

	function slide_out_fix_position( $el ) {
		if( $el.hasClass( 'bottom-center' ) || $el.hasClass( 'top-center' ) ) {
			$el.css( 'marginLeft', ( ( $el.width() / 2 ) * -1 ) + 'px' );
		} else if ( $el.hasClass( 'center-left' ) || $el.hasClass( 'center-right' ) ) {
			$el.css( 'marginTop', ( ( $el.height() / 2 ) * -1 ) + 'px' );
		}
	}

	function close( $el ) {
		var style = $el.data( 'style' );
		if( style == 'classic' || style == 'fullscreen' ) {
			$( '.mfp-close' ).click();
		} else if ( style == 'slide-out' ) {
			$el.find( '.themify-popup-close' ).click();
		}
	}

	$( 'body' ).on( 'click', '.themify-popup .themify-popup-close', function(){
		var popup = $( this ).closest( '.themify-popup' );
		apply_animation( popup, popup.data( 'animation-exit' ), function(){
			popup.hide();
		} );
	} );

	$(function(){
		$( '.themify-popup' ).each( function(){
			var $this = $( this );

			// manual trigger, open popup when a link calls it
			$( 'body' ).on( 'click', '[href="#themify-popup-' + $this.data( 'object-id' ) + '"]', function(e){
				e.preventDefault();
				open( $this );
			} );

			// automatic trigger
			if( $this.data( 'trigger' ) == 'default' ) {
				open( $this );
			} else if( $this.data( 'trigger' ) == 'timedelay' ) {
				setTimeout(function() {
					open( $this );
				}, $this.data( 'time-delay' ) * 1000 );
			} else if( $this.data( 'trigger' ) == 'scroll' ) {
				var position = $this.data( 'scroll-position' );
				var on = $this.data( 'scroll-on' );
				$( window ).scroll( function(){
					if( position > 0 ) {
						if( ( on == 'px' && window.scrollY > position ) || ( on == '%' && getScrollPercent() > position ) ) {
							open( $this );
							position = -1; // prevent the popup from being displayed again
						}
					}
				} );
			} else if( $this.data( 'trigger' ) == 'exit' ) {
				var show = true;
				document.addEventListener("mousemove", function(e) {
					// Get current scroll position
					var scroll = window.pageYOffset || document.documentElement.scrollTop;
					if ( ( e.pageY - scroll ) < 7 && show ) {
						open( $this );
						show = false;
					}
				});
			}
		});
	});

})(jQuery, window, document);