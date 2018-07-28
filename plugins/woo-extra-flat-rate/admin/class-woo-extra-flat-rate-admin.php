<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.multidots.com
 * @since      1.0.0
 *
 * @package    Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro
 * @subpackage Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro
 * @subpackage Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro/admin
 * @author     Multidots <inquiry@multidots.in>
 */
class Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function afrsm_free_enqueue_styles() {
        global $post;
        /* @var $_GET type */
        if (isset($_GET['page']) && !empty($_GET['page']) && ($_GET['page'] == 'afrsm-free-list' || $_GET['page'] == 'afrsm-free-add-shipping' || $_GET['page'] == 'afrsm-free-get-started' || $_GET['page'] == 'afrsm-free-information' || $_GET['page'] == 'afrsm-pro-details' || $_GET['page'] == 'afrsm-free-edit-shipping')) {
            wp_enqueue_style($this->plugin_name . '-choose-css', plugin_dir_url(__FILE__) . 'css/chosen.min.css', array(), $this->version, 'all');
            wp_enqueue_style($this->plugin_name . '-jquery-ui-css', plugin_dir_url(__FILE__) . 'css/jquery-ui.min.css', array(), $this->version, 'all');
            wp_enqueue_style($this->plugin_name . 'font-awesome', plugin_dir_url(__FILE__) . 'css/font-awesome.min.css', array(), $this->version, 'all');
            wp_enqueue_style($this->plugin_name . 'main-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), 'all');
            wp_enqueue_style($this->plugin_name . 'media-css', plugin_dir_url(__FILE__) . 'css/media.css', array(), 'all');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function afrsm_free_enqueue_scripts() {
        global $post;
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-accordion');
        if (isset($_GET['page']) && !empty($_GET['page']) && ($_GET['page'] == 'afrsm-free-list' || $_GET['page'] == 'afrsm-free-add-shipping' || $_GET['page'] == 'afrsm-free-get-started' || $_GET['page'] == 'afrsm-free-information' || $_GET['page'] == 'afrsm-pro-details' || $_GET['page'] == 'afrsm-free-edit-shipping')) {
            wp_enqueue_script($this->plugin_name . '-choose-js', plugin_dir_url(__FILE__) . 'js/chosen.jquery.min.js', array('jquery', 'jquery-ui-datepicker'), $this->version, false);
            wp_enqueue_script($this->plugin_name . '-tablesorter-js', plugin_dir_url(__FILE__) . 'js/jquery.tablesorter.js', array('jquery'), $this->version, false);
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/woo-extra-flat-rate-admin.js', array('jquery', 'jquery-ui-dialog', 'jquery-ui-accordion', 'jquery-ui-sortable'), $this->version, false);
            wp_localize_script($this->plugin_name, 'coditional_vars', array('plugin_url' => plugin_dir_url(__FILE__)));
        }
    }
    
    /*
     * Shipping method Pro Menu
     * 
     * @since 3.0.0
     */
    public function dot_store_menu_shipping_method_pro() {
        global $GLOBALS;
        if (empty($GLOBALS['admin_page_hooks']['dots_store'])) {
            add_menu_page('DotStore Plugins', __('DotStore Plugins'), 'null', 'dots_store', array($this, 'dot_store_menu_page'), AFRSM_PLUGIN_URL . 'admin/images/menu-icon.png', 25);
        }
        
        add_submenu_page('dots_store', 'Advanced Flat Rate Shipping For WooCommerce', 'Advanced Flat Rate Shipping For WooCommerce', 'manage_options', 'afrsm-free-list', array($this, 'afrsm_free_fee_list_page'));
        add_submenu_page('dots_store', 'Add Shipping Method', 'Add Shipping Method', 'manage_options', 'afrsm-free-add-shipping', array($this, 'afrsm_free_add_new_fee_page'));
        add_submenu_page('dots_store', 'Edit Shipping Method', 'Edit Shipping Method', 'manage_options', 'afrsm-free-edit-shipping', array($this, 'afrsm_free_edit_fee_page'));
        add_submenu_page('dots_store', 'Getting Started', 'Getting Started', 'manage_options', 'afrsm-free-get-started', array($this, 'afrsm_free_get_started_page'));
        add_submenu_page('dots_store', 'Quick info', 'Quick info', 'manage_options', 'afrsm-free-information', array($this, 'afrsm_free_information_page'));
        add_submenu_page('dots_store', 'Premium Version', 'Premium Version', 'manage_options', 'afrsm-pro-details', array($this, 'afrsm_pro_details_page'));
    }

    public function afrsm_free_fee_list_page() {
        require_once('partials/afrsm-free-list-page.php');
    }

    public function afrsm_free_add_new_fee_page() {
        require_once('partials/afrsm-free-add-new-page.php');
    }

    public function afrsm_free_edit_fee_page() {
        require_once('partials/afrsm-free-add-new-page.php');
    }

    public function afrsm_free_get_started_page() {
        require_once('partials/afrsm-free-get-started-page.php');
    }
    
    public function afrsm_free_information_page() {
        require_once('partials/afrsm-free-information-page.php');
    }
    
    public function afrsm_pro_details_page() {
        require_once('partials/afrsm-pro-details-page.php');
    }
    
    /**
     * Function for redirect to shipping list
     */
    public function afrsm_free_redirect_shipping_function() {
        $afrsm_menu_url = admin_url('/admin.php?page=afrsm-free-list');
        if (!empty($_GET['section']) && $_GET['section'] == 'advanced_flat_rate_shipping') {
            wp_redirect($afrsm_menu_url);
            exit;
        }
    }
    
    /**
     * Function for redirect to welcome screen to activation
     */
    public function afrsm_free_welcome_shipping_method_screen_do_activation_redirect() {
        // if no activation redirect
        if (!get_transient('_welcome_screen_afrsm_free_mode_activation_redirect_data')) {
            return;
        }

        // Delete the redirect transient
        delete_transient('_welcome_screen_afrsm_free_mode_activation_redirect_data');

        // if activating from network, or bulk
        if (is_network_admin() || isset($_GET['activate-multi'])) {
            return;
        }
        // Redirect to extra cost welcome  page
        wp_safe_redirect(add_query_arg(array('page' => 'afrsm-free-get-started'), admin_url('admin.php')));
    }
    
    /**
     * Set Active menu 
     */
    public function afrsm_free_active_menu() {
        $screen = get_current_screen();
        
        //DotStore Menu Submenu based conditions
        if ( !empty($screen) && ($screen->id == 'dotstore-plugins_page_afrsm-free-add-shipping' || $screen->id == 'dotstore-plugins_page_afrsm-free-edit-shipping' ||
                $screen->id == 'dotstore-plugins_page_afrsm-free-get-started' || $screen->id == 'dotstore-plugins_page_afrsm-free-information' ||
                $screen->id == 'dotstore-plugins_page_afrsm-pro-details' ) ) {
    ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('a[href="admin.php?page=afrsm-free-list"]').parent().addClass('current');
                    $('a[href="admin.php?page=afrsm-free-list"]').addClass('current');
                });
            </script>
    <?php
        }
        //DotStore shipping tabs based conditions
        if (!empty($screen) && $screen->id == 'woocommerce_page_wc-settings') {
            
            $request_page       = isset($_REQUEST['page']) && !empty($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $request_tab        = isset($_REQUEST['tab']) && !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : '';
            $request_section    = isset($_REQUEST['section']) && !empty($_REQUEST['section']) ? $_REQUEST['section'] : '';
            
            if ($request_page == 'wc-settings' && $request_tab == 'shipping' && ($request_section == 'advanced_flat_rate_shipping' || $request_section == 'forceall' ) ) {
        ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    $('#toplevel_page_woocommerce').removeClass('wp-menu-open wp-has-current-submenu').addClass('wp-not-current-submenu');
                    $('#toplevel_page_woocommerce > ul li a').removeClass('current');
                    $('#toplevel_page_woocommerce > ul li').removeClass('current');
                    $('#toplevel_page_dots_store').addClass('wp-has-current-submenu').removeClass('wp-not-current-submenu');
                    $('#toplevel_page_dots_store a.toplevel_page_dots_store').addClass('wp-has-current-submenu').removeClass('wp-not-current-submenu');
                    $('a[href="admin.php?page=afrsm-free-list"]').parent().addClass('current');
                    $('a[href="admin.php?page=afrsm-free-list"]').addClass('current');
                });
            </script>
        <?php
            }
        }
    }

    public function afrsm_free_remove_admin_submenus() {
        remove_submenu_page('dots_store', 'afrsm-free-add-shipping');
        remove_submenu_page('dots_store', 'afrsm-free-edit-shipping');
        remove_submenu_page('dots_store', 'afrsm-free-get-started');
        remove_submenu_page('dots_store', 'afrsm-free-information');
        remove_submenu_page('dots_store', 'afrsm-pro-details');
    }
    
    /*
     * Is Available
     */
    public function afrsm_free_condition_match_rules($sm_post_data = array(), $package = array()) {
        $final_condition_flag = 0;
        global $woocommerce, $sitepress;

        if (!empty($sitepress)) {
            $default_lang = $sitepress->get_default_language();
        }

        $is_passed = array();
        $cart_array = $woocommerce->cart->get_cart();
        
        $sm_status          = get_post_meta($sm_post_data->ID, 'sm_status', true);
        $get_condition_array = get_post_meta($sm_post_data->ID, 'sm_metabox', true);
        
        if (isset($sm_status) && $sm_status == 'off') {
            return false;
        }
        
        if (!empty($get_condition_array)) {

            $country_array = array();
            $product_array = array();
            $category_array = array();
            $cart_total_array = array();
            
            foreach ($get_condition_array as $key => $value) {
                
                if (array_search('country', $value)) {
                    $country_array[$key] = $value;
                }
                if (array_search('product', $value)) {
                    $product_array[$key] = $value;
                }
                if (array_search('category', $value)) {
                    $category_array[$key] = $value;
                }
                if (array_search('cart_total', $value)) {
                    $cart_total_array[$key] = $value;
                }
                
                //Check if is country exist
                if (is_array($country_array) && isset($country_array) && !empty($country_array) && !empty($cart_array)) {
                    $selected_country = $woocommerce->customer->get_shipping_country();
                    $is_passed['has_fee_based_on_country'] = '';
                    $passed_country = array();
                    $notpassed_country = array();
                    foreach ($country_array as $country) {
                        if ($country['product_fees_conditions_is'] == 'is_equal_to') {
                            if (!empty($country['product_fees_conditions_values'])) {
                                foreach ($country['product_fees_conditions_values'] as $country_id) {
                                    $passed_country[] = $country_id;
                                    if ($country_id == $selected_country) {
                                        $is_passed['has_fee_based_on_country'] = 'yes';
                                    }
                                }
                            }
                            if (empty($country['product_fees_conditions_values'])) {
                                $is_passed['has_fee_based_on_country'] = 'yes';
                            }
                        }
                        if ($country['product_fees_conditions_is'] == 'not_in') {
                            if (!empty($country['product_fees_conditions_values'])) {
                                foreach ($country['product_fees_conditions_values'] as $country_id) {
                                    $notpassed_country[] = $country_id;
                                    if ($country_id == 'all' || $country_id == $selected_country) {
                                        $is_passed['has_fee_based_on_country'] = 'no';
                                    }
                                }
                            }
                        }
                    }
                    if (empty($is_passed['has_fee_based_on_country']) && empty($passed_country)) {
                        $is_passed['has_fee_based_on_country'] = 'yes';
                    } elseif (empty($is_passed['has_fee_based_on_country']) && !empty($passed_country)) {
                        $is_passed['has_fee_based_on_country'] = 'no';
                    }
                }

                //Check if is product exist
                if (is_array($product_array) && isset($product_array) && !empty($product_array) && !empty($cart_array)) {

                    $cart_products_array = array();
                    $cart_product = $this->fee_array_column($cart_array, 'product_id');

                    if (isset($cart_product) && !empty($cart_product)) {
                        foreach ($cart_product as $key => $cart_product_id) {
                            if (!empty($sitepress)) {
                                $cart_products_array[] = apply_filters('wpml_object_id', $cart_product_id, 'product', TRUE, $default_lang);
                            } else {
                                $cart_products_array[] = $cart_product_id;
                            }
                        }
                    }

                    $is_passed['has_fee_based_on_product'] = '';
                    $passed_product = array();
                    $notpassed_product = array();
                    foreach ($product_array as $product) {
                        if ($product['product_fees_conditions_is'] == 'is_equal_to') {
                            if (!empty($product['product_fees_conditions_values'])) {
                                foreach ($product['product_fees_conditions_values'] as $product_id) {

                                    $passed_product[] = $product_id;
                                    if (in_array($product_id, $cart_products_array)) {
                                        $is_passed['has_fee_based_on_product'] = 'yes';
                                    }
                                }
                            }
                        }
                        if ($product['product_fees_conditions_is'] == 'not_in') {
                            if (!empty($product['product_fees_conditions_values'])) {
                                foreach ($product['product_fees_conditions_values'] as $product_id) {
                                    $notpassed_product = $product_id;
                                    if (in_array($product_id, $cart_product)) {
                                        $is_passed['has_fee_based_on_product'] = 'no';
                                    }
                                }
                            }
                        }
                    }
                    if (empty($is_passed['has_fee_based_on_product']) && empty($passed_product)) {
                        $is_passed['has_fee_based_on_product'] = 'yes';
                    } elseif (empty($is_passed['has_fee_based_on_product']) && !empty($passed_product)) {
                        $is_passed['has_fee_based_on_product'] = 'no';
                    }
                }

                //Check if is Category exist
                if (is_array($category_array) && isset($category_array) && !empty($category_array) && !empty($cart_array)) {
                    $cart_product = $this->fee_array_column($cart_array, 'product_id');
                    $cart_category_id_array = array();
                    $cart_products_array = array();

                    if (isset($cart_product) && !empty($cart_product)) {
                        foreach ($cart_product as $key => $cart_product_id) {
                            if (!empty($sitepress)) {
                                $cart_products_array[] = apply_filters('wpml_object_id', $cart_product_id, 'product', TRUE, $default_lang);
                            } else {
                                $cart_products_array[] = $cart_product_id;
                            }
                        }
                    }

                    if (!empty($cart_products_array)) {
                        foreach ($cart_products_array as $product) {
                            $product_array = new WC_Product($product);
                            if ( !( $product_array->is_virtual('yes') ) ) {
                                $cart_product_category = wp_get_post_terms($product, 'product_cat', array('fields' => 'ids'));
                                if (isset($cart_product_category) && !empty($cart_product_category) && is_array($cart_product_category)) {
                                    $cart_category_id_array[] = $cart_product_category;
                                }
                            }
                        }
                        $get_cat_all = array_unique($this->array_flatten($cart_category_id_array));
                        $is_passed['has_fee_based_on_category'] = '';
                        $passed_category = array();
                        $notpassed_category = array();
                        foreach ($category_array as $category) {
                            if ($category['product_fees_conditions_is'] == 'is_equal_to') {
                                if (!empty($category['product_fees_conditions_values'])) {
                                    foreach ($category['product_fees_conditions_values'] as $category_id) {
                                        $passed_category[] = $category_id;
                                        if (in_array($category_id, $get_cat_all)) {
                                            $is_passed['has_fee_based_on_category'] = 'yes';
                                        }
                                    }
                                }
                            }
                            if ($category['product_fees_conditions_is'] == 'not_in') {
                                if (!empty($category['product_fees_conditions_values'])) {
                                    foreach ($category['product_fees_conditions_values'] as $category_id) {
                                        $notpassed_category[] = $category_id;
                                        if (in_array($category_id, $get_cat_all)) {
                                            $is_passed['has_fee_based_on_category'] = 'no';
                                        }
                                    }
                                }
                            }
                        }
                        if (empty($is_passed['has_fee_based_on_category']) && empty($passed_category)) {
                            $is_passed['has_fee_based_on_category'] = 'yes';
                        } elseif (empty($is_passed['has_fee_based_on_category']) && !empty($passed_category)) {
                            $is_passed['has_fee_based_on_category'] = 'no';
                        }
                    }
                }

                //Check if is Cart Subtotal (Before Discount) exist
                if (is_array($cart_total_array) && isset($cart_total_array) && !empty($cart_total_array) && !empty($cart_array)) {
                    $total = $woocommerce->cart->subtotal;
                    if (isset($woocommerce_wpml) && !empty($woocommerce_wpml->multi_currency)) {
                        $new_total = $woocommerce_wpml->multi_currency->prices->unconvert_price_amount($total);
                    } else {
                        $new_total = $total;
                    }

                    foreach ($cart_total_array as $cart_total) {
                        if ($cart_total['product_fees_conditions_is'] == 'is_equal_to') {
                            if (!empty($cart_total['product_fees_conditions_values'])) {
                                if ($cart_total['product_fees_conditions_values'] == $new_total) {
                                    $is_passed['has_fee_based_on_cart_total'] = 'yes';
                                } else {
                                    $is_passed['has_fee_based_on_cart_total'] = 'no';
                                    break;
                                }
                            }
                        }
                        if ($cart_total['product_fees_conditions_is'] == 'less_equal_to') {
                            if (!empty($cart_total['product_fees_conditions_values'])) {
                                if ($cart_total['product_fees_conditions_values'] >= $new_total) {
                                    $is_passed['has_fee_based_on_cart_total'] = 'yes';
                                } else {
                                    $is_passed['has_fee_based_on_cart_total'] = 'no';
                                    break;
                                }
                            }
                        }
                        if ($cart_total['product_fees_conditions_is'] == 'less_then') {
                            if (!empty($cart_total['product_fees_conditions_values'])) {
                                if ($cart_total['product_fees_conditions_values'] > $new_total) {
                                    $is_passed['has_fee_based_on_cart_total'] = 'yes';
                                } else {
                                    $is_passed['has_fee_based_on_cart_total'] = 'no';
                                    break;
                                }
                            }
                        }
                        if ($cart_total['product_fees_conditions_is'] == 'greater_equal_to') {
                            if (!empty($cart_total['product_fees_conditions_values'])) {
                                if ($cart_total['product_fees_conditions_values'] <= $new_total) {
                                    $is_passed['has_fee_based_on_cart_total'] = 'yes';
                                } else {
                                    $is_passed['has_fee_based_on_cart_total'] = 'no';
                                    break;
                                }
                            }
                        }
                        if ($cart_total['product_fees_conditions_is'] == 'greater_then') {
                            if (!empty($cart_total['product_fees_conditions_values'])) {
                                if ($cart_total['product_fees_conditions_values'] < $new_total) {
                                    $is_passed['has_fee_based_on_cart_total'] = 'yes';
                                } else {
                                    $is_passed['has_fee_based_on_cart_total'] = 'no';
                                    break;
                                }
                            }
                        }
                        if ($cart_total['product_fees_conditions_is'] == 'not_in') {
                            if (!empty($cart_total['product_fees_conditions_values'])) {
                                if ($new_total == $cart_total['product_fees_conditions_values']) {
                                    $is_passed['has_fee_based_on_cart_total'] = 'no';
                                    break;
                                } else {
                                    $is_passed['has_fee_based_on_cart_total'] = 'yes';
                                }
                            }
                        }
                    }
                }

            }
        }
        
        if (isset($is_passed) && !empty($is_passed) && is_array($is_passed)) {
            if (!in_array('no', $is_passed)) {
                $final_condition_flag = 1;
            }
        }
        
        if( $final_condition_flag == 1 ) {
            return true;
        } else {
            return false;
        }
        
    }

    public function array_flatten($array) {
        if (!is_array($array)) {
            return FALSE;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function fee_array_column(array $input, $columnKey, $indexKey = null) {
        $array = array();
        foreach ($input as $value) {
            if (!isset($value[$columnKey])) {
                trigger_error("Key \"$columnKey\" does not exist in array");
                return false;
            }
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            } else {
                if (!isset($value[$indexKey])) {
                    trigger_error("Key \"$indexKey\" does not exist in array");
                    return false;
                }
                if (!is_scalar($value[$indexKey])) {
                    trigger_error("Key \"$indexKey\" does not contain scalar value");
                    return false;
                }
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
    
    /*
     * Remove WooCommerce currency symbol
     * 
     * @since 1.0.0
     */
    public function afrsm_free_remove_currency_symbol($price) {
        $wc_currency_symbol = get_woocommerce_currency_symbol();
        $new_price  = str_replace($wc_currency_symbol, '', $price);
        $new_price2 = (double) preg_replace('/[^.\d]/', '', $new_price);
        return $new_price2;
    }
    
    /*
     * Get WooCommerce version number
     * 
     * @since 1.0.0
     */
    function afrsm_get_woo_version_number() {
        // If get_plugins() isn't available, require it
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        
        // Create the plugins folder and file variables
	$plugin_folder = get_plugins( '/' . 'woocommerce' );
	$plugin_file = 'woocommerce.php';
	
	// If the plugin version number is set, return it 
	if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];
	} else {
            // Otherwise return null
            return NULL;
	}
    }
    
    /**
     * Function for save master settings data
     * 
     * @since 1.0.0
     */
    public function afrsm_free_sm_sort_order() {
        global $wpdb;
        $smOrderArray = !empty($_REQUEST['smOrderArray']) ? $_REQUEST['smOrderArray'] : '';
        if (isset($smOrderArray) && !empty($smOrderArray)) {
            update_option('sm_sortable_order', $smOrderArray);
        }
        wp_die();
    }
    
    /**
     * Function for save master settings data
     * 
     * @since 1.0.0
     */
    public function afrsm_free_save_master_settings() {
        $shipping_display_mode = !empty($_REQUEST['shipping_display_mode']) ? $_REQUEST['shipping_display_mode'] : '';
        
        if (isset($shipping_display_mode) && !empty($shipping_display_mode)) {
            update_option('md_woocommerce_shipping_method_format', $shipping_display_mode);
        }
        wp_die();
    }
    
    public function afrsm_free_product_fees_conditions_values_ajax() {
        
        $condition = isset($_POST['condition']) ? $_POST['condition'] : '';
        $count = isset($_POST['count']) ? $_POST['count'] : '';
        $html = '';
        
        if ($condition == 'country') {
            $html .= $this->afrsm_free_get_country_list($count);
        } elseif ($condition == 'product') {
            $html .= $this->afrsm_free_get_product_list($count);
        } elseif ($condition == 'category') {
            $html .= $this->afrsm_free_get_category_list($count);
        } elseif ($condition == 'cart_total') {
            $html .= '<input type="text" name="fees[product_fees_conditions_values][value_' . $count . ']" id="product_fees_conditions_values" class="product_fees_conditions_values" value="">';
        }
        echo $html;
        wp_die(); // this is required to terminate immediately and return a proper response
    }
    
    /**
     * Function for select country list
     *
     */
    public function afrsm_free_get_country_list($count = '', $selected = array()) {
        
        $countries_obj = new WC_Countries();
        $getCountries = $countries_obj->__get('countries');
        $html = '<select name="fees[product_fees_conditions_values][value_' . $count . '][]" class="product_fees_conditions_values multiselect2 product_fees_conditions_values_country" multiple="multiple">';
        if (!empty($getCountries)) {
            foreach ($getCountries as $code => $country) {
                $selectedVal = is_array($selected) && !empty($selected) && in_array($code, $selected) ? 'selected=selected' : '';
                $html .= '<option value="' . $code . '" ' . $selectedVal . '>' . $country . '</option>';
            }
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Function for select product list
     *
     */
    public function afrsm_free_get_product_list($count = '', $selected = array()) {
        global $sitepress;
        
        if (!empty($sitepress)) {
            $default_lang = $sitepress->get_default_language();
        }

        $get_all_products = new WP_Query(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        $html = '<select id="product-filter" rel-id="' . $count . '" name="fees[product_fees_conditions_values][value_' . $count . '][]" class="product_fees_conditions_values multiselect2" multiple="multiple">';
        if (isset($get_all_products->posts) && !empty($get_all_products->posts)) {

            foreach ($get_all_products->posts as $get_all_product) {

                if (!empty($sitepress)) {
                    $new_product_id = apply_filters('wpml_object_id', $get_all_product->ID, 'product', TRUE, $default_lang);
                } else {
                    $new_product_id = $get_all_product->ID;
                }

                $selectedVal = is_array($selected) && !empty($selected) && in_array($new_product_id, $selected) ? 'selected=selected' : '';
                if ($selectedVal != '') {
                    $html .= '<option value="' . $new_product_id . '" ' . $selectedVal . '>' . '#' . $new_product_id . ' - ' . get_the_title($new_product_id) . '</option>';
                }
            }
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Function for select cat list
     *
     */
    public function afrsm_free_get_category_list($count = '', $selected = array()) {

        global $sitepress;
        $taxonomy = 'product_cat';
        $post_status = 'publish';
        $orderby = 'name';
        $hierarchical = 1;      // 1 for yes, 0 for no
        $empty = 0;

        if (!empty($sitepress)) {
            $default_lang = $sitepress->get_default_language();
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => $post_status,
            'taxonomy' => $taxonomy,
            'orderby' => $orderby,
            'hierarchical' => $hierarchical,
            'hide_empty' => $empty,
            'posts_per_page' => -1
        );
        $get_all_categories = get_categories($args);
        $html = '<select rel-id="' . $count . '" name="fees[product_fees_conditions_values][value_' . $count . '][]" class="product_fees_conditions_values multiselect2" multiple="multiple">';
        
        if (isset($get_all_categories) && !empty($get_all_categories)) {
            foreach ($get_all_categories as $get_all_category) {

                if (!empty($sitepress)) {
                    $new_cat_id = apply_filters('wpml_object_id', $get_all_category->term_id, 'product_cat', TRUE, $default_lang);
                } else {
                    $new_cat_id = $get_all_category->term_id;
                }

                $selectedVal = is_array($selected) && !empty($selected) && in_array($new_cat_id, $selected) ? 'selected=selected' : '';
                $category = get_term_by('id', $new_cat_id, 'product_cat');
                $parent_category = get_term_by('id', $category->parent, 'product_cat');

                if ($category->parent > 0) {
                    $html .= '<option value=' . $category->term_id . ' ' . $selectedVal . '>' . '#' . $parent_category->name . '->' . $category->name . '</option>';
                } else {
                    $html .= '<option value=' . $category->term_id . ' ' . $selectedVal . '>' . $category->name . '</option>';
                }
            }
        }
        $html .= '</select>';
        return $html;
    }

    public function afrsm_free_product_fees_conditions_values_product_ajax() {
        global $sitepress;
        $post_value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
        
        $baselang_product_ids = array();

        if (!empty($sitepress)) {
            $default_lang = $sitepress->get_default_language();
        }

        function afrsm_posts_where($where, &$wp_query) {
            global $wpdb;
            if ($search_term = $wp_query->get('search_pro_title')) {
                $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql(like_escape($search_term)) . '%\'';
            }
            return $where;
        }

        $product_args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'search_pro_title' => $post_value,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        );

        add_filter('posts_where', 'afrsm_posts_where', 10, 2);
        $wp_query = new WP_Query($product_args);
        remove_filter('posts_where', 'afrsm_posts_where', 10, 2);

        $get_all_products = $wp_query->posts;

        if (isset($get_all_products) && !empty($get_all_products)) {
            foreach ($get_all_products as $get_all_product) {
                if (!empty($sitepress)) {
                    $defaultlang_product_id = apply_filters('wpml_object_id', $get_all_product->ID, 'product', TRUE, $default_lang);
                } else {
                    $defaultlang_product_id = $get_all_product->ID;
                }
                $baselang_product_ids[] = $defaultlang_product_id;
            }
        }

        $html = '';
        if (isset($baselang_product_ids) && !empty($baselang_product_ids)) {
            foreach ($baselang_product_ids as $baselang_product_id) {
                $html .= '<option value="' . $baselang_product_id . '">' . '#' . $baselang_product_id . ' - ' . get_the_title($baselang_product_id) . '</option>';
            }
        }
        echo $html;
        wp_die();
    }

    /**
     * Function for delete multiple shipping method
     */
    public function afrsm_free_wc_multiple_delete_shipping_method() {
        $result = 0;
        $allVals = !empty($_POST['allVals']) ? $_POST['allVals'] : array();
        if (!empty($allVals)) {
            foreach ($allVals as $val) {
                wp_delete_post($val);
                $result = 1;
            }
        }
        echo $result;
        wp_die();
    }

    function afrsm_free_fees_conditions_save($post) {
        if (empty($post)) {
            return false;
        }
        if (isset($_POST['post_type']) && $_POST['post_type'] == 'wc_afrsm') {
            if ($post['fee_post_id'] == '') {
                $fee_post = array(
                    'post_title' => $post['fee_settings_product_fee_title'],
                    'post_status' => 'publish',
                    'post_type' => 'wc_afrsm',
                );
                $post_id = wp_insert_post($fee_post);
            } else {
                $fee_post = array(
                    'ID' => $post['fee_post_id'],
                    'post_title' => $post['fee_settings_product_fee_title'],
                    'post_status' => 'publish'
                );
                $post_id = wp_update_post($fee_post);
            }
            
            if (isset($post['sm_status'])) {
                update_post_meta($post_id, 'sm_status', 'on');
            } else {
                update_post_meta($post_id, 'sm_status', 'off');
            }
            if (isset($post['sm_product_cost'])) {
                update_post_meta($post_id, 'sm_product_cost', esc_attr($post['sm_product_cost']));
            }
            if (isset($post['sm_tooltip_desc'])) {
                update_post_meta($post_id, 'sm_tooltip_desc', esc_attr($post['sm_tooltip_desc']));
            }
            if (isset($post['sm_select_taxable'])) {
                update_post_meta($post_id, 'sm_select_taxable', esc_attr($post['sm_select_taxable']));
            }
            if (isset($post['sm_extra_cost'])) {
                update_post_meta($post_id, 'sm_extra_cost', $post['sm_extra_cost']);
            }
            if (isset($post['sm_extra_cost_calculation_type'])) {
                update_post_meta($post_id, 'sm_extra_cost_calculation_type', esc_attr($post['sm_extra_cost_calculation_type']));
            }
            
            $feesArray = array();
            $fees = isset($post['fees']) ? $post['fees'] : array();
            $condition_key = isset($post['condition_key']) ? $post['condition_key'] : array();
            $fees_conditions = $fees['product_fees_conditions_condition'];
            $conditions_is = $fees['product_fees_conditions_is'];
            $conditions_values = isset($fees['product_fees_conditions_values']) && !empty($fees['product_fees_conditions_values']) ? $fees['product_fees_conditions_values'] : array();
            $size = count($fees_conditions);
            foreach ($condition_key as $key => $value) {
                if (!array_key_exists($key, $conditions_values)) {
                    $conditions_values[$key] = array();
                }
            }
            uksort($conditions_values, 'strnatcmp');
            foreach ($conditions_values as $k => $v) {
                $conditionsValuesArray[] = $v;
            }
            for ($i = 0; $i < $size; $i++) {
                $feesArray[] = array(
                    'product_fees_conditions_condition' => $fees_conditions[$i],
                    'product_fees_conditions_is' => $conditions_is[$i],
                    'product_fees_conditions_values' => $conditionsValuesArray[$i]
                );
            }
            update_post_meta($post_id, 'sm_metabox', $feesArray);
            wp_redirect(admin_url('/admin.php?page=afrsm-free-list'));
            exit();
        }
    }
    
    public function afrsm_free_admin_footer_review() {
        echo 'If you like <strong>Advanced Flat Rate Shipping For WooCommerce</strong> plugin, please leave us ★★★★★ ratings on <a href="' . esc_url('store.multidots.com/advanced-flat-rate-shipping-method-for-woocommerce') . '" target="_blank">DotStore</a> or <a href="' . esc_url('codecanyon.net/item/advance-flat-rate-shipping-method-for-woocommerce/reviews/15831725') . '" target="_blank">CodeCanyon</a>.';
    }
}