<?php

/**
 * Plugin Name:       Advanced Flat Rate Shipping For WooCommerce
 * Plugin URI:        https://store.multidots.com/advanced-flat-rate-shipping-method-for-woocommerce
 * Description:       Advanced Flat Rate Shipping Method plugin is for add new flat rate option in your WooCommerce site. All Shipping option is display in Front side so User can choose shipping method based on that.
 * Version:           3.1.3
 * Author:            Multidots
 * Author URI:        http://www.multidots.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-extra-flat-rate
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AFRSM_PLUGIN_VERSION')) {
    define('AFRSM_PLUGIN_VERSION', '3.1.3');
}
if (!defined('AFRSM_PLUGIN_URL')) {
    define('AFRSM_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('AFRSM_PLUGIN_DIR')) {
    define('AFRSM_PLUGIN_DIR', dirname(__FILE__));
}
if (!defined('AFRSM_PLUGIN_DIR_PATH')) {
    define('AFRSM_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
}
if (!defined('AFRSM_PLUGIN_BASENAME')) {
    define('AFRSM_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('AFRSM_PLUGIN_NAME')) {
    define('AFRSM_PLUGIN_NAME', 'Advanced Flat Rate Shipping For WooCommerce');
}
if (!defined('AFRSM_TEXT_DOMAIN')) {
    define('AFRSM_TEXT_DOMAIN', 'woo-extra-flat-rate');
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woo-extra-flat-rate-activator.php
 */
function activate_advanced_flat_rate_shipping_for_woocommerce_pro() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-extra-flat-rate-activator.php';
    Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woo-extra-flat-rate-deactivator.php
 */
function deactivate_advanced_flat_rate_shipping_for_woocommerce_pro() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-extra-flat-rate-deactivator.php';
    Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_advanced_flat_rate_shipping_for_woocommerce_pro');
register_deactivation_hook(__FILE__, 'deactivate_advanced_flat_rate_shipping_for_woocommerce_pro');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-woo-extra-flat-rate.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_advanced_flat_rate_shipping_for_woocommerce_pro() {
    $plugin = new Advanced_Flat_Rate_Shipping_For_WooCommerce_Pro();
    $plugin->run();
}
run_advanced_flat_rate_shipping_for_woocommerce_pro();

function advanced_flat_rate_shipping_for_woocommerce_pro_plugin_path() {
    return untrailingslashit(plugin_dir_path(__FILE__));
}

?>