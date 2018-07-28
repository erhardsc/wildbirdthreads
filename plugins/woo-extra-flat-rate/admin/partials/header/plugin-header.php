<?php
$plugin_name = AFRSM_PLUGIN_NAME;
$plugin_version = AFRSM_PLUGIN_VERSION;
?>
<div id="dotsstoremain">
    <div class="all-pad">
        <header class="dots-header">
            <div class="dots-logo-main">
                <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/advance-flat-rate.png'; ?>">
            </div>
            <div class="dots-header-right">
                <div class="logo-detail">
                    <strong><?php _e($plugin_name, AFRSM_TEXT_DOMAIN); ?></strong>
                    <span><?php _e('Free Version', AFRSM_TEXT_DOMAIN); ?> <?php echo $plugin_version; ?></span>
                </div>

                <div class="button-dots">
                    <span class="upgrade_pro_image">
                        <a target="_blank" href="<?php echo esc_url('store.multidots.com/advanced-flat-rate-shipping-method-for-woocommerce'); ?>"> 
                            <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/upgrade_new.png'; ?>">
                        </a>
                    </span>
                    <span class="support_dotstore_image">
                        <a target="_blank" href="<?php echo esc_url('store.multidots.com/dotstore-support-panel'); ?>" > 
                            <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/support_new.png'; ?>">
                        </a>
                    </span>
                </div>
            </div>

            <?php
                $afrsm_shipping_list        = isset($_GET['page']) && $_GET['page'] == 'afrsm-free-list' ? 'active' : '';
                $afrsm_shipping_add         = isset($_GET['page']) && $_GET['page'] == 'afrsm-free-add-shipping' ? 'active' : '';
                $afrsm_getting_started      = isset($_GET['page']) && $_GET['page'] == 'afrsm-free-get-started' ? 'active' : '';
                $afrsm_information          = isset($_GET['page']) && $_GET['page'] == 'afrsm-free-information' ? 'active' : '';
                $afrsm_pro_details          = isset($_GET['page']) && $_GET['page'] == 'afrsm-pro-details' ? 'active' : '';

                if (isset($_GET['page']) && $_GET['page'] == 'afrsm-free-information' || isset($_GET['page']) && $_GET['page'] == 'afrsm-free-get-started') {
                    $fee_about = 'active';
                } else {
                    $fee_about = '';
                }

                if (!empty($_REQUEST['action'])) {
                    if ($_REQUEST['action'] == 'add' || $_REQUEST['action'] == 'edit') {
                        $afrsm_shipping_add = 'active';
                    }
                }
            ?>
            <div class="dots-menu-main">
                <nav>
                    <ul>
                        <li>
                            <a class="dotstore_plugin <?php echo $afrsm_shipping_list; ?>" href="<?php echo admin_url('/admin.php?page=afrsm-free-list'); ?>"><?php _e('Manage Shipping Methods', AFRSM_TEXT_DOMAIN); ?></a>
                        </li>
                        <li>
                            <a class="dotstore_plugin <?php echo $afrsm_shipping_add; ?>" href="<?php echo admin_url('/admin.php?page=afrsm-free-add-shipping'); ?>"><?php _e('Add New Shipping Method', AFRSM_TEXT_DOMAIN); ?></a>
                        </li>
                        <li>
                            <a class="dotstore_plugin <?php echo $fee_about; ?>" href="<?php echo admin_url('/admin.php?page=afrsm-free-get-started'); ?>"><?php _e('About Plugin', AFRSM_TEXT_DOMAIN); ?></a>
                            <ul class="sub-menu">
                                <li><a class="dotstore_plugin <?php echo $afrsm_getting_started; ?>" href="<?php echo admin_url('/admin.php?page=afrsm-free-get-started'); ?>"><?php _e('Getting Started', AFRSM_TEXT_DOMAIN); ?></a></li>
                                <li><a class="dotstore_plugin <?php echo $afrsm_information; ?>" href="<?php echo admin_url('/admin.php?page=afrsm-free-information'); ?>"><?php _e('Quick info', AFRSM_TEXT_DOMAIN); ?></a></li>
                            </ul>
                        </li>
                        <li>
                            <a class="dotstore_plugin <?php echo $afrsm_pro_details; ?>" href="<?php echo admin_url('/admin.php?page=afrsm-pro-details'); ?>"><?php _e('Premium Version', AFRSM_TEXT_DOMAIN); ?></a>
                        </li>
                        <li>
                            <a class="dotstore_plugin"><?php _e('Dotstore', AFRSM_TEXT_DOMAIN); ?></a>
                            <ul class="sub-menu">
                                <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/woocommerce-plugins'); ?>"><?php _e('WooCommerce Plugins', AFRSM_TEXT_DOMAIN); ?></a></li>
                                <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/wordpress-plugins'); ?>"><?php _e('Wordpress Plugins', AFRSM_TEXT_DOMAIN); ?></a></li><br>
                                <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/free-wordpress-plugins'); ?>"><?php _e('Free Plugins', AFRSM_TEXT_DOMAIN); ?></a></li>
                                <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/themes'); ?>"><?php _e('Free Themes', AFRSM_TEXT_DOMAIN); ?></a></li>
                                <li><a target="_blank" href="<?php echo esc_url('store.multidots.com/dotstore-support-panel'); ?>"><?php _e('Contact Support', AFRSM_TEXT_DOMAIN); ?></a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>