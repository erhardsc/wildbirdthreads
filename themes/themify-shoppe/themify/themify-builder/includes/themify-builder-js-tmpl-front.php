<script type="text/html" id="tmpl-builder_visual_sub_row_item">
		<div class="themify_builder_sub_row_top">
			<?php themify_builder_grid_lists('sub_row'); ?>
			<ul class="sub_row_action">
				<li><a href="#" data-title="<?php _e('Export', 'themify') ?>" rel="themify-tooltip-bottom"
				       class="themify_builder_export_component" data-component="sub-row">
						<span class="ti-export"></span>
				</a></li>
				<li><a href="#" data-title="<?php _e('Import', 'themify') ?>" rel="themify-tooltip-bottom"
					   class="themify_builder_import_component" data-component="sub-row">
						<span class="ti-import"></span>
				</a></li>
				<li class="separator"></li>
				<li><a href="#" data-title="<?php _e('Copy', 'themify') ?>" rel="themify-tooltip-bottom"
				       class="themify_builder_copy_component" data-component="sub-row">
						<span class="ti-files"></span>
					</a></li>
				<li><a href="#" data-title="<?php _e('Paste', 'themify') ?>" rel="themify-tooltip-bottom"
				       class="themify_builder_paste_component" data-component="sub-row">
						<span class="ti-clipboard"></span>
					</a></li>
				<li class="separator"></li>
				<li><a href="#" class="themify_builder_style_subrow" rel="themify-tooltip-bottom"
						data-title="<?php _e('Styling', 'themify') ?>">
						 <span class="ti-brush"></span>
				</a></li>
				<li><a href="#" class="sub_row_duplicate" rel="themify-tooltip-bottom"
				       data-title="<?php _e('Duplicate', 'themify') ?>">
						<span class="ti-layers"></span>
				</a></li>
				<li><a href="#" class="sub_row_delete" rel="themify-tooltip-bottom"
				       data-title="<?php _e('Delete', 'themify') ?>">
						<span class="ti-close"></span>
				</a></li>
			</ul>
		</div>
		<div class="sub_row_inner_wrapper">
			<div class="themify_builder_sub_row_content"></div>
		</div>
		<div class="subrow-data-styling" data-styling=""></div>
</script>

<script type="text/html" id="tmpl-builder_visual_row_item">
	<div class="themify_builder_row_top">

		<?php themify_builder_grid_lists('row'); ?>

		<ul class="row_action">
			<li><a href="#" data-title="<?php _e('Export', 'themify') ?>" class="themify_builder_export_component"
				   data-component="row" rel="themify-tooltip-bottom">
					<span class="ti-export"></span>
				</a></li>
			<li><a href="#" data-title="<?php _e('Import', 'themify') ?>" class="themify_builder_import_component"
				   data-component="row" rel="themify-tooltip-bottom">
					<span class="ti-import"></span>
				</a></li>
			<li class="separator"></li>
			<li><a href="#" data-title="<?php _e('Copy', 'themify') ?>" class="themify_builder_copy_component"
				   data-component="row" rel="themify-tooltip-bottom">
					<span class="ti-files"></span>
				</a></li>
			<li><a href="#" data-title="<?php _e('Paste', 'themify') ?>" class="themify_builder_paste_component"
				   data-component="row" rel="themify-tooltip-bottom">
					<span class="ti-clipboard"></span>
				</a></li>
			<li class="separator"></li>
			<li><a href="#" data-title="<?php _e('Options', 'themify') ?>" class="themify_builder_option_row"
				   rel="themify-tooltip-bottom">
					<span class="ti-pencil"></span>
				</a></li>
			<li><a href="#" data-title="<?php _e('Styling', 'themify') ?>" class="themify_builder_style_row"
				   rel="themify-tooltip-bottom">
					<span class="ti-brush"></span>
				</a></li>
			<li><a href="#" data-title="<?php _e('Duplicate', 'themify') ?>" class="themify_builder_duplicate_row"
				   rel="themify-tooltip-bottom">
					<span class="ti-layers"></span>
				</a></li>
			<li><a href="#" data-title="<?php _e('Delete', 'themify') ?>" class="themify_builder_delete_row"
				   rel="themify-tooltip-bottom">
					<span class="ti-close"></span>
				</a></li>
			<li class="separator"></li>
			<li><a href="#" data-title="<?php _e('Toggle Row', 'themify') ?>" class="themify_builder_toggle_row">
					<span class="ti-angle-up"></span>
				</a></li>
		</ul>
	</div><!-- /.row_top -->

	<div class="row_inner_wrapper">
		<div class="row_inner">
			<div class="themify_builder_row_content"></div>
			<div class="row-data-styling" data-styling=""></div>
		</div>
	</div>
	<div class="empty_holder_text"></div>
</script>

