<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

require_once('header/plugin-header.php');
?>

<div class="afrsm-section-left">
    <div class="afrsm-main-table res-cl">
        <h2><?php _e('Quick info', AFRSM_TEXT_DOMAIN); ?></h2>
        <table class="table-outer">
            <tbody>
                <tr>
                    <td class="fr-1"><?php _e('Product Type', AFRSM_TEXT_DOMAIN); ?></td>
                    <td class="fr-2"><?php _e('WooCommerce Plugin', AFRSM_TEXT_DOMAIN); ?></td>
                </tr>
                <tr>
                    <td class="fr-1"><?php _e('Product Name', AFRSM_TEXT_DOMAIN); ?></td>
                    <td class="fr-2"><?php _e($plugin_name, AFRSM_TEXT_DOMAIN); ?></td>
                </tr>
                <tr>
                    <td class="fr-1"><?php _e('Installed Version', AFRSM_TEXT_DOMAIN); ?></td>
                    <td class="fr-2"><?php _e('Free Version', AFRSM_TEXT_DOMAIN); ?> <?php echo $plugin_version; ?></td>
                </tr>
                <tr>
                    <td class="fr-1"><?php _e('License & Terms of use', AFRSM_TEXT_DOMAIN); ?></td>
                    <td class="fr-2"><a target="_blank"  href="<?php echo esc_url('store.multidots.com/terms-conditions'); ?>"><?php _e('Click here', AFRSM_TEXT_DOMAIN); ?></a><?php _e(' to view license and terms of use.', AFRSM_TEXT_DOMAIN); ?></td>
                </tr>
                <tr>
                    <td class="fr-1"><?php _e('Help & Support', AFRSM_TEXT_DOMAIN); ?></td>
                    <td class="fr-2">
                        <ul>
                            <li><a href="<?php echo admin_url('/admin.php?page=afrsm-free-get-started'); ?>"><?php _e('Quick Start', AFRSM_TEXT_DOMAIN); ?></a></li>
                            <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/docs/plugin/advanced-flat-rate-shipping-method-for-woocommerce'); ?>"><?php _e('Guide Documentation', AFRSM_TEXT_DOMAIN); ?></a></li> 
                            <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/dotstore-support-panel'); ?>"><?php _e('Support Forum', AFRSM_TEXT_DOMAIN); ?></a></li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td class="fr-1"><?php _e('Localization', AFRSM_TEXT_DOMAIN); ?></td>
                    <td class="fr-2"><?php _e('English, German', AFRSM_TEXT_DOMAIN); ?></td>
                </tr>

            </tbody>
        </table>
    </div>
</div>
<?php require_once('header/plugin-sidebar.php'); ?>