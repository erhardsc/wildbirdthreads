<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once('header/plugin-header.php');
?>
<div class="afrsm-section-left">
    <div class="afrsm-main-table res-cl">
        <div class="afrsm-premium-features">
            <div class="section section-odd clear">
                <h1><?php _e('Premium Features', AFRSM_TEXT_DOMAIN);?></h1>
                <div class="landing-container pro-master-settings">
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('When multiple shipping methods are visible on cart page', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <ul>
                            <li><b><?php _e('Allow customer to choose:', AFRSM_TEXT_DOMAIN) ?></b> <?php _e('Let\'s customer choose one shipping method from available shipping methods', AFRSM_TEXT_DOMAIN);?></li>
                            <li><b><?php _e('Apply Highest:', AFRSM_TEXT_DOMAIN) ?></b> <?php _e('Shipping method with the highest cost would be displayed from the available shipping methods', AFRSM_TEXT_DOMAIN);?></li>
                            <li><b><?php _e('Apply smallest:', AFRSM_TEXT_DOMAIN) ?></b> <?php _e('Shipping method with the lowest cost would be displayed from the available shipping methods', AFRSM_TEXT_DOMAIN);?></li>
                            <li><b><?php _e('Force all:', AFRSM_TEXT_DOMAIN) ?></b> <?php _e('All the shipping methods are forcefully invoked with shipping charge as summed up of all shipping methods', AFRSM_TEXT_DOMAIN);?></li>
                        </ul>
                    </div>
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_13.png'; ?>" alt="<?php _e('When multiple shipping methods are visible on cart page', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                </div>
            </div>
            <div class="section section-even clear">
                <div class="landing-container">
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_01.png'; ?>" alt="<?php _e('Shipping method Based On Country, State and Zipcode', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method Based On Country, State and Zipcode', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('Using this feature you can apply shipping rule for a country, state(s) or Zipcode(s). With this option you can create "International flat-rate shipping" method for your WooCommerce store.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, if your store in the USA and you want to create shipping method for Alabama and Alaska state with specific postcodes.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                </div>
            </div>
            <div class="section section-odd clear">
                <div class="landing-container">
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method Based On Custom Zone', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('You can create custom shipping zone as per your requirements. You can apply multiple shipping methods based on that different custom zones.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><b><?php _e('Create shipping zone as per below:', AFRSM_TEXT_DOMAIN);?></b></p>
                        <ul>
                            <li><?php _e('Countries based shipping zone', AFRSM_TEXT_DOMAIN);?></li>
                            <li><?php _e('State and Counties based shipping zone', AFRSM_TEXT_DOMAIN);?></li>
                            <li><?php _e('Post Codes / Zips based shipping zone', AFRSM_TEXT_DOMAIN);?></li>
                        </ul>
                    </div>
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_12.png'; ?>" alt="<?php _e('Shipping method Based On Custom Zone', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                </div>
            </div>
            <div class="section section-even clear">
                <div class="landing-container">
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_04.png'; ?>" alt="<?php _e('Shipping method based on Tag', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method based on Tag', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('Using this feature you can create shipping method for specific tag\'s products.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, you can create "Tag-based shipping" for $10. This method should be visible when the cart has any product having "Tag1" tag.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                </div>
            </div>
            <div class="section section-odd clear">
                <div class="landing-container">
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method based on SKU', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('Using this feature you can create shipping method for specific SKU\'s products.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, you can create "SKU based shipping" for $12. This method should be visible when the cart has any product having "woo-single1" SKU.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_05.png'; ?>" alt="<?php _e('Shipping method based on SKU', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                </div>
            </div>
            <div class="section section-even clear">
                <div class="landing-container">
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_06.png'; ?>" alt="<?php _e('Shipping method for specific users', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method for specific users', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('Using this feature you can create shipping method for specific users.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, you have created shipping method for "John" user with $18 charge. When John is logged in and place some order then for all the orders shipping method would be displayed.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                </div>
            </div>
            <div class="section section-odd clear">
                <div class="landing-container">
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method based on User Role', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('Using this feature, shipping method based is visible for specific user role/group.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, you have created shipping method for "Editor" role. Now, when any user with role "Editor" is logged in and place an order then this shipping method is visible.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_07.png'; ?>" alt="<?php _e('Shipping method based on User Role', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                </div>
            </div>
            <div class="section section-even clear">
                <div class="landing-container">
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_09.png'; ?>" alt="<?php _e('Shipping method based on total cart quantity', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method based on total cart quantity', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('This shipping method allows you to create shipping method based on total quantity of cart. There are multiple conditions (like =, !=, <, <=, >, >=) available for this parameter.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, if you have created shipping method like quantity >= 5. When total quantity of cart is greater than 5 then shipping method is visible.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                </div>
            </div>
            <div class="section section-odd clear">
                <div class="landing-container">
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Shipping method based on total cart\'s weight', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('This shipping method allows you to create shipping method based on total weight of cart. There are multiple conditions (like =, !=, <, <=, >, >=) available for this parameter.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('For example, if you have created shipping method like weight != 5. When total weight of cart is not equal to 5 then shipping method is visible.', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_10.png'; ?>" alt="<?php _e('Shipping method based on total cart\'s weight', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                </div>
            </div>
            <div class="section section-even clear">
                <div class="landing-container">
                    <div class="col-1">
                        <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/features_11.png'; ?>" alt="<?php _e('Additional shipping charges based on shipping class', AFRSM_TEXT_DOMAIN);?>" />
                    </div>
                    <div class="col-2">
                        <div class="section-title">
                            <h2><?php _e('Additional shipping charges based on shipping class', AFRSM_TEXT_DOMAIN);?></h2>
                        </div>
                        <p><?php _e('This option allows a user to add extra cost based on shipping classes. It provides all shipping classes which are already used for the product. It displays all shipping classes list with a text box to add cost.', AFRSM_TEXT_DOMAIN);?></p>
                        <p><?php _e('The shipping class cost will be added to the shipping charge. For example, if you set $49 as a shipping charge and "Poster class" shipping cost would be $10. Now when cart having a product that has poster class then total shipping charge would be $59(49 + 10).', AFRSM_TEXT_DOMAIN);?></p>
                    </div>
                </div>
            </div>
            <div class="section section-cta section-odd">
                <div class="landing-container afsrm_upgrade_to_pro">
                    <div class="afrsm-wishlist-cta">
                        <p><?php _e("Upgrade to the PREMIUM VERSION to increase your affiliate program bonus!", AFRSM_TEXT_DOMAIN) ?></p>
                        <a target="_blank" href="<?php echo esc_url('store.multidots.com/advanced-flat-rate-shipping-method-for-woocommerce'); ?>"> 
                            <img src="<?php echo AFRSM_PLUGIN_URL . 'admin/images/upgrade_new.png'; ?>">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once('header/plugin-sidebar.php'); ?>