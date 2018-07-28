<?php

defined( 'ABSPATH' ) or die;

$GLOBALS['processed_terms'] = array();
$GLOBALS['processed_posts'] = array();

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function themify_import_post( $post ) {
	global $processed_posts, $processed_terms;

	if ( ! post_type_exists( $post['post_type'] ) ) {
		return;
	}

	/* Menu items don't have reliable post_title, skip the post_exists check */
	if( $post['post_type'] !== 'nav_menu_item' ) {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
		if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
			$processed_posts[ intval( $post['ID'] ) ] = intval( $post_exists );
			return;
		}
	}

	if( $post['post_type'] == 'nav_menu_item' ) {
		if( ! isset( $post['tax_input']['nav_menu'] ) || ! term_exists( $post['tax_input']['nav_menu'], 'nav_menu' ) ) {
			return;
		}
		$_menu_item_type = $post['meta_input']['_menu_item_type'];
		$_menu_item_object_id = $post['meta_input']['_menu_item_object_id'];

		if ( 'taxonomy' == $_menu_item_type && isset( $processed_terms[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_terms[ intval( $_menu_item_object_id ) ];
		} else if ( 'post_type' == $_menu_item_type && isset( $processed_posts[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_posts[ intval( $_menu_item_object_id ) ];
		} else if ( 'custom' != $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			// $missing_menu_items[] = $item;
			return;
		}
	}

	$post_parent = ( $post['post_type'] == 'nav_menu_item' ) ? $post['meta_input']['_menu_item_menu_item_parent'] : (int) $post['post_parent'];
	$post['post_parent'] = 0;
	if ( $post_parent ) {
		// if we already know the parent, map it to the new local ID
		if ( isset( $processed_posts[ $post_parent ] ) ) {
			if( $post['post_type'] == 'nav_menu_item' ) {
				$post['meta_input']['_menu_item_menu_item_parent'] = $processed_posts[ $post_parent ];
			} else {
				$post['post_parent'] = $processed_posts[ $post_parent ];
			}
		}
	}

	/**
	 * for hierarchical taxonomies, IDs must be used so wp_set_post_terms can function properly
	 * convert term slugs to IDs for hierarchical taxonomies
	 */
	if( ! empty( $post['tax_input'] ) ) {
		foreach( $post['tax_input'] as $tax => $terms ) {
			if( is_taxonomy_hierarchical( $tax ) ) {
				$terms = explode( ', ', $terms );
				$post['tax_input'][ $tax ] = array_map( 'themify_get_term_id_by_slug', $terms, array_fill( 0, count( $terms ), $tax ) );
			}
		}
	}

	$post['post_author'] = (int) get_current_user_id();
	$post['post_status'] = 'publish';

	$old_id = $post['ID'];

	unset( $post['ID'] );
	$post_id = wp_insert_post( $post, true );
	if( is_wp_error( $post_id ) ) {
		return false;
	} else {
		$processed_posts[ $old_id ] = $post_id;

		if( isset( $post['has_thumbnail'] ) && $post['has_thumbnail'] ) {
			$placeholder = themify_get_placeholder_image();
			if( ! is_wp_error( $placeholder ) ) {
				set_post_thumbnail( $post_id, $placeholder );
			}
		}

		return $post_id;
	}
}

function themify_get_placeholder_image() {
	static $placeholder_image = null;

	if( $placeholder_image == null ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$upload = wp_upload_bits( $post['post_name'] . '.jpg', null, $wp_filesystem->get_contents( THEMIFY_DIR . '/img/image-placeholder.jpg' ) );

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'themify' ) );

		$post['guid'] = $upload['url'];
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		$placeholder_image = $post_id;
	}

	return $placeholder_image;
}

function themify_import_term( $term ) {
	global $processed_terms;

	if( $term_id = term_exists( $term['slug'], $term['taxonomy'] ) ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term['term_id'] ) )
			$processed_terms[ intval( $term['term_id'] ) ] = (int) $term_id;
		return (int) $term_id;
	}

	if ( empty( $term['parent'] ) ) {
		$parent = 0;
	} else {
		$parent = term_exists( $term['parent'], $term['taxonomy'] );
		if ( is_array( $parent ) ) $parent = $parent['term_id'];
	}

	$id = wp_insert_term( $term['name'], $term['taxonomy'], array(
		'parent' => $parent,
		'slug' => $term['slug'],
		'description' => $term['description'],
	) );
	if ( ! is_wp_error( $id ) ) {
		if ( isset( $term['term_id'] ) ) {
			$processed_terms[ intval($term['term_id']) ] = $id['term_id'];
			return $term['term_id'];
		}
	}

	return false;
}

function themify_get_term_id_by_slug( $slug, $tax ) {
	$term = get_term_by( 'slug', $slug, $tax );
	if( $term ) {
		return $term->term_id;
	}

	return false;
}

function themify_undo_import_term( $term ) {
	$term_id = term_exists( $term['slug'], $term['taxonomy'] );
	if ( $term_id ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term_id ) ) {
			wp_delete_term( $term_id, $term['taxonomy'] );
		}
	}
}

/**
 * Determine if a post exists based on title, content, and date
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $args array of database parameters to check
 * @return int Post ID if post exists, 0 otherwise.
 */
function themify_post_exists( $args = array() ) {
	global $wpdb;

	$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
	$db_args = array();

	foreach ( $args as $key => $value ) {
		$value = wp_unslash( sanitize_post_field( $key, $value, 0, 'db' ) );
		if( ! empty( $value ) ) {
			$query .= ' AND ' . $key . ' = %s';
			$db_args[] = $value;
		}
	}

	if ( !empty ( $args ) )
		return (int) $wpdb->get_var( $wpdb->prepare($query, $args) );

	return 0;
}

function themify_undo_import_post( $post ) {
	if( $post['post_type'] == 'nav_menu_item' ) {
		$post_exists = themify_post_exists( array(
			'post_name' => $post['post_name'],
			'post_modified' => $post['post_date'],
			'post_type' => 'nav_menu_item',
		) );
	} else {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
	}
	if( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
		/**
		 * check if the post has been modified, if so leave it be
		 *
		 * NOTE: posts are imported using wp_insert_post() which modifies post_modified field
		 * to be the same as post_date, hence to check if the post has been modified,
		 * the post_modified field is compared against post_date in the original post.
		 */
		if( $post['post_date'] == get_post_field( 'post_modified', $post_exists ) ) {
			wp_delete_post( $post_exists, true ); // true: bypass trash
		}
	}
}

