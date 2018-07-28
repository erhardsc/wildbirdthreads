(function($) {
    'use strict';
    $(window).load(function() {
        $(".chosen-select").chosen();
        
        jQuery("div.dialogButtons .ui-dialog-buttonset button").removeClass('ui-state-default');
        jQuery("div.dialogButtons .ui-dialog-buttonset button").addClass("button-primary woocommerce-save-button");
        jQuery(".multiselect2").chosen();

        var ele = $('#total_row').val();
        if (ele > 2) {
            var count = ele;
        } else {
            var count = 2;
        }
        $('body').on('click', '#fee-add-field', function() {
            var tds = '<tr id=row_' + count + '>';
            tds += '<td><select rel-id=' + count + ' id=product_fees_conditions_condition_' + count + ' name="fees[product_fees_conditions_condition][]" class="product_fees_conditions_condition"><optgroup label="Location Specific"><option value="country">Country</option></optgroup><optgroup label="Product Specific"><option value="product">Cart contains product</option><option value="category">Cart contains category\'s product</option></optgroup><optgroup label="Cart Specific"><option value="cart_total">Cart Subtotal (Before Discount)</option></optgroup></select></td>';
            tds += '<td><select name="fees[product_fees_conditions_is][]" class="product_fees_conditions_is product_fees_conditions_is_' + count + '"><option value="is_equal_to">Equal to ( = )</option><option value="not_in">Not Equal to ( != )</option></select></td>';
            tds += '<td id=column_' + count + '><select name="fees[product_fees_conditions_values][value_' + count + '][]" class="product_fees_conditions_values product_fees_conditions_values_' + count + ' multiselect2" multiple="multiple"></select><input type="hidden" name="condition_key[value_' + count + '][]" value=""></td>';
            tds += '<td><a id="fee-delete-field" rel-id="' + count + '" title="Delete" class="delete-row" href="javascript:;"><i class="fa fa-trash"></i></a></td>';
            tds += '</tr>';
            $('#tbl-shipping-method').append(tds);
            jQuery(".product_fees_conditions_values_" + count).append(jQuery(".default-country-box select").html());
            jQuery(".product_fees_conditions_values_" + count).trigger("chosen:updated");
            jQuery(".multiselect2").chosen();
            count++;
        });
        $('body').on('click', '#fee-delete-field', function() {
            var deleId = $(this).attr('rel-id');
            $("#row_" + deleId).remove();
        });
        $('body').on('change', '.product_fees_conditions_condition', function() {
            var condition = $(this).val();
            var count = $(this).attr('rel-id');
            $('#column_' + count).html('<img src="' + coditional_vars.plugin_url + 'images/ajax-loader.gif">');
            var data = {
                'action': 'afrsm_free_product_fees_conditions_values_ajax',
                'condition': condition,
                'count': count
            };
            // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
            jQuery.post(ajaxurl, data, function(response) {
                if (condition == 'cart_total') {
                    jQuery('.product_fees_conditions_is_' + count).html('');
                    jQuery('.product_fees_conditions_is_' + count).append(jQuery(".text-condtion-is select.text-condition").html());
                    jQuery('.product_fees_conditions_is_' + count).trigger("chosen:updated");
                } else {
                    jQuery('.product_fees_conditions_is_' + count).html('');
                    jQuery('.product_fees_conditions_is_' + count).append(jQuery(".text-condtion-is select.select-condition").html());
                    jQuery('.product_fees_conditions_is_' + count).trigger("chosen:updated");
                }
                $('#column_' + count).html('');
                $('#column_' + count).append(response);
                $('#column_' + count).append('<input type="hidden" name="condition_key[value_' + count + '][]" value="">');
                jQuery(".multiselect2").chosen();
                if (condition == 'product') {
                    $('#product_filter_chosen input').val('Please enter 3 or more characters');
                }

            });
        });
        $('body').on('keyup', '#product_filter_chosen input', function() {
            var countId = $(this).closest("td").attr('id');
            $('#product_filter_chosen ul li.no-results').html('Please enter 3 or more characters');
            var value = $(this).val();
            var valueLenght = value.replace(/\s+/g, '');
            var valueCount = valueLenght.length;
            var remainCount = 3 - valueCount;
            if (valueCount >= 3) {
                $('#product_filter_chosen ul li.no-results').html('<img src="' + coditional_vars.plugin_url + 'images/ajax-loader.gif">');
                var data = {
                    'action': 'afrsm_free_product_fees_conditions_values_product_ajax',
                    'value': value
                };
                jQuery.post(ajaxurl, data, function(response) {

                    if (response.length != 0) {
                        $('#' + countId + ' #product-filter').append(response);
                    } else {
                        $('#product-filter option').not(':selected').remove();
                    }
                    $('#' + countId + ' #product-filter option').each(function() {
                        $(this).siblings("[value='" + this.value + "']").remove();
                    });
                    jQuery('#' + countId + ' #product-filter').trigger("chosen:updated");
                    $('#product_filter_chosen .search-field input').val(value);
                    $('#' + countId + ' #product-filter').chosen().change(function() {
                        var productVal = $('#' + countId + ' #product-filter').chosen().val();
                        jQuery('#' + countId + ' #product-filter option').each(function() {
                            $(this).siblings("[value='" + this.value + "']").remove();
                            if (jQuery.inArray(this.value, productVal) == -1) {
                                jQuery(this).remove();
                            }
                        });
                        jQuery('#' + countId + ' #product-filter').trigger("chosen:updated");
                    });
                    $('#product_filter_chosen ul li.no-results').html('');
                });
            } else {
                if (remainCount > 0) {
                    $('#product_filter_chosen ul li.no-results').html('Please enter ' + remainCount + ' or more characters');
                }
            }
        });
        $(".condition-check-all").click(function() {
            $('input.multiple_delete_fee:checkbox').not(this).prop('checked', this.checked);
        });
        $('#delete-shipping-method').click(function() {
            if ($('.multiple_delete_fee:checkbox:checked').length == 0) {
                alert('Please select at least one shipping method');
                return false;
            }
            if (confirm('Are You Sure You Want to Delete?')) {
                var allVals = [];
                $(".multiple_delete_fee:checked").each(function() {
                    allVals.push($(this).val());
                });
                var data = {
                    'action': 'afrsm_free_wc_multiple_delete_shipping_method',
                    'allVals': allVals
                };
                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data, function(response) {
                    if (response == 1) {
                        alert('Delete Successfully');
                        $(".multiple_delete_fee").prop("checked", false);
                        location.reload();
                    }
                });
            }
        });
        /* description toggle */
        $('span.advanced_flat_rate_shipping_for_woocommerce_tab_description').click(function(event) {
            event.preventDefault();
            var data = $(this);
            $(this).next('p.description').toggle();
            //$('span.advance_extra_flat_rate_disctiption_tab').next('p.description').toggle();
        });
    });
    jQuery(document).ready(function($) {
        $(".tablesorter").tablesorter({
            headers: {
                0: {
                    sorter: false
                },
                4: {
                    sorter: false
                }
            }
        });
        var fixHelperModified = function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width());
            });
            return $helper;
        };
        //Make diagnosis table sortable
        $("table#shipping-methods-listing tbody").sortable({
            helper: fixHelperModified
        });
        $("table#shipping-methods-listing tbody").disableSelection();
        
        $(document).on( 'click', '.shipping-methods-order', function() {
            var smOrderArray = [];
            
            $('table#shipping-methods-listing tbody tr').each(function() {
                smOrderArray.push(this.id);
            });
            
            var data = {
                'action': 'sm_sort_order',
                'smOrderArray': smOrderArray
            };
            
            // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
            jQuery.post(ajaxurl, data, function(response) {
                alert('Shipping method order saved successfully');
            });
            
        });
        
        //Save Master Settings
        $(document).on('click', '#save_master_settings', function() {
            
            var shipping_display_mode = $('#shipping_display_mode').val();

            var data = {
                'action': 'save_master_settings',
                'shipping_display_mode': shipping_display_mode
            };

            // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
            jQuery.post(ajaxurl, data, function(response) {
                
                $('<div class="ms-msg">Your settings successfully saved.</div>').insertBefore( ".afrsm-section-left .afrsm-main-table" );
                $("html, body").animate({ scrollTop: 0 }, "slow");
                setTimeout(function(){
                    $('.ms-msg').remove();
                }, 1000);
                
            });

        });
       
    });
})(jQuery);