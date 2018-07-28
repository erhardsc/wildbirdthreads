<?php

// time: 1495432373


function themify_import_get_term_id_from_slug( $slug ) {
	$menu = get_term_by( "slug", $slug, "nav_menu" );
	return is_wp_error( $menu ) ? 0 : (int) $menu->term_id;
}

	$widgets = get_option( "widget_text" );
$widgets[1002] = array (
  'title' => '',
  'text' => 'Shoppe is a powerful shop theme created by Themify. It’s powered by WooCommerce and is highly customizable.

[themify_button link="https://themify.me/themes/shoppe" style="small outline black rect"]Download[/themify_button]',
  'filter' => true,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1003] = array (
  'title' => 'Search',
  'text' => '[searchandfilter id="sidebar_filter"]',
  'filter' => false,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1004] = array (
  'title' => 'Social',
  'show_link_name' => 'on',
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
  'orientation' => 'vertical',
);
update_option( "widget_themify-social-links", $widgets );

$widgets = get_option( "widget_woocommerce_product_categories" );
$widgets[1005] = array (
  'title' => 'Categories',
  'orderby' => 'name',
  'dropdown' => 0,
  'count' => 0,
  'hierarchical' => 1,
  'show_children_only' => 0,
  'hide_empty' => 0,
);
update_option( "widget_woocommerce_product_categories", $widgets );

$widgets = get_option( "widget_nav_menu" );
$widgets[1006] = array (
  'title' => 'About',
  'nav_menu' => themify_import_get_term_id_from_slug( "footer-about-menu" ),
);
update_option( "widget_nav_menu", $widgets );

$widgets = get_option( "widget_nav_menu" );
$widgets[1007] = array (
  'title' => 'Support',
  'nav_menu' => themify_import_get_term_id_from_slug( "footer-support-menu" ),
);
update_option( "widget_nav_menu", $widgets );



$sidebars_widgets = array (
  'below-logo-widget' => 
  array (
    0 => 'text-1002',
  ),
  'sidebar-shop' => 
  array (
    0 => 'text-1003',
  ),
  'footer-widget-1' => 
  array (
    0 => 'themify-social-links-1004',
  ),
  'footer-widget-2' => 
  array (
    0 => 'woocommerce_product_categories-1005',
  ),
  'footer-widget-3' => 
  array (
    0 => 'nav_menu-1006',
  ),
  'footer-widget-4' => 
  array (
    0 => 'nav_menu-1007',
  ),
); 
update_option( "sidebars_widgets", $sidebars_widgets );

$menu_locations = array();
$menu = get_terms( "nav_menu", array( "slug" => "main-navigation" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["main-nav"] = $menu[0]->term_id;
$menu = get_terms( "nav_menu", array( "slug" => "icon-navigation-menu" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["icon-menu"] = $menu[0]->term_id;
$menu = get_terms( "nav_menu", array( "slug" => "footer-about-menu" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["footer-nav"] = $menu[0]->term_id;
set_theme_mod( "nav_menu_locations", $menu_locations );


$homepage = get_posts( array( 'name' => 'home', 'post_type' => 'page' ) );
			if( is_array( $homepage ) && ! empty( $homepage ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $homepage[0]->ID );
			}
			
	ob_start(); ?>a:263:{s:15:"setting-favicon";s:0:"";s:23:"setting-custom_feed_url";s:0:"";s:19:"setting-header_html";s:0:"";s:19:"setting-footer_html";s:0:"";s:23:"setting-search_settings";s:0:"";s:16:"setting-page_404";s:1:"0";s:21:"setting-feed_settings";s:0:"";s:21:"setting-webfonts_list";s:11:"recommended";s:24:"setting-webfonts_subsets";s:0:"";s:22:"setting-default_layout";s:12:"sidebar-none";s:27:"setting-default_post_layout";s:16:"list-large-image";s:27:"setting-post_content_layout";s:0:"";s:20:"setting-post_masonry";s:3:"yes";s:19:"setting-post_gutter";s:6:"gutter";s:30:"setting-default_layout_display";s:7:"excerpt";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:26:"setting-default_post_title";s:0:"";s:33:"setting-default_unlink_post_title";s:0:"";s:25:"setting-default_post_meta";s:0:"";s:32:"setting-default_post_meta_author";s:0:"";s:34:"setting-default_post_meta_category";s:0:"";s:33:"setting-default_post_meta_comment";s:0:"";s:29:"setting-default_post_meta_tag";s:0:"";s:25:"setting-default_post_date";s:0:"";s:30:"setting-default_media_position";s:5:"above";s:26:"setting-default_post_image";s:0:"";s:33:"setting-default_unlink_post_image";s:0:"";s:31:"setting-image_post_feature_size";s:5:"blank";s:24:"setting-image_post_width";s:0:"";s:25:"setting-image_post_height";s:0:"";s:32:"setting-default_page_post_layout";s:12:"sidebar-none";s:31:"setting-default_page_post_title";s:0:"";s:38:"setting-default_page_unlink_post_title";s:0:"";s:30:"setting-default_page_post_meta";s:0:"";s:37:"setting-default_page_post_meta_author";s:0:"";s:39:"setting-default_page_post_meta_category";s:0:"";s:38:"setting-default_page_post_meta_comment";s:0:"";s:34:"setting-default_page_post_meta_tag";s:0:"";s:30:"setting-default_page_post_date";s:0:"";s:31:"setting-default_page_post_image";s:0:"";s:38:"setting-default_page_unlink_post_image";s:0:"";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:31:"setting-image_post_single_width";s:4:"1160";s:32:"setting-image_post_single_height";s:3:"550";s:27:"setting-default_page_layout";s:12:"sidebar-none";s:23:"setting-hide_page_title";s:0:"";s:23:"setting-hide_page_image";s:0:"";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1024";s:43:"setting-customizer_responsive_design_tablet";s:3:"716";s:43:"setting-customizer_responsive_design_mobile";s:3:"414";s:33:"setting-mobile_menu_trigger_point";s:4:"1000";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:26:"setting-page_builder_cache";s:2:"on";s:18:"setting-cache_gzip";s:2:"on";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:21:"setting-header_design";s:18:"header-logo-center";s:21:"setting-footer_design";s:18:"footer-left-column";s:22:"setting-footer_widgets";s:17:"footerwidget-4col";s:30:"setting-footer_widget_position";s:0:"";s:27:"setting-imagefilter_options";s:0:"";s:33:"setting-imagefilter_options_hover";s:0:"";s:27:"setting-imagefilter_applyto";s:12:"featuredonly";s:18:"setting-more_posts";s:8:"infinite";s:19:"setting-entries_nav";s:8:"numbered";s:30:"settings-footer_banner_twitter";s:7:"twitter";s:39:"settings-footer_banner_twitter_username";s:8:"@themify";s:35:"settings-footer_banner_twitter_link";s:27:"https://twitter.com/themify";s:36:"settings-footer_banner_twitter_image";s:76:"https://themify.me/demo/themes/shoppe/files/2016/11/footer-share-twitter.jpg";s:31:"settings-footer_banner_facebook";s:8:"facebook";s:40:"settings-footer_banner_facebook_username";s:7:"Themify";s:36:"settings-footer_banner_facebook_link";s:32:"https://www.facebook.com/themify";s:37:"settings-footer_banner_facebook_image";s:77:"https://themify.me/demo/themes/shoppe/files/2016/11/footer-share-facebook.jpg";s:32:"settings-footer_banner_pinterest";s:9:"pinterest";s:41:"settings-footer_banner_pinterest_username";s:7:"Themify";s:37:"settings-footer_banner_pinterest_link";s:26:"https://www.pinterest.com/";s:38:"settings-footer_banner_pinterest_image";s:78:"https://themify.me/demo/themes/shoppe/files/2016/11/footer-share-pinterest.jpg";s:39:"settings-footer_banner_youtube_username";s:0:"";s:35:"settings-footer_banner_youtube_link";s:0:"";s:36:"settings-footer_banner_youtube_image";s:0:"";s:40:"settings-footer_banner_linkedin_username";s:0:"";s:36:"settings-footer_banner_linkedin_link";s:0:"";s:37:"settings-footer_banner_linkedin_image";s:0:"";s:38:"settings-footer_banner_google_username";s:0:"";s:34:"settings-footer_banner_google_link";s:0:"";s:35:"settings-footer_banner_google_image";s:0:"";s:24:"setting-footer_text_left";s:0:"";s:25:"setting-footer_text_right";s:0:"";s:19:"setting-shop_layout";s:8:"sidebar1";s:27:"setting-shop_archive_layout";s:8:"sidebar1";s:23:"setting-products_layout";s:5:"grid3";s:30:"setting-product_content_layout";s:0:"";s:27:"setting-product_post_gutter";s:6:"gutter";s:23:"setting-products_slider";s:6:"enable";s:30:"setting-shop_products_per_page";s:1:"9";s:34:"setting-product_archive_hide_title";s:0:"";s:34:"setting-product_archive_hide_price";s:0:"";s:23:"setting-hide_shop_count";s:2:"on";s:34:"setting-product_archive_show_short";s:0:"";s:46:"setting-default_product_index_image_post_width";s:3:"262";s:47:"setting-default_product_index_image_post_height";s:3:"287";s:29:"setting-single_product_layout";s:12:"sidebar-none";s:28:"setting-product_image_layout";s:8:"img-left";s:47:"setting-default_product_single_image_post_width";s:3:"520";s:48:"setting-default_product_single_image_post_height";s:3:"574";s:28:"setting-product_gallery_type";s:4:"zoom";s:30:"setting-related_products_limit";s:1:"3";s:35:"setting-product_related_image_width";s:0:"";s:36:"setting-product_related_image_height";s:0:"";s:21:"setting-wishlist_page";s:3:"253";s:18:"setting-cart_style";s:8:"dropdown";s:19:"setting-spark_color";s:0:"";s:27:"setting-global_feature_size";s:5:"blank";s:22:"setting-link_icon_type";s:9:"font-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:32:"setting-link_link_themify-link-0";s:0:"";s:31:"setting-link_img_themify-link-0";s:101:"https://themify.me/demo/themes/shoppe/wp-content/themes/themify-shoppe/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:32:"setting-link_link_themify-link-1";s:0:"";s:31:"setting-link_img_themify-link-1";s:102:"https://themify.me/demo/themes/shoppe/wp-content/themes/themify-shoppe/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:32:"setting-link_link_themify-link-2";s:0:"";s:31:"setting-link_img_themify-link-2";s:105:"https://themify.me/demo/themes/shoppe/wp-content/themes/themify-shoppe/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:32:"setting-link_link_themify-link-3";s:0:"";s:31:"setting-link_img_themify-link-3";s:101:"https://themify.me/demo/themes/shoppe/wp-content/themes/themify-shoppe/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:32:"setting-link_link_themify-link-4";s:0:"";s:31:"setting-link_img_themify-link-4";s:103:"https://themify.me/demo/themes/shoppe/wp-content/themes/themify-shoppe/themify/img/social/pinterest.png";s:32:"setting-link_type_themify-link-5";s:9:"font-icon";s:33:"setting-link_title_themify-link-5";s:7:"Twitter";s:32:"setting-link_link_themify-link-5";s:27:"https://twitter.com/themify";s:33:"setting-link_ficon_themify-link-5";s:14:"ti-twitter-alt";s:35:"setting-link_ficolor_themify-link-5";s:0:"";s:37:"setting-link_fibgcolor_themify-link-5";s:0:"";s:32:"setting-link_type_themify-link-6";s:9:"font-icon";s:33:"setting-link_title_themify-link-6";s:8:"Facebook";s:32:"setting-link_link_themify-link-6";s:32:"https://www.facebook.com/themify";s:33:"setting-link_ficon_themify-link-6";s:11:"ti-facebook";s:35:"setting-link_ficolor_themify-link-6";s:0:"";s:37:"setting-link_fibgcolor_themify-link-6";s:0:"";s:32:"setting-link_type_themify-link-8";s:9:"font-icon";s:33:"setting-link_title_themify-link-8";s:7:"YouTube";s:32:"setting-link_link_themify-link-8";s:39:"https://www.youtube.com/user/themifyme/";s:33:"setting-link_ficon_themify-link-8";s:10:"ti-youtube";s:35:"setting-link_ficolor_themify-link-8";s:0:"";s:37:"setting-link_fibgcolor_themify-link-8";s:0:"";s:32:"setting-link_type_themify-link-9";s:9:"font-icon";s:33:"setting-link_title_themify-link-9";s:9:"Pinterest";s:32:"setting-link_link_themify-link-9";s:25:"https://www.pinterest.com";s:33:"setting-link_ficon_themify-link-9";s:12:"ti-pinterest";s:35:"setting-link_ficolor_themify-link-9";s:0:"";s:37:"setting-link_fibgcolor_themify-link-9";s:0:"";s:22:"setting-link_field_ids";s:307:"{"themify-link-0":"themify-link-0","themify-link-1":"themify-link-1","themify-link-2":"themify-link-2","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4","themify-link-5":"themify-link-5","themify-link-6":"themify-link-6","themify-link-8":"themify-link-8","themify-link-9":"themify-link-9"}";s:23:"setting-link_field_hash";s:2:"10";s:30:"setting-page_builder_is_active";s:6:"enable";s:41:"setting-page_builder_animation_appearance";s:0:"";s:42:"setting-page_builder_animation_parallax_bg";s:0:"";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:55:"setting-page_builder_responsive_design_tablet_landscape";s:4:"1024";s:45:"setting-page_builder_responsive_design_tablet";s:3:"768";s:45:"setting-page_builder_responsive_design_mobile";s:3:"680";s:22:"setting-google_map_key";s:0:"";s:23:"setting-hooks_field_ids";s:2:"[]";s:27:"setting-custom_panel-editor";s:7:"default";s:27:"setting-custom_panel-author";s:7:"default";s:32:"setting-custom_panel-contributor";s:7:"default";s:33:"setting-custom_panel-shop_manager";s:7:"default";s:25:"setting-customizer-editor";s:7:"default";s:25:"setting-customizer-author";s:7:"default";s:30:"setting-customizer-contributor";s:7:"default";s:31:"setting-customizer-shop_manager";s:7:"default";s:22:"setting-backend-editor";s:7:"default";s:22:"setting-backend-author";s:7:"default";s:27:"setting-backend-contributor";s:7:"default";s:28:"setting-backend-shop_manager";s:7:"default";s:23:"setting-frontend-editor";s:7:"default";s:23:"setting-frontend-author";s:7:"default";s:28:"setting-frontend-contributor";s:7:"default";s:29:"setting-frontend-shop_manager";s:7:"default";s:16:"setting-fontello";s:0:"";s:4:"skin";s:94:"https://themify.me/demo/themes/shoppe/wp-content/themes/themify-shoppe/skins/default/style.css";s:27:"setting-search_exclude_post";s:0:"";s:31:"setting-search_settings_exclude";s:0:"";s:30:"setting-search_exclude_product";s:0:"";s:43:"setting-search_exclude_themify_storelocator";s:0:"";s:23:"setting-exclude_img_rss";s:0:"";s:20:"setting-excerpt_more";s:0:"";s:27:"setting-auto_featured_image";s:0:"";s:40:"setting-default_page_display_date_inline";s:0:"";s:22:"setting-comments_posts";s:0:"";s:23:"setting-post_author_box";s:0:"";s:24:"setting-post_nav_disable";s:0:"";s:25:"setting-post_nav_same_cat";s:0:"";s:22:"setting-comments_pages";s:0:"";s:33:"setting-disable_responsive_design";s:0:"";s:31:"setting-lightbox_content_images";s:0:"";s:29:"setting-fixed_header_disabled";s:0:"";s:26:"setting-full_height_header";s:0:"";s:25:"setting-exclude_site_logo";s:0:"";s:28:"setting-exclude_site_tagline";s:0:"";s:27:"setting-exclude_search_form";s:0:"";s:31:"setting-exclude_menu_navigation";s:0:"";s:32:"setting-exclude_footer_site_logo";s:0:"";s:30:"setting-exclude_footer_widgets";s:0:"";s:28:"setting-exclude_footer_texts";s:0:"";s:27:"setting-exclude_footer_back";s:0:"";s:20:"setting-autoinfinite";s:0:"";s:30:"settings-footer_banner_youtube";s:0:"";s:31:"settings-footer_banner_linkedin";s:0:"";s:29:"settings-footer_banner_google";s:0:"";s:29:"setting-footer_text_left_hide";s:0:"";s:30:"setting-footer_text_right_hide";s:0:"";s:29:"setting-shop_masonry_disabled";s:0:"";s:29:"setting-hide_shop_breadcrumbs";s:0:"";s:25:"setting-hide_shop_sorting";s:0:"";s:23:"setting-hide_shop_title";s:0:"";s:27:"setting-hide_shop_more_info";s:0:"";s:23:"setting-hide_shop_share";s:0:"";s:30:"setting-single_hide_shop_share";s:0:"";s:23:"setting-product_reviews";s:0:"";s:24:"setting-related_products";s:0:"";s:24:"setting-wishlist_disable";s:0:"";s:23:"setting-spark_animation";s:0:"";s:28:"setting-disable_shop_sidebar";s:0:"";s:38:"setting-page_builder_disable_shortcuts";s:0:"";s:34:"setting-page_builder_exc_accordion";s:0:"";s:28:"setting-page_builder_exc_box";s:0:"";s:32:"setting-page_builder_exc_buttons";s:0:"";s:32:"setting-page_builder_exc_callout";s:0:"";s:32:"setting-page_builder_exc_contact";s:0:"";s:32:"setting-page_builder_exc_divider";s:0:"";s:38:"setting-page_builder_exc_fancy-heading";s:0:"";s:32:"setting-page_builder_exc_feature";s:0:"";s:32:"setting-page_builder_exc_gallery";s:0:"";s:34:"setting-page_builder_exc_highlight";s:0:"";s:29:"setting-page_builder_exc_icon";s:0:"";s:30:"setting-page_builder_exc_image";s:0:"";s:36:"setting-page_builder_exc_layout-part";s:0:"";s:28:"setting-page_builder_exc_map";s:0:"";s:29:"setting-page_builder_exc_menu";s:0:"";s:35:"setting-page_builder_exc_plain-text";s:0:"";s:34:"setting-page_builder_exc_portfolio";s:0:"";s:29:"setting-page_builder_exc_post";s:0:"";s:35:"setting-page_builder_exc_pro-slider";s:0:"";s:43:"setting-page_builder_exc_product-categories";s:0:"";s:33:"setting-page_builder_exc_products";s:0:"";s:37:"setting-page_builder_exc_service-menu";s:0:"";s:31:"setting-page_builder_exc_slider";s:0:"";s:28:"setting-page_builder_exc_tab";s:0:"";s:43:"setting-page_builder_exc_testimonial-slider";s:0:"";s:36:"setting-page_builder_exc_testimonial";s:0:"";s:29:"setting-page_builder_exc_text";s:0:"";s:30:"setting-page_builder_exc_video";s:0:"";s:31:"setting-page_builder_exc_widget";s:0:"";s:35:"setting-page_builder_exc_widgetized";s:0:"";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	?>