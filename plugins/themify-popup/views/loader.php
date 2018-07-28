<?php
/**
 * Adds script files to load assets required by the Popup plugin
 *
 * @package Themify Popup
 */
?>
<script>
jQuery(function($){
	if( $( '.themify-popup' ).length ) {
		var path = '<?php echo THEMIFY_POPUP_URI; ?>assets';
		if( ! $( '#themify-builder-animate-css' ).length ) {
			$( 'head' ).append( '<link rel="stylesheet" id="themify-builder-animate-css" href="' + path + '/animate.min.css" />' );
		}
		if( ! $( '#magnific-css' ).length ) {
			$( 'head' ).append( '<link rel="stylesheet" id="magnific-css" href="' + path + '/lightbox.css" />' );
		}
		$( 'head' ).append( '<link rel="stylesheet" id="themify-popup" href="' + path + '/styles.css" />' );
		function load() {
			window.themifyPopup = { ajaxurl : '<?php echo admin_url( 'admin-ajax.php' ); ?>' };
			$.getScript( path + '/scripts.js' );
		}
		if( typeof $.fn.magnificPopup != 'function' ) {
			$.getScript( path + '/lightbox.min.js' ).done( load );
		} else {
			load();
		}
	}
});
</script>