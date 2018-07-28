<?php

class Themify_Popup {

	private static $instance = null;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @return	A single instance of this class.
	 */
	public static function get_instance() {
		return null == self::$instance ? self::$instance = new self : self::$instance;
	}

	private function __construct() {
		include THEMIFY_POPUP_DIR . 'includes/plugin-compatibility.php';

		add_action( 'init', array( $this, 'i18n' ), 5 );
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'themify_do_metaboxes', array( $this, 'meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'wp_nav_menu_objects' ) );
		add_filter( 'mce_external_plugins', array( $this, 'mce_external_plugins' ) );
		add_filter( 'mce_buttons', array( $this, 'mce_buttons' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'tinymce_localize' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'tinymce_localize' ) );
		add_filter( 'template_include', array( $this, 'template_include' ), 100 );
		add_action( 'template_redirect', array( $this, 'hooks' ) );
	}

	public function hooks() {
		if( ! is_singular( 'themify_popup' ) ) {
			add_action( 'wp_footer', array( $this, 'render' ), 1 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 13 );
			add_action( 'wp_footer', array( $this, 'loader' ), 2 );
			add_shortcode( 'tf_popup', array( $this, 'shortcode' ) );

			$this->update_page_view();
		} else {
			if( ! current_user_can( 'manage_options' ) ) {
				wp_redirect( home_url() );
				exit;
			}
		}
	}

	public function i18n() {
		load_plugin_textdomain( 'themify-popup', false, THEMIFY_POPUP_DIR . 'languages/' );
	}

	function register_post_type() {
		$labels = array(
			'name'               => _x( 'Popups', 'post type general name', 'themify-popup' ),
			'singular_name'      => _x( 'Popup', 'post type singular name', 'themify-popup' ),
			'menu_name'          => _x( 'Themify Popups', 'admin menu', 'themify-popup' ),
			'name_admin_bar'     => _x( 'Popup', 'add new on admin bar', 'themify-popup' ),
			'add_new'            => _x( 'Add New', 'book', 'themify-popup' ),
			'add_new_item'       => __( 'Add New Popup', 'themify-popup' ),
			'new_item'           => __( 'New Popup', 'themify-popup' ),
			'edit_item'          => __( 'Edit Popup', 'themify-popup' ),
			'all_items'          => __( 'Manage Popups', 'themify-popup' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'themify_popup' ),
			'capability_type'    => 'post',
			'menu_position'      => 80, /* below Settings */
			'has_archive'        => false,
			'supports'           => array( 'title', 'editor' ),
		);

		register_post_type( 'themify_popup', $args );
	}

	function meta_box( $panels ) {
		$options = include( $this->get_view_path( 'config.php' ) );
		$panels[] = array(
			"name" => __( 'Popup Settings', 'themify' ),
			'id' => 'themify-popup',
			"options" => $options,
			"pages" => "themify_popup"
		);
		$panels[] = array(
			'name' => __( 'Custom CSS', 'themify-popup' ),
			'id' => 'themify-popup-css',
			"options" => array(
				array(
					'name' => 'custom_css',
					'title' => __( 'Custom CSS', 'themify-popup' ),
					'type' => 'textarea',
					'size' => 55,
					'rows' => 25,
					'description' => __( 'You can use <code>%POPUP%</code> to reference this popup.', 'themify-popup' ),
				),
			),
			"pages" => "themify_popup"
		);

		return $panels;
	}

	function is_admin_screen() {
		global $hook_suffix, $post;
		if( ( 'post.php' == $hook_suffix || 'post-new.php' == $hook_suffix ) && $post->post_type == 'themify_popup' ) {
			return true;
		}
		return false;
	}

	public function admin_enqueue() {
		global $post, $hook_suffix;

		if( ! $this->is_admin_screen() )
			return;

		wp_enqueue_script( 'themify-popup', THEMIFY_POPUP_URI . 'assets/admin.js', array( 'jquery' ), THEMIFY_POPUP_VERSION, true );
	}

	function get_popups() {
		$datenow = date_i18n('Y-m-d H:i:s');
		$args = array(
			'post_type' => 'themify_popup',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'popup_start_at',
					'value' => $datenow,
					'compare' => '<=',
					'type' => 'datetime'
				),
				array(
					'key' => 'popup_end_at',
					'value' => $datenow,
					'type' => 'datetime',
					'compare' => '>='
				)
			)
		);
		if (class_exists('SitePress')) {
			/*
			* For some unknown reason WPML 4.0.2 will not render posts for other languages if suppress_filters or posts_per_page value is not a string type.
			*/
			$args['suppress_filters'] = '0';
		}
		$the_query = new WP_Query();
		$args = apply_filters( "themify_popup_query_args", $args );
		$posts = $the_query->query( $args );

