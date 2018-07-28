<?php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

require_once('header/plugin-header.php');
?>

<?php
if ( isset($_POST['submitFee']) && !empty($_POST['submitFee']) ) {
    $post = $_POST;
    $this->afrsm_free_fees_conditions_save($post);
}
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'edit') {
    $post_id                = $_REQUEST['id'];
    $sm_status              = get_post_meta($post_id, 'sm_status', true);
    $sm_title               = __(get_the_title($post_id), AFRSM_TEXT_DOMAIN);
    $sm_cost                = get_post_meta($post_id, 'sm_product_cost', true);
    $sm_tooltip_desc        = get_post_meta($post_id, 'sm_tooltip_desc', true);
    $sm_is_taxable          = get_post_meta($post_id, 'sm_select_taxable', true);
    $sm_extra_cost          = get_post_meta($post_id, 'sm_extra_cost', true);
    $sm_extra_cost_calc_type = get_post_meta($post_id, 'sm_extra_cost_calculation_type', true);
    $sm_metabox             = get_post_meta($post_id, 'sm_metabox', true);
} else {
    $post_id                = '';
    $sm_status              = '';
    $sm_title               = '';
    $sm_cost                = '';
    $sm_tooltip_desc        = '';
    $sm_is_taxable          = '';
    $sm_extra_cost          = array();
    $sm_extra_cost_calc_type = '';
    $sm_metabox             = array();
}

$sm_status              = ((!empty($sm_status) && $sm_status == 'on') || empty($sm_status)) ? 'checked' : '';
$sm_title               = !empty($sm_title) ? esc_attr( stripslashes( $sm_title )) : '';
$sm_cost                = ($sm_cost !== '') ? esc_attr( stripslashes( $sm_cost )) : '';
$sm_tooltip_desc        = !empty($sm_tooltip_desc) ? $sm_tooltip_desc : '';
$submit_text            = __('Save changes', AFRSM_TEXT_DOMAIN);
?>
<div class="text-condtion-is" style="display:none;">
    <select class="text-condition">
        <option value="is_equal_to"><?php _e('Equal to ( = )', AFRSM_TEXT_DOMAIN); ?></option>
        <option value="less_equal_to"><?php _e('Less or Equal to ( <= )', AFRSM_TEXT_DOMAIN); ?></option>
        <option value="less_then"><?php _e('Less than ( < )', AFRSM_TEXT_DOMAIN); ?></option>
        <option value="greater_equal_to"><?php _e('Greater or Equal to ( >= )', AFRSM_TEXT_DOMAIN); ?></option>
        <option value="greater_then"><?php _e('Greater than ( > )', AFRSM_TEXT_DOMAIN); ?></option>
        <option value="not_in"><?php _e('Not Equal to ( != )', AFRSM_TEXT_DOMAIN); ?></option>	
    </select>
    <select class="select-condition">
        <option value="is_equal_to"><?php _e('Equal to ( = )', AFRSM_TEXT_DOMAIN); ?></option>
        <option value="not_in"><?php _e('Not Equal to ( != )', AFRSM_TEXT_DOMAIN); ?></option>	
    </select>
</div>
<div class="default-country-box" style="display:none;">
    <?php echo $this->afrsm_free_get_country_list(); ?>
</div>
<div class="afrsm-section-left">
    <div class="afrsm-main-table res-cl">
        <h2><?php _e('Shipping Method Configuration', AFRSM_TEXT_DOMAIN); ?></h2>
        <form method="POST" name="feefrm" action="">
            <input type="hidden" name="post_type" value="wc_afrsm">
            <input type="hidden" name="fee_post_id" value="<?php echo $post_id ?>">
            <table class="form-table table-outer shipping-method-table">
                <tbody>
                    <tr valign="top">
                        <th class="titledesc" scope="row">
                            <label for="onoffswitch"><?php _e('Status', AFRSM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td class="forminp">
                            <label class="switch">
                                <input type="checkbox" name="sm_status" value="on" <?php echo $sm_status; ?>>
                                <div class="slider round"></div>
                            </label>
                            <span class="advanced_flat_rate_shipping_for_woocommerce_tab_description"></span>
                            <p class="description" style="display:none;"><?php _e('Enable this shipping method (This method will be visible to customers only if it is enabled).', AFRSM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th class="titledesc" scope="row">
                            <label for="fee_settings_product_fee_title"><?php _e('Shipping Method Name', AFRSM_TEXT_DOMAIN); ?> <span class="required-star">*</span></label>
                        </th>
                        <td class="forminp">
                            <input type="text" name="fee_settings_product_fee_title" class="text-class" id="fee_settings_product_fee_title" value="<?php echo $sm_title; ?>" required="1" placeholder="<?php _e('Enter product fees title', AFRSM_TEXT_DOMAIN); ?>">
                            <span class="advanced_flat_rate_shipping_for_woocommerce_tab_description"></span>
                            <p class="description" style="display:none;"><?php _e('This name will be visible to the customer at the time of checkout. This should convey the purpose of the charges you are applying to the order. For example "Ground Shipping", "Express Shipping Flat Rate", "Christmas Next Day Shipping" etc', AFRSM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th class="titledesc" scope="row">
                            <label for="sm_product_cost"><?php _e('Shipping Charge', AFRSM_TEXT_DOMAIN); ?> <?php echo '(' . get_woocommerce_currency_symbol() . ')' ?> <span class="required-star">*</span></label>
                        </th>
                        <td class="forminp">
                            <input type="text" name="sm_product_cost" required="1" class="text-class" id="sm_product_cost" value="<?php echo $sm_cost; ?>" placeholder="<?php echo get_woocommerce_currency_symbol(); ?>">
                            <span class="advanced_flat_rate_shipping_for_woocommerce_tab_description"></span>
                            <p class="description" style="display:none;">
                                <?php _e('When customer select this shipping method the amount will be added to the cart subtotal.'
                                    . ' You can enter fixed amount or make it dynamic using below parameters:<br>'
                                    . '[qty] -> total number of items in cart,<br>'
                                    . '[cost] -> cost of items,<br>'
                                    . '[fee percent=10 min_fee=20] -> Percentage based fee.<br><br>'
                                    . 'Below are some examples: <br>'
                                    . 'i. 10.00  -> To add flat 10.00 shipping charge'."<br>"
                                    . 'ii. 10.00 * [qty] -> To charge 10.00 per quantity in the cart. It will be 50.00 if the cart has 5 quantity.'."<br>"
                                    . 'iii. [fee percent=10 min_fee=20] -> This means charge 10 percent of cart subtotal, minimum 20 charge will be applicable.', AFRSM_TEXT_DOMAIN); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th class="titledesc" scope="row">
                            <label for="sm_tooltip_desc"><?php _e('Tooltip Description', AFRSM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td class="forminp">
                            <textarea name="sm_tooltip_desc" rows="3" cols="70" id="sm_tooltip_desc" placeholder="<?php _e('Enter tooltip short description', AFRSM_TEXT_DOMAIN); ?>"><?php echo $sm_tooltip_desc; ?></textarea>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th class="titledesc" scope="row">	
                            <label for="sm_select_taxable"><?php _e('Is Amount Taxable?', AFRSM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td class="forminp">
                            <select name="sm_select_taxable" id="sm_select_taxable" class="">
                                <option value="no" <?php echo isset($sm_is_taxable) && $sm_is_taxable == 'no' ? 'selected="selected"' : '' ?>><?php _e('No', AFRSM_TEXT_DOMAIN); ?></option>
                                <option value="yes" <?php echo isset($sm_is_taxable) && $sm_is_taxable == 'yes' ? 'selected="selected"' : '' ?>><?php _e('Yes', AFRSM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php
                $all_shipping_classes = WC()->shipping->get_shipping_classes();
                if(!empty($all_shipping_classes)) {
            ?>
                <div class="sub-title">
                    <h2><?php _e('Additional Shipping Charges Based on Shipping Class', AFRSM_TEXT_DOMAIN); ?></h2>
                </div>
                <div class="tap">
                    <table class="form-table table-outer shipping-method-table">
                        <tbody>
                            <tr valign="top">
                                <td class="forminp" colspan="2">
                                    <?php echo sprintf(__('These costs can optionally be added based on the %sproduct shipping class%s.', AFRSM_TEXT_DOMAIN), '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=classes') . '">', '</a>'); ?>
                                </td>
                            </tr>
                            <?php
                                foreach ($all_shipping_classes as $key => $shipping_class) {
                                    $shipping_extra_cost = isset($sm_extra_cost["$shipping_class->term_id"]) && ($sm_extra_cost["$shipping_class->term_id"] !== '') ? $sm_extra_cost["$shipping_class->term_id"] : "";
                            ?>
                                <tr valign="top">
                                    <th class="titledesc" scope="row">
                                        <label for="extra_cost_<?php echo $shipping_class->term_id; ?>"><?php echo $shipping_class->name; ?></label>
                                    </th>
                                    <td class="forminp">
                                        <input type="text" name="sm_extra_cost[<?php echo $shipping_class->term_id; ?>]" class="text-class" id="extra_cost_<?php echo $shipping_class->term_id; ?>" value="<?php echo htmlentities($shipping_extra_cost); ?>" placeholder="<?php echo get_woocommerce_currency_symbol(); ?>">
                                    </td>
                                </tr>
                            <?php } ?>
                            <tr valign="top">
                                <th class="titledesc" scope="row">
                                    <label for="sm_extra_cost_calculation_type"><?php _e('Calculation Type', AFRSM_TEXT_DOMAIN) ?></label>
                                </th>
                                <td class="forminp">
                                    <select name="sm_extra_cost_calculation_type" id="sm_extra_cost_calculation_type">
                                        <option value="per_class" <?php selected( $sm_extra_cost_calc_type, 'per_class' ); ?>><?php _e('Per Class: Charge shipping for each shipping class individually', AFRSM_TEXT_DOMAIN); ?></option>
                                        <option value="per_order" <?php selected( $sm_extra_cost_calc_type, 'per_order' ); ?>><?php _e('Per Order: Charge shipping for the most expensive shipping class', AFRSM_TEXT_DOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
            
            <div class="sub-title">
                <h2><?php _e('Shipping Method Rules', AFRSM_TEXT_DOMAIN); ?></h2>
                <div class="tap">
                    <a id="fee-add-field" class="button button-primary button-large" href="javascript:;"><?php _e('+ Add Rule', AFRSM_TEXT_DOMAIN); ?></a>
                </div>
            </div>
            <div class="tap">
                <table id="tbl-shipping-method" class="tbl_product_fee table-outer tap-cas form-table shipping-method-table">
                    <tbody>
                        <?php
                        if (isset($sm_metabox) && !empty($sm_metabox)) {
                            $i = 2;
                            foreach ($sm_metabox as $key => $productfees) {
                                $fees_conditions = isset($productfees['product_fees_conditions_condition']) ? $productfees['product_fees_conditions_condition'] : '';
                                $condition_is = isset($productfees['product_fees_conditions_is']) ? $productfees['product_fees_conditions_is'] : '';
                                $condtion_value = isset($productfees['product_fees_conditions_values']) ? $productfees['product_fees_conditions_values'] : array();
                                ?>
                                <tr id="row_<?php echo $i; ?>" valign="top">
                                    <th class="titledesc th_product_fees_conditions_condition" scope="row">
                                        <select rel-id="<?php echo $i; ?>" id="product_fees_conditions_condition_<?php echo $i; ?>" name="fees[product_fees_conditions_condition][]" id="product_fees_conditions_condition" class="product_fees_conditions_condition">
                                            <optgroup label="<?php _e('Location Specific', AFRSM_TEXT_DOMAIN); ?>">
                                                <option value="country" <?php echo ($fees_conditions == 'country' ) ? 'selected' : '' ?>><?php _e('Country', AFRSM_TEXT_DOMAIN); ?></option>
                                            </optgroup>
                                            <optgroup label="<?php _e('Product Specific', AFRSM_TEXT_DOMAIN); ?>">
                                                <option value="product" <?php echo ($fees_conditions == 'product' ) ? 'selected' : '' ?>><?php _e('Cart contains product', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="category" <?php echo ($fees_conditions == 'category' ) ? 'selected' : '' ?>><?php _e('Cart contains category\'s product', AFRSM_TEXT_DOMAIN); ?></option>
                                            </optgroup>
                                            <optgroup label="<?php _e('Cart Specific', AFRSM_TEXT_DOMAIN); ?>">
                                                <?php
                                                    $currency_symbol = get_woocommerce_currency_symbol();
                                                    $currency_symbol = !empty($currency_symbol) ? '(' . $currency_symbol . ')' : '';
                                                ?>
                                                <option value="cart_total" <?php echo ($fees_conditions == 'cart_total' ) ? 'selected' : '' ?>><?php _e('Cart Subtotal (Before Discount) ', AFRSM_TEXT_DOMAIN); ?><?php echo $currency_symbol; ?></option>
                                            </optgroup>
                                        </select>
                                    </th>
                                    <td class="select_condition_for_in_notin">
                                        <?php if ($fees_conditions == 'cart_total') { ?>
                                            <select name="fees[product_fees_conditions_is][]" class="product_fees_conditions_is_<?php echo $i; ?>">
                                                <option value="is_equal_to" <?php echo ($condition_is == 'is_equal_to' ) ? 'selected' : '' ?>><?php _e('Equal to ( = )', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="less_equal_to" <?php echo ($condition_is == 'less_equal_to' ) ? 'selected' : '' ?>><?php _e('Less or Equal to ( <= )', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="less_then" <?php echo ($condition_is == 'less_then' ) ? 'selected' : '' ?>><?php _e('Less than ( < )', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="greater_equal_to" <?php echo ($condition_is === 'greater_equal_to' ) ? 'selected' : '' ?>><?php _e('Greater or Equal to ( >= )', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="greater_then" <?php echo ($condition_is == 'greater_then' ) ? 'selected' : '' ?>><?php _e('Greater than ( > )', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="not_in" <?php echo ($condition_is == 'not_in' ) ? 'selected' : '' ?>><?php _e('Not Equal to ( != )', AFRSM_TEXT_DOMAIN); ?></option>
                                            </select>
                                        <?php } else { ?>
                                            <select name="fees[product_fees_conditions_is][]" class="product_fees_conditions_is_<?php echo $i; ?>">
                                                <option value="is_equal_to" <?php echo ($condition_is == 'is_equal_to' ) ? 'selected' : '' ?>><?php _e('Equal to ( = )', AFRSM_TEXT_DOMAIN); ?></option>
                                                <option value="not_in" <?php echo ($condition_is == 'not_in' ) ? 'selected' : '' ?>><?php _e('Not Equal to ( != )', AFRSM_TEXT_DOMAIN); ?> </option>
                                            </select>
                                        <?php } ?>
                                    </td>
                                    <td class="condition-value" id="column_<?php echo $i; ?>">
                                        <?php
                                            $html = '';
                                            if ($fees_conditions == 'country') {
                                                $html .= $this->afrsm_free_get_country_list($i, $condtion_value);
                                            } elseif ($fees_conditions == 'product') {
                                                $html .= $this->afrsm_free_get_product_list($i, $condtion_value);
                                            } elseif ($fees_conditions == 'category') {
                                                $html .= $this->afrsm_free_get_category_list($i, $condtion_value);
                                            } elseif ($fees_conditions == 'cart_total') {
                                                $html .= '<input type = "text" name = "fees[product_fees_conditions_values][value_' . $i . ']" id = "product_fees_conditions_values" class = "product_fees_conditions_values" value = "' . $condtion_value . '">';
                                            }
                                            echo $html;
                                        ?>
                                        <input type="hidden" name="condition_key[<?php echo 'value_' . $i; ?>]" value="">
                                    </td>
                                    <td><a id="fee-delete-field" rel-id="<?php echo $i; ?>" class="delete-row" href="javascript:;" title="Delete"><i class="fa fa-trash"></i></a></td>
                                </tr>
                        <?php
                                $i++;
                            }
                        ?>
                    <?php
                        } else {
                            $i = 1;
                    ?>	
                            <tr id="row_1" valign="top">
                                <th class="titledesc th_product_fees_conditions_condition" scope="row">
                                    <select rel-id="1" id="product_fees_conditions_condition_1" name="fees[product_fees_conditions_condition][]" id="product_fees_conditions_condition" class="product_fees_conditions_condition">
                                        <optgroup label="<?php _e('Location Specific', AFRSM_TEXT_DOMAIN); ?>">
                                            <option value="country"><?php _e('Country', AFRSM_TEXT_DOMAIN); ?></option>
                                        </optgroup>
                                        <optgroup label="<?php _e('Product Specific', AFRSM_TEXT_DOMAIN); ?>">
                                            <option value="product"><?php _e('Cart contains product', AFRSM_TEXT_DOMAIN); ?></option>
                                            <option value="category"><?php _e('Cart contains category\'s product', AFRSM_TEXT_DOMAIN); ?></option>
                                        </optgroup>
                                        <optgroup label="<?php _e('Cart Specific', AFRSM_TEXT_DOMAIN); ?>">
                                            <?php
                                            $currency_symbols = get_woocommerce_currency_symbol();
                                            $currency_symbol    = !empty($currency_symbols) ? '(' . $currency_symbols . ')' : ''; ?>
                                            <option value="cart_total"><?php _e('Cart Subtotal (Before Discount) ', AFRSM_TEXT_DOMAIN); ?><?php echo $currency_symbol; ?></option>
                                        </optgroup>
                                    </select>		
                                <td class="select_condition_for_in_notin">
                                    <select name="fees[product_fees_conditions_is][]" class="product_fees_conditions_is product_fees_conditions_is_1">
                                        <option value="is_equal_to"><?php _e('Equal to ( = )', AFRSM_TEXT_DOMAIN); ?></option>
                                        <option value="not_in"><?php _e('Not Equal to ( != )', AFRSM_TEXT_DOMAIN); ?></option>
                                    </select>
                                </td>
                                <td id="column_1" class="condition-value">
                                    <?php echo $this->afrsm_free_get_country_list(1); ?>
                                    <input type="hidden" name="condition_key[value_1][]" value="">
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <input type="hidden" name="total_row" id="total_row" value="<?php echo $i; ?>">
            </div>
            <p class="submit">
                <input type="submit" name="submitFee" class="button button-primary button-large" value="<?php echo $submit_text; ?>">
            </p>
        </form>
    </div>
</div>

<?php require_once('header/plugin-sidebar.php'); ?>