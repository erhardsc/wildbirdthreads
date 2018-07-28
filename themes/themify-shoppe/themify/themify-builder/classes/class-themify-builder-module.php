<?php
/**
 * This file contain abstraction class to create module object.
 *
 * Themify_Builder_Module class should be used as main class and
 * create any child extend class for module.
 * 
 *
 * @package    Themify_Builder
 * @subpackage Themify_Builder/classes
 */

/**
 * The abstract class for Module.
 *
 * Abstraction class to initialize module object, don't initialize
 * this class directly but please create child class of it.
 *
 *
 * @package    Themify_Builder
 * @subpackage Themify_Builder/classes
 * @author     Themify
 */
abstract class Themify_Builder_Module {

	/**
	 * Module Name.
	 * 
	 * @access public
	 * @var string $name
	 */
	public $name;

	/**
	 * Module Slug.
	 * 
	 * @access public
	 * @var string $slug
	 */
	public $slug;

	/**
	 * Custom Post Type arguments.
	 * 
	 * @access public
	 * @var array $cpt_args
	 */
	public $cpt_args = array();

	/**
	 * Custom Post Type options
	 * 
	 * @access public
	 * @var array $cpt_options
	 */
	public $cpt_options = array();

	/**
	 * Taxonomy options.
	 * 
	 * @access public
	 * @var array $tax_options
	 */
	public $tax_options = array();

	/**
	 * Metabox options.
	 * 
	 * @access public
	 * @var array $meta_box
	 */
	public $meta_box = array();

	/**
	 * Compatibility with legacy versions of Builder, stores the array of options containing the module options
	 * 
	 * @access public
	 * @var array $_legacy_options
	 */
	public $_legacy_options = array();

	/**
	 * Flag if assets are loaded on frontend
	 */
	private $_assets_done = false;

	/**
	 * Constructor.
	 * 
	 * @access public
	 * @param array $params 
	 */
	public function __construct( $params ) {
		$this->name = $params['name'];
		$this->slug = $params['slug'];

		add_filter( 'themify_builder_addons_assets', array( $this, 'themify_builder_addons_assets' ), 10, 1 );
	}

	/**
	 * Load the script files for the frontend
	 *
	 * @todo: move this to do_assets() method
	 */
	function themify_builder_addons_assets( $assets ) {
		$_assets = $this->get_assets();
		if ( ! empty( $_assets ) ) {
			$assets[ $this->slug ] = $_assets;
		}

		return $assets;
	}

	/**
	 * Get module options.
	 * 
	 * @access public
	 */
	public function get_options() {
		if( isset( $this->_legacy_options['options'] ) ) {
			return $this->_legacy_options['options'];
		}
	}

	/**
	 * Used internally to get the Styling settings from module
	 *
	 * Uses get_styling() method and then applies set_styling_field_types() on
	 * the result to set the special styling fields
	 *
	 * @return array
	 */
	public function get_styling_settings() {
		$styling = $this->get_styling();

		if( ! empty( $styling ) ) {
			$styling = $this->set_styling_field_types( $styling );
		}

		return $styling;
	}

	function set_styling_field_types( $var ) {
		if( is_array( $var ) ) {
			foreach( $var as $key => $value ) {
				if( isset( $value['type'] ) && $value['type'] == 'tabs' ) {
					foreach( $value['tabs'] as $tab_key => $tab_value ) {
						$var[ $key ]['tabs'][ $tab_key ]['fields'] = $this->set_styling_field_types( $var[ $key ]['tabs'][ $tab_key ]['fields'] );
					}
				} elseif( isset( $value['type'] ) && $value['type'] == 'border' ) {
					$border_options = $this->border_styling( $value );
					array_splice( $var, $key, 0, $border_options );
				}
			}
		}

		return $var;
	}

	/**
	 * Generate the array for Border styling settings
	 * Used when type = "border"
	 *
	 * @return array
	 */
	function border_styling( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'separator' => true,
			'selector' => '.module',
			'id' => 'border',
			'label' => __( 'Border', 'themify' ),
		) );
		extract( $args );

		$options = array();
		if( $separator ) {
			$options[] = array(
				'type' => 'separator',
				'meta' => array('html'=>'<hr />')
			);
			$options[] = array(
				'id' => "separator_{$id}",
				'type' => 'separator',
				'meta' => array('html'=>'<h4>' . $label . '</h4>'),
			);
		}

		$options = array_merge( $options, array(
			array(
				'id' => "multi_{$id}_top",
				'type' => 'multi',
				'label' => __( 'Border', 'themify' ),
				'fields' => array(
					array(
						'id' => "{$id}_top_color",
						'type' => 'color',
						'class' => 'small',
						'prop' => 'border-top-color',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_top_width",
						'type' => 'text',
						'description' => 'px',
						'class' => 'style_border style_field xsmall',
						'prop' => 'border-top-width',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_top_style",
						'type' => 'select',
						'description' => __('top', 'themify'),
						'meta' => Themify_Builder_model::get_border_styles(),
						'prop' => 'border-top-style',
						'selector' => $selector,
						'default' => 'solid',
					),
				)
			),
			array(
				'id' => "multi_{$id}_right",
				'type' => 'multi',
				'label' => '',
				'fields' => array(
					array(
						'id' => "{$id}_right_color",
						'type' => 'color',
						'class' => 'small',
						'prop' => 'border-right-color',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_right_width",
						'type' => 'text',
						'description' => 'px',
						'class' => 'style_border style_field xsmall',
						'prop' => 'border-right-width',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_right_style",
						'type' => 'select',
						'description' => __('right', 'themify'),
						'meta' => Themify_Builder_model::get_border_styles(),
						'prop' => 'border-right-style',
						'selector' => $selector,
						'default' => 'solid',
					)
				)
			),
			array(
				'id' => 'multi_border_bottom',
				'type' => 'multi',
				'label' => '',
				'fields' => array(
					array(
						'id' => "{$id}_bottom_color",
						'type' => 'color',
						'class' => 'small',
						'prop' => 'border-bottom-color',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_bottom_width",
						'type' => 'text',
						'description' => 'px',
						'class' => 'style_border style_field xsmall',
						'prop' => 'border-bottom-width',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_bottom_style",
						'type' => 'select',
						'description' => __('bottom', 'themify'),
						'meta' => Themify_Builder_model::get_border_styles(),
						'prop' => 'border-bottom-style',
						'selector' => $selector,
						'default' => 'solid',
					)
				)
			),
			array(
				'id' => "multi_{$id}_left",
				'type' => 'multi',
				'label' => '',
				'fields' => array(
					array(
						'id' => "{$id}_left_color",
						'type' => 'color',
						'class' => 'small',
						'prop' => 'border-left-color',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_left_width",
						'type' => 'text',
						'description' => 'px',
						'class' => 'style_border style_field xsmall',
						'prop' => 'border-left-width',
						'selector' => $selector,
					),
					array(
						'id' => "{$id}_left_style",
						'type' => 'select',
						'description' => __('left', 'themify'),
						'meta' => Themify_Builder_model::get_border_styles(),
						'prop' => 'border-left-style',
						'selector' => $selector,
						'default' => 'solid',
					)
				)
			),
			// "Apply all" // apply all border
			array(
				'id' => 'checkbox_border_apply_all',
				'class' => 'style_apply_all style_apply_all_border',
				'type' => 'checkbox',
				'label' => false,
				'default' => 'border',
				'options' => array(
					array( 'name' => 'border', 'value' => __( 'Apply to all border', 'themify' ) )
				)
			)
		) );

		return $options;
	}

	/**
	 * Get module styling options.
	 * 
	 * @access public
	 */
	public function get_styling() {
		if( isset( $this->_legacy_options['styling'] ) ) {
			return $this->_legacy_options['styling'];
		}
	}

	/**
	 * Render a module, as a plain text
	 *
	 * @return string
	 */
	public function get_plain_text( $module ) {
		$options = $this->get_options();
		if( empty( $options ) )
			return '';
		$out = array();

		foreach( $options as $field ) {
			// sanitization, check for existence of needed keys
			if( ! ( isset( $field['type'] ) && isset( $field['id'] ) && isset( $module[ $field['id'] ] ) ) )
				continue;

			// text, textarea, and wp_editor field types
			if( in_array( $field['type'], array( 'text', 'textarea', 'wp_editor' ) ) ) {
				$out[] = $module[ $field['id'] ];
			}
			// builder field type
			elseif( $field['type'] === 'builder' && is_array( $module[ $field['id'] ] ) ) {
				// gather text field types included in the "builder" field type
				$text_fields = array();
				foreach( $field['options'] as $row_field ) {
					if( isset( $row_field['type'] ) && in_array( $row_field['type'], array( 'text', 'textarea', 'wp_editor' ) ) ) {
						$text_fields[] = $row_field['id'];
					}
				}
				foreach( $module[ $field['id'] ] as $row ) {
					// separate fields from the row that have text fields
					$texts = array_intersect_key( $row, array_flip( $text_fields ) );
					// add them to the output
					$out = array_merge( array_values( $texts ), $out );
				}
			}
		}

		return implode( ' ', $out );
	}

	public function render( $mod_id, $builder_id, $settings ) {
		global $ThemifyBuilder;
		return $ThemifyBuilder->retrieve_template('template-' . $this->slug . '.php', array(
			'module_ID' => $mod_id,
			'mod_name' => $this->slug,
			'builder_id' => $builder_id,
			'mod_settings' => $settings
		), '', '', false);
	}

	public function do_assets() {
		$output = '';
		if ( $this->_assets_done ) {
			return $output;
		}

		$assets = $this->get_assets();
		if ( ! empty( $assets['css'] ) ) {
			foreach( (array) $assets['css'] as $stylesheet ) {
				$ver = isset( $assets['ver'] ) ? '?ver=' . $assets['ver'] : '';
				$link_tag = "<link id='{$this->slug}-css' rel='stylesheet' href='{$stylesheet}{$ver}' type='text/css' />";
				$output .= '<script type="text/javascript">
							if( ! jQuery( "#' . $this->slug . '-css" ).length ) jQuery( "body" ).append( "' . $link_tag . '" );
							</script>';
			}
		}

		$this->_assets_done = true;

		return $output;
	}

	/**
	 * Return a list of assets required for this module on frontend
	 *
	 * Format of the array should be:
	 * array(
	 *		'css' => array(),
	 *		'js' => array(),
	 *		'external' => array(),
	 *		'selector' => '',
	 *		'ver' => '',
	 *	)
	 *
	 * @return array
	 */
	public function get_assets() {
		return array();
	}

	/**
	 * Get module styling CSS Selectors.
	 * 
	 * @access public
	 */
	public function get_css_selectors() {
		if( isset( $this->_legacy_options['styling_selector'] ) ) {
			return $this->_legacy_options['styling_selector'];
		}
	}

	/**
	 * Initialize Custom Post Type.
	 * 
	 * @access public
	 * @param array $args 
	 */
	public function initialize_cpt( $args ) {
		$this->cpt_args = $args;
		add_action( 'init', array( $this, 'load_cpt' ) );
		add_filter( 'post_updated_messages', array( $this, 'cpt_updated_messages' ) );
	}

	/**
	 * Load Custom Post Type.
	 * 
	 * @access public
	 */
	public function load_cpt() {
		global $ThemifyBuilder;

		if ( post_type_exists( $this->slug ) ) {
			// check taxonomy register
			if ( ! taxonomy_exists( $this->slug . '-category' ) ) {
				$this->register_taxonomy();
			}
		} else {
			$this->register_cpt();
			$this->register_taxonomy();
			add_filter( 'themify_do_metaboxes', array( $this, 'cpt_meta_boxes' ) );
			
			// push to themify builder class
			$ThemifyBuilder->push_post_types( $this->slug );
		}
	}

	/**
	 * Customize post type updated messages.
	 * 
	 * @access public
	 * @param $messages
	 * @return mixed
	 */
	public function cpt_updated_messages( $messages ) {
		global $post, $post_ID;
		$view = get_permalink( $post_ID );

		$messages[ $this->slug ] = array(
			0 => '',
			1 => sprintf( __('%s updated. <a href="%s">View %s</a>.', 'themify'), $this->name, esc_url( $view ), $this->name ),
			2 => __( 'Custom field updated.', 'themify' ),
			3 => __( 'Custom field deleted.', 'themify' ),
			4 => sprintf( __('%s updated.', 'themify'), $this->name ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( '%s restored to revision from %s', 'themify' ), $this->name, wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
			6 => sprintf( __('%s published.', 'themify'), $this->name ),
			7 => sprintf( __('%s saved.', 'themify'), $this->name ),
			8 => sprintf( __('%s submitted.', 'themify'), $this->name ),
			9 => sprintf( __( '%s scheduled for: <strong>%s</strong>.', 'themify' ),
				$this->name, date_i18n( __( 'M j, Y @ G:i', 'themify' ), strtotime( $post->post_date ) ) ),
			10 => sprintf( __( '%s draft updated.', 'themify' ), $this->name )
		);
		return $messages;
	}

	/**
	 * Register Post type.
	 * 
	 * @access public
	 * @param array $cpt 
	 * @return void
	 */
	public function register_cpt( $cpt = array() ) {
		$cpt = $this->cpt_args;
		$options = array(
			'labels' => array(
				'name' => $cpt['plural'],
				'singular_name' => $cpt['singular'],
				'add_new' => __( 'Add New', 'themify' ),
				'add_new_item' => sprintf(__( 'Add New %s', 'themify' ), $cpt['singular']),
				'edit_item' => sprintf(__( 'Edit %s', 'themify' ), $cpt['singular']),
				'new_item' => sprintf(__( 'New %s', 'themify' ), $cpt['singular']),
				'view_item' => sprintf(__( 'View %s', 'themify' ), $cpt['singular']),
				'search_items' => sprintf(__( 'Search %s', 'themify' ), $cpt['plural']),
				'not_found' => sprintf(__( 'No %s found', 'themify' ), $cpt['plural']),
				'not_found_in_trash' => sprintf(__( 'No %s found in Trash', 'themify' ), $cpt['plural']),
				'menu_name' => $cpt['plural']
			),
			'supports' => isset($cpt['supports'])? $cpt['supports'] : array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'),
			//'menu_position' => $position++,
			'hierarchical' => false,
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => false,
			'publicly_queryable' => true,
			'rewrite' => array( 'slug' => isset($cpt['rewrite'])? $cpt['rewrite']: strtolower($cpt['singular']) ),
			'query_var' => true,
			'can_export' => true,
			'capability_type' => 'post',
			'menu_icon' => isset( $cpt['menu_icon'] ) ? $cpt['menu_icon'] : ''
		);

		$options = wp_parse_args( $this->cpt_options, $options );

		register_post_type( $this->slug, $options );
	}

	/**
	 * Register Taxonomy.
	 * 
	 * @access public
	 * @param array $cpt 
	 * @return void
	 */
	public function register_taxonomy( $cpt = array() ) {
		global $ThemifyBuilder;

		$cpt = $this->cpt_args;
		$options = array(
			'labels' => array(
				'name' => sprintf(__( '%s Categories', 'themify' ), $cpt['singular']),
				'singular_name' => sprintf(__( '%s Category', 'themify' ), $cpt['singular']),
				'search_items' => sprintf(__( 'Search %s Categories', 'themify' ), $cpt['singular']),
				'popular_items' => sprintf(__( 'Popular %s Categories', 'themify' ), $cpt['singular']),
				'all_items' => sprintf(__( 'All Categories', 'themify' ), $cpt['singular']),
				'parent_item' => sprintf(__( 'Parent %s Category', 'themify' ), $cpt['singular']),
				'parent_item_colon' => sprintf(__( 'Parent %s Category:', 'themify' ), $cpt['singular']),
				'edit_item' => sprintf(__( 'Edit %s Category', 'themify' ), $cpt['singular']),
				'update_item' => sprintf(__( 'Update %s Category', 'themify' ), $cpt['singular']),
				'add_new_item' => sprintf(__( 'Add New %s Category', 'themify' ), $cpt['singular']),
				'new_item_name' => sprintf(__( 'New %s Category', 'themify' ), $cpt['singular']),
				'separate_items_with_commas' => sprintf(__( 'Separate %s Category with commas', 'themify' ), $cpt['singular']),
				'add_or_remove_items' => sprintf(__( 'Add or remove %s Category', 'themify' ), $cpt['singular']),
				'choose_from_most_used' => sprintf(__( 'Choose from the most used %s Category', 'themify' ), $cpt['singular']),
				'menu_name' => sprintf(__( '%s Category', 'themify' ), $cpt['singular']),
			),
			'public' => true,
			'show_in_nav_menus' => false,
			'show_ui' => true,
			'show_admin_column' => true,
			'show_tagcloud' => true,
			'hierarchical' => true,
			'rewrite' => true,
			'query_var' => true
		);
		$options = wp_parse_args( $this->tax_options, $options );

		register_taxonomy( $this->slug . '-category', array( $this->slug ), $options );
		add_filter( 'manage_edit-' . $this->slug .'-category_columns', array($ThemifyBuilder, 'taxonomy_header'), 10, 2 );
		add_filter( 'manage_'. $this->slug .'-category_custom_column', array($ThemifyBuilder, 'taxonomy_column_id'), 10, 3 );

		// admin column custom taxonomy
		add_filter( 'manage_taxonomies_for_'. $this->slug .'_columns', array( $this, 'category_columns' ) );
	}

	/**
	 * Category Columns.
	 * 
	 * @access public
	 * @param array $taxonomies 
	 * @return array
	 */
	public function category_columns( $taxonomies ) {
		$taxonomies[] = $this->slug . '-category';
		return $taxonomies;
	}

	/**
	 * If there's not an options tab in Themify Custom Panel meta box already defined for this post type, like "Portfolio Options", add one.
	 *
	 * @since 2.3.8
	 *
	 * @param array $meta_boxes
	 *
	 * @return array
	 */
	public function cpt_meta_boxes( $meta_boxes = array() ) {
		$meta_box_id = $this->slug . '-options';
		if ( ! in_array( $meta_box_id, wp_list_pluck( $meta_boxes, 'id' ) ) ) {
			$meta_boxes = array_merge( $meta_boxes, array(
				array(
					'name'	  => esc_html__( sprintf( __( '%s Options', 'themify' ), $this->cpt_args['singular'] ) ),
					'id' 	  => $meta_box_id,
					'options' => $this->meta_box,
					'pages'	  => $this->slug
				)
			));
		}
		return $meta_boxes;
	}

	/**
	 * Get Module Title.
	 * 
	 * @access public
	 * @param object $module 
	 */
	public function get_title( $module ) {
		return '';
	}

	public function print_template() {
		global $ThemifyBuilder;

		ob_start();

		/* look for template-{slug}-visual template file first, revert back to _visual_template() method
		 * if there's no template file.
		 */
		$template_file = $ThemifyBuilder->locate_template( 'template-' . $this->slug . '-visual.php' );
		if( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			$this->_visual_template();
		}

		$content_template = ob_get_clean();

		if ( empty( $content_template ) ) {
			return;
		}
		?>
		<script type="text/html" id="tmpl-builder-<?php echo $this->slug; ?>-content">
			<?php echo $content_template; ?>
		</script>
		<?php
	}

	public function print_template_form() {
		ob_start();

		$this->_form_template();

		$output = ob_get_clean();

		if ( empty( $output ) ) {
			return;
		}
		?>
		<script type="text/html" id="tmpl-builder_form_module_<?php echo esc_attr( $this->slug ); ?>">
			<?php echo $output; ?>
		</script>
		<?php
	}

	/**
	 * Template for live preview
	 *
	 * By default looks for a template-{slug}-visual.php template file
	 *
	 * @return string
	 */
	protected function _visual_template() {}

	public function get_default_settings() { return array(); }

	public function get_module_args() {
		return apply_filters( 'themify_builder_module_args', array() );
	}

	public function get_form_settings() {
		$module_form_settings = array(
			'setting' => array(
				'name' => ucfirst( $this->name ),
				'options' => apply_filters('themify_builder_module_settings_fields', $this->get_options(), $this)
			),
			'styling' => array(
				'name' => esc_html__( 'Styling', 'themify' ),
				'options' => apply_filters('themify_builder_styling_settings_fields', $this->get_styling_settings(), $this)
			)
		);

		if ( method_exists( $this, 'get_animation' ) ) {
				$module_form_settings['animation'] = array(
						'name' => esc_html__( 'Animation', 'themify' ),
						'options' => apply_filters('themify_builder_animation_settings_fields', $this->get_animation(), $this)
				);
		}
		return apply_filters( 'themify_builder_module_lightbox_form_settings', $module_form_settings, $this );
	}

	protected function _form_template() { 
		$module_form_settings = $this->get_form_settings();
	?>
	
		<form id="tfb_module_settings">

			<div id="themify_builder_lightbox_options_tab_items">
		
				<?php foreach( $module_form_settings as $setting_key => $setting ):
					if ( isset( $setting['options'] ) && count( $setting['options'] ) == 0 ) continue; ?>
					<li><a href="#themify_builder_options_<?php echo esc_attr( $setting_key ); ?>">
						<?php echo esc_attr( $setting['name'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</div>

			<div id="themify_builder_lightbox_actions_items">
				<button id="builder_submit_module_settings" class="builder_button"><?php _e('Save', 'themify') ?></button>
			</div>

			<?php foreach( $module_form_settings as $setting_key => $setting ): ?>
			
			<div id="themify_builder_options_<?php echo esc_attr( $setting_key ); ?>" class="themify_builder_options_tab_wrapper<?php echo $setting_key==='styling'?' themify_builder_style_tab':''?>"<?php echo $setting_key==='styling'?' data-module="'.$this->slug .'"':''?>>
				<div class="themify_builder_options_tab_content">
					<?php
					if ( isset( $setting['options'] ) && count( $setting['options'] ) > 0 ) {

						if ( 'setting' === $setting_key ) {
							themify_builder_module_settings_field( $setting['options'], $this->slug );
						} else {
							themify_render_styling_settings( $setting['options'] );
						}

						if ( 'styling' === $setting_key ) { ?>
							<p>
								<a href="#" class="reset-styling" data-reset="module">
									<i class="ti ti-close"></i>
									<?php _e('Reset Styling', 'themify') ?>
								</a>
							</p>
						<?php
						}
					}
					?>
				</div>
			</div>

			<?php endforeach; ?>

		</form>

	<?php
	}
}