		return $posts;
	}

	public function get_view_path( $name ) {
		if( locate_template( 'themify-popup/' . $name ) ) {
			return locate_template( 'themify-popup/' . $name );
		} elseif( file_exists( THEMIFY_POPUP_DIR . 'views/' . $name ) ) {
			return THEMIFY_POPUP_DIR . 'views/' . $name;
		}

		return false;
	}

	public function load_view( $name, $data = array() ) {
		extract( $data );
		if( $view = $this->get_view_path( $name ) ) {
			ob_start();
			include( $view );
			return ob_get_clean();
		}

		return '';
	}

	function render() {
		global $ThemifyBuilder;

		do_action( 'themify_popup_before_render' );

		echo $this->load_view( 'render.php', array(
			'popups' => $this->get_popups()
		) );

		do_action( 'themify_popup_after_render' );
	}

	public function enqueue() {
		wp_enqueue_script( 'jquery' );
	}

	function loader() {
		include( $this->get_view_path( 'loader.php' ) );
	}

	public function get_element_attributes( $props ) {
		$out = '';
		foreach( $props as $atts => $val ) { 
			if( ! in_array( $atts, array( 'id', 'class', 'style' ) ) && substr( $atts, 0, 5 ) != 'data-' ) {
				$atts = 'data-' . $atts;
			}
			$out .= ' '. $atts . '="' . esc_attr( $val ) . '"'; 
		}
		return $out;
	}

	/**
	 * Fix URLs in menu items pointing to an inline popup
	 */
	function wp_nav_menu_objects( $items ) {
		foreach( $items as $item ) {
			if( $item->type == 'post_type' && $item->object == 'themify_popup' ) {
				$item->url = '#themify-popup-' . $item->object_id;
				$item->classes[] = 'tf-popup';
			}
		}

		return $items;
	}

	function update_page_view() {
		$days = 1;
		$count = isset( $_COOKIE['themify_popup_page_view'] ) ? (int) $_COOKIE['themify_popup_page_view'] + 1 : 1;
		setcookie( 'themify_popup_page_view', $count, time() + 3600 * 24 * $days, COOKIEPATH, COOKIE_DOMAIN, false );
	}

	/**
	 * Add plugin JS file to list of external plugins.
	 *
	 * @param array $mce_external_plugins
	 * @return mixed
	 */
	function mce_external_plugins( $mce_external_plugins ) {
		global $wp_version;
		$mce_external_plugins['mceThemifyPopup'] = THEMIFY_POPUP_URI . 'assets/tinymce.js';

		return $mce_external_plugins;
	}

	function mce_buttons( $mce_buttons ) {
		array_push( $mce_buttons, 'separator', 'mceThemifyPopup' );
		return $mce_buttons;
	}

	function tinymce_localize() {
		$fields = include( $this->get_view_path( 'shortcode-fields.php' ) );
		wp_localize_script( 'editor', 'mceThemifyPopup', array(
			'fields' => $fields,
			'labels' => array(
				'menuName' => __( 'Themify Popup', 'themify-popup' ),
			)
		));
	}

	function get_manual_popup_list() {
		$list = array();
		$args = array(
			'post_type' => 'themify_popup',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'nopaging' => true,
			'meta_query' => array(
				array(
					'key' => 'popup_trigger',
					'value' => 'manual',
					'compare' => '=',
				),
			)
		);
		if (class_exists('SitePress')) {
			/*
			* This will insure that only wpml current language related popups are shown in shortcode popup list.
			*/
			$args['suppress_filters'] = '0';
		}
		$query = get_posts( $args );
		if( ! empty( $query ) ) {
			foreach( $query as $popup ) {
				$list[] = array( 'text' => $popup->post_title, 'value' => $popup->ID );
			}
		}

		return $list;
	}

	function shortcode( $atts, $content = null ) {
		extract( shortcode_atts( array(
			'color' => '',
			'size' 	=> '',
			'style'	=> '',
			'link' 	=> 0,
			'target'=> '',
			'text'	=> ''
		), $atts, 'tf_popup' ) );

		if( ! $post = get_post( $link ) ) {
			return;
		}

		if ( $color ) {
			$color = "background-color: $color;";
		}
		if ( $text ) {
			$text = "color: $text;";	
		}
		$html = '<a href="#themify-popup-' . $link . '" class="tf_popup '. esc_attr( $style.' '.$size ) . '"';
		if ( $color || $text ) {
			$html.=' style="'.esc_attr( $color.$text ).'"';
		}
		if ( $target ) {
			$html.=' target="'.esc_attr( $target ).'"';
		}
		$html.= '>' . do_shortcode( $content ) . '</a>';

		return $html;
	}

	/**
	 * Use custom template file on popup single pages
	 *
	 * @since 1.0
	 */
	function template_include( $template ) {
		if( is_singular( 'themify_popup' ) ) {
			$template = $this->get_view_path( 'single-popup.php' );
		}

		return $template;
	}

	/**
	 * Checks whether a popup should be displayed or not
	 *
	 * @since 1.0
	 * @return bool
	 */
	function is_popup_visible( $id ) {
		$visible = true;

		// popup is disabled for mobile
		if( themify_popup_check( 'popup_mobile_disable' ) && wp_is_mobile() ) {
			$visible = false;
		}

		// has user seen this popup before?
		/**
		 * Migration routine: previsouly used "show_once" checkbox is converted to "limit_count" (number).
		 */
		if ( themify_popup_check( 'popup_show_once' ) ) {
			delete_post_meta( $id, 'popup_show_once' );
			add_post_meta( $id, 'popup_limit_count', 1 );
		}
		if ( themify_popup_check( 'popup_limit_count' ) && isset( $_COOKIE["themify-popup-{$id}"] ) && $_COOKIE["themify-popup-{$id}"] >= themify_popup_get( 'popup_limit_count' ) ) {
			$visible = false;
		}

		// check if popup has a page view limit
		if( $view_count = themify_popup_get( 'popup_page_view', 0 ) ) {
			if( ! ( isset( $_COOKIE['themify_popup_page_view'] ) && $_COOKIE['themify_popup_page_view'] >= $view_count ) ) {
				$visible = false;
			}
		}

		if( themify_popup_get( 'popup_show_on_toggle', 'all-pages' ) == 'specific-pages' && themify_popup_check( 'popup_show' ) ) {
			if( ! themify_verify_assignments( themify_popup_get( 'popup_show' ) ) ) {
				$visible = false;
			}
		}

		if( ( themify_popup_get( 'popup_show_to' ) == 'guest' && is_user_logged_in() )
			|| ( themify_popup_get( 'popup_show_to' ) == 'user' && ! is_user_logged_in() )
		) {
			$visible = false;
		}

		return $visible;
	}

}

/**
 * Check if option is set for the current popup in the loop
 *
 * @since 1.0
 */
function themify_popup_check( $var ) {
	global $post;

	if ( is_object( $post ) && get_post_meta( $post->ID, $var, true ) != '' && get_post_meta( $post->ID, $var, true ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Get an option for the current popup in the loop
 *
 * @since 1.0
 */
function themify_popup_get( $var, $default = null ) {
	global $post;

	if ( is_object( $post ) && get_post_meta( $post->ID, $var, true ) !== '' ) {
		return get_post_meta( $post->ID, $var, true );
	} else {
		return $default;
	}
}

/**
 * Return the custom CSS codes for current popup (in the loop)
 *
 * @return string
 */
function themify_popup_get_custom_css() {
	$css = themify_popup_get( 'custom_css' );
	$css = str_replace( '%POPUP%', '#themify-popup-' . get_the_id(), $css );

	return $css;
}