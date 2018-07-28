jQuery(document).ready(function($) {
	$( document.body ).on( 'wc_backbone_modal_loaded', function() {
		init_wcs_conditions();
	});

	/**
	 * Insert existing conditions to the table
	 */
	function init_wcs_conditions() {
		var table = $('table.wcs-conditions-table');
		if ( table.length > 0 ) {
			for (var i = 0; i < table.data('conditions').length; i++) {
				add_wcs_condition(table, table.data('conditions')[i]);
			}
		}

		$( document.body ).trigger( 'wc-enhanced-select-init' );
		wcs_toggle_all_value_inputs();
		init_wcs_autocomplete_fields();
	}
	init_wcs_conditions();

	/**
	 * Init autocomplete product fields
	 */
	function init_wcs_autocomplete_fields() {
		$('select.wcs_product_value_ajax_input:not(.inited)').each(function() {
			$(this).addClass('inited');

			$(this).select2({
			  ajax: {
			    url: ajaxurl,
			    dataType: 'json',
					data: function (params) {
						var query = {
							q: params.term,
							action: 'wcs_product_search'
						}

						return query;
					}
			  }
			});
		});
	}

	/**
	 * Show correct fields when changing condition type
	 */
	$(document).on('change', 'select.wcs_condition_type_select', function() {
		var row = $(this).closest('tr');
		wcs_toggle_value_inputs($(this).val(), row);
	});

	/**
	 * Toggle value inputs based on condition type
	 */
	function wcs_toggle_all_value_inputs() {
		$('table.wcs-conditions-table tbody tr').each(function() {
			var type = $('select.wcs_condition_type_select', this).val();
			wcs_toggle_value_inputs(type, $(this));
		});
	}

	/**
	 * Display correct value input when changing condition type
	 */
	function wcs_toggle_value_inputs(type, row) {
		$('fieldset.wcs_condition_value_inputs .value_input', row).hide();

		if (type.indexOf('category') !== -1) {
			$('fieldset.wcs_condition_value_inputs .wcs_category_value_input', row).show();
		} else if (type.indexOf('shipping_class') !== -1) {
			$('fieldset.wcs_condition_value_inputs .wcs_shipping_class_value_input', row).show();
		} else if (type.indexOf('product') !== -1) {
			$('fieldset.wcs_condition_value_inputs .wcs_product_value_input', row).show();
		} else {
			$('fieldset.wcs_condition_value_inputs .wcs_text_value_input', row).show();
		}
	}

	/**
	 * Remove selected conditions when clicking the button
	 */
	$(document).on('click', 'button#wcs-remove-conditions', function() {
		var table = $(this).closest('table.wcs-conditions-table');

		$('.condition_row input.remove_condition:checked', table).closest('tr.condition_row').remove();
	});

	/**
	 * Add new condition to the table
	 */
	function add_wcs_condition(table, data) {
		// Get index
		var index = table.data('index');
		if (typeof index == 'undefined') { index = 0; }
		data['index'] = index;

		// Get instance ID
		var instance_id = table.data( 'instance-id' );

		// Add one to conditions table index
		table.data('index', index + 1);

		// Get template
		var row_template = wp.template( 'wcs_row_template_' + instance_id );

		// Add products
		var products_data = table.data('selected-products');
		data.selected_products = [];
		if (typeof data.product_ids !== 'undefined' && data.product_ids !== null && data.product_ids.length > 0) {
			jQuery.each(data.product_ids, function(index, product_id) {
				if (typeof products_data[product_id] !== 'undefined') {
					data.selected_products.push({
						'id': product_id,
						'title': products_data[product_id]
					});
				}
			});
		}

		// Render template and add to the table
		$('tbody', table).append( row_template(data) );

		$( document.body ).trigger( 'wc-enhanced-select-init' );
		wcs_toggle_all_value_inputs();
		init_wcs_autocomplete_fields();
	}

	/**
	 * Add new condition when clicking the Add button
	 */
	$(document).on('click', 'button#wcs-add-condition', function() {
		var table = $(this).closest('table.wcs-conditions-table');
		add_wcs_condition(table, {});
	});
});
