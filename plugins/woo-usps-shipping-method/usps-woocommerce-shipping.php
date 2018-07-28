<?php
/*
	Plugin Name: USPS (BASIC) WooCommerce Shipping
	Plugin URI: https://www.xadapter.com/product/woocommerce-usps-shipping-plugin-with-print-label/
	Description: Obtain real time Shipping Rates via the USPS Shipping API.
	Version: 1.2.1
	Author: XAdapter
	WC requires at least: 2.6
	WC tested up to: 3.3
	Author URI: https://www.xadapter.com/
	https://www.usps.com/webtools/htm/Rate-Calculators-v1-5.htm
	https://www.usps.com/business/web-tools-apis/delivery-confirmation-domestic-shipping-label-api.htm
*/
//Dev Version: 2.6.7

function wf_usps_basic_pre_activation_check(){
	if ( is_plugin_active('usps-woocommerce-shipping/usps-woocommerce-shipping.php') ){
        deactivate_plugins( basename( __FILE__ ) );
		wp_die(__("Is everything fine? You already have the Premium version installed in your website. For any issues, kindly raise a ticket via <a target='_blank' href='https://support.xadapter.com/'>support.xadapter.com</a>", "wf-usps-woocommerce-shipping"), "", array('back_link' => 1 ));
	}
}

register_activation_hook( __FILE__, 'wf_usps_basic_pre_activation_check' );
if( !defined('WF_USPS_ID') ){
	define("WF_USPS_ID", "wf_shipping_usps");
}

/**
 * Check if WooCommerce is active
 */
if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {

	/**
	 * WC_USPS class
	 */
	if(! class_exists('USPS_WooCommerce_Shipping') ){ 
		class USPS_WooCommerce_Shipping {
			/**
			 * Constructor
			 */
			public function __construct() {
				add_action( 'init', array( $this, 'init' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				add_action( 'woocommerce_shipping_init', array( $this, 'shipping_init' ) );
				add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
			}

			/**
			 * Localisation
			 */
			
			public function init(){
				if ( ! class_exists( 'wf_order' ) ) {
					include_once 'includes/class-wf-legacy.php';
				}		
			}


			/**
			 * Plugin page links
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=wf_shipping_usps' ) . '">' . __( 'Settings', 'wf-usps-woocommerce-shipping' ) . '</a>',

					'<a href="https://www.xadapter.com/product/woocommerce-usps-shipping-plugin-with-print-label/" target="_blank">' . __( 'Premium Upgrade', 'wf-shipping-canada-post' ) . '</a>',

					'<a href="https://wordpress.org/support/plugin/woo-usps-shipping-method" target="_blank">' . __( 'Support', 'wf-usps-woocommerce-shipping' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}

			/**
			 * Load gateway class
			 */
			public function shipping_init() {
				include_once( 'includes/class-wf-shipping-usps.php' );
			}

			/**
			 * Add method to WC
			 */
			public function add_method( $methods ) {
				$methods[] = 'WF_Shipping_USPS';
				return $methods;
			}

			/**
			 * Enqueue scripts
			 */
			public function scripts() {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-ui-sortable' );
				wp_enqueue_script('wf_common_script',	plugins_url( '/resources/wf_common.js',	__FILE__ ), array( 'jquery' ) );
			wp_enqueue_style('wf_common_style',		plugins_url( '/resources/wf_common_style.css',	__FILE__ ));
			}
		}
		new USPS_WooCommerce_Shipping();
	}
}
