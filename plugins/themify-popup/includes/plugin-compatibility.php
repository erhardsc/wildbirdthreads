<?php
/**
 * Houses codes that provide compatibility with other plugins
 */

class Themify_Popup_Plugin_Compatibility {

	function __construct() {
		add_action( 'after_setup_theme', array( $this, 'themify_builder_compat' ), 20 );
		add_filter( 'themify_builder_layout_providers', array( $this, 'add_sample_popup_layouts' ) );
	}

	/**
	 * Disable Builder frontend editor for the popup posts loaded on frontend
	 *
	 * @since 1.0.0
	 */
	function themify_builder_compat() {
		global $ThemifyBuilder;

		if( isset( $ThemifyBuilder ) ) {
			add_action( 'themify_popup_before_render', array( $this, 'themify_popup_before_render' ) );
			add_action( 'themify_popup_after_render', array( $this, 'themify_popup_after_render' ) );
		}
	}

	/**
	 * Actions to perform before rendering the popup
	 *
	 * @since 1.0.5
	 */
	function themify_popup_before_render() {
		global $ThemifyBuilder;

		/* disable Builder editor for popups */
		$GLOBALS['ThemifyBuilder']->in_the_loop = true;

		/* Fix bug in WC Shop page, see #6334 */
		$GLOBALS['ThemifyBuilder']->skip_display_check = true;

		/* disable the static stylesheet generation in Builder, forces Builder to make inline <style> tag for everything */
		add_filter( 'themify_builder_enqueue_stylesheet', array( $this, 'disable_static_stylesheet' ) );
		add_action( 'themify_builder_before_template_content_render', array( $ThemifyBuilder->stylesheet, 'enqueue_stylesheet' ), 10 );

		/* disable Row Width options: rows inside the popup cannot be displayed as fullwidth */
		add_filter( 'themify_builder_row_classes', array( $this, 'themify_builder_row_classes' ), 10, 3 );
	}

	/**
	 * Revert the changes made in self::themify_popup_before_render()
	 *
	 * @since 1.0.5
	 */
	function themify_popup_after_render() {
		remove_filter( 'themify_builder_enqueue_stylesheet', array( $this, 'disable_static_stylesheet' ) );
		$GLOBALS['ThemifyBuilder']->in_the_loop = false;
		$GLOBALS['ThemifyBuilder']->skip_display_check = false;
		remove_filter( 'themify_builder_row_classes', array( $this, 'themify_builder_row_classes' ), 10, 3 );
	}

	function disable_static_stylesheet( $enable ) {
		return false;
	}

	function themify_builder_row_classes( $row_classes, $row, $builder_id ) {
		$row_classes = str_replace( array( 'fullwidth_row_container', 'fullwidth' ), '', $row_classes );

		return $row_classes;
	}

	/**
	 * Add sample layouts bundled with Popup plugin to Themify Builder
	 *
	 * @since 1.0.0
	 */
	function add_sample_popup_layouts( $providers ) {
		include THEMIFY_POPUP_DIR . 'includes/themify-builder-popup-layout-provider.php';
		$providers[] = 'Themify_Builder_Layouts_Provider_Themify_Popup';
		return $providers;
	}
}
new Themify_Popup_Plugin_Compatibility;