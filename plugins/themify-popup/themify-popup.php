<?php
/*
Plugin Name:  Themify Popup
Plugin URI:   https://themify.me/popup
Version:      1.1.1
Author:       Themify
Description:  A free plugin to display popups for ads, newsletter subscriptions, and general info. It comes with various popup styles (classic, slide-out, fullscreen, etc.). Popups can be scheduled and configured to show on certain posts, pages, categories, and user roles.
Text Domain:  themify-popup
Domain Path:  /languages
*/

defined( 'ABSPATH' ) or die( '-1' );

/**
 * Bootstrap Popup plugin
 *
 * @since 1.0
 */
function themify_popup_setup() {
	$data = get_file_data( __FILE__, array( 'Version' ) );
	if( ! defined( 'THEMIFY_POPUP_DIR' ) )
		define( 'THEMIFY_POPUP_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );

	if( ! defined( 'THEMIFY_POPUP_URI' ) )
		define( 'THEMIFY_POPUP_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

	if( ! defined( 'THEMIFY_POPUP_VERSION' ) )
		define( 'THEMIFY_POPUP_VERSION', $data[0] );

	include THEMIFY_POPUP_DIR . 'includes/themify-metabox/themify-metabox.php';
	include THEMIFY_POPUP_DIR . 'includes/system.php';

	Themify_Popup::get_instance();
}
add_action( 'after_setup_theme', 'themify_popup_setup' );

function themify_popup_activate() {
	$path = trailingslashit( dirname( __FILE__ ) );
	include( $path . 'sample/sample.php' );

	themify_popup_setup();
	Themify_Popup::get_instance()->register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'themify_popup_activate' );
add_filter( 'plugin_row_meta', 'themify_popup_plugin_meta', 10, 2 );
function themify_popup_plugin_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) == $file ) {
		$row_meta = array(
		  'changelogs'    => '<a href="' . esc_url( 'https://themify.me/changelogs/' ) . basename( dirname( $file ) ) .'.txt" target="_blank" aria-label="' . esc_attr__( 'Plugin Changelogs', 'themify-popup' ) . '">' . esc_html__( 'View Changelogs', 'themify-popup' ) . '</a>'
		);
 
		return array_merge( $links, $row_meta );
	}
	return (array) $links;
}