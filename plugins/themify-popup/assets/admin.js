(function($){

	var $start_date = $( '#popup_start_at' ),
		$end_date = $( '#popup_end_at' );

	// auto-fill the Start Date and End Date fields
	$( '#popup_no_date_limit' ).on( 'change', function(){
		if ( $(this).is( ':checked' ) ) {
			if( $start_date.val() == '1970-01-01 00:00:00' ) {
				$start_date.val( '' );
			}
			if( $end_date.val() == '3015-01-01 00:00:00' ) {
				$end_date.val( '' );
			}
			$( '#popup_start_at' ).closest( '.themify_field_row' ).show();
			$( '#popup_end_at' ).closest( '.themify_field_row' ).show();
		} else {
			$start_date.val( '1970-01-01 00:00:00' ).closest( '.themify_field_row' ).hide();
			$end_date.val( '3015-01-01 00:00:00' ).closest( '.themify_field_row' ).hide();
		}
	} ).change();

	$( '#popup_auto_close' ).on( 'change', function(){
		if ( $(this).is( ':checked' ) ) {
			$( '#popup_auto_close_delay' ).closest( '.themify_field_row' ).show();
		} else {
			$( '#popup_auto_close_delay' ).closest( '.themify_field_row' ).hide();
		}
	}).change();

	$( '#popup_limit_count' ).on( 'change', function(){
		if ( $(this).val() !== '' ) {
			$( '#popup_cookie_expiration' ).closest( '.themify_field_row' ).show();
		} else {
			$( '#popup_cookie_expiration' ).closest( '.themify_field_row' ).hide();
		}
	}).change();

	$( '#popup_auto_height' ).on( 'change', function(){
		if ( $(this).is( ':checked' ) ) {
			$( this ).parent().nextAll().hide();
		} else {
			$( this ).parent().nextAll().show();
		}
	}).change();
})(jQuery, window, document);