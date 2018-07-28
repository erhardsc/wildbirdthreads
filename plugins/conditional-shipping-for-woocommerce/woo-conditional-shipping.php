<?php

/*
Plugin Name: Conditional Shipping for WooCommerce
Description: Disable shipping methods based on shipping classes, weight, categories and much more.
Version:     1.0.10
Author:      Lauri Karisola / WooElements.com
Author URI:  https://wooelements.com
Text Domain: woo-conditional-shipping
Domain Path: /languages
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable Tag:  1.0.10
WC requires at least: 3.0.0
WC tested up to: 3.4.0
*/

/**
 * Prevent direct access to the script.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load plugin textdomain
 *
 * @return void
 */
add_action( 'plugins_loaded', 'woo_conditional_shipping_load_textdomain' );
function woo_conditional_shipping_load_textdomain() {
  load_plugin_textdomain( 'woo-conditional-shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

class Woo_Conditional_Shipping {
	private $allowed_classes;
	private $wc_shipping;

	function __construct() {
	}

	public function setup() {
		// WooCommerce not activated, abort
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}

		// Prevent running same actions twice if Pro version is enabled
		if ( class_exists( 'Woo_Conditional_Shipping_Pro' ) ) {
			return;
		}

		$this->wc_shipping = WC_Shipping::instance();
		$this->allowed_classes = $this->wc_shipping->get_shipping_method_class_names();

		// Process options for all shipping methods
		foreach ( array_keys( $this->allowed_classes ) as $class_id ) {
			add_filter( 'woocommerce_shipping_' . $class_id . '_instance_settings_values', array( $this, 'process_options' ), 10, 2 );
		}

		// Add fields for conditions
		add_filter( 'woocommerce_shipping_zone_shipping_methods', array( $this, 'add_fields_modal' ), 10, 4 );
		add_filter( 'woocommerce_settings_shipping', array( $this, 'add_fields' ), 20, 0 );

		// Exclude shipping methods
		add_filter( 'woocommerce_package_rates', array( $this, 'exclude_shipping_methods' ), 10, 2 );

		// Add admin JS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Add AJAX page for searching products
		add_action( 'wp_ajax_wcs_product_search', array( $this, 'product_ajax_search' ) );

		// Go Pro settings link
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_settings_link' ) );

		// Show save button for shipping methods created with WooCommerce Services
		add_action( 'wc_connect_service_admin_options', array( $this, 'enable_save_button' ), 10, 0 );
	}

	/**
	 * Add settings link to the plugins page.
	 */
	public function add_settings_link( $links ) {
		$link = '<span style="font-weight:bold;"><a href="https://wooelements.com/products/conditional-shipping" style="color:#46b450;" target="_blank">' . __( 'Go Pro' ) . '</a></span>';

		return array_merge( array( $link ), $links );
	}

	/**
	 * Add admin JS
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_script( 'woo_conditional_shipping_js', plugin_dir_url( __FILE__ ) . '/admin/js/woo-conditional-shipping.js', array( 'jquery', 'wp-util' ) );
		wp_enqueue_style( 'woo_conditional_shipping_css', plugin_dir_url( __FILE__ ) . '/admin/css/woo-conditional-shipping.css' );
	}

	/**
 	* Enable save button for shipping methods done with WooCommerce Services
 	 */
	public function enable_save_button() {
		global $hide_save_button;
		$hide_save_button = false;
	}

	/**
	 * Process conditions when saving a shipping method
	 */
	public function process_options( $instance_settings, $method ) {
		$instance_settings['wcs_conditions'] = array();

		$post_data = $method->get_post_data();

		$settings_prefix = $method->plugin_id . $method->id;

		if ( isset( $post_data[ $settings_prefix . '_wcs_condition_ids' ] ) ) {
			foreach ( $post_data[ $settings_prefix . '_wcs_condition_ids' ] as $key ) {
				$instance_settings['wcs_conditions'][] = array(
					'type' => $this->get_field_value( $method, $key, 'wcs_type' ),
					'value' => $this->get_field_value( $method, $key, 'wcs_value' ),
					'product_ids' => $this->get_field_value( $method, $key, 'wcs_value_product_ids' ),
				);
			}
		}

		return $instance_settings;
	}

	/**
	 * Get field value from post data
	 */
	private function get_field_value( $method, $key, $index ) {
		$post_data = $method->get_post_data();
		$settings_prefix = $method->plugin_id . $method->id;
		$settings_key = "{$settings_prefix}_{$index}_{$key}";

		return isset( $post_data[$settings_key] ) ? $post_data[$settings_key] : NULL;
	}

	/**
	 * Get a grouped list of filters
	 */
	public function filter_groups() {
		return array(
			array(
				'title' => __( 'Measurements', 'woo-conditional-shipping' ),
				'filters' => array(
					'min_weight' => sprintf( __( 'Minimum Weight (%s)', 'woo-conditional-shipping' ), get_option( 'woocommerce_weight_unit' ) ),
					'max_weight' => sprintf( __( 'Maximum Weight (%s)', 'woo-conditional-shipping' ), get_option( 'woocommerce_weight_unit' ) ),
					'max_height' => sprintf( __( 'Maximum Total Height (%s)', 'woo-conditional-shipping' ), get_option( 'woocommerce_dimension_unit' ) ),
					'max_length' => sprintf( __( 'Maximum Total Length (%s)', 'woo-conditional-shipping' ), get_option( 'woocommerce_dimension_unit' ) ),
					'max_width' => sprintf( __( 'Maximum Total Width (%s)', 'woo-conditional-shipping' ), get_option( 'woocommerce_dimension_unit' ) ),
					'min_volume' => sprintf( __( 'Minimum Total Volume (%s&sup3;)', 'woo-conditional-shipping' ), get_option( 'woocommerce_dimension_unit' ) ),
					'max_volume' => sprintf( __( 'Maximum Total Volume (%s&sup3;)', 'woo-conditional-shipping' ), get_option( 'woocommerce_dimension_unit' ) ),
				)
			),
			array(
				'title' => __( 'Order Totals', 'woo-conditional-shipping' ),
				'filters' => array(
					'min_subtotal' => __( 'Minimum Subtotal', 'woo-conditional-shipping' ),
					'max_subtotal' => __( 'Maximum Subtotal', 'woo-conditional-shipping' ),
				)
			),
			array(
				'title' => __( 'Products', 'woo-conditional-shipping' ),
				'filters' => array(
					'product_include' => __( 'Required Products', 'woo-conditional-shipping' ),
					'product_exclude' => __( 'Excluded Products', 'woo-conditional-shipping' ),
					'product_exclusive' => __( 'Exclusive Products', 'woo-conditional-shipping' ),
				)
			),
		);
	}

	/**
	 * Add fields to a shipping method settings in a modal
	 */
	public function add_fields_modal( $methods, $raw_methods, $allowed_classes, $wc_shipping_zone ) {
		foreach ( $methods as $instance_id => $method ) {
			if ( $method->has_settings ) {
				// Do not add settings to the modal if there are no other settings. Plugins
				// like USPS only show settings in a separate window.
				if ( ! empty ( $method->settings_html ) ) {
					$methods[$instance_id]->settings_html .= $this->generate_settings_html( $method );
				}
			}
		}

		return $methods;
	}

	/**
	 * Add fields to a shipping method settings in a separate page
	 */
	public function add_fields() {
		if ( isset( $_REQUEST['instance_id'] ) && ! empty( $_REQUEST['instance_id'] ) ) {
			$instance_id = absint( $_REQUEST['instance_id'] );
			$zone = WC_Shipping_Zones::get_zone_by( 'instance_id', $instance_id );
			$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

			if ( ! $shipping_method || ! $zone || ! $shipping_method->has_settings() ) {
				return;
			}

			echo $this->generate_settings_html( $shipping_method );
		}
	}

	/**
	 * Generate settings HTML for conditions
	 */
	public function generate_settings_html( $method ) {
		$output = '';
		$output .= $this->generate_title_html( __( 'Conditions', 'woo-conditional-shipping' ) );
		$output .= $this->generate_table_html( $method );

		return $output;
	}

	/**
	 * Generate settings title
	 */
	private function generate_title_html( $title ) {
		ob_start();
		?>
			<h3 class="wc-settings-sub-title"><?php echo wp_kses_post( $title ); ?></h3>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate table HTML
	 */
	private function generate_table_html( $method ) {
		if ( method_exists( $method, 'init_instance_settings' ) && empty( $method->instance_settings ) ) {
			$method->init_instance_settings();
		}

		$conditions = array();
		if ( isset( $method->instance_settings['wcs_conditions'] ) ) {
			$conditions = $method->instance_settings['wcs_conditions'];
		}

		// Products in condition fields
		// Needed for showing titles in select2 fields
		$products = $this->load_products_for_method( $method );

		return '<table class="form-table wcs-conditions-table" data-instance-id="' . $method->instance_id .'" data-selected-products="' . htmlspecialchars( json_encode( $products ), ENT_QUOTES, 'UTF-8' ) . '" data-conditions="' . htmlspecialchars( json_encode( $conditions ), ENT_QUOTES, 'UTF-8' ) . '"><tbody>' . $this->generate_rows_html( $method ) . '</tbody>' . $this->generate_tfoot_html() . '</table>';
	}

	/**
	 * Generate table rows HTML
	 */
	private function generate_rows_html( $method ) {
		ob_start();
		?>
		<?php $this->_conditions_row_template( $method ); ?>
		<?php

		return ob_get_clean();
	}

	/**
	 * Template for conditions row
	 */
	private function _conditions_row_template( $method ) {
	?>
		<script type="text/html" id="tmpl-wcs_row_template_<?php echo $method->instance_id; ?>">
			<tr valign="top" class="condition_row">
				<th class="condition_remove">
					<input type="checkbox" class="remove_condition">
				</th>
				<th scope="row" class="titledesc">
					<fieldset>
						<input type="hidden" name="<?php echo $this->get_field_key( $method, 'wcs_condition_ids'); ?>[]" value="{{ data.index }}" />
						<select name="<?php echo $this->get_field_key( $method, 'wcs_type' ); ?>_{{data.index}}" class="wcs_condition_type_select">
							<?php foreach ( $this->filter_groups() as $filter_group ) { ?>
								<optgroup label="<?php echo $filter_group['title']; ?>">
									<?php foreach ( $filter_group['filters'] as $key => $title ) { ?>
										<option value="<?php echo $key; ?>" <# if ( data.type == '<?php echo $key; ?>' ) { #>selected<# } #>><?php echo $title; ?></option>
									<?php } ?>
								</optgroup>
							<?php } ?>
						</select>
					</fieldset>
				</th>
				<td class="forminp">
					<fieldset class="wcs_condition_value_inputs">
						<input class="input-text value_input regular-input wcs_text_value_input" type="text" name="<?php echo $this->get_field_key( $method, 'wcs_value' ); ?>_{{data.index}}" value="{{data.value}}" />

						<div class="value_input wcs_product_value_input">
							<select class="wcs_product_value_ajax_input" name="<?php echo $this->get_field_key( $method, 'wcs_value_product_ids' ); ?>_{{data.index}}[]" class="select" multiple>
								<# if ( data.selected_products && data.selected_products.length > 0 ) { #>
									<# _.each(data.selected_products, function(product) { #>
										<option value="{{ product['id'] }}" selected>{{ product['title'] }}</option>
									<# }) #>
								<# } #>
							</select>
						</div>
					</fieldset>
				</td>
			</tr>
		</script>
	<?php
	}

	/**
 	* Get field key for a shipping method
 	*/
	private function get_field_key( $method, $key ) {
		return $method->plugin_id . $method->id . '_' . $key;
	}

	/**
	 * Generate table foot HTML
	 */
	public function generate_tfoot_html() {
		ob_start();
		?>
		<tfoot>
			<tr valign="top">
				<td colspan="2" class="forminp">
					<button type="button" class="button" id="wcs-add-condition"><?php _e( 'Add Condition', 'woo-conditional-shipping' ); ?></button>
					<button type="button" class="button" id="wcs-remove-conditions"><?php _e( 'Remove Selected', 'woo-conditional-shipping' ); ?></button>
				</td>
			</tr>
		</tfoot>
		<?php

		return ob_get_clean();
	}

	/**
	 * Load all products in the conditions for a method
	 */
	private function load_products_for_method( $method ) {
		$product_ids = array();

		if ( isset( $method->instance_settings ) ) {
			if ( isset( $method->instance_settings['wcs_conditions'] ) ) {
				foreach ( $method->instance_settings['wcs_conditions'] as $condition ) {
					if ( is_array( $condition['product_ids'] ) ) {
						$product_ids = array_merge( $product_ids, $condition['product_ids'] );
					}
				}
			}
		}

		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product = get_post( $product_id );
			if ( $product ) {
				$products[$product_id] = $product->post_title;
			}
		}

		return $products;
	}

	/**
	 * AJAX search for products
	 */
	public function product_ajax_search() {
		if ( ! is_admin() ) {
			wp_die( __( 'Access denied', 'woo-conditional-shipping' ) );
		}

		if ( empty( $_GET['q'] ) ) {
			echo json_encode(array());
			wp_die();
		}

		$args = array(
			'post_type' => array( 'product', 'product_variation' ),
			'posts_per_page' => 10,
			'offset' => 0,
			's' => $_GET['q'],
			'orderby' => 'title',
			'order' => 'ASC'
		);
		$query = new WP_Query( $args );

		$results = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$id = get_the_ID();
				$title = get_the_title();

				$results[] = array(
					'id' => $id,
					'text' => html_entity_decode( $title )
				);
			}
		}

		$output = array(
			'results' => $results
		);

		echo json_encode($output);

		wp_die();
	}

	/**
	 * Filter shipping classes in the checkout
	 */
	public function exclude_shipping_methods( $rates, $package ) {
		foreach( $rates as $key => $rate ) {
			if ( ! is_object( $rate ) || ! isset( $rate->method_id ) ) {
				continue;
			}

			// Get instance ID
			$instance_id = FALSE;
			if ( method_exists( $rate, 'get_instance_id' ) && strlen( strval( $rate->get_instance_id() ) ) > 0 ) {
				$instance_id = $rate->get_instance_id();
			} else {
				if ( $rate->method_id == 'oik_weight_zone_shipping' ) {
					$ids = explode( '_', $rate->id );
					$instance_id = end( $ids );
				} else {
					$ids = explode( ':', $rate->id );
					if ( count($ids) >= 2 ) {
						$instance_id = $ids[1];
					}
				}
			}

			$instance_id = strval( $instance_id );
			if ( $instance_id === FALSE || ! ctype_digit( $instance_id ) ) {
				continue;
			}

			$class_name = $this->allowed_classes[$rate->method_id];

			if ( ! is_object( $class_name ) && ! class_exists( $class_name ) ) {
				continue;
			}

			// Some 3rd party shipping methods such as WooCommerce Services provides object
			// directly instead of class name
			if ( is_object( $class_name ) ) {
				$method = $class_name;
			} else {
				$method = new $class_name($instance_id);
			}

			$instance_settings = isset( $method->instance_settings ) ? $method->instance_settings : array();
			if ( ( ! isset( $method->instance_settings ) || empty( $method->instance_settings ) ) && method_exists( $method, 'init_instance_settings' ) ) {
				$method->init_instance_settings();
				$instance_settings = $method->instance_settings;
			}

			// Fix for WooCommerce Services
			if ( strpos( $method->id, 'wc_services' ) !== FALSE && empty( $instance_settings ) && ! empty( $instance_id ) ) {
				$option_key = $method->plugin_id . $method->id . '_' . $instance_id . '_settings';
				$instance_settings = get_option( $option_key, array() );
 			}

			if ( isset( $instance_settings['wcs_conditions'] ) && is_array( $instance_settings['wcs_conditions'] ) ) {
				foreach ( $instance_settings['wcs_conditions'] as $index => $condition ) {
					if ( isset( $condition['type'] ) && ! empty( $condition['type'] ) && method_exists( $this, "_filter_{$condition['type']}" ) ) {
						if ( call_user_func( array( $this, "_filter_{$condition['type']}" ), $condition, $package ) ) {
							unset( $rates[$key] );
						}
					}
				}
			}
		}

		return $rates;
	}

	/**
	 * Parse string number into float
	 */
	private function _parse_number($number) {
		$number = str_replace( ',', '.', $number );

		if ( is_numeric( $number ) ) {
			return floatval( $number );
		}

		return FALSE;
	}

	/**
	 * Filter by cart maximum weight
	 */
	private function _filter_max_weight( $condition, $package ) {
		$weight = $this->calculate_package_weight( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_weight = $this->_parse_number( $condition['value'] );
			if ( $max_weight !== FALSE && $max_weight > 0 && $weight > $max_weight ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by cart minimum weight
	 */
	private function _filter_min_weight( $condition, $package ) {
		$weight = $this->calculate_package_weight( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$min_weight = $this->_parse_number( $condition['value'] );
			if ( $min_weight !== FALSE && $min_weight > 0 && $weight < $min_weight ) {
				return TRUE;
			}
		}
	}

	/**
	 * Filter by cart maximum height
	 */
	private function _filter_max_height( $condition, $package ) {
		$height = $this->calculate_package_height( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_height = $this->_parse_number( $condition['value'] );
			if ( $max_height !== FALSE && $max_height > 0 && $height > $max_height ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by cart maximum length
	 */
	private function _filter_max_length( $condition, $package ) {
		$length = $this->calculate_package_length( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_length = $this->_parse_number( $condition['value'] );
			if ( $max_length !== FALSE && $max_length > 0 && $length > $max_length ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by cart maximum width
	 */
	private function _filter_max_width( $condition, $package ) {
		$width = $this->calculate_package_width( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_width = $this->_parse_number( $condition['value'] );
			if ( $max_width !== FALSE && $max_width > 0 && $width > $max_width ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by cart minimum volume
	 */
	private function _filter_min_volume( $condition, $package ) {
		$volume = $this->calculate_package_volume( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$min_volume = $this->_parse_number( $condition['value'] );
			if ( $min_volume !== FALSE && $min_volume > 0 && $volume < $min_volume ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by cart maximum volume
	 */
	private function _filter_max_volume( $condition, $package ) {
		$volume = $this->calculate_package_volume( $package );

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_volume = $this->_parse_number( $condition['value'] );
			if ( $max_volume !== FALSE && $max_volume > 0 && $volume > $max_volume ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by maximum subtotal
	 */
	private function _filter_max_subtotal( $condition, $package ) {
		$subtotal = WC()->cart->subtotal;

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_subtotal = $this->_parse_number( $condition['value'] );
			if ( $max_subtotal !== FALSE && $max_subtotal > 0 && $subtotal > $max_subtotal ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Filter by cart minimum subtotal
	 */
	private function _filter_min_subtotal( $condition, $package ) {
		$subtotal = WC()->cart->subtotal;

		if ( isset( $condition['value'] ) && ! empty( $condition['value'] ) ) {
			$max_subtotal = $this->_parse_number( $condition['value'] );
			if ( $max_subtotal !== FALSE && $max_subtotal > 0 && $subtotal < $max_subtotal ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Calculate cart weight
	 */
	private function calculate_package_weight($package) {
		$total_weight = 0;

		foreach ( $package['contents'] as $key => $data ) {
			$product = $data['data'];

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$item_weight = $product->get_weight();

			if ( $item_weight ) {
				$total_weight += $item_weight * $data['quantity'];
			}
		}

		return $total_weight;
	}

	/**
	 * Calculate cart volume
	 */
	private function calculate_package_volume($package) {
		$total_volume = 0;

		foreach ( $package['contents'] as $key => $data ) {
			$product = $data['data'];

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$length = $product->get_length();
			$width = $product->get_width();
			$height = $product->get_height();

			if ( is_numeric ( $length ) && is_numeric( $width ) && is_numeric( $height ) ) {
				$volume = $length * $width * $height;
				$total_volume += $volume * $data['quantity'];
			}
		}

		return $total_volume;
	}

	/**
	 * Calculate cart height
	 */
	private function calculate_package_height($package) {
		$total = 0;

		foreach ( $package['contents'] as $key => $data ) {
			$product = $data['data'];

			if ( ! $product->needs_shipping() || ! $product->has_dimensions() ) {
				continue;
			}

			$item_height = $product->get_height();

			if ( $item_height ) {
				$total += floatval( $item_height ) * $data['quantity'];
			}
		}

		return $total;
	}

	/**
	 * Filter by excluded products
	 *
	 * If cart contains excluded products, the shipping method
	 * won't be available in the checkout.
	 */
	private function _filter_product_exclude( $condition, $package ) {
		if ( isset( $condition['product_ids'] ) && ! empty( $condition['product_ids'] ) ) {
			$product_ids = $condition['product_ids'];
			$product_ids = $this->merge_product_children_ids( $product_ids );

			foreach ( $package['contents'] as $key => $item ) {
				$product = $item['data'];
				$product_id = $product->get_id();

				if ( in_array( $product_id, $product_ids ) ) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Filter by included products
	 *
	 * At least one product must be included in the cart for the shipping
	 * method to be available in the checkout.
	 */
	private function _filter_product_include( $condition, $package ) {
		if ( isset( $condition['product_ids'] ) && ! empty( $condition['product_ids'] ) ) {
			$product_ids = $condition['product_ids'];
			$product_ids = $this->merge_product_children_ids( $product_ids );

			foreach ( $package['contents'] as $key => $item ) {
				$product = $item['data'];
				$product_id = $product->get_id();

				if ( in_array( $product_id, $product_ids ) ) {
					return FALSE;
				}
			}

			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Filter by exclusive products
	 *
	 * All products in the cart must be in exlusive products for the
	 * shipping method to be available in the checkout.
	 */
	private function _filter_product_exclusive( $condition, $package ) {
		if ( isset( $condition['product_ids'] ) && ! empty( $condition['product_ids'] ) ) {
			$product_ids = $condition['product_ids'];
			$product_ids = $this->merge_product_children_ids( $product_ids );

			foreach ( $package['contents'] as $key => $item ) {
				$product = $item['data'];
				$product_id = $product->get_id();

				if ( ! in_array( $product_id, $product_ids ) ) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Merge children IDs for parent product IDs
	 */
	private function merge_product_children_ids( $product_ids ) {
		$args = array(
			'post_type' => array( 'product_variation' ),
			'post_parent__in' => $product_ids,
			'fields' => 'ids'
		);
		$query = new WP_Query( $args );

		$children_ids = $query->posts;

		return array_merge( $children_ids, $product_ids );
	}

	/**
	 * Calculate cart length
	 */
	private function calculate_package_length($package) {
		$total = 0;

		foreach ( $package['contents'] as $key => $data ) {
			$product = $data['data'];

			if ( ! $product->needs_shipping() || ! $product->has_dimensions() ) {
				continue;
			}

			$length = $product->get_length();

			if ( $length ) {
				$total += floatval( $length ) * $data['quantity'];
			}
		}

		return $total;
	}

	/**
	 * Calculate cart width
	 */
	private function calculate_package_width($package) {
		$total = 0;

		foreach ( $package['contents'] as $key => $data ) {
			$product = $data['data'];

			if ( ! $product->needs_shipping() || ! $product->has_dimensions() ) {
				continue;
			}

			$width = $product->get_width();

			if ( $width ) {
				$total += floatval( $width ) * $data['quantity'];
			}
		}

		return $total;
	}
}

function init_woo_conditional_shipping() {
	$woo_conditional_shipping = new Woo_Conditional_Shipping();
	$woo_conditional_shipping->setup();
}

add_action( 'init', 'init_woo_conditional_shipping', 110 );
