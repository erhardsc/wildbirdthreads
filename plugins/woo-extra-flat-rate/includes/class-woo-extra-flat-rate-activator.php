<?php

/**
 * Fired during plugin activation
 *
 * @link       http://www.multidots.com
 * @since      1.0.0
 *
 * @package    Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro
 * @subpackage Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro
 * @subpackage Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro/includes
 * @author     Multidots <inquiry@multidots.in>
 */
class Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb, $woocommerce;
        set_transient('_welcome_screen_afrsm_free_mode_activation_redirect_data', true, 30);
        add_option('afrsm_version', Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro::WCPFC_VERSION);

        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !is_plugin_active_for_network('woocommerce/woocommerce.php')) {
            wp_die("<strong>Advanced Flat Rate Shipping For WooCommerce</strong> plugin requires <strong>WooCommerce</strong>. Return to <a href='" . get_admin_url(null, 'plugins.php') . "'>Plugins page</a>.");
        } else {
            
            /* Data Migration Script Start */
            $afrsm_db_upgrade = get_option('afrsm_db_upgrade');

            if( empty($afrsm_db_upgrade) ) {
                $db_upgrade_flag = self::afrsm_data_migration_script();
                if( $db_upgrade_flag == 1 ) {
                    update_option( 'afrsm_db_upgrade', 'required' );
                }
            }
            /* Data Migration Script End */
            
            $wpdb->hide_errors();
            $collate = '';

            if ($wpdb->has_cap('collation')) {
                if (!empty($wpdb->charset)) {
                    $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
                }
                if (!empty($wpdb->collate)) {
                    $collate .= " COLLATE $wpdb->collate";
                }
            }

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        }
    }

    public static function afrsm_data_migration_script() {
        global $wpdb;
        
        $db_upgrade_flag = 0;
        
        $shipping_method_format = get_option('md_woocommerce_shipping_method_format');
        if( $shipping_method_format == 'select' ) {
          update_option('md_woocommerce_shipping_method_format', 'dropdown_mode');
        } else {
          update_option('md_woocommerce_shipping_method_format', 'radio_button_mode');
        }

        $afrsm_settings_query   = "SELECT * FROM {$wpdb->prefix}options WHERE option_name LIKE 'extra_%'";
        $afrsm_settings_result  = $wpdb->get_results($afrsm_settings_query);
        
        if( !empty( $afrsm_settings_result ) ) {
            foreach ($afrsm_settings_result as $key => $afrsm_setting) {
                
                $shipping_metabox = array();
                $country_base = array();
                $product_base = array();
                $category_base = array();
                
                $afrsm_setting_unser = maybe_unserialize($afrsm_setting->option_value);
                
                $shipping_enabled       = (!empty($afrsm_setting_unser['enabled']) && $afrsm_setting_unser['enabled'] == 'yes') ? 'on' : 'off';
                $shipping_title         = !empty($afrsm_setting_unser['title']) ? esc_attr(stripslashes($afrsm_setting_unser['title'])) : '';
                $shipping_cost          = !empty($afrsm_setting_unser['cost']) ? esc_attr(stripslashes($afrsm_setting_unser['cost'])) : 0;
                $shipping_description   = !empty($afrsm_setting_unser['shipping_description']) ? $afrsm_setting_unser['shipping_description'] : '';
                $shipping_tax_status    = (!empty($afrsm_setting_unser['tax_status']) && $afrsm_setting_unser['tax_status'] == 'taxable') ? 'yes' : 'no';
                
                $shipping_country_base  = !empty($afrsm_setting_unser['countries']) ? $afrsm_setting_unser['countries'] : array();

                $shipping_product_base  = !empty($afrsm_setting_unser['product_base']) ? $afrsm_setting_unser['product_base'] : array();
                $shipping_category_base = !empty($afrsm_setting_unser['category_base']) ? $afrsm_setting_unser['category_base'] : array();
                
                $shipping_type          = !empty($afrsm_setting_unser['type']) ? $afrsm_setting_unser['type'] : '';
                
                $new_shipping_cost = self::afrsm_string_sanitize($shipping_cost);
                
                /* Country base metabox */
                if( !empty($shipping_country_base) ) {
                    $country_base['product_fees_conditions_condition'] = 'country';
                    $country_base['product_fees_conditions_is'] = 'is_equal_to';
                    $country_base['product_fees_conditions_values'] = $shipping_country_base;
                    
                    $shipping_metabox[] = $country_base;
                }
                
                /* Product base metabox */
                if( !empty($shipping_product_base) ) {
                    $product_base['product_fees_conditions_condition'] = 'product';
                    $product_base['product_fees_conditions_is'] = 'is_equal_to';
                    $product_base['product_fees_conditions_values'] = $shipping_product_base;
                    
                    $shipping_metabox[] = $product_base;
                }
                
                /* Category base metabox */
                if( !empty($shipping_category_base) ) {
                    $category_base['product_fees_conditions_condition'] = 'category';
                    $category_base['product_fees_conditions_is'] = 'is_equal_to';
                    $category_base['product_fees_conditions_values'] = $shipping_category_base;

                    $shipping_metabox[] = $category_base;
                }
                
                /* SHIPPING CLASS CONDITIONS START */
                $shipping_classes = WC()->shipping->get_shipping_classes();
                
                /* Shipping Class Cost Type */
                if (!empty($shipping_classes)) {
                    $shipping_class_cost_array = array();
                    foreach ($shipping_classes as $shipping_class) {
                        
                        if (!empty($shipping_class->term_id)) {
                            
                            $shipping_class_cost = isset($afrsm_setting_unser['class_cost_' . $shipping_class->term_id]) ? $afrsm_setting_unser['class_cost_' . $shipping_class->term_id] : '';
                            $shipping_class_cost = (isset($shipping_class_cost) && !empty($shipping_class_cost)) ? esc_attr(stripslashes($shipping_class_cost)) : '';
                            
                            $shipping_class_cost_new = self::afrsm_string_sanitize($shipping_class_cost);
                            $shipping_class_cost_array[$shipping_class->term_id] = $shipping_class_cost_new;
                        }
                    }
                }
                
                /* Cost Calculation Type */
                if( $shipping_type == 'order' ) {
                    $sm_extra_cost_calculation_type = 'per_order';
                } else {
                    $sm_extra_cost_calculation_type = 'per_class';
                }
                /* SHIPPING CLASS CONDITIONS END */
                
                // Create new shipping method
                $afrsm_post = array(
                                'post_title'    => wp_strip_all_tags( $shipping_title ),
                                'post_type'     => 'wc_afrsm',
                                'post_status'   => 'publish'
                              );
                $post_id = wp_insert_post($afrsm_post);
                
                //Update shipping method data
                if( !empty($post_id) ) {
                    update_post_meta($post_id, 'sm_status', esc_attr($shipping_enabled));
                    update_post_meta($post_id, 'sm_product_cost', esc_attr($new_shipping_cost));
                    update_post_meta($post_id, 'sm_tooltip_desc', esc_attr($shipping_description));
                    update_post_meta($post_id, 'sm_select_taxable', esc_attr($shipping_tax_status));
                    update_post_meta($post_id, 'sm_metabox', $shipping_metabox);
                    update_post_meta($post_id, 'sm_extra_cost', $shipping_class_cost_array);
                    update_post_meta($post_id, 'sm_extra_cost_calculation_type', $sm_extra_cost_calculation_type);
                }
                
            }
            $db_upgrade_flag = 1;
        }
        return $db_upgrade_flag;
    }
    
    public static function afrsm_string_sanitize($string) {
        $result = preg_replace("/[^ A-Za-z0-9_=.*()+\-\[\]\/]+/", "", html_entity_decode($string, ENT_QUOTES));
        return $result;
    }
    
}
?>