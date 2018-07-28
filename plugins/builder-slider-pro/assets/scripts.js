(function ($) {

         if(!$.fn.themifyBuilderImagesLoaded){
            $.fn.themifyBuilderImagesLoaded = function (callback) {
                    var elems = this.filter('img'),
                                    len = elems.length,
                                    blank = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";

                    elems.bind('load.imgloaded', function () {
                            if (--len <= 0 && this.src !== blank) {
                                    elems.unbind('load.imgloaded');
                                    callback.call(elems, this);
                            }
                    }).each(function () {
                            // cached images don't fire load sometimes, so we reset src.
                            if (this.complete || this.complete === undefined) {
                                    var src = this.src;
                                    this.src = blank;
                                    this.src = src;
                            }
                    });

                    return this;
            };
        }
        
	function do_pro_slider(el,type) {
            var items = $( '.module.module-pro-slider', el );
            if(el && el.hasClass('module-pro-slider') && el.hasClass('module')){
                items = items.add(el)
            }
                
            Themify.LoadAsync(builderSliderPro.url+'jquery.sliderPro.js',function(){
                Themify.LoadAsync(builderSliderPro.url+'sliderPro.helpers.js',callback, '1.2.1', null, function(){
                        return ($.inArray('TransitionEffects', $.SliderPro.modules)!==-1);
                } );
                }, '1.2.1', null, function(){
                    return ('undefined' !== typeof $.fn.sliderPro);
            } );
               
            function callback(){
                    
                    function call_slider($this,config){
                            $this.find( '.slider-pro' ).sliderPro( config )
                                    .css( 'visibility', 'visible' )
                                    .trigger( 'resize' );
                         
                    }
                    items.each(function(){
                            var $this = $( this ),
                            config = {
                                    buttons: !$this.hasClass( 'pager-none' ) && !$this.hasClass( 'pager-type-thumb' ),
                                    arrows : true,
                                    responsive : true,
                                    thumbnailWidth: $this.data( 'thumbnail-width' ),
                                    thumbnailHeight: $this.data( 'thumbnail-height' ),
                                    width : $this.data( 'slider-width' ) !== '' ? $this.data( 'slider-width' ) !== '100%' ? parseInt( $this.data( 'slider-width' ) ) : $this.data( 'slider-width' ) : '100%', // set default slider width to 100%
                                    // forceSize : 'fullWidth', // force the slider to span the width of the page
                                    height : $this.data( 'slider-height' ) !== '' ? $this.data( 'slider-height' ) !== '100vh' ? parseInt( $this.data( 'slider-height' ) ) : $this.data( 'slider-height' ) : 400, // set default slider height to 400
                                    fadeOutPreviousSlide: false,		
                                    touchSwipe : tbLocalScript.isTouch, // on touch devices, enable the touchSwipe
                                    init:function(){
                                            $this.find('.themify_builder_slider_loader').remove();
                                    },
                                    gotoSlideComplete: function( event ) {
										if( $(window).width() > 320 ) {
											event.slider.parent().data( 'isKeySliding', false );
										}
                                    }
                            };

                            /* custom autoplay module settings */
                            config.autoHeightOnReize = true;
                            config._autoplay = $this.data( 'autoplay' ) !== 'off';
                            config._autoplayDelay = $this.data( 'autoplay' );
                            config.timer_bar = $this.data( 'timer-bar' ) === 'yes';
                            config._autoplayOnHover = $this.data( 'hover-pause' );
                            if( $this.data( 'timer-bar' ) === 'yes' ) {
                                    $( '.slider-pro', $this ).prepend( '<div class="bsp-timer-bar" />' );
                            }
                            /* do not edit these configurations */
                            config.autoplay = false; // disable the default Autoplay module
                            config.autoHeight = false;
                            config.thumbnailTouchSwipe = true; // this is required for the thumbnail click action to work
                            var $images = $this.find('img');
                            if($images.length>0){
                                    $images.themifyBuilderImagesLoaded(function () {
                                            call_slider($this,config);
                                    });
                            }
                            else{
                                call_slider($this,config);
                            }
                    });
                }
	}
        do_pro_slider();
        if(Themify.is_builder_active){
            $( 'body' ).on( 'builder_load_module_partial', function(e,el,type){
                do_pro_slider(el,type);
            } );
        }

}(jQuery));