function themify_do_demo_import() {

	if ( isset( $GLOBALS["ThemifyBuilder_Data_Manager"] ) ) {
		remove_action( "save_post", array( $GLOBALS["ThemifyBuilder_Data_Manager"], "save_builder_text_only"), 10, 3 );
	}
$term = array (
  'term_id' => 6,
  'name' => 'Necklace',
  'slug' => 'necklace',
  'term_group' => 0,
  'taxonomy' => 'product_cat',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 7,
  'name' => 'Bracelet',
  'slug' => 'bracelet',
  'term_group' => 0,
  'taxonomy' => 'product_cat',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 8,
  'name' => 'Earring',
  'slug' => 'earring',
  'term_group' => 0,
  'taxonomy' => 'product_cat',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 9,
  'name' => 'Ring',
  'slug' => 'ring',
  'term_group' => 0,
  'taxonomy' => 'product_cat',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 10,
  'name' => 'Main Navigation',
  'slug' => 'main-navigation',
  'term_group' => 0,
  'taxonomy' => 'nav_menu',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$post = array (
  'ID' => 7,
  'post_date' => '2016-12-18 08:02:37',
  'post_date_gmt' => '2016-12-18 08:02:37',
  'post_content' => '',
  'post_title' => 'Shop',
  'post_excerpt' => '',
  'post_name' => 'shop',
  'post_modified' => '2016-12-18 08:02:37',
  'post_modified_gmt' => '2016-12-18 08:02:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/shop/',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 8,
  'post_date' => '2016-12-18 08:02:38',
  'post_date_gmt' => '2016-12-18 08:02:38',
  'post_content' => '[woocommerce_cart]',
  'post_title' => 'Cart',
  'post_excerpt' => '',
  'post_name' => 'cart',
  'post_modified' => '2016-12-27 03:12:57',
  'post_modified_gmt' => '2016-12-27 03:12:57',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/cart/',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 9,
  'post_date' => '2016-12-18 08:02:38',
  'post_date_gmt' => '2016-12-18 08:02:38',
  'post_content' => '[woocommerce_checkout]',
  'post_title' => 'Checkout',
  'post_excerpt' => '',
  'post_name' => 'checkout',
  'post_modified' => '2016-12-27 03:12:54',
  'post_modified_gmt' => '2016-12-27 03:12:54',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/checkout/',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 10,
  'post_date' => '2016-12-18 08:02:38',
  'post_date_gmt' => '2016-12-18 08:02:38',
  'post_content' => '[woocommerce_my_account]',
  'post_title' => 'My Account',
  'post_excerpt' => '',
  'post_name' => 'my-account',
  'post_modified' => '2016-12-27 03:13:39',
  'post_modified_gmt' => '2016-12-27 03:13:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/my-account/',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 18,
  'post_date' => '2016-12-18 15:57:27',
  'post_date_gmt' => '2016-12-18 15:57:27',
  'post_content' => '<!--themify_builder_static--><img src="https://themify.me/demo/themes/shoppe-elegant/files/2017/01/gold-loop-bracelet.png" alt="" /> <h3 data-show-transition="up" data-show-duration="500" data-hide-transition="up" data-hide-duration="500">New Collection</h3> <p>This season most elegant collection has arrived.</p> <a href="https://themify.me" data-show-transition="up" data-show-duration="500" data-hide-transition="left" data-hide-duration="500"> Shop Now </a> 
 <img src="https://themify.me/demo/themes/shoppe-elegant/files/2017/01/gold-heart-ring-1.png" alt="" /> <h3 data-show-transition="down" data-show-duration="1000" data-hide-transition="up" data-hide-duration="1000">Super Sale</h3> <p>Take advantage of our sale up to 30% off!</p> <a href="https://themify.me/" data-show-transition="left" data-show-duration="1000" data-hide-transition="up" data-hide-duration="1000"> MORE </a> 
 <img src="https://themify.me/demo/themes/shoppe-elegant/files/2017/01/earrings.png" alt="" /> <h3 data-show-transition="up" data-show-duration="1000" data-hide-transition="up" data-hide-duration="1000">Best Quality</h3> <p>Check the beauties handcrafted by our masters.</p> <a href="https://themify.me/" data-show-transition="up" data-show-duration="1000" data-hide-transition="up" data-hide-duration="1000"> SHOP NOW </a> 
 
 
 <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/ring/" > <img src="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Green-Tourmaline-Ring-1024x649-450x250.jpg" width="450" height="250" title="Rings" alt="Rings" /> </a> <h3> <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/ring/" > Rings </a> </h3> 
 
 <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/earring/" > <img src="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/pearl-earrings-1024x649-250x200.jpg" width="250" height="200" title="Ear Rings" alt="Ear Rings" srcset="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/pearl-earrings-1024x649-250x200.jpg 250w, https://themify.me/demo/themes/shoppe-elegant/files/2016/12/pearl-earrings-200x160.jpg 200w" sizes="(max-width: 250px) 100vw, 250px" /> </a> <h3> <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/earring/" > Ear Rings </a> </h3> 
 
 <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/necklace/" > <img src="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Love-Heart-Tag-Key-1024x649-250x200.jpg" width="250" height="200" title="Necklets" alt="Necklets" srcset="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Love-Heart-Tag-Key-1024x649-250x200.jpg 250w, https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Love-Heart-Tag-Key-200x160.jpg 200w" sizes="(max-width: 250px) 100vw, 250px" /> </a> <h3> <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/necklace/" > Necklets </a> </h3> 
 
 <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/bracelet/" > <img src="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/bracelet-3-600x575.jpg" width="600" height="575" title="Bracelets" alt="Bracelets" srcset="https://themify.me/demo/themes/shoppe-elegant/files/2016/12/bracelet-3.jpg 600w, https://themify.me/demo/themes/shoppe-elegant/files/2016/12/bracelet-3-300x288.jpg 300w" sizes="(max-width: 600px) 100vw, 600px" /> </a> <h3> <a href="https://themify.me/demo/themes/shoppe-elegant/product-category/bracelet/" > Bracelets </a> </h3><!--/themify_builder_static-->',
  'post_title' => 'Home',
  'post_excerpt' => '',
  'post_name' => 'home',
  'post_modified' => '2017-11-08 06:20:06',
  'post_modified_gmt' => '2017-11-08 06:20:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?page_id=18',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"pro-slider\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"builder_slider_pro_slides\\":[{\\"builder_ps_layout\\":\\"bsp-slide-content-right\\",\\"builder_ps_slide_type\\":\\"Image\\",\\"builder-ps-bg-color\\":\\"#e0e0e0_0.43\\",\\"builder-ps-bg-image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/model-wearing-bracelet.jpg\\",\\"builder-ps-slide-image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2017\\\\/01\\\\/gold-loop-bracelet.png\\",\\"builder_ps_heading\\":\\"New Collection\\",\\"builder_ps_text\\":\\"<p>This season most elegant collection has arrived.<\\\\/p>\\",\\"builder_ps_text_color\\":\\"ffffff\\",\\"builder_ps_text_link_color\\":\\"ffffff\\",\\"builder_ps_button_text\\":\\"Shop Now\\",\\"builder_ps_button_link\\":\\"https:\\\\/\\\\/themify.me\\",\\"builder_ps_tranzition\\":\\"zoomBottom\\",\\"builder_ps_tranzition_duration\\":\\"fast\\",\\"builder_ps_h3s_timer\\":\\"shortTop\\",\\"builder_ps_h3s_tranzition_duration\\":\\"fast\\",\\"builder_ps_h3e_timer\\":\\"shortTopOut\\",\\"builder_ps_h3e_tranzition_duration\\":\\"fast\\",\\"builder_ps_ps_timer\\":\\"shortTop\\",\\"builder_ps_ps_tranzition_duration\\":\\"fast\\",\\"builder_ps_pe_timer\\":\\"shortBottomOut\\",\\"builder_ps_pe_tranzition_duration\\":\\"fast\\",\\"builder_ps_as_timer\\":\\"shortTop\\",\\"builder_ps_as_tranzition_duration\\":\\"fast\\",\\"builder_ps_ae_timer\\":\\"shortLeftOut\\",\\"builder_ps_ae_tranzition_duration\\":\\"fast\\",\\"builder_ps_imgs_timer\\":\\"shortLeft\\",\\"builder_ps_imgs_tranzition_duration\\":\\"fast\\",\\"builder_ps_imge_timer\\":\\"shortRightOut\\",\\"builder_ps_imge_tranzition_duration\\":\\"fast\\"},{\\"builder_ps_layout\\":\\"bsp-slide-content-left\\",\\"builder_ps_slide_type\\":\\"Image\\",\\"builder-ps-bg-image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/model-wearing-necklace-1-1024x684.jpg\\",\\"builder-ps-slide-image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2017\\\\/01\\\\/gold-heart-ring-1.png\\",\\"builder_ps_heading\\":\\"Super Sale\\",\\"builder_ps_text\\":\\"<p>Take advantage of our sale up to 30% off!<\\\\/p>\\",\\"builder_ps_text_color\\":\\"ffffff\\",\\"builder_ps_button_text\\":\\"MORE\\",\\"builder_ps_button_link\\":\\"https:\\\\/\\\\/themify.me\\\\/\\",\\"builder_ps_button_color\\":\\"ffffff\\",\\"builder_ps_tranzition\\":\\"slideTop\\",\\"builder_ps_h3s_timer\\":\\"shortBottom\\",\\"builder_ps_h3e_timer\\":\\"shortTopOut\\",\\"builder_ps_ps_timer\\":\\"shortTop\\",\\"builder_ps_pe_timer\\":\\"shortTopOut\\",\\"builder_ps_as_timer\\":\\"shortLeft\\",\\"builder_ps_ae_timer\\":\\"shortTopOut\\",\\"builder_ps_imgs_timer\\":\\"shortTop\\",\\"builder_ps_imge_timer\\":\\"shortBottomOut\\"},{\\"builder_ps_layout\\":\\"bsp-slide-content-right\\",\\"builder_ps_slide_type\\":\\"Image\\",\\"builder-ps-bg-image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/model-wear-earring-1024x683.jpg\\",\\"builder-ps-slide-image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2017\\\\/01\\\\/earrings.png\\",\\"builder_ps_heading\\":\\"Best Quality\\",\\"builder_ps_text\\":\\"<p>Check the beauties handcrafted by our masters.<\\\\/p>\\",\\"builder_ps_text_color\\":\\"ffffff\\",\\"builder_ps_text_link_color\\":\\"ffffff\\",\\"builder_ps_button_text\\":\\"SHOP NOW\\",\\"builder_ps_button_link\\":\\"https:\\\\/\\\\/themify.me\\\\/\\",\\"builder_ps_tranzition\\":\\"slideRightFade\\",\\"builder_ps_h3s_timer\\":\\"shortTop\\",\\"builder_ps_h3e_timer\\":\\"shortTopOut\\",\\"builder_ps_ps_timer\\":\\"shortTop\\",\\"builder_ps_pe_timer\\":\\"shortTopOut\\",\\"builder_ps_as_timer\\":\\"shortTop\\",\\"builder_ps_ae_timer\\":\\"shortTopOut\\",\\"builder_ps_imgs_timer\\":\\"shortTop\\",\\"builder_ps_imge_timer\\":\\"shortTopOut\\"}],\\"builder_ps_triggers_position\\":\\"standard\\",\\"builder_ps_triggers_type\\":\\"square\\",\\"builder_ps_aa\\":\\"off\\",\\"builder_ps_hover_pause\\":\\"none\\",\\"builder_ps_timer\\":\\"yes\\",\\"cid\\":\\"c13\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"font_color\\":\\"ffffff_1.00\\",\\"link_color\\":\\"ffffff_1.00\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_border_apply_all\\":\\"border\\"}}],\\"styling\\":{\\"row_width\\":\\"fullwidth\\",\\"background_type\\":\\"image\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/Green-Tourmaline-Ring.jpg\\",\\"width_image\\":\\"450\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"250\\",\\"title_image\\":\\"Rings\\",\\"link_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/product-category\\\\/ring\\\\/\\",\\"param_image\\":\\"regular\\",\\"alt_image\\":\\"Rings\\",\\"background_image-type\\":\\"image\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"margin_bottom\\":\\"25\\",\\"border_top_color\\":\\"e6e6e6_1.00\\",\\"border_top_width\\":\\"1\\",\\"border_right_color\\":\\"e6e6e6_1.00\\",\\"border_right_width\\":\\"1\\",\\"border_bottom_color\\":\\"e6e6e6_1.00\\",\\"border_bottom_width\\":\\"1\\",\\"border_left_color\\":\\"e6e6e6_1.00\\",\\"border_left_width\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile_hide\\":\\"hide\\",\\"cid\\":\\"c24\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/pearl-earrings.jpg\\",\\"width_image\\":\\"250\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"200\\",\\"title_image\\":\\"Ear Rings\\",\\"link_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/product-category\\\\/earring\\\\/\\",\\"param_image\\":\\"regular\\",\\"background_image-type\\":\\"image\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"e6e6e6_1.00\\",\\"border_top_width\\":\\"1\\",\\"border_right_color\\":\\"e6e6e6_1.00\\",\\"border_right_width\\":\\"1\\",\\"border_bottom_color\\":\\"e6e6e6_1.00\\",\\"border_bottom_width\\":\\"1\\",\\"border_left_color\\":\\"e6e6e6_1.00\\",\\"border_left_width\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile_hide\\":\\"hide\\",\\"cid\\":\\"c36\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/Love-Heart-Tag-Key.jpg\\",\\"width_image\\":\\"250\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"200\\",\\"title_image\\":\\"Necklets\\",\\"link_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/product-category\\\\/necklace\\\\/\\",\\"param_image\\":\\"regular\\",\\"background_image-type\\":\\"image\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"e6e6e6_1.00\\",\\"border_top_width\\":\\"1\\",\\"border_right_color\\":\\"e6e6e6_1.00\\",\\"border_right_width\\":\\"1\\",\\"border_bottom_color\\":\\"e6e6e6_1.00\\",\\"border_bottom_width\\":\\"1\\",\\"border_left_color\\":\\"e6e6e6_1.00\\",\\"border_left_width\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile_hide\\":\\"hide\\",\\"cid\\":\\"c44\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_border_apply_all\\":\\"border\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/bracelet-3.jpg\\",\\"width_image\\":\\"600\\",\\"height_image\\":\\"575\\",\\"title_image\\":\\"Bracelets\\",\\"link_image\\":\\"https://themify.me/demo/themes/shoppe-elegant\\\\/product-category\\\\/bracelet\\\\/\\",\\"param_image\\":\\"regular\\",\\"background_image-type\\":\\"image\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"none\\",\\"border_right_style\\":\\"none\\",\\"border_bottom_style\\":\\"none\\",\\"border_left_style\\":\\"none\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile_hide\\":\\"hide\\",\\"cid\\":\\"c52\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"e6e6e6_1.00\\",\\"border_top_width\\":\\"1\\",\\"border_right_color\\":\\"e6e6e6_1.00\\",\\"border_right_width\\":\\"1\\",\\"border_bottom_color\\":\\"e6e6e6_1.00\\",\\"border_bottom_width\\":\\"1\\",\\"border_left_color\\":\\"e6e6e6_1.00\\",\\"border_left_width\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"border\\"}}],\\"column_alignment\\":\\"col_align_bottom\\",\\"gutter\\":\\"gutter-narrow\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 333,
  'post_date' => '2017-01-07 17:19:27',
  'post_date_gmt' => '2017-01-07 17:19:27',
  'post_content' => '',
  'post_title' => 'Wishlist',
  'post_excerpt' => '',
  'post_name' => 'wishlist',
  'post_modified' => '2017-01-07 17:19:27',
  'post_modified_gmt' => '2017-01-07 17:19:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?page_id=333',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 90,
  'post_date' => '2016-12-23 09:25:38',
  'post_date_gmt' => '2016-12-23 09:25:38',
  'post_content' => '',
  'post_title' => 'About',
  'post_excerpt' => '',
  'post_name' => 'about',
  'post_modified' => '2017-01-20 03:12:56',
  'post_modified_gmt' => '2017-01-20 03:12:56',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?page_id=90',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h1>The Jeweler<\\\\/h1><h2>Established since 1908<\\\\/h2><p>We are the best jeweler in Toronto Canada<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff_1.00\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"custom_css_column\\":\\"\\"}}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jewelry-maker-3-1024x346.jpg\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"000000_0.71\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"7\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"7\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"1\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_middle\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Top Quality Materials<\\\\/h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"right\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"custom_css_column\\":\\"\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/crystal-stone-1-565x400-2.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"565\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"400\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"2\\",\\"gutter\\":\\"gutter-narrow\\",\\"column_alignment\\":\\"col_align_bottom\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-working-565x400-2.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"565\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"400\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"row_order\\":\\"2\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"grid_width\\":\\"\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-270x220-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"270\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"220\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"grid_width\\":\\"\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/woman-creating-jewelery-270x220-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"270\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"220\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}],\\"styling\\":[]}],\\"styling\\":[]}},\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Detailed &amp; Precision Process<\\\\/h2><p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/making-ring-1-575x450-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"575\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"450\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"f2f2f2_1.00\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"10\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"10\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"3\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"row_order\\":\\"1\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-1 first\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"grid_width\\":\\"\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h3>From the first generation until present, we always keep the original handcrafted method to create the best jewelry.<\\\\/h3>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"buttons_size\\":\\"small\\",\\"buttons_style\\":\\"outline\\",\\"content_button\\":[{\\"label\\":\\"MORE\\",\\"link\\":\\"https:\\\\/\\\\/themify.me\\\\/\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"button_color_bg\\":\\"default\\"}],\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top\\":\\"20\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"padding_top_link_unit\\":\\"px\\",\\"padding_right_link_unit\\":\\"px\\",\\"padding_bottom_link_unit\\":\\"px\\",\\"padding_left_link_unit\\":\\"px\\",\\"checkbox_link_padding_apply_all\\":\\"padding\\",\\"checkbox_link_padding_apply_all_padding\\":\\"padding\\",\\"link_margin_top_unit\\":\\"px\\",\\"link_margin_right_unit\\":\\"px\\",\\"link_margin_bottom_unit\\":\\"px\\",\\"link_margin_left_unit\\":\\"px\\",\\"link_checkbox_margin_apply_all\\":\\"margin\\",\\"link_checkbox_margin_apply_all_margin\\":\\"margin\\",\\"link_border_top_style\\":\\"solid\\",\\"link_border_right_style\\":\\"solid\\",\\"link_border_bottom_style\\":\\"solid\\",\\"link_border_left_style\\":\\"solid\\",\\"link_checkbox_border_apply_all\\":\\"border\\",\\"link_checkbox_border_apply_all_border\\":\\"border\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col4-1 last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}},\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff_1.00\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"custom_css_column\\":\\"\\"}}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-making-ring-1024x504-1-1024x504.jpg\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"000000_0.33\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"7\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"7\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"4\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Original Certification Guarantee<\\\\/h2><p>At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"30\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"row_order\\":\\"2\\",\\"gutter\\":\\"gutter-narrow\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"grid_width\\":\\"\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-checking-diamond-565x400-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"565\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"400\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"grid_width\\":\\"\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-checking-diamond-originality-565x400-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"565\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"400\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}],\\"styling\\":[]}],\\"styling\\":[]}},\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"custom_css_column\\":\\"\\"}}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"10\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"5\\",\\"gutter\\":\\"gutter-narrow\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1 first\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-shop-380x448-2.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"380\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"448\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-shop-380x448-1.jpg\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/client-byuing-jewelry-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"auto_fullwidth\\":\\"|\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-2 last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Trusted by clients<\\\\/h2><p>At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"style_image\\":\\"image-overlay\\",\\"url_image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/jeweler-selling-jewelry-1-762x450-1.jpg\\",\\"appearance_image\\":\\"|\\",\\"width_image\\":\\"762\\",\\"auto_fullwidth\\":\\"|\\",\\"height_image\\":\\"450\\",\\"param_image\\":\\"regular\\",\\"image_zoom_icon\\":\\"|\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_title\\":\\"default\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_family_caption\\":\\"default\\",\\"font_size_caption_unit\\":\\"px\\",\\"line_height_caption_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"margin_top\\":\\"10\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"6\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 175,
  'post_date' => '2016-12-27 16:39:16',
  'post_date_gmt' => '2016-12-27 16:39:16',
  'post_content' => '',
  'post_title' => 'Contact',
  'post_excerpt' => '',
  'post_name' => 'contact',
  'post_modified' => '2017-03-11 04:45:32',
  'post_modified_gmt' => '2017-03-11 04:45:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?page_id=175',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"maps-pro\\",\\"mod_settings\\":{\\"map_display_type\\":\\"dynamic\\",\\"w_map\\":\\"100\\",\\"unit_w\\":\\"%\\",\\"w_map_static\\":\\"500\\",\\"h_map\\":\\"600\\",\\"unit_h\\":\\"px\\",\\"type_map\\":\\"ROADMAP\\",\\"style_map\\":\\"subtle-grayscale\\",\\"scrollwheel_map\\":\\"disable\\",\\"draggable_map\\":\\"enable\\",\\"disable_map_ui\\":\\"no\\",\\"map_link\\":\\"|\\",\\"zoom_map\\":\\"15\\",\\"map_center\\":\\"Toronto Canada\\",\\"markers\\":[{\\"address\\":\\"McCaul St  Canada\\",\\"title\\":\\"McCaul St  Toronto Canada\\",\\"image\\":\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/shoppe-elegant\\\\/files\\\\/2016\\\\/12\\\\/diamond-1.png\\"}],\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"fullwidth-content\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"1\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-2 first\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h1>Contact Us<\\\\/h1>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"contact\\",\\"mod_settings\\":{\\"layout_contact\\":\\"style1\\",\\"field_subject_active\\":\\"yes\\",\\"field_captcha_active\\":\\"|\\",\\"field_sendcopy_active\\":\\"|\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"font_family_labels\\":\\"default\\",\\"font_size_labels_unit\\":\\"px\\",\\"font_family_inputs\\":\\"default\\",\\"font_size_inputs_unit\\":\\"px\\",\\"font_family_send\\":\\"default\\",\\"font_size_send_unit\\":\\"px\\",\\"font_family_success_message\\":\\"default\\",\\"text_align_success_message_left\\":\\"left\\",\\"text_align_success_message_center\\":\\"center\\",\\"text_align_success_message_right\\":\\"right\\",\\"text_align_success_message_justify\\":\\"justify\\",\\"padding_top_success_message_unit\\":\\"px\\",\\"padding_right_success_message_unit\\":\\"px\\",\\"padding_bottom_success_message_unit\\":\\"px\\",\\"padding_left_success_message_unit\\":\\"px\\",\\"margin_top_success_message_unit\\":\\"px\\",\\"margin_right_success_message_unit\\":\\"px\\",\\"margin_bottom_success_message_unit\\":\\"px\\",\\"margin_left_success_message_unit\\":\\"px\\",\\"font_family_error_message\\":\\"default\\",\\"text_align_error_message_left\\":\\"left\\",\\"text_align_error_message_center\\":\\"center\\",\\"text_align_error_message_right\\":\\"right\\",\\"text_align_error_message_justify\\":\\"justify\\",\\"padding_top_error_message_unit\\":\\"px\\",\\"padding_right_error_message_unit\\":\\"px\\",\\"padding_bottom_error_message_unit\\":\\"px\\",\\"padding_left_error_message_unit\\":\\"px\\",\\"margin_top_error_message_unit\\":\\"px\\",\\"margin_right_error_message_unit\\":\\"px\\",\\"margin_bottom_error_message_unit\\":\\"px\\",\\"margin_left_error_message_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1 last\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h4>Address<\\\\/h4>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"15\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>123 McCaul St  Toronto Canada.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"3\\":{\\"mod_name\\":\\"icon\\",\\"mod_settings\\":{\\"icon_size\\":\\"normal\\",\\"icon_style\\":\\"none\\",\\"icon_arrangement\\":\\"icon_vertical\\",\\"content_icon\\":[{\\"icon\\":\\"fa-phone\\",\\"icon_color_bg\\":\\"black\\",\\"label\\":\\"+1 245-344-7112\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\"},{\\"icon\\":\\"fa-envelope\\",\\"icon_color_bg\\":\\"black\\",\\"label\\":\\"info@shoppe.com\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\"}],\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"50\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"4\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h4>Follow Us<\\\\/h4>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"0\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"10\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"|\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"5\\":{\\"mod_name\\":\\"icon\\",\\"mod_settings\\":{\\"icon_size\\":\\"large\\",\\"icon_style\\":\\"none\\",\\"icon_arrangement\\":\\"icon_horizontal\\",\\"content_icon\\":[{\\"icon\\":\\"fa-facebook\\",\\"icon_color_bg\\":\\"black\\",\\"link\\":\\"https:\\\\/\\\\/www.facebook.com\\\\/themify\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\"},{\\"icon\\":\\"fa-twitter\\",\\"icon_color_bg\\":\\"black\\",\\"link\\":\\"https:\\\\/\\\\/www.twitter.com\\\\/themify\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\"},{\\"icon\\":\\"fa-google-plus\\",\\"icon_color_bg\\":\\"black\\",\\"link\\":\\"https:\\\\/\\\\/plus.google.com\\\\/u\\\\/2\\\\/109280316400365629341\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\"},{\\"icon\\":\\"fa-pinterest-p\\",\\"icon_color_bg\\":\\"black\\",\\"link\\":\\"http:\\\\/\\\\/pinterest.com\\\\/\\",\\"link_options\\":\\"regular\\",\\"lightbox_size_unit_width\\":\\"pixels\\",\\"lightbox_size_unit_height\\":\\"pixels\\"}],\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"30\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"000000_1.00\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"custom_css_column\\":\\"\\"}}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"2\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 11,
  'post_date' => '2016-12-18 08:06:03',
  'post_date_gmt' => '2016-12-18 08:06:03',
  'post_content' => '<div>
<div class="product-description">

Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.

</div>
</div>',
  'post_title' => 'Hinged Diamond Bracelet',
  'post_excerpt' => '<div class="product-description">
<div class="product-description"> Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.</div>
</div>',
  'post_name' => 'hinged-diamond-bracelet',
  'post_modified' => '2016-12-23 09:11:34',
  'post_modified_gmt' => '2016-12-23 09:11:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=11',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484294:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '750',
    '_price' => '750',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
    '_wp_old_slug' => 'ethnic-wood-bracelet__trashed',
    '_thumbnail_id' => '38',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Hinged-Diamond-Bracelet.jpg',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'bracelet',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 22,
  'post_date' => '2016-12-17 16:19:59',
  'post_date_gmt' => '2016-12-17 16:19:59',
  'post_content' => '<div class="product-description">

Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

</div>',
  'post_title' => 'Gold Chain Necklace',
  'post_excerpt' => '<div class="product-description">
<div class="product-description"> Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>
</div>',
  'post_name' => 'gold-chain-necklace',
  'post_modified' => '2016-12-23 09:11:40',
  'post_modified_gmt' => '2016-12-23 09:11:40',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=22',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484300:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '670',
    '_sale_price' => '620',
    '_price' => '620',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
    '_wp_old_slug' => 'bunny-headband__trashed',
    '_thumbnail_id' => '40',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Chain-Necklace.jpg',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'necklace',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 26,
  'post_date' => '2016-12-16 17:53:14',
  'post_date_gmt' => '2016-12-16 17:53:14',
  'post_content' => '<div class="product-description">
<div>
<div class="product-description"> Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.</div>
</div>
</div>',
  'post_title' => 'Open Heart Ring',
  'post_excerpt' => '<div class="product-description">
<div>

Ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt.

</div>
</div>',
  'post_name' => 'open-heart-ring',
  'post_modified' => '2016-12-23 09:11:46',
  'post_modified_gmt' => '2016-12-23 09:11:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=26',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484306:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '450',
    '_price' => '450',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
    '_wp_old_slug' => 'womans-red-hat__trashed',
    '_thumbnail_id' => '42',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Open-Heart-Ring.jpg',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'ring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 29,
  'post_date' => '2016-12-14 17:59:00',
  'post_date_gmt' => '2016-12-14 17:59:00',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.',
  'post_title' => 'Heart Earring',
  'post_excerpt' => '<div class="product-description">
<div>

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.

</div>
</div>',
  'post_name' => 'heart-earring',
  'post_modified' => '2016-12-23 09:11:58',
  'post_modified_gmt' => '2016-12-23 09:11:58',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=29',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484318:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '450',
    '_price' => '450',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
    '_wp_old_slug' => 'smartwatch__trashed',
    '_thumbnail_id' => '46',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Heart-earrings.jpg',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'earring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 31,
  'post_date' => '2016-12-13 18:02:44',
  'post_date_gmt' => '2016-12-13 18:02:44',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.',
  'post_title' => 'Green Tourmaline Ring',
  'post_excerpt' => '<div class="product-description">
<div>

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.

</div>
</div>',
  'post_name' => 'green-tourmaline-ring',
  'post_modified' => '2016-12-23 09:12:04',
  'post_modified_gmt' => '2016-12-23 09:12:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=31',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484324:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '650',
    '_price' => '650',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wp_old_slug' => 'womans-crystal-necklace__trashed',
    '_thumbnail_id' => '47',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Green-Tourmaline-Ring.jpg',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'ring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 33,
  'post_date' => '2016-12-10 18:04:29',
  'post_date_gmt' => '2016-12-10 18:04:29',
  'post_content' => '<div class="product-description">
<div>

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.

</div>
</div>',
  'post_title' => 'Mesh Scarf Earrings',
  'post_excerpt' => '<div class="product-description">
<div>

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.

</div>
</div>',
  'post_name' => 'mesh-scarf-earrings',
  'post_modified' => '2016-12-23 09:12:10',
  'post_modified_gmt' => '2016-12-23 09:12:10',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=33',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484330:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '780',
    '_price' => '780',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
    '_wp_old_slug' => 'diamond-drop-earring__trashed',
    '_thumbnail_id' => '55',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Mesh-scarf-earrings-1.jpg',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'earring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 67,
  'post_date' => '2016-12-09 07:35:29',
  'post_date_gmt' => '2016-12-09 07:35:29',
  'post_content' => 'Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.',
  'post_title' => 'Olive Leaf Pearl Tassel Necklace',
  'post_excerpt' => '<div>

Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.

</div>',
  'post_name' => 'olive-leaf-pearl-tassel-necklace',
  'post_modified' => '2016-12-23 09:12:18',
  'post_modified_gmt' => '2016-12-23 09:12:18',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=67',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484338:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '58',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Olive-Leaf-Pearl-Tassel-Necklace.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '800',
    '_price' => '800',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'necklace',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 68,
  'post_date' => '2016-12-08 07:37:49',
  'post_date_gmt' => '2016-12-08 07:37:49',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
  'post_title' => 'Gold Loop Bracelet',
  'post_excerpt' => '<div>

eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco.

</div>',
  'post_name' => 'gold-loop-bracelet',
  'post_modified' => '2016-12-23 09:12:23',
  'post_modified_gmt' => '2016-12-23 09:12:23',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=68',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484343:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '54',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Loop-Bracelet.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '670',
    '_price' => '670',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'bracelet',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 69,
  'post_date' => '2016-12-07 07:40:07',
  'post_date_gmt' => '2016-12-07 07:40:07',
  'post_content' => 'Voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.',
  'post_title' => 'Hinged Wrap Bracelet',
  'post_excerpt' => '<div>

Numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis.

</div>',
  'post_name' => 'hinged-wrap-bracelet',
  'post_modified' => '2016-12-23 09:12:28',
  'post_modified_gmt' => '2016-12-23 09:12:28',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=69',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484348:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '66',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Hinged-Wrap-Bracelet.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '560',
    '_price' => '560',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'bracelet',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 70,
  'post_date' => '2016-12-06 07:41:57',
  'post_date_gmt' => '2016-12-06 07:41:57',
  'post_content' => 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.',
  'post_title' => 'Titanium Infinity Necklace',
  'post_excerpt' => '<div>

Voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est qui dolorem ipsum quia dolor sit amet consectetur adipisci velit.

</div>',
  'post_name' => 'titanium-infinity-necklace',
  'post_modified' => '2016-12-23 09:12:33',
  'post_modified_gmt' => '2016-12-23 09:12:33',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=70',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484353:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '52',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Infinity-necklace.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '890',
    '_price' => '890',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'necklace',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 71,
  'post_date' => '2016-12-04 07:44:56',
  'post_date_gmt' => '2016-12-04 07:44:56',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.',
  'post_title' => 'Knot Bead Bracelet',
  'post_excerpt' => '<div>

Aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.

</div>',
  'post_name' => 'knot-bead-bracelet',
  'post_modified' => '2016-12-23 09:12:40',
  'post_modified_gmt' => '2016-12-23 09:12:40',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=71',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484360:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '53',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Knot-Bead-Bracelet.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '200',
    '_price' => '200',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'bracelet',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 72,
  'post_date' => '2016-12-03 07:46:35',
  'post_date_gmt' => '2016-12-03 07:46:35',
  'post_content' => '<div>

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.

</div>',
  'post_title' => 'Gold Narrow Ring',
  'post_excerpt' => '<div>

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.

</div>',
  'post_name' => 'gold-narrow-ring',
  'post_modified' => '2016-12-23 09:12:47',
  'post_modified_gmt' => '2016-12-23 09:12:47',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=72',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484367:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '56',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Narrow-Ring.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '450',
    '_price' => '450',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'ring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 73,
  'post_date' => '2016-12-02 07:48:55',
  'post_date_gmt' => '2016-12-02 07:48:55',
  'post_content' => 'Similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum.',
  'post_title' => 'Pearl Leaf Earring',
  'post_excerpt' => '<div>

Omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum.

</div>',
  'post_name' => 'pearl-leaf-earring',
  'post_modified' => '2016-12-23 09:12:56',
  'post_modified_gmt' => '2016-12-23 09:12:56',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=73',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484376:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '60',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/pearl-earrings.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '345',
    '_sale_price' => '300',
    '_price' => '300',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'earring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 75,
  'post_date' => '2016-12-02 07:53:48',
  'post_date_gmt' => '2016-12-02 07:53:48',
  'post_content' => 'Similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum.',
  'post_title' => 'Pearl Bracelet',
  'post_excerpt' => '<div>

Omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum.

</div>',
  'post_name' => 'pearl-bracelet',
  'post_modified' => '2016-12-23 09:12:51',
  'post_modified_gmt' => '2016-12-23 09:12:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=75',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484371:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '61',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Six-Row-Pearl-Bracelet.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '250',
    '_sale_price' => '200',
    '_price' => '200',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'bracelet',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 76,
  'post_date' => '2016-11-29 07:56:05',
  'post_date_gmt' => '2016-11-29 07:56:05',
  'post_content' => 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.',
  'post_title' => 'Mixed Cluster Drop Earrings',
  'post_excerpt' => '<div>

Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.

</div>',
  'post_name' => 'mixed-cluster-drop-earrings',
  'post_modified' => '2016-12-23 09:13:02',
  'post_modified_gmt' => '2016-12-23 09:13:02',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=76',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484382:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '64',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/mixed-cluster-drop-earrings.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '425',
    '_price' => '425',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'earring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 77,
  'post_date' => '2016-11-28 07:58:39',
  'post_date_gmt' => '2016-11-28 07:58:39',
  'post_content' => 'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.',
  'post_title' => 'Titanium ID Ring',
  'post_excerpt' => '<div>

Nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.

</div>',
  'post_name' => 'titanium-id-ring',
  'post_modified' => '2016-12-23 09:13:06',
  'post_modified_gmt' => '2016-12-23 09:13:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=77',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484386:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '51',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/I.D.-Ring.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '265',
    '_price' => '265',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'ring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 80,
  'post_date' => '2016-11-26 08:04:58',
  'post_date_gmt' => '2016-11-26 08:04:58',
  'post_content' => 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam.',
  'post_title' => 'Titanium Snake Ring',
  'post_excerpt' => '<div>

Aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet.

</div>',
  'post_name' => 'titanium-snake-ring',
  'post_modified' => '2016-12-23 09:13:16',
  'post_modified_gmt' => '2016-12-23 09:13:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=80',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484396:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '79',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Snake-Ring-1.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '450',
    '_sale_price' => '420',
    '_price' => '420',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'ring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 81,
  'post_date' => '2016-11-26 08:19:33',
  'post_date_gmt' => '2016-11-26 08:19:33',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit.',
  'post_title' => 'Love Heart Necklace',
  'post_excerpt' => '<div>

Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.

</div>',
  'post_name' => 'love-heart-necklace',
  'post_modified' => '2016-12-23 09:13:10',
  'post_modified_gmt' => '2016-12-23 09:13:10',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=81',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484390:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '63',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Love-Heart-Tag-Key.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '340',
    '_price' => '340',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'necklace',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 82,
  'post_date' => '2016-11-23 08:22:06',
  'post_date_gmt' => '2016-11-23 08:22:06',
  'post_content' => 'Numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.',
  'post_title' => 'Olive Leaf Drop Earrings',
  'post_excerpt' => '<div>

Dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.

</div>',
  'post_name' => 'olive-leaf-drop-earrings',
  'post_modified' => '2016-12-23 09:13:22',
  'post_modified_gmt' => '2016-12-23 09:13:22',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=82',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484438:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '57',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/olive-leaf-drop-earrings.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '345',
    '_price' => '345',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'earring',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 87,
  'post_date' => '2016-11-22 09:13:59',
  'post_date_gmt' => '2016-11-22 09:13:59',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid.',
  'post_title' => 'Double Interlocking Drop',
  'post_excerpt' => '<div>

Totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia.

</div>',
  'post_name' => 'double-interlocking-drop',
  'post_modified' => '2016-12-23 09:15:38',
  'post_modified_gmt' => '2016-12-23 09:15:38',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=product&#038;p=87',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_edit_lock' => '1482484531:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
    '_visibility' => 'visible',
    '_stock_status' => 'instock',
    'content_width' => 'default_width',
    'builder_switch_frontend' => '0',
    '_thumbnail_id' => '88',
    'post_image' => 'https://themify.me/demo/themes/shoppe-elegant/files/2016/12/Double-Interlocking-Drop.jpg',
    'total_sales' => '0',
    '_downloadable' => 'no',
    '_virtual' => 'no',
    '_featured' => 'no',
    '_product_attributes' => 
    array (
    ),
    '_regular_price' => '650',
    '_price' => '650',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_product_version' => '2.6.8',
    '_yoast_wpseo_primary_product_cat' => '6',
    '_yoast_wpseo_content_score' => '30',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_review_count' => '0',
    '_wc_average_rating' => '0',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'necklace',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 339,
  'post_date' => '2017-01-20 07:23:19',
  'post_date_gmt' => '2017-01-20 07:23:19',
  'post_content' => '',
  'post_title' => 'Home Popup',
  'post_excerpt' => '',
  'post_name' => 'home-popup',
  'post_modified' => '2017-01-21 03:09:13',
  'post_modified_gmt' => '2017-01-21 03:09:13',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=themify_popup&#038;p=339',
  'menu_order' => 0,
  'post_type' => 'themify_popup',
  'meta_input' => 
  array (
    'popup_show_on_toggle' => 'specific-pages',
    'popup_show' => 
    array (
      'general' => 
      array (
        'home' => 'on',
      ),
    ),
    'popup_show_to' => 'guest',
    'popup_start_at' => '1970-01-01 00:00:00',
    'popup_end_at' => '3015-01-01 00:00:00',
    'popup_width' => '700',
    'popup_height' => '410',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h1>Never miss a deal<\\\\/h1><p>Subscribe our newsletter to receive deals &amp; promotions. You will receive a 20% discount code once subscribed.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"20\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[mc4wp_form id=\\\\\\\\\\\\\\"343\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"60\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"5\\",\\"padding_right_unit\\":\\"%\\",\\"padding_bottom\\":\\"40\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"5\\",\\"padding_left_unit\\":\\"%\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"1\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 346,
  'post_date' => '2017-01-20 16:03:41',
  'post_date_gmt' => '2017-01-20 16:03:41',
  'post_content' => '',
  'post_title' => 'Bottom Right Popup',
  'post_excerpt' => '',
  'post_name' => 'bottom-right-popup',
  'post_modified' => '2017-01-30 18:04:09',
  'post_modified_gmt' => '2017-01-30 18:04:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?post_type=themify_popup&#038;p=346',
  'menu_order' => 0,
  'post_type' => 'themify_popup',
  'meta_input' => 
  array (
    'popup_show_to' => 'guest',
    'popup_start_at' => '1970-01-01 00:00:00',
    'popup_end_at' => '3015-01-01 00:00:00',
    'popup_trigger' => 'scroll',
    'popup_trigger_scroll_position' => '70',
    'popup_trigger_scroll_on' => '%',
    'popup_style' => 'slide-out',
    'popup_width' => '300',
    'popup_auto_height' => 'on',
    'popup_height' => '300',
    'popup_animation_exit' => 'fadeOut',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"grid_width\\":\\"\\",\\"modules\\":{\\"1\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h1>Newsletter<\\\\/h1><p>Subscribe our newsletter to receive deals &amp; promotions. You will receive a 20% discount code once subscribed.<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"|\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},\\"2\\":{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[mc4wp_form id=\\\\\\\\\\\\\\"343\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-type_gradient\\":\\"gradient\\",\\"background_image-gradient-type\\":\\"linear\\",\\"background_image-gradient-angle\\":\\"180\\",\\"background_image-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_image-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\n\\",\\"font_family\\":\\"default\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_size_h1_unit\\":\\"px\\",\\"line_height_h1_unit\\":\\"px\\",\\"font_family_h2\\":\\"default\\",\\"font_size_h2_unit\\":\\"px\\",\\"line_height_h2_unit\\":\\"px\\",\\"font_family_h3\\":\\"default\\",\\"font_size_h3_unit\\":\\"px\\",\\"line_height_h3_unit\\":\\"px\\",\\"font_family_h4\\":\\"default\\",\\"font_size_h4_unit\\":\\"px\\",\\"line_height_h4_unit\\":\\"px\\",\\"font_family_h5\\":\\"default\\",\\"font_size_h5_unit\\":\\"px\\",\\"line_height_h5_unit\\":\\"px\\",\\"font_family_h6\\":\\"default\\",\\"font_size_h6_unit\\":\\"px\\",\\"line_height_h6_unit\\":\\"px\\",\\"custom_parallax_scroll_reverse\\":\\"|\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade\\":\\"|\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}},\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"px\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"px\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"custom_css_column\\":\\"\\"}}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_size\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_gradient-gradient-type\\":\\"linear\\",\\"background_gradient-gradient-angle\\":\\"180\\",\\"background_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"background_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"background_repeat\\":\\"\\",\\"background_position\\":\\"\\",\\"background_color\\":\\"\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"\\",\\"cover_gradient-gradient-type\\":\\"linear\\",\\"cover_gradient-gradient-angle\\":\\"180\\",\\"cover_gradient-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"cover_color_hover\\":\\"\\",\\"cover_gradient_hover-gradient-type\\":\\"linear\\",\\"cover_gradient_hover-gradient-angle\\":\\"180\\",\\"cover_gradient_hover-gradient\\":\\"0% rgb(0, 0, 0)|100% rgb(255, 255, 255)\\",\\"cover_gradient_hover-css\\":\\"background-image: -moz-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -webkit-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -o-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: -ms-linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\nbackground-image: linear-gradient(180deg,rgb(0, 0, 0) 0%, rgb(255, 255, 255) 100%);\\\\r\\\\n\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\".95\\",\\"font_size_unit\\":\\"em\\",\\"line_height\\":\\"1.4\\",\\"line_height_unit\\":\\"em\\",\\"text_align\\":\\"left\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"25\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"25\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"10\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"25\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"solid\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_mobile\\":\\"show\\",\\"animation_effect\\":\\"\\",\\"animation_effect_delay\\":\\"\\",\\"animation_effect_repeat\\":\\"\\",\\"custom_parallax_scroll_speed\\":\\"\\",\\"custom_parallax_scroll_zindex\\":\\"\\"}},{\\"row_order\\":\\"1\\",\\"gutter\\":\\"gutter-default\\",\\"column_alignment\\":\\"col_align_top\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"grid_width\\":\\"\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 173,
  'post_date' => '2016-12-27 16:38:55',
  'post_date_gmt' => '2016-12-27 16:38:55',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '173',
  'post_modified' => '2016-12-27 16:47:19',
  'post_modified_gmt' => '2016-12-27 16:47:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?p=173',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '18',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-navigation',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 172,
  'post_date' => '2016-12-27 16:38:55',
  'post_date_gmt' => '2016-12-27 16:38:55',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '172',
  'post_modified' => '2016-12-27 16:47:19',
  'post_modified_gmt' => '2016-12-27 16:47:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?p=172',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '90',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-navigation',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 174,
  'post_date' => '2016-12-27 16:38:55',
  'post_date_gmt' => '2016-12-27 16:38:55',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '174',
  'post_modified' => '2016-12-27 16:47:19',
  'post_modified_gmt' => '2016-12-27 16:47:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?p=174',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '7',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-navigation',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 178,
  'post_date' => '2016-12-27 16:45:34',
  'post_date_gmt' => '2016-12-27 16:45:34',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '178',
  'post_modified' => '2016-12-27 16:47:19',
  'post_modified_gmt' => '2016-12-27 16:47:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-elegant/?p=178',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '175',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-navigation',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}


function themify_import_get_term_id_from_slug( $slug ) {
	$menu = get_term_by( "slug", $slug, "nav_menu" );
	return is_wp_error( $menu ) ? 0 : (int) $menu->term_id;
}

	$widgets = get_option( "widget_search" );
$widgets[1002] = array (
  'title' => '',
);
update_option( "widget_search", $widgets );

$widgets = get_option( "widget_recent-posts" );
$widgets[1003] = array (
  'title' => '',
  'number' => 5,
);
update_option( "widget_recent-posts", $widgets );

$widgets = get_option( "widget_recent-comments" );
$widgets[1004] = array (
  'title' => '',
  'number' => 5,
);
update_option( "widget_recent-comments", $widgets );

$widgets = get_option( "widget_archives" );
$widgets[1005] = array (
  'title' => '',
  'count' => 0,
  'dropdown' => 0,
);
update_option( "widget_archives", $widgets );

$widgets = get_option( "widget_categories" );
$widgets[1006] = array (
  'title' => '',
  'count' => 0,
  'hierarchical' => 0,
  'dropdown' => 0,
);
update_option( "widget_categories", $widgets );

$widgets = get_option( "widget_meta" );
$widgets[1007] = array (
  'title' => '',
);
update_option( "widget_meta", $widgets );



$sidebars_widgets = array (
  'sidebar-main' => 
  array (
    0 => 'search-1002',
    1 => 'recent-posts-1003',
    2 => 'recent-comments-1004',
    3 => 'archives-1005',
    4 => 'categories-1006',
    5 => 'meta-1007',
  ),
); 
update_option( "sidebars_widgets", $sidebars_widgets );

$menu_locations = array();
$menu = get_terms( "nav_menu", array( "slug" => "main-navigation" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["main-nav"] = $menu[0]->term_id;
set_theme_mod( "nav_menu_locations", $menu_locations );


$homepage = get_posts( array( 'name' => 'home', 'post_type' => 'page' ) );
			if( is_array( $homepage ) && ! empty( $homepage ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $homepage[0]->ID );
			}
			
	ob_start(); ?>a:80:{s:16:"setting-page_404";s:1:"0";s:21:"setting-webfonts_list";s:11:"recommended";s:22:"setting-default_layout";s:12:"sidebar-none";s:27:"setting-default_post_layout";s:9:"list-post";s:20:"setting-post_masonry";s:3:"yes";s:19:"setting-post_gutter";s:6:"gutter";s:30:"setting-default_layout_display";s:7:"content";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:30:"setting-default_media_position";s:5:"above";s:31:"setting-image_post_feature_size";s:5:"blank";s:32:"setting-default_page_post_layout";s:12:"sidebar-none";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:27:"setting-default_page_layout";s:12:"sidebar-none";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1024";s:43:"setting-customizer_responsive_design_tablet";s:3:"768";s:43:"setting-customizer_responsive_design_mobile";s:3:"480";s:33:"setting-mobile_menu_trigger_point";s:4:"1200";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:21:"setting-header_design";s:16:"header-logo-left";s:21:"setting-footer_design";s:12:"footer-block";s:22:"setting-footer_widgets";s:17:"footerwidget-3col";s:27:"setting-imagefilter_applyto";s:12:"featuredonly";s:18:"setting-more_posts";s:8:"infinite";s:19:"setting-entries_nav";s:8:"numbered";s:19:"setting-shop_layout";s:12:"sidebar-none";s:27:"setting-shop_archive_layout";s:12:"sidebar-none";s:23:"setting-products_layout";s:5:"grid3";s:27:"setting-product_post_gutter";s:6:"gutter";s:23:"setting-products_slider";s:6:"enable";s:30:"setting-shop_products_per_page";s:1:"9";s:46:"setting-default_product_index_image_post_width";s:3:"400";s:47:"setting-default_product_index_image_post_height";s:3:"336";s:29:"setting-single_product_layout";s:12:"sidebar-none";s:47:"setting-default_product_single_image_post_width";s:3:"600";s:48:"setting-default_product_single_image_post_height";s:3:"380";s:28:"setting-product_gallery_type";s:4:"zoom";s:30:"setting-related_products_limit";s:1:"3";s:21:"setting-wishlist_page";s:3:"333";s:18:"setting-cart_style";s:8:"dropdown";s:27:"setting-global_feature_size";s:5:"blank";s:22:"setting-link_icon_type";s:9:"font-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:31:"setting-link_img_themify-link-0";s:109:"https://themify.me/demo/themes/shoppe-elegant/wp-content/themes/themify-shoppe/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:31:"setting-link_img_themify-link-1";s:110:"https://themify.me/demo/themes/shoppe-elegant/wp-content/themes/themify-shoppe/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:31:"setting-link_img_themify-link-2";s:113:"https://themify.me/demo/themes/shoppe-elegant/wp-content/themes/themify-shoppe/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:31:"setting-link_img_themify-link-3";s:109:"https://themify.me/demo/themes/shoppe-elegant/wp-content/themes/themify-shoppe/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:31:"setting-link_img_themify-link-4";s:111:"https://themify.me/demo/themes/shoppe-elegant/wp-content/themes/themify-shoppe/themify/img/social/pinterest.png";s:32:"setting-link_type_themify-link-5";s:9:"font-icon";s:33:"setting-link_title_themify-link-5";s:7:"Twitter";s:33:"setting-link_ficon_themify-link-5";s:10:"fa-twitter";s:32:"setting-link_type_themify-link-6";s:9:"font-icon";s:33:"setting-link_title_themify-link-6";s:8:"Facebook";s:33:"setting-link_ficon_themify-link-6";s:11:"fa-facebook";s:32:"setting-link_type_themify-link-7";s:9:"font-icon";s:33:"setting-link_title_themify-link-7";s:7:"Google+";s:33:"setting-link_ficon_themify-link-7";s:14:"fa-google-plus";s:32:"setting-link_type_themify-link-8";s:9:"font-icon";s:33:"setting-link_title_themify-link-8";s:7:"YouTube";s:33:"setting-link_ficon_themify-link-8";s:10:"fa-youtube";s:32:"setting-link_type_themify-link-9";s:9:"font-icon";s:33:"setting-link_title_themify-link-9";s:9:"Pinterest";s:33:"setting-link_ficon_themify-link-9";s:12:"fa-pinterest";s:22:"setting-link_field_ids";s:341:"{"themify-link-0":"themify-link-0","themify-link-1":"themify-link-1","themify-link-2":"themify-link-2","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4","themify-link-5":"themify-link-5","themify-link-6":"themify-link-6","themify-link-7":"themify-link-7","themify-link-8":"themify-link-8","themify-link-9":"themify-link-9"}";s:23:"setting-link_field_hash";s:2:"10";s:30:"setting-page_builder_is_active";s:6:"enable";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:4:"skin";s:102:"https://themify.me/demo/themes/shoppe-elegant/wp-content/themes/themify-shoppe/skins/elegant/style.css";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	
}
themify_do_demo_import();