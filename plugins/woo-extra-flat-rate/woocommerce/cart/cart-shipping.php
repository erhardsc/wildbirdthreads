<?php
/**
 * Shipping Methods Display
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<tr class="shipping">
    <th><?php echo wp_kses_post($package_name); ?></th>
    <td data-title="<?php echo esc_attr($package_name); ?>">
        <?php
            $shipping_method_format = get_option('md_woocommerce_shipping_method_format');
            $shipping_method_format = !empty( $shipping_method_format ) ? $shipping_method_format : 'radio_button_mode';
        ?>
        
        <?php if (1 < count($available_methods)) { ?>
            <?php
                $sort_order = array();
                $getSortOrder = get_option('sm_sortable_order');
                
                if (isset($getSortOrder) && !empty($getSortOrder)) {
                    foreach ($getSortOrder as $sort) {
                        $sort_order[$sort] = array();
                    }
                }
                
                foreach ($available_methods as $carrier_id => $carrier) {
                    $carrier_name = $carrier->id;

                    if (array_key_exists($carrier_name, $sort_order)) {
                        $sort_order[$carrier_name][$carrier_id] = $available_methods[$carrier_id];
                        unset($available_methods[$carrier_id]);
                    }
                }
                foreach ($sort_order as $carriers) {
                    $available_methods = array_merge($available_methods, $carriers);
                }
                
            ?>
            <?php if( $shipping_method_format === 'dropdown_mode' ) { ?>
                <select name="shipping_method[<?php echo $index; ?>]" data-index="<?php echo $index; ?>" id="shipping_method_<?php echo $index; ?>" class="shipping_method">
                    <?php foreach ($available_methods as $method) { ?>
                        <option value="<?php echo esc_attr($method->id); ?>" <?php selected($method->id, $chosen_method); ?>><?php echo wp_kses_post(wc_cart_totals_shipping_method_label($method)); ?></option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <ul id="shipping_method">
                    <?php foreach ($available_methods as $method) { ?>
                        <li>
                            <?php
                                $tool_tip_html = '';
                                $final_shipping_label = '';

                                $sm_tooltip_desc = get_post_meta($method->id, 'sm_tooltip_desc', true);
                                $sm_tooltip_desc = ( isset($sm_tooltip_desc) && !empty($sm_tooltip_desc) ) ? $sm_tooltip_desc : '';

                                $final_shipping_label  .= $sm_tooltip_desc;
                                
                                if(!empty($final_shipping_label)) {
                                    $tool_tip_html .= '<div class="extra-flat-tool-tip"><a data-tooltip="' . $final_shipping_label . '"><i class="fa fa-question-circle fa-lg"></i></a></div>';
                                }
                                
                                printf($tool_tip_html . '<label class="shipping_method_tooltip" id="hover-tips" for="shipping_method_%1$d_%2$s"><input type="radio" name="shipping_method_[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />
                                                                            %5$s</label>', $index, sanitize_title( $method->id ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ), wc_cart_totals_shipping_method_label( $method ));
                                do_action('woocommerce_after_shipping_rate', $method, $index);
                            ?>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        
        <?php } elseif (1 === count($available_methods)) { ?>
        
            <?php
                /* Tool tip html start */
                $tool_tip_html = '';
                $final_shipping_label = '';

                $method = current($available_methods);
                $chosen_shipping_methods_array = explode(' ', $method->id);
                WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods_array);
                
                $sm_tooltip_desc = get_post_meta($method->id, 'sm_tooltip_desc', true);
                $sm_tooltip_desc = ( isset($sm_tooltip_desc) && !empty($sm_tooltip_desc) ) ? $sm_tooltip_desc : '';

                $final_shipping_label .= $sm_tooltip_desc;

                if (!empty($sm_tooltip_desc)) {
                    $tool_tip_html .= '<div class="extra-flat-tool-tip"><a data-tooltip="' . $final_shipping_label . '"><i class="fa fa-question-circle fa-lg"></i></a></div>';
                }
                
                printf($tool_tip_html . ' %3$s <input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d" value="%2$s" class="shipping_method" />', $index, esc_attr($method->id), wc_cart_totals_shipping_method_label($method) );
                do_action('woocommerce_after_shipping_rate', $method, $index);
                /* Tool tip html end */
            ?>
        
        <?php } elseif (!WC()->customer->has_calculated_shipping()) { ?>
            <?php echo wpautop(__('Shipping costs will be calculated once you have provided your address.', 'woocommerce')); ?>
        <?php } else { ?>
            <?php echo apply_filters(is_cart() ? 'woocommerce_cart_no_shipping_available_html' : 'woocommerce_no_shipping_available_html', wpautop(__('There are no shipping methods available. Please double check your address, or contact us if you need any help.', 'woocommerce'))); ?>
        <?php } ?>

        <?php if ($show_package_details) { ?>
            <?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html($package_details) . '</small></p>'; ?>
        <?php } ?>

        <?php if (!empty($show_shipping_calculator)) { ?>
            <?php woocommerce_shipping_calculator(); ?>
        <?php } ?>
    </td>
</tr>