<script type="text/html" id="tmpl-builder_visual_column_item">
	<div class="themify_grid_drag themify_drag_right"></div>
	<div class="themify_grid_drag themify_drag_left"></div>
	<ul class="themify_builder_column_action">
		<li class="menu_icon"><a href="#" class="js-column-menu-icon"><span class="ti-menu"></span></a>
			<ul>
				<li><a href="#" class="themify_builder_option_column" data-title="<?php esc_html_e( 'Styling', 'themify' );?>" rel="themify-tooltip-bottom"><span class="ti-brush"></span> <?php esc_html_e( 'Styling', 'themify' );?></a></li>
				<li class="separator"></li>
				<li><a href="#" class="themify_builder_export_component" data-title="<?php esc_html_e( 'Export', 'themify' );?>" rel="themify-tooltip-bottom" data-component="{{ data.component_name }}"><span class="ti-export"></span> <?php esc_html_e( 'Export', 'themify' );?></a></li>
				<li><a href="#" class="themify_builder_import_component" data-title="<?php esc_html_e( 'Import', 'themify' );?>" rel="themify-tooltip-bottom" data-component="{{ data.component_name }}"><span class="ti-import"></span> <?php esc_html_e( 'Import', 'themify' );?></a></li>
				<li class="separator"></li>
				<li><a href="#" class="themify_builder_copy_component" data-title="<?php esc_html_e( 'Copy', 'themify' );?>" rel="themify-tooltip-bottom" data-component="{{ data.component_name }}"><span class="ti-files"></span> <?php esc_html_e( 'Copy', 'themify' );?></a></li>
				<li><a href="#" class="themify_builder_paste_component" data-title="<?php esc_html_e( 'Paste', 'themify' );?>" rel="themify-tooltip-bottom" data-component="{{ data.component_name }}"><span class="ti-clipboard"></span> <?php esc_html_e( 'Paste', 'themify' );?></a></li>
				<li class="separator last-sep"></li>
			</ul>
		</li>
		<li class="themify_builder_column_dragger_li"><a href="#" class="themify_builder_column_dragger"><span class="ti-arrows-horizontal"></span></a></li>
	</ul>
	<div class="tb-column-inner">
		<div class="themify_module_holder"></div>
	</div>
	<div class="column-data-styling" data-styling=""></div>
</script>

<script type="text/html" id="tmpl-builder_visual_module_item">
	<div class="themify_builder_module_front_overlay"></div>
	<div class="module_menu_front">
		<ul class="themify_builder_dropdown_front">
			<li class="themify_module_menu"><span class="ti-menu"></span>
				<ul>
					<li><a href="#" data-title="<?php _e('Edit', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_module_options" data-module-name="<?php echo esc_attr($mod_name); ?>">
	<?php _e('Edit', 'themify') ?>
						</a></li>
					<li><a href="#" data-title="<?php _e('Styling', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_builder_module_styling js--themify_builder_module_styling ti-brush" data-module-name="<?php echo esc_attr($mod_name); ?>">
	<?php _e('Styling', 'themify') ?>
						</a></li>
					<li><a href="#" data-title="<?php _e('Duplicate', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_module_duplicate">
	<?php _e('Duplicate', 'themify') ?>
						</a></li>
					<li class="separator">
						<div></div>
					</li>
					<li><a href="#" data-title="<?php _e('Export', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_builder_export_component ti-export" data-component="module">
	<?php _e('Export', 'themify') ?>
						</a></li>
					<li><a href="#" data-title="<?php _e('Import', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_builder_import_component ti-import" data-component="module">
	<?php _e('Import', 'themify') ?>
						</a></li>
					<li class="separator">
						<div></div>
					</li>
					<li><a href="#" data-title="<?php _e('Copy', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_builder_copy_component ti-files" data-component="module">
	<?php _e('Copy', 'themify') ?>
						</a></li>
					<li><a href="#" data-title="<?php _e('Paste', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_builder_paste_component ti-clipboard" data-component="module">
	<?php _e('Paste', 'themify') ?>
						</a></li>
					<li class="separator"><div></div></li>
					<li><a href="#" data-title="<?php _e('Delete', 'themify') ?>" rel="themify-tooltip-bottom"
						   class="themify_module_delete">
	<?php _e('Delete', 'themify') ?>
						</a></li>
				</ul>
			</li>
		</ul>
		<div class="themify_module_settings mod_settings_{{ data.slug }}" data-mod-name="{{ data.slug }}">
			<# print("<sc" + "ript type='text/json'>"); #>
			<# print("</sc"+"ript>"); #>
		</div>
	</div>
	<div class="themify_builder_data_mod_name">{{ data.name }}</div>
</script>

<?php

foreach( Themify_Builder_Model::$modules as $module ) {
	$module->print_template();
}