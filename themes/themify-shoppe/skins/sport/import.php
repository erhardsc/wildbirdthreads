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
  'term_id' => 17,
  'name' => 'Tips',
  'slug' => 'tips',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 18,
  'name' => 'News',
  'slug' => 'news',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 19,
  'name' => 'Fans Club',
  'slug' => 'fans-club',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 16,
  'name' => 'Skateboard',
  'slug' => 'skateboard',
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
  'term_id' => 20,
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

$term = array (
  'term_id' => 21,
  'name' => 'My Account Menu',
  'slug' => 'my-account-menu',
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
  'ID' => 6,
  'post_date' => '2018-05-01 15:32:48',
  'post_date_gmt' => '2018-05-01 15:32:48',
  'post_content' => 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias at vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis.

quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus.',
  'post_title' => 'We Ride With Pride',
  'post_excerpt' => '',
  'post_name' => 'we-ride-with-pride',
  'post_modified' => '2018-05-13 02:53:33',
  'post_modified_gmt' => '2018-05-13 02:53:33',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=6',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'tips',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 7,
  'post_date' => '2018-04-25 15:19:27',
  'post_date_gmt' => '2018-04-25 15:19:27',
  'post_content' => 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias at vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis.

quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus.',
  'post_title' => 'Time to Skating',
  'post_excerpt' => '',
  'post_name' => 'time-to-skating',
  'post_modified' => '2018-05-13 02:53:39',
  'post_modified_gmt' => '2018-05-13 02:53:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=7',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'hide_post_title' => 'no',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'news',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 11,
  'post_date' => '2018-05-10 15:19:35',
  'post_date_gmt' => '2018-05-10 15:19:35',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.

inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.',
  'post_title' => 'Ready For The New Tricks',
  'post_excerpt' => '',
  'post_name' => 'ready-for-the-new-tricks',
  'post_modified' => '2018-05-13 02:52:49',
  'post_modified_gmt' => '2018-05-13 02:52:49',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=11',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'fans-club',
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
  'post_date' => '2018-05-08 15:22:05',
  'post_date_gmt' => '2018-05-08 15:22:05',
  'post_content' => 'Ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit ess. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam.',
  'post_title' => 'Pro Skater',
  'post_excerpt' => '',
  'post_name' => 'pro-skater',
  'post_modified' => '2018-05-13 02:52:59',
  'post_modified_gmt' => '2018-05-13 02:52:59',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=26',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'fans-club',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 28,
  'post_date' => '2018-05-07 15:23:33',
  'post_date_gmt' => '2018-05-07 15:23:33',
  'post_content' => 'Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat epellendus.  Ton provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae.

<span id="more-133"></span>Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur? Vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate',
  'post_title' => 'Waiting For Friends',
  'post_excerpt' => '',
  'post_name' => 'waiting-for-friends',
  'post_modified' => '2018-05-13 02:53:04',
  'post_modified_gmt' => '2018-05-13 02:53:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=28',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'news',
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
  'post_date' => '2018-05-06 15:25:21',
  'post_date_gmt' => '2018-05-06 15:25:21',
  'post_content' => 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est. Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.

Commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.

Perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.',
  'post_title' => 'My New Trick',
  'post_excerpt' => '',
  'post_name' => 'my-new-trick',
  'post_modified' => '2018-05-13 02:53:21',
  'post_modified_gmt' => '2018-05-13 02:53:21',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=31',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'tips',
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
  'post_date' => '2018-05-09 15:26:47',
  'post_date_gmt' => '2018-05-09 15:26:47',
  'post_content' => 'Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus. Et quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus omnis.

At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.',
  'post_title' => 'How to be a Professional Skateboarder',
  'post_excerpt' => '',
  'post_name' => 'how-to-be-a-professional-skateboarder',
  'post_modified' => '2018-05-13 02:52:55',
  'post_modified_gmt' => '2018-05-13 02:52:55',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=33',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'tips',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 36,
  'post_date' => '2018-05-02 15:30:11',
  'post_date_gmt' => '2018-05-02 15:30:11',
  'post_content' => 'Totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.

Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur. Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae.',
  'post_title' => 'Riding My New Skateboard',
  'post_excerpt' => '',
  'post_name' => 'riding-my-new-skateboard',
  'post_modified' => '2018-05-13 02:54:11',
  'post_modified_gmt' => '2018-05-13 02:54:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=36',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'news',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 39,
  'post_date' => '2018-04-24 15:31:39',
  'post_date_gmt' => '2018-04-24 15:31:39',
  'post_content' => 'Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus. At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates.

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?”  repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis.',
  'post_title' => 'Practice for Perfection',
  'post_excerpt' => '',
  'post_name' => 'practice-for-perfection',
  'post_modified' => '2018-05-13 02:53:44',
  'post_modified_gmt' => '2018-05-13 02:53:44',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=39',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'tips',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 44,
  'post_date' => '2018-05-11 15:38:35',
  'post_date_gmt' => '2018-05-11 15:38:35',
  'post_content' => 'Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus. At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates.

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?” repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis.',
  'post_title' => 'Safe and Best Practice Tips From the Pros',
  'post_excerpt' => '',
  'post_name' => 'safe-and-best-practice-tips-from-the-pros',
  'post_modified' => '2018-05-13 02:52:41',
  'post_modified_gmt' => '2018-05-13 02:52:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=44',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'tips',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 46,
  'post_date' => '2018-04-22 15:39:29',
  'post_date_gmt' => '2018-04-22 15:39:29',
  'post_content' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.

Inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.',
  'post_title' => 'First Time Skating',
  'post_excerpt' => '',
  'post_name' => 'first-time-skating',
  'post_modified' => '2018-05-13 02:53:51',
  'post_modified_gmt' => '2018-05-13 02:53:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=46',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'tips',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 49,
  'post_date' => '2018-04-20 15:41:32',
  'post_date_gmt' => '2018-04-20 15:41:32',
  'post_content' => 'Ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit ess. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam.',
  'post_title' => 'Time For Competition',
  'post_excerpt' => '',
  'post_name' => 'time-for-competition',
  'post_modified' => '2018-05-13 02:53:56',
  'post_modified_gmt' => '2018-05-13 02:53:56',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=49',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'news',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 51,
  'post_date' => '2018-04-19 15:42:40',
  'post_date_gmt' => '2018-04-19 15:42:40',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.<span id="more-5716"></span>

Magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt neque porro quisquam.',
  'post_title' => 'Find New Atmosphere',
  'post_excerpt' => '',
  'post_name' => 'find-new-atmosphere',
  'post_modified' => '2018-05-13 02:54:00',
  'post_modified_gmt' => '2018-05-13 02:54:00',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=51',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'news',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 57,
  'post_date' => '2018-05-11 03:12:34',
  'post_date_gmt' => '2018-05-11 03:12:34',
  'post_content' => '',
  'post_title' => 'Shop',
  'post_excerpt' => '',
  'post_name' => 'shop',
  'post_modified' => '2018-05-11 03:12:34',
  'post_modified_gmt' => '2018-05-11 03:12:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/shop/',
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
  'ID' => 58,
  'post_date' => '2018-05-11 03:12:34',
  'post_date_gmt' => '2018-05-11 03:12:34',
  'post_content' => '[woocommerce_cart]',
  'post_title' => 'Cart',
  'post_excerpt' => '',
  'post_name' => 'cart',
  'post_modified' => '2018-05-11 03:12:34',
  'post_modified_gmt' => '2018-05-11 03:12:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/cart/',
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
  'ID' => 59,
  'post_date' => '2018-05-11 03:12:34',
  'post_date_gmt' => '2018-05-11 03:12:34',
  'post_content' => '[woocommerce_checkout]',
  'post_title' => 'Checkout',
  'post_excerpt' => '',
  'post_name' => 'checkout',
  'post_modified' => '2018-05-11 03:12:34',
  'post_modified_gmt' => '2018-05-11 03:12:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/checkout/',
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
  'ID' => 60,
  'post_date' => '2018-05-11 03:12:34',
  'post_date_gmt' => '2018-05-11 03:12:34',
  'post_content' => '[woocommerce_my_account]',
  'post_title' => 'My account',
  'post_excerpt' => '',
  'post_name' => 'my-account',
  'post_modified' => '2018-05-11 03:12:34',
  'post_modified_gmt' => '2018-05-11 03:12:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/my-account/',
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
  'ID' => 61,
  'post_date' => '2018-05-11 04:42:23',
  'post_date_gmt' => '2018-05-11 04:42:23',
  'post_content' => '<!--themify_builder_static--><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-806x970.png" width="806" height="970" alt="skateboard" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard.png 806w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-249x300.png 249w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-768x924.png 768w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-600x722.png 600w" sizes="(max-width: 806px) 100vw, 806px" /> 
 <h1>Skateboarding done right</h1> <h3>Strong &#038; comes in all size</h3>
 
 <a href="https://themify.me/demo/themes/shoppe-sport/shop/" >Shop Now</a> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/adio-logo-156x95.png" width="156" height="95" alt="adio logo" /> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/dgk-logo-171x58.png" width="171" height="58" alt="dgk logo" /> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/rpm-logo-137x114.png" width="137" height="114" alt="rpm logo" /> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/dc-logo-81x70.png" width="81" height="70" alt="dc logo" /> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/penny-australia-122x123.png" width="122" height="123" alt="penny australia" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/penny-australia.png 122w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/penny-australia-40x40.png 40w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/penny-australia-100x100.png 100w" sizes="(max-width: 122px) 100vw, 122px" /> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/vans-257x113.png" width="257" height="113" alt="vans" /> 
<h2>Features<br/></h2>
 <p>Reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt.</p> <ul> <li>Magna aliqua. Ut enim ad minim veniam</li> <li>Fugiat nulla paria.</li> <li>Officia deserunt mollit ani.</li> <li>Magna aliqua. Ut enim ad minim veniam.</li> <li>Fugiat nulla paria</li> <li>Officia deserunt mollit ani</li> <li>Officia deserunt mollit anim</li> </ul>
 
 <a href="https://themify.me/demo/themes/shoppe-sport/shop/" >See More</a> 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skate-image-935x805.png" width="935" height="805" alt="skate image" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skate-image.png 935w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skate-image-300x258.png 300w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skate-image-768x661.png 768w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skate-image-600x517.png 600w" sizes="(max-width: 935px) 100vw, 935px" /> 
<h2>Features<br/></h2>
 
 <ul data-width="380" data-height="502">
 <li data-product-id="63"> <figure ><a href="https://themify.me/demo/themes/shoppe-sport/product/bob-marley-bamboo/"><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9-380x502.png" width="380" height="502" alt="skateboard9" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9-380x502.png 380w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9-227x300.png 227w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9-5x6.png 5w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9-53x70.png 53w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9.png 530w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard9-370x488.png 370w" sizes="(max-width: 380px) 100vw, 380px" /></a></figure> <a href="https://themify.me/demo/themes/shoppe-sport/product/bob-marley-bamboo/"> <h3>Bob Marley Bamboo</h3> &#36;122.00 </a> <a href="/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?add-to-cart=63" data-quantity="1" data-product_id="63" data-product_sku="" aria-label="Add &ldquo;Bob Marley Bamboo&rdquo; to your cart" rel="nofollow">Add to cart</a>[<a href="https://themify.me/demo/themes/shoppe-sport/wp-admin/post.php?post=63&#038;action=edit">Edit</a>] <a data-id="63" onclick="javascript:void(0)" href="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?action=themify_add_wishlist&id=63"> Wishlist </a> <a onclick="return false;" data-image="https://themify.me/demo/themes/shoppe-sport/wp-content/plugins/woocommerce/assets/images/placeholder.png" href="https://themify.me/demo/themes/shoppe-sport/product/bob-marley-bamboo/?post_in_lightbox=1">Quick Look</a> <a href="javascript:void(0);"></a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fbob-marley-bamboo&#038;text=Bob+Marley+Bamboo\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fbob-marley-bamboo&#038;t=Bob+Marley+Bamboo&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fbob-marley-bamboo%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fbob-marley-bamboo&#038;description=Bob+Marley+Bamboo&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Ffiles%2F2018%2F05%2Fskateboard9.png\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fbob-marley-bamboo\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fbob-marley-bamboo&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 </li> <li data-product-id="73"> <figure ><a href="https://themify.me/demo/themes/shoppe-sport/product/robo-monster-chaos/"><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8-380x502.png" width="380" height="502" alt="skateboard8" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8-380x502.png 380w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8-227x300.png 227w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8-5x6.png 5w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8-53x70.png 53w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8.png 530w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8-370x488.png 370w" sizes="(max-width: 380px) 100vw, 380px" /></a></figure> <a href="https://themify.me/demo/themes/shoppe-sport/product/robo-monster-chaos/"> <h3>Robo Monster Chaos</h3> &#36;225.00 </a> <a href="/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?add-to-cart=73" data-quantity="1" data-product_id="73" data-product_sku="" aria-label="Add &ldquo;Robo Monster Chaos&rdquo; to your cart" rel="nofollow">Add to cart</a>[<a href="https://themify.me/demo/themes/shoppe-sport/wp-admin/post.php?post=73&#038;action=edit">Edit</a>] <a data-id="73" onclick="javascript:void(0)" href="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?action=themify_add_wishlist&id=73"> Wishlist </a> <a onclick="return false;" data-image="https://themify.me/demo/themes/shoppe-sport/wp-content/plugins/woocommerce/assets/images/placeholder.png" href="https://themify.me/demo/themes/shoppe-sport/product/robo-monster-chaos/?post_in_lightbox=1">Quick Look</a> <a href="javascript:void(0);"></a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Frobo-monster-chaos&#038;text=Robo+Monster+Chaos\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Frobo-monster-chaos&#038;t=Robo+Monster+Chaos&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Frobo-monster-chaos%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Frobo-monster-chaos&#038;description=Robo+Monster+Chaos&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Ffiles%2F2018%2F05%2Fskateboard8.png\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Frobo-monster-chaos\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Frobo-monster-chaos&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 </li> <li data-product-id="74"> <figure ><a href="https://themify.me/demo/themes/shoppe-sport/product/kryptonics-board/"><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-380x502.png" width="380" height="502" alt="skateboard7" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-380x502.png 380w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-227x300.png 227w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-5x6.png 5w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-53x70.png 53w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7.png 530w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-370x488.png 370w" sizes="(max-width: 380px) 100vw, 380px" /></a></figure> <a href="https://themify.me/demo/themes/shoppe-sport/product/kryptonics-board/"> <h3>Kryptonics Board</h3> &#36;125.00 </a> <a href="/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?add-to-cart=74" data-quantity="1" data-product_id="74" data-product_sku="" aria-label="Add &ldquo;Kryptonics Board&rdquo; to your cart" rel="nofollow">Add to cart</a>[<a href="https://themify.me/demo/themes/shoppe-sport/wp-admin/post.php?post=74&#038;action=edit">Edit</a>] <a data-id="74" onclick="javascript:void(0)" href="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?action=themify_add_wishlist&id=74"> Wishlist </a> <a onclick="return false;" data-image="https://themify.me/demo/themes/shoppe-sport/wp-content/plugins/woocommerce/assets/images/placeholder.png" href="https://themify.me/demo/themes/shoppe-sport/product/kryptonics-board/?post_in_lightbox=1">Quick Look</a> <a href="javascript:void(0);"></a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fkryptonics-board&#038;text=Kryptonics+Board\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fkryptonics-board&#038;t=Kryptonics+Board&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fkryptonics-board%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fkryptonics-board&#038;description=Kryptonics+Board&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Ffiles%2F2018%2F05%2Fskateboard7.png\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fkryptonics-board\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fkryptonics-board&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 </li> <li data-product-id="75"> <figure ><a href="https://themify.me/demo/themes/shoppe-sport/product/ethnic-wood/"><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6-380x502.png" width="380" height="502" alt="skateboard6" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6-380x502.png 380w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6-227x300.png 227w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6-5x6.png 5w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6-53x70.png 53w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6.png 530w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6-370x488.png 370w" sizes="(max-width: 380px) 100vw, 380px" /></a></figure> <a href="https://themify.me/demo/themes/shoppe-sport/product/ethnic-wood/"> <h3>Ethnic Wood</h3> &#36;250.00 </a> <a href="/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?add-to-cart=75" data-quantity="1" data-product_id="75" data-product_sku="" aria-label="Add &ldquo;Ethnic Wood&rdquo; to your cart" rel="nofollow">Add to cart</a>[<a href="https://themify.me/demo/themes/shoppe-sport/wp-admin/post.php?post=75&#038;action=edit">Edit</a>] <a data-id="75" onclick="javascript:void(0)" href="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?action=themify_add_wishlist&id=75"> Wishlist </a> <a onclick="return false;" data-image="https://themify.me/demo/themes/shoppe-sport/wp-content/plugins/woocommerce/assets/images/placeholder.png" href="https://themify.me/demo/themes/shoppe-sport/product/ethnic-wood/?post_in_lightbox=1">Quick Look</a> <a href="javascript:void(0);"></a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fethnic-wood&#038;text=Ethnic+Wood\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fethnic-wood&#038;t=Ethnic+Wood&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fethnic-wood%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fethnic-wood&#038;description=Ethnic+Wood&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Ffiles%2F2018%2F05%2Fskateboard6.png\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fethnic-wood\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fethnic-wood&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 </li> <li data-product-id="76"> <figure ><a href="https://themify.me/demo/themes/shoppe-sport/product/hero-clown/"><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5-380x502.png" width="380" height="502" alt="skateboard5" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5-380x502.png 380w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5-227x300.png 227w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5-5x6.png 5w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5-53x70.png 53w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5.png 530w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5-370x488.png 370w" sizes="(max-width: 380px) 100vw, 380px" /></a></figure> <a href="https://themify.me/demo/themes/shoppe-sport/product/hero-clown/"> <h3>Hero Clown</h3> &#36;275.00 </a> <a href="/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?add-to-cart=76" data-quantity="1" data-product_id="76" data-product_sku="" aria-label="Add &ldquo;Hero Clown&rdquo; to your cart" rel="nofollow">Add to cart</a>[<a href="https://themify.me/demo/themes/shoppe-sport/wp-admin/post.php?post=76&#038;action=edit">Edit</a>] <a data-id="76" onclick="javascript:void(0)" href="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?action=themify_add_wishlist&id=76"> Wishlist </a> <a onclick="return false;" data-image="https://themify.me/demo/themes/shoppe-sport/wp-content/plugins/woocommerce/assets/images/placeholder.png" href="https://themify.me/demo/themes/shoppe-sport/product/hero-clown/?post_in_lightbox=1">Quick Look</a> <a href="javascript:void(0);"></a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fhero-clown&#038;text=Hero+Clown\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fhero-clown&#038;t=Hero+Clown&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fhero-clown%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fhero-clown&#038;description=Hero+Clown&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Ffiles%2F2018%2F05%2Fskateboard5.png\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fhero-clown\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Fhero-clown&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 </li> <li data-product-id="77"> <figure ><a href="https://themify.me/demo/themes/shoppe-sport/product/futurio-board-v1/"><img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4-380x502.png" width="380" height="502" alt="skateboard4" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4-380x502.png 380w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4-227x300.png 227w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4-5x6.png 5w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4-53x70.png 53w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4.png 530w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4-370x488.png 370w" sizes="(max-width: 380px) 100vw, 380px" /></a></figure> <a href="https://themify.me/demo/themes/shoppe-sport/product/futurio-board-v1/"> <h3>Futurio Board V1</h3> &#36;345.00 </a> <a href="/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?add-to-cart=77" data-quantity="1" data-product_id="77" data-product_sku="" aria-label="Add &ldquo;Futurio Board V1&rdquo; to your cart" rel="nofollow">Add to cart</a>[<a href="https://themify.me/demo/themes/shoppe-sport/wp-admin/post.php?post=77&#038;action=edit">Edit</a>] <a data-id="77" onclick="javascript:void(0)" href="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php?action=themify_add_wishlist&id=77"> Wishlist </a> <a onclick="return false;" data-image="https://themify.me/demo/themes/shoppe-sport/wp-content/plugins/woocommerce/assets/images/placeholder.png" href="https://themify.me/demo/themes/shoppe-sport/product/futurio-board-v1/?post_in_lightbox=1">Quick Look</a> <a href="javascript:void(0);"></a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Ffuturio-board-v1&#038;text=Futurio+Board+V1\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Ffuturio-board-v1&#038;t=Futurio+Board+V1&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Ffuturio-board-v1%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Ffuturio-board-v1&#038;description=Futurio+Board+V1&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Ffiles%2F2018%2F05%2Fskateboard4.png\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Ffuturio-board-v1\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fshoppe-sport%2Fproduct%2Ffuturio-board-v1&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 </li> </ul>
 
 
 <a href="https://www.youtube.com/watch?v=nFdVOdP9UGU" data-zoom-config="90%|60%" > <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/left-column-skateboard.jpg" alt="left column skateboard" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/left-column-skateboard.jpg 800w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/left-column-skateboard-600x579.jpg 600w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/left-column-skateboard-768x741.jpg 768w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/left-column-skateboard-40x40.jpg 40w" sizes="(max-width: 800px) 100vw, 800px" /> </a> 
<h2>Testimonial<br/></h2>
 <ul data-id="testimonial-slider-0-" data-visible="1" data-scroll="1" data-auto-scroll="off" data-speed="1" data-wrap="yes" data-arrow="no" data-pagination="yes" data-effect="scroll" data-height="variable" data-pause-on-hover="resume" > <li> 
 <p>The best sport shop, I can find the board I need for the next competition, and they delivered free. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam</p> Tony Eagle </li> <li> 
 <p>Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?</p> Bam Blomqvist </li> <li> 
 <p>Vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate</p> Jonathan Exo </li> </ul> 
 <h4>Free Delivery</h4> <p>Free delivery over $50 orders</p>
 
 <a href="https://themify.me/demo/themes/shoppe-sport/shop/" >Shop Now</a> 
<h2>Latest News<br/></h2><!--/themify_builder_static-->',
  'post_title' => 'Home',
  'post_excerpt' => '',
  'post_name' => 'home',
  'post_modified' => '2018-05-14 23:50:56',
  'post_modified_gmt' => '2018-05-14 23:50:56',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?page_id=61',
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
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/skateboard.png\\",\\"width_image\\":\\"806\\",\\"height_image\\":\\"970\\",\\"param_image\\":\\"regular\\",\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"30\\",\\"checkbox_border_apply_all\\":\\"1\\"}}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h1>Skateboarding done right<\\\\/h1>\\\\n<h3>Strong &amp; comes in all size<\\\\/h3>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"button_background_color\\":\\"#ec2e81\\",\\"link_color\\":\\"#ffffff\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"normal\\",\\"buttons_style\\":\\"squared\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"Shop Now\\",\\"link\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/shop\\\\/\\",\\"link_options\\":\\"regular\\",\\"icon_alignment\\":\\"left\\"}]}}]}],\\"column_alignment\\":\\"col_align_middle\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-top-1.png\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"left-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"left-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"7\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"8\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col6-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/adio-logo.png\\",\\"width_image\\":\\"156\\",\\"height_image\\":\\"95\\",\\"param_image\\":\\"regular\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col6-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/dgk-logo.png\\",\\"width_image\\":\\"171\\",\\"height_image\\":\\"58\\",\\"param_image\\":\\"regular\\"}}]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col6-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/rpm-logo.png\\",\\"width_image\\":\\"137\\",\\"height_image\\":\\"114\\",\\"param_image\\":\\"regular\\"}}]},{\\"column_order\\":\\"3\\",\\"grid_class\\":\\"col6-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/dc-logo.png\\",\\"width_image\\":\\"81\\",\\"height_image\\":\\"70\\",\\"param_image\\":\\"regular\\"}}]},{\\"column_order\\":\\"4\\",\\"grid_class\\":\\"col6-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/penny-australia.png\\",\\"width_image\\":\\"122\\",\\"height_image\\":\\"123\\",\\"param_image\\":\\"regular\\"}}]},{\\"column_order\\":\\"5\\",\\"grid_class\\":\\"col6-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-top\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/vans.png\\",\\"width_image\\":\\"257\\",\\"height_image\\":\\"113\\",\\"param_image\\":\\"regular\\"}}]}],\\"column_alignment\\":\\"col_align_middle\\",\\"gutter\\":\\"gutter-none\\",\\"col_mobile\\":\\"column3-1 tb_3col\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"fancy-heading\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"heading\\":\\"Features\\",\\"heading_tag\\":\\"h2\\",\\"text_alignment\\":\\"themify-text-left\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>Reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt.<\\\\/p>\\\\n<ul>\\\\n<li>Magna aliqua. Ut enim ad minim veniam<\\\\/li>\\\\n<li>Fugiat nulla paria.<\\\\/li>\\\\n<li>Officia deserunt mollit ani.<\\\\/li>\\\\n<li>Magna aliqua. Ut enim ad minim veniam.<\\\\/li>\\\\n<li>Fugiat nulla paria<\\\\/li>\\\\n<li>Officia deserunt mollit ani<\\\\/li>\\\\n<li>Officia deserunt mollit anim<\\\\/li>\\\\n<\\\\/ul>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"button_background_color\\":\\"#ec2e81\\",\\"link_color\\":\\"#ffffff\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"normal\\",\\"buttons_style\\":\\"squared\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"See More\\",\\"link\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/shop\\\\/\\",\\"link_options\\":\\"regular\\",\\"icon_alignment\\":\\"left\\"}]}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/number-02.png\\",\\"background_repeat\\":\\"repeat-none\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"left-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/skate-image.png\\",\\"width_image\\":\\"935\\",\\"height_image\\":\\"805\\",\\"param_image\\":\\"regular\\"}},{\\"mod_name\\":\\"fancy-heading\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/number-03.png\\",\\"background_repeat\\":\\"no-repeat\\",\\"padding_top\\":\\"6\\",\\"padding_top_unit\\":\\"em\\",\\"padding_bottom\\":\\"9\\",\\"padding_bottom_unit\\":\\"em\\",\\"margin_top\\":\\"-7\\",\\"margin_top_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"heading\\":\\"Features\\",\\"heading_tag\\":\\"h2\\",\\"text_alignment\\":\\"themify-text-center\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"products\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"query_products\\":\\"all\\",\\"category_products\\":\\"0|multiple\\",\\"hide_child_products\\":\\"no\\",\\"hide_free_products\\":\\"no\\",\\"hide_outofstock_products\\":\\"no\\",\\"post_per_page_products\\":\\"6\\",\\"orderby_products\\":\\"date\\",\\"order_products\\":\\"desc\\",\\"template_products\\":\\"list\\",\\"layout_products\\":\\"grid3\\",\\"layout_slider\\":\\"slider-default\\",\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"off\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"pause_on_hover_slider\\":\\"resume\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"yes\\",\\"height_slider\\":\\"variable\\",\\"description_products\\":\\"none\\",\\"hide_feat_img_products\\":\\"no\\",\\"img_width_products\\":\\"380\\",\\"img_height_products\\":\\"502\\",\\"unlink_feat_img_products\\":\\"no\\",\\"hide_post_title_products\\":\\"no\\",\\"unlink_post_title_products\\":\\"no\\",\\"hide_price_products\\":\\"no\\",\\"hide_add_to_cart_products\\":\\"no\\",\\"hide_rating_products\\":\\"no\\",\\"hide_sales_badge\\":\\"no\\",\\"hide_page_nav_products\\":\\"yes\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat-none\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"left-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat-none\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"right-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/left-column-skateboard.jpg\\",\\"auto_fullwidth\\":\\"1\\",\\"link_image\\":\\"https:\\\\/\\\\/www.youtube.com\\\\/watch?v=nFdVOdP9UGU\\",\\"param_image\\":\\"lightbox\\",\\"image_zoom_icon\\":\\"zoom\\",\\"lightbox_width\\":\\"90\\",\\"lightbox_size_unit_width\\":\\"percents\\",\\"lightbox_height\\":\\"60\\",\\"lightbox_size_unit_height\\":\\"percents\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"fancy-heading\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"0\\",\\"background_repeat\\":\\"no-repeat\\",\\"padding_top_unit\\":\\"em\\",\\"padding_bottom_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"2\\",\\"margin_top_unit\\":\\"em\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"heading\\":\\"Testimonial\\",\\"heading_tag\\":\\"h2\\",\\"text_alignment\\":\\"themify-text-center\\"}},{\\"mod_name\\":\\"testimonial-slider\\",\\"mod_settings\\":{\\"font_size\\":\\"1.2\\",\\"font_size_unit\\":\\"em\\",\\"line_height\\":\\"1.5\\",\\"line_height_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"layout_testimonial\\":\\"image-top\\",\\"tab_content_testimonial\\":[{\\"content_testimonial\\":\\"<p>The best sport shop, I can find the board I need for the next competition, and they delivered free. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam<\\\\/p>\\",\\"person_name_testimonial\\":\\"Tony Eagle\\"},{\\"content_testimonial\\":\\"<p>Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?<\\\\/p>\\",\\"person_name_testimonial\\":\\"Bam Blomqvist\\"},{\\"content_testimonial\\":\\"<p>Vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate<\\\\/p>\\",\\"person_name_testimonial\\":\\"Jonathan Exo\\"}],\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"off\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"pause_on_hover_slider\\":\\"resume\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"no\\",\\"height_slider\\":\\"variable\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/number-04.png\\",\\"background_repeat\\":\\"repeat-none\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right\\":\\"5\\",\\"padding_right_unit\\":\\"%\\",\\"padding_left\\":\\"5\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}],\\"column_alignment\\":\\"col_align_middle\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-1\\"},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"font_size\\":\\"1.3\\",\\"font_size_unit\\":\\"em\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h4>Free Delivery<\\\\/h4>\\\\n<p>Free delivery over $50 orders<\\\\/p>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"button_background_color\\":\\"#ec2e81\\",\\"link_color\\":\\"#ffffff\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"normal\\",\\"buttons_style\\":\\"squared\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"Shop Now\\",\\"link\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/shop\\\\/\\",\\"link_options\\":\\"regular\\",\\"icon_alignment\\":\\"left\\"}]}}]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col4-1\\"}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-free-delivery-05-1024x410.jpg\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"10\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"10\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"6\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"fancy-heading\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"0\\",\\"background_repeat\\":\\"no-repeat\\",\\"padding_top_unit\\":\\"em\\",\\"padding_bottom_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top_unit\\":\\"em\\",\\"margin_bottom\\":\\"2\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"heading\\":\\"Latest News\\",\\"heading_tag\\":\\"h2\\",\\"text_alignment\\":\\"themify-text-center\\"}},{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"layout_post\\":\\"grid4\\",\\"post_type_post\\":\\"post\\",\\"content_layout\\":\\"boxed\\",\\"masonry_post\\":\\"yes\\",\\"type_query_post\\":\\"category\\",\\"category_post\\":\\"0|multiple\\",\\"post_tag_post\\":\\"0|multiple\\",\\"product_cat_post\\":\\"0|multiple\\",\\"product_tag_post\\":\\"0|multiple\\",\\"product_shipping_class_post\\":\\"0|multiple\\",\\"post_per_page_post\\":\\"4\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"270\\",\\"img_height_post\\":\\"180\\",\\"hide_post_date_post\\":\\"no\\",\\"hide_post_meta_post\\":\\"no\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/number-06.png\\",\\"background_repeat\\":\\"repeat-none\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"7\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
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
  'ID' => 172,
  'post_date' => '2018-05-12 02:50:21',
  'post_date_gmt' => '2018-05-12 02:50:21',
  'post_content' => '',
  'post_title' => 'Wishlist',
  'post_excerpt' => '',
  'post_name' => 'wishlist',
  'post_modified' => '2018-05-12 02:50:21',
  'post_modified_gmt' => '2018-05-12 02:50:21',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?page_id=172',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
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
  'post_date' => '2018-05-12 02:51:29',
  'post_date_gmt' => '2018-05-12 02:51:29',
  'post_content' => '<!--themify_builder_static--><h1>News</h1>
<h3>Latest News<br/></h3>
 <h3>PACIFIC SKATEBOARD OCEAN</h3> <p>Free delivery over $50 orders</p>
 
 <a href="https://themify.me/demo/themes/shoppe-sport/shop/" >Shop Now</a><!--/themify_builder_static-->',
  'post_title' => 'Blog',
  'post_excerpt' => '',
  'post_name' => 'blog',
  'post_modified' => '2018-05-14 23:45:55',
  'post_modified_gmt' => '2018-05-14 23:45:55',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?page_id=175',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'layout' => 'grid4',
    'image_width' => '280',
    'image_height' => '160',
    'product_query_type' => 'all',
    'product_archive_show_short' => 'excerpt',
    'product_hide_navigation' => 'no',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h1>News<\\\\/h1>\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-top-title.png\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"12\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"12\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"fancy-heading\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"20\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"heading\\":\\"Latest News\\",\\"heading_tag\\":\\"h3\\",\\"text_alignment\\":\\"themify-text-center\\"}},{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"layout_post\\":\\"grid4\\",\\"post_type_post\\":\\"post\\",\\"content_layout\\":\\"boxed\\",\\"masonry_post\\":\\"yes\\",\\"type_query_post\\":\\"category\\",\\"category_post\\":\\"0|multiple\\",\\"post_tag_post\\":\\"0|multiple\\",\\"product_cat_post\\":\\"0|multiple\\",\\"product_tag_post\\":\\"0|multiple\\",\\"product_shipping_class_post\\":\\"0|multiple\\",\\"post_per_page_post\\":\\"8\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"270\\",\\"img_height_post\\":\\"180\\",\\"hide_page_nav_post\\":\\"no\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-our-blog.png\\",\\"background_repeat\\":\\"best-fit-image\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"right-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"10\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"10\\",\\"padding_bottom_unit\\":\\"%\\",\\"margin_top\\":\\"-8\\",\\"margin_top_unit\\":\\"%\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"font_size\\":\\"1.3\\",\\"font_size_unit\\":\\"em\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h3>PACIFIC SKATEBOARD OCEAN<\\\\/h3>\\\\n<p>Free delivery over $50 orders<\\\\/p>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"button_background_color\\":\\"#242856\\",\\"link_color\\":\\"#ffffff\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"normal\\",\\"buttons_style\\":\\"squared\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"Shop Now\\",\\"link\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/shop\\\\/\\",\\"link_options\\":\\"regular\\",\\"icon_alignment\\":\\"left\\"}]}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-skater-1024x410.jpg\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"18\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"18\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
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
  'ID' => 191,
  'post_date' => '2018-05-13 02:56:57',
  'post_date_gmt' => '2018-05-13 02:56:57',
  'post_content' => '<!--themify_builder_static--><h1>About Us</h1>
 <h2>Skateboards that make you fly</h2> <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sit amet mattis sapien. Mauris cursus tellus ut egestas maximus. Aliquam laoreet felis sed porta ultriciese, et eleifend turpis eleifend. lacinia semper. Nulla in lectus non ante efficitur aliquet. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Donec elementum turpis a lacinia semper.</p>
 <h3>About Skate free style</h3> <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sit amet mattis sapien. Mauris cursus tellus ut egestas maximus. Aliquam laoreet felis sed porta ultricies. Aliquam pulvinar erat ut tortor vulputate, et eleifend turpis eleifend. Nam sollicitudin bibendum tellus et consequat. Curabitur ultrices posuere bibendum. Ut a mi lorem. Nunc pulvinar ipsum vitae egestas suscipit. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Donec elementum turpis a lacinia semper. Nulla in lectus non ante efficitur aliquet.</p>
 <p>Reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt.</p> <ul> <li>Magna aliqua. Ut enim ad minim veniam</li> <li>Fugiat nulla paria.</li> <li>Officia deserunt mollit ani.</li> <li>Magna aliqua. Ut enim ad minim veniam.</li> <li>Fugiat nulla paria</li> <li>Officia deserunt mollit ani</li> <li>Officia deserunt mollit anim</li> </ul>
 
 <a href="https://themify.me/demo/themes/shoppe-sport/shop/" >Shop Now</a> 
 <h2>Make your uphill battle a breeze</h2> <ul> <li>Magna aliqua Ut enim ad minim veniam.</li> <li>Fugiat nulla paria.</li> <li>Officia deserunt mollit.</li> <li>Ani officia deserunt mollit anim.</li> </ul>
 <h2>A Belt Drive System</h2> <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sit amet mattis sapien. Mauris cursus tellus ut egestas maximus. Aliquam laoreet felis sed porta ultricies. Aliquam pulvinar erat ut tortor vulputate, et eleifend turpis eleifend. Nam sollicitudin bibendum tellus et consequat. Curabitur ultrices posuere bibendum. Ut a mi lorem. Nunc pulvinar ipsum vitae egestas suscipit.</p>
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-image.png" alt="skateboard image" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-image.png 1042w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-image-300x275.png 300w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-image-768x703.png 768w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-image-1024x938.png 1024w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard-image-600x549.png 600w" sizes="(max-width: 1042px) 100vw, 1042px" /> 
<h2>Our Team<br/></h2>
 <p>You’ve come to the right place! Skate&#8217;s dedicated support and service team is here for you, from diagnosing board issues to answering questions about technical specs–and everything in between. Whether you’re still deciding on a board or have been riding for 2 years, we’ve got your back. Visit our support page or give us a ring at +62 (021) 898999.</p>
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/teenagers-1-1024x535.jpg" alt="teenagers 1" srcset="https://themify.me/demo/themes/shoppe-sport/files/2018/05/teenagers-1-1024x535.jpg 1024w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/teenagers-1-300x157.jpg 300w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/teenagers-1-768x402.jpg 768w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/teenagers-1-600x314.jpg 600w, https://themify.me/demo/themes/shoppe-sport/files/2018/05/teenagers-1.jpg 1268w" sizes="(max-width: 1024px) 100vw, 1024px" /> 
 <h3>PACIFIC SKATEBOARD OCEAN</h3> <p>Free delivery over $50 orders</p>
 
 <a href="https://themify.me/demo/themes/shoppe-sport/shop/" >Shop Now</a><!--/themify_builder_static-->',
  'post_title' => 'About Us',
  'post_excerpt' => '',
  'post_name' => 'about-us',
  'post_modified' => '2018-05-14 23:33:39',
  'post_modified_gmt' => '2018-05-14 23:33:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?page_id=191',
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
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h1>About Us<\\\\/h1>\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-top-title.png\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"12\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"12\\",\\"padding_bottom_unit\\":\\"%\\",\\"margin_bottom\\":\\"-7\\",\\"margin_bottom_unit\\":\\"%\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_size\\":\\"1.2\\",\\"font_size_unit\\":\\"em\\",\\"line_height\\":\\"1.6\\",\\"line_height_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>Skateboards that make you fly<\\\\/h2>\\\\n<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sit amet mattis sapien. Mauris cursus tellus ut egestas maximus. Aliquam laoreet felis sed porta ultriciese, et eleifend turpis eleifend. lacinia semper. Nulla in lectus non ante efficitur aliquet. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Donec elementum turpis a lacinia semper.<\\\\/p>\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"5\\",\\"margin_top_unit\\":\\"%\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h3>About Skate free style<\\\\/h3>\\\\n<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sit amet mattis sapien. Mauris cursus tellus ut egestas maximus. Aliquam laoreet felis sed porta ultricies. Aliquam pulvinar erat ut tortor vulputate, et eleifend turpis eleifend. Nam sollicitudin bibendum tellus et consequat. Curabitur ultrices posuere bibendum. Ut a mi lorem. Nunc pulvinar ipsum vitae egestas suscipit. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Donec elementum turpis a lacinia semper. Nulla in lectus non ante efficitur aliquet.<\\\\/p>\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-about-top.png\\",\\"background_repeat\\":\\"best-fit-image\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"right-top\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"14\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"10\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\"},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>Reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt.<\\\\/p>\\\\n<ul>\\\\n<li>Magna aliqua. Ut enim ad minim veniam<\\\\/li>\\\\n<li>Fugiat nulla paria.<\\\\/li>\\\\n<li>Officia deserunt mollit ani.<\\\\/li>\\\\n<li>Magna aliqua. Ut enim ad minim veniam.<\\\\/li>\\\\n<li>Fugiat nulla paria<\\\\/li>\\\\n<li>Officia deserunt mollit ani<\\\\/li>\\\\n<li>Officia deserunt mollit anim<\\\\/li>\\\\n<\\\\/ul>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"0\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"normal\\",\\"buttons_style\\":\\"squared\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"Shop Now\\",\\"link\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/shop\\\\/\\",\\"link_options\\":\\"regular\\",\\"icon_alignment\\":\\"left\\"}]}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#242856\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"8\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"7\\",\\"margin_top_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-features-1024x625.png\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-bottom\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"20\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"20\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"5\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>Make your uphill battle a breeze<\\\\/h2>\\\\n<ul>\\\\n<li>Magna aliqua Ut enim ad minim veniam.<\\\\/li>\\\\n<li>Fugiat nulla paria.<\\\\/li>\\\\n<li>Officia deserunt mollit.<\\\\/li>\\\\n<li>Ani officia deserunt mollit anim.<\\\\/li>\\\\n<\\\\/ul>\\",\\"custom_parallax_scroll_speed\\":\\"2\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>A Belt Drive System<\\\\/h2>\\\\n<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sit amet mattis sapien. Mauris cursus tellus ut egestas maximus. Aliquam laoreet felis sed porta ultricies. Aliquam pulvinar erat ut tortor vulputate, et eleifend turpis eleifend. Nam sollicitudin bibendum tellus et consequat. Curabitur ultrices posuere bibendum. Ut a mi lorem. Nunc pulvinar ipsum vitae egestas suscipit.<\\\\/p>\\",\\"custom_parallax_scroll_speed\\":\\"1\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-right\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/skateboard-image.png\\",\\"param_image\\":\\"regular\\"}}]}],\\"column_alignment\\":\\"col_align_middle\\",\\"gutter\\":\\"gutter-none\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"fancy-heading\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"heading\\":\\"Our Team\\",\\"heading_tag\\":\\"h2\\",\\"text_alignment\\":\\"themify-text-center\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>You’ve come to the right place! Skate\\\\\\\\\\\'s dedicated support and service team is here for you, from diagnosing board issues to answering questions about technical specs–and everything in between. Whether you’re still deciding on a board or have been riding for 2 years, we’ve got your back. Visit our support page or give us a ring at +62 (021) 898999.<\\\\/p>\\"}},{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/teenagers-1-1024x535.jpg\\",\\"param_image\\":\\"regular\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-text-1024x430.png\\",\\"background_repeat\\":\\"repeat-none\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"left-top\\",\\"background_color\\":\\"#f6f6f8\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"14\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"font_size\\":\\"1.3\\",\\"font_size_unit\\":\\"em\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"1\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h3>PACIFIC SKATEBOARD OCEAN<\\\\/h3>\\\\n<p>Free delivery over $50 orders<\\\\/p>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"0\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"large\\",\\"buttons_style\\":\\"squared\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"Shop Now\\",\\"link\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/shop\\\\/\\",\\"link_options\\":\\"regular\\",\\"icon_alignment\\":\\"left\\"}]}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-skater-1024x410.jpg\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"18\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"18\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\"}},{\\"row_order\\":\\"6\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
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
  'ID' => 193,
  'post_date' => '2018-05-13 02:57:40',
  'post_date_gmt' => '2018-05-13 02:57:40',
  'post_content' => '<!--themify_builder_static--><h1>Contact</h1>
 <p>We love to hear from you, whether a complaint or a compliment. Get in touch now.</p>
 
 <form action="https://themify.me/demo/themes/shoppe-sport/wp-admin/admin-ajax.php" id="contact-0--form" method="post"> 
 <label for="contact-0--contact-name">Your Name *</label> <input type="text" name="contact-name" placeholder="" id="contact-0--contact-name" value="" required /> 
 <label for="contact-0--contact-email">Your Email *</label> <input type="text" name="contact-email" placeholder="" id="contact-0--contact-email" value="" required /> 
 <label for="contact-0--contact-subject">Subject *</label> <input type="text" name="contact-subject" placeholder="" id="contact-0--contact-subject" value="" required /> <label for="contact-0--contact-message">Message *</label> <textarea name="contact-message" placeholder="" id="contact-0--contact-message" rows="8" cols="45" required></textarea> 
 <label> <input type="checkbox" name="gdpr" value="1" required> I consent to my submitted data being collected and stored * </label> <button type="submit"> Submit </button> </form>
 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/office-icon.png" title="Office Address" alt="Oplication Block Street, Block 36, City, Australia 804538" /> <h3> Office Address </h3> Oplication Block Street, Block 36, City, Australia 804538 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/offfice-hours.png" title="Office Hours" alt="MON - FRIDAY: 12 Noon to 9 PM SAT - SUN: 14 Noon to 12 PM" /> <h3> Office Hours </h3> MON &#8211; FRIDAY: 12 Noon to 9 PM SAT &#8211; SUN: 14 Noon to 12 PM 
 
 <img src="https://themify.me/demo/themes/shoppe-sport/files/2018/05/office-mail.png" title="Contact" alt="Support: 1-800-268-8866 Email: info@skate.com" /> <h3> Contact </h3> Support: 1-800-268-8866 Email: info@skate.com 
 
 
 Armoury St, Toronto, ON Canada<!--/themify_builder_static-->',
  'post_title' => 'Contact',
  'post_excerpt' => '',
  'post_name' => 'contact',
  'post_modified' => '2018-05-14 23:18:53',
  'post_modified_gmt' => '2018-05-14 23:18:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?page_id=193',
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
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_color\\":\\"#ffffff\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h1>Contact<\\\\/h1>\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/bg-contact-page.png\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#fcfcfc\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"12\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"22\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>We love to hear from you, whether a complaint or a compliment. Get in touch now.<\\\\/p>\\"}},{\\"mod_name\\":\\"contact\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_border_inputs_apply_all\\":\\"1\\",\\"checkbox_border_send_apply_all\\":\\"1\\",\\"checkbox_padding_success_message_apply_all\\":\\"1\\",\\"checkbox_margin_success_message_apply_all\\":\\"1\\",\\"checkbox_border_success_message_apply_all\\":\\"1\\",\\"checkbox_padding_error_message_apply_all\\":\\"1\\",\\"checkbox_margin_error_message_apply_all\\":\\"1\\",\\"checkbox_border_error_message_apply_all\\":\\"1\\",\\"layout_contact\\":\\"animated-label\\",\\"mail_contact\\":\\"jempolsemut@gmail.com\\",\\"gdpr\\":\\"accept\\",\\"field_name_label\\":\\"Your Name\\",\\"field_email_label\\":\\"Your Email\\",\\"field_subject_label\\":\\"Subject\\",\\"field_subject_require\\":\\"yes\\",\\"field_subject_active\\":\\"yes\\",\\"field_message_label\\":\\"Message\\",\\"field_extra\\":\\"{ \\\\\\\\\\\\\\"fields\\\\\\\\\\\\\\": [] }\\",\\"field_sendcopy_label\\":\\"Send a copy to myself\\",\\"field_order\\":\\"{}\\",\\"field_send_label\\":\\"Submit\\",\\"field_send_align\\":\\"left\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#ffffff\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"8\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top_unit\\":\\"em\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_top_color\\":\\"#ededed\\",\\"border_top_width\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"-18\\",\\"margin_top_unit\\":\\"%\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top_unit\\":\\"%\\",\\"checkbox_border_apply_all\\":\\"1\\"}}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-left\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/office-icon.png\\",\\"title_image\\":\\"Office Address\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"Oplication Block Street, Block 36, \\\\nCity, Australia 804538\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#fafafa\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"4\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_color\\":\\"#dddddd\\",\\"border_right_width\\":\\"1\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_style\\":\\"none\\"}}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-left\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/offfice-hours.png\\",\\"title_image\\":\\"Office Hours\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"MON - FRIDAY: 12 Noon to 9 PM\\\\nSAT - SUN: 14 Noon to 12 PM\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#fafafa\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"4\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_color\\":\\"#dddddd\\",\\"border_right_width\\":\\"1\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_style\\":\\"none\\"}}},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-left\\",\\"url_image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/office-mail.png\\",\\"title_image\\":\\"Contact\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"Support: 1-800-268-8866\\\\nEmail: info@skate.com\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#fafafa\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"4\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\"}}],\\"gutter\\":\\"gutter-none\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top_unit\\":\\"em\\",\\"margin_bottom\\":\\"-80\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"custom_parallax_scroll_zindex\\":\\"1\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"maps-pro\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"map_display_type\\":\\"dynamic\\",\\"w_map\\":\\"100\\",\\"unit_w\\":\\"%\\",\\"w_map_static\\":\\"500\\",\\"h_map\\":\\"645\\",\\"type_map\\":\\"ROADMAP\\",\\"style_map\\":\\"greyscale\\",\\"scrollwheel_map\\":\\"disable\\",\\"draggable_map\\":\\"enable\\",\\"disable_map_ui\\":\\"no\\",\\"zoom_map\\":\\"17\\",\\"map_polyline\\":\\"no\\",\\"map_polyline_geodesic\\":\\"no\\",\\"map_center\\":\\"toronto\\",\\"markers\\":[{\\"address\\":\\"armoury St, Toronto, ON Canada\\",\\"latlng\\":\\"43.6533297,-79.3864873\\",\\"title\\":\\"Armoury St, Toronto, ON Canada\\",\\"image\\":\\"https://themify.me/demo/themes/shoppe-sport\\\\/files\\\\/2018\\\\/05\\\\/map-marker-icon.png\\"}]}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#f0f0f0\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
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
  'ID' => 63,
  'post_date' => '2018-05-11 06:15:45',
  'post_date_gmt' => '2018-05-11 06:15:45',
  'post_content' => 'Perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.
<div id="themify_builder_content-31" class="themify_builder_content themify_builder_content-31 themify_builder" data-postid="31"></div>',
  'post_title' => 'Bob Marley Bamboo',
  'post_excerpt' => 'Beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores.',
  'post_name' => 'bob-marley-bamboo',
  'post_modified' => '2018-05-11 06:33:01',
  'post_modified_gmt' => '2018-05-11 06:33:01',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=63',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526020288:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '72',
    '_regular_price' => '122',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '122',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
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
  'post_date' => '2018-05-10 06:17:33',
  'post_date_gmt' => '2018-05-10 06:17:33',
  'post_content' => 'Inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.',
  'post_title' => 'Robo Monster Chaos',
  'post_excerpt' => 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.',
  'post_name' => 'robo-monster-chaos',
  'post_modified' => '2018-05-11 06:33:41',
  'post_modified_gmt' => '2018-05-11 06:33:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=73',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526020287:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '71',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard8.png',
    '_wp_old_date' => '2018-05-11',
    '_regular_price' => '225',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '225',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 74,
  'post_date' => '2018-05-09 06:20:44',
  'post_date_gmt' => '2018-05-09 06:20:44',
  'post_content' => 'Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus. Et quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus omnis.',
  'post_title' => 'Kryptonics Board',
  'post_excerpt' => 'Et quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi.',
  'post_name' => 'kryptonics-board',
  'post_modified' => '2018-05-15 09:21:22',
  'post_modified_gmt' => '2018-05-15 09:21:22',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=74',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526375967:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard7-1.png',
    '_regular_price' => '125',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '125',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
    '_wp_old_date' => '2018-05-11',
    '_thumbnail_id' => '387',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
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
  'post_date' => '2018-05-08 06:21:19',
  'post_date_gmt' => '2018-05-08 06:21:19',
  'post_content' => 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.',
  'post_title' => 'Ethnic Wood',
  'post_excerpt' => 'Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.',
  'post_name' => 'ethnic-wood',
  'post_modified' => '2018-05-11 06:22:27',
  'post_modified_gmt' => '2018-05-11 06:22:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=75',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526020287:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '69',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard6.png',
    '_wp_old_date' => '2018-05-11',
    '_regular_price' => '250',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '250',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
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
  'post_date' => '2018-05-07 06:23:07',
  'post_date_gmt' => '2018-05-07 06:23:07',
  'post_content' => 'Ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.',
  'post_title' => 'Hero Clown',
  'post_excerpt' => 'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia.',
  'post_name' => 'hero-clown',
  'post_modified' => '2018-05-11 06:24:09',
  'post_modified_gmt' => '2018-05-11 06:24:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=76',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526019711:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '68',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard5.png',
    '_wp_old_date' => '2018-05-11',
    '_regular_price' => '275',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '275',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
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
  'post_date' => '2018-05-06 06:25:27',
  'post_date_gmt' => '2018-05-06 06:25:27',
  'post_content' => 'Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit ess. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam.',
  'post_title' => 'Futurio Board V1',
  'post_excerpt' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam.',
  'post_name' => 'futurio-board-v1',
  'post_modified' => '2018-05-11 06:25:38',
  'post_modified_gmt' => '2018-05-11 06:25:38',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=77',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526019956:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '67',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard4.png',
    '_regular_price' => '345',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '345',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
    '_wp_old_date' => '2018-05-11',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 78,
  'post_date' => '2018-05-05 06:25:56',
  'post_date_gmt' => '2018-05-05 06:25:56',
  'post_content' => 'Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur? Vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate',
  'post_title' => 'Angry Orange',
  'post_excerpt' => 'Vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti',
  'post_name' => 'angry-orange',
  'post_modified' => '2018-05-11 06:27:11',
  'post_modified_gmt' => '2018-05-11 06:27:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=78',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526019899:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '66',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard3.png',
    '_wp_old_date' => '2018-05-11',
    '_regular_price' => '156',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '156',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 79,
  'post_date' => '2018-05-04 06:27:23',
  'post_date_gmt' => '2018-05-04 06:27:23',
  'post_content' => 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est. Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
  'post_title' => 'UK Flag',
  'post_excerpt' => 'Laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit',
  'post_name' => 'uk-flag',
  'post_modified' => '2018-05-11 06:28:21',
  'post_modified_gmt' => '2018-05-11 06:28:21',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=79',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526019967:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '65',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard2.png',
    '_wp_old_date' => '2018-05-11',
    '_regular_price' => '145',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '145',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
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
  'post_date' => '2018-05-04 06:28:26',
  'post_date_gmt' => '2018-05-04 06:28:26',
  'post_content' => 'Commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur.',
  'post_title' => 'TMNT Purple',
  'post_excerpt' => 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est.',
  'post_name' => 'tmnt-purple',
  'post_modified' => '2018-05-11 06:29:52',
  'post_modified_gmt' => '2018-05-11 06:29:52',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?post_type=product&#038;p=80',
  'menu_order' => 0,
  'post_type' => 'product',
  'meta_input' => 
  array (
    '_wc_review_count' => '0',
    '_wc_rating_count' => 
    array (
    ),
    '_wc_average_rating' => '0',
    '_edit_lock' => '1526020167:172',
    '_edit_last' => '172',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
    'content_width' => 'default_width',
    '_thumbnail_id' => '64',
    'post_image' => 'https://themify.me/demo/themes/shoppe-sport/files/2018/05/skateboard1.png',
    '_wp_old_date' => '2018-05-11',
    '_regular_price' => '123',
    'total_sales' => '0',
    '_tax_status' => 'taxable',
    '_manage_stock' => 'no',
    '_backorders' => 'no',
    '_sold_individually' => 'no',
    '_upsell_ids' => 
    array (
    ),
    '_crosssell_ids' => 
    array (
    ),
    '_default_attributes' => 
    array (
    ),
    '_virtual' => 'no',
    '_downloadable' => 'no',
    '_download_limit' => '-1',
    '_download_expiry' => '-1',
    '_stock' => NULL,
    '_stock_status' => 'instock',
    '_product_version' => '3.3.5',
    '_price' => '123',
    '_yoast_wpseo_primary_product_cat' => '16',
    '_yoast_wpseo_content_score' => '30',
  ),
  'tax_input' => 
  array (
    'product_type' => 'simple',
    'product_cat' => 'skateboard',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 198,
  'post_date' => '2018-05-13 02:58:53',
  'post_date_gmt' => '2018-05-13 02:58:53',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '198',
  'post_modified' => '2018-05-15 02:00:31',
  'post_modified_gmt' => '2018-05-15 02:00:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=198',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '61',
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
  'ID' => 385,
  'post_date' => '2018-05-15 02:01:08',
  'post_date_gmt' => '2018-05-15 02:01:08',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '385',
  'post_modified' => '2018-05-15 02:01:08',
  'post_modified_gmt' => '2018-05-15 02:01:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=385',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '57',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'my-account-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 199,
  'post_date' => '2018-05-13 02:58:53',
  'post_date_gmt' => '2018-05-13 02:58:53',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '199',
  'post_modified' => '2018-05-15 02:00:31',
  'post_modified_gmt' => '2018-05-15 02:00:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=199',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '57',
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
  'ID' => 381,
  'post_date' => '2018-05-15 02:01:08',
  'post_date_gmt' => '2018-05-15 02:01:08',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '381',
  'post_modified' => '2018-05-15 02:01:08',
  'post_modified_gmt' => '2018-05-15 02:01:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=381',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '172',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'my-account-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 196,
  'post_date' => '2018-05-13 02:58:53',
  'post_date_gmt' => '2018-05-13 02:58:53',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '196',
  'post_modified' => '2018-05-15 02:00:31',
  'post_modified_gmt' => '2018-05-15 02:00:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=196',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '191',
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
  'ID' => 382,
  'post_date' => '2018-05-15 02:01:08',
  'post_date_gmt' => '2018-05-15 02:01:08',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '382',
  'post_modified' => '2018-05-15 02:01:08',
  'post_modified_gmt' => '2018-05-15 02:01:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=382',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '60',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'my-account-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 197,
  'post_date' => '2018-05-13 02:58:53',
  'post_date_gmt' => '2018-05-13 02:58:53',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '197',
  'post_modified' => '2018-05-15 02:00:31',
  'post_modified_gmt' => '2018-05-15 02:00:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=197',
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

$post = array (
  'ID' => 383,
  'post_date' => '2018-05-15 02:01:08',
  'post_date_gmt' => '2018-05-15 02:01:08',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '383',
  'post_modified' => '2018-05-15 02:01:08',
  'post_modified_gmt' => '2018-05-15 02:01:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=383',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '59',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'my-account-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 195,
  'post_date' => '2018-05-13 02:58:53',
  'post_date_gmt' => '2018-05-13 02:58:53',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '195',
  'post_modified' => '2018-05-15 02:00:31',
  'post_modified_gmt' => '2018-05-15 02:00:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=195',
  'menu_order' => 5,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '193',
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
  'ID' => 384,
  'post_date' => '2018-05-15 02:01:08',
  'post_date_gmt' => '2018-05-15 02:01:08',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '384',
  'post_modified' => '2018-05-15 02:01:08',
  'post_modified_gmt' => '2018-05-15 02:01:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/shoppe-sport/?p=384',
  'menu_order' => 5,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '58',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'my-account-menu',
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

	$widgets = get_option( "widget_themify-feature-posts" );
$widgets[1002] = array (
  'title' => 'Recent Posts',
  'category' => '0',
  'show_count' => '5',
  'show_date' => NULL,
  'show_thumb' => NULL,
  'display' => 'none',
  'hide_title' => NULL,
  'thumb_width' => '50',
  'thumb_height' => '50',
  'excerpt_length' => '55',
  'orderby' => 'date',
  'order' => 'DESC',
);
update_option( "widget_themify-feature-posts", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1003] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
  'orientation' => 'horizontal',
);
update_option( "widget_themify-social-links", $widgets );

$widgets = get_option( "widget_nav_menu" );
$widgets[1004] = array (
  'title' => 'Shop Links',
  'nav_menu' => themify_import_get_term_id_from_slug( "my-account-menu" ),
);
update_option( "widget_nav_menu", $widgets );

$widgets = get_option( "widget_nav_menu" );
$widgets[1005] = array (
  'title' => 'Main Menu',
  'nav_menu' => themify_import_get_term_id_from_slug( "main-navigation" ),
);
update_option( "widget_nav_menu", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1006] = array (
  'title' => 'Contact',
  'text' => 'Contact us: +1 423-344-4409
<a href="#">noreply@domain.com</a>

288 Fifth Avenue
Toronto, Ontario',
  'filter' => true,
  'visual' => true,
);
update_option( "widget_text", $widgets );



$sidebars_widgets = array (
  'sidebar-main' => 
  array (
    0 => 'themify-feature-posts-1002',
  ),
  'below-logo-widget' => 
  array (
    0 => 'themify-social-links-1003',
  ),
  'footer-widget-1' => 
  array (
    0 => 'nav_menu-1004',
  ),
  'footer-widget-2' => 
  array (
    0 => 'nav_menu-1005',
  ),
  'footer-widget-3' => 
  array (
    0 => 'text-1006',
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
			
	ob_start(); ?>a:83:{s:16:"setting-page_404";s:1:"0";s:21:"setting-webfonts_list";s:11:"recommended";s:22:"setting-default_layout";s:12:"sidebar-none";s:27:"setting-default_post_layout";s:5:"grid4";s:20:"setting-post_masonry";s:3:"yes";s:19:"setting-post_gutter";s:6:"gutter";s:30:"setting-default_layout_display";s:7:"content";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:30:"setting-default_media_position";s:5:"above";s:31:"setting-image_post_feature_size";s:5:"blank";s:32:"setting-default_page_post_layout";s:12:"sidebar-none";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:27:"setting-default_page_layout";s:12:"sidebar-none";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1280";s:43:"setting-customizer_responsive_design_tablet";s:3:"768";s:43:"setting-customizer_responsive_design_mobile";s:3:"680";s:33:"setting-mobile_menu_trigger_point";s:3:"900";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:21:"setting-header_design";s:16:"header-logo-left";s:21:"setting-footer_design";s:18:"footer-left-column";s:22:"setting-footer_widgets";s:17:"footerwidget-3col";s:27:"setting-imagefilter_applyto";s:12:"featuredonly";s:18:"setting-more_posts";s:8:"infinite";s:19:"setting-entries_nav";s:8:"numbered";s:19:"setting-shop_layout";s:12:"sidebar-none";s:27:"setting-shop_archive_layout";s:12:"sidebar-none";s:23:"setting-products_layout";s:5:"grid3";s:27:"setting-product_post_gutter";s:6:"gutter";s:23:"setting-products_slider";s:6:"enable";s:30:"setting-shop_products_per_page";s:1:"6";s:46:"setting-default_product_index_image_post_width";s:3:"370";s:29:"setting-single_product_layout";s:12:"sidebar-none";s:30:"setting-related_products_limit";s:1:"3";s:21:"setting-wishlist_page";s:3:"172";s:18:"setting-cart_style";s:9:"slide-out";s:19:"setting-spark_color";s:7:"#ea2079";s:25:"setting-img_php_base_size";s:5:"large";s:27:"setting-global_feature_size";s:5:"blank";s:22:"setting-link_icon_type";s:9:"font-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:31:"setting-link_img_themify-link-0";s:107:"https://themify.me/demo/themes/shoppe-sport/wp-content/themes/themify-shoppe/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:31:"setting-link_img_themify-link-1";s:108:"https://themify.me/demo/themes/shoppe-sport/wp-content/themes/themify-shoppe/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:31:"setting-link_img_themify-link-2";s:111:"https://themify.me/demo/themes/shoppe-sport/wp-content/themes/themify-shoppe/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:31:"setting-link_img_themify-link-3";s:107:"https://themify.me/demo/themes/shoppe-sport/wp-content/themes/themify-shoppe/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:31:"setting-link_img_themify-link-4";s:109:"https://themify.me/demo/themes/shoppe-sport/wp-content/themes/themify-shoppe/themify/img/social/pinterest.png";s:32:"setting-link_type_themify-link-6";s:9:"font-icon";s:33:"setting-link_title_themify-link-6";s:8:"Facebook";s:32:"setting-link_link_themify-link-6";s:32:"https://www.facebook.com/themify";s:33:"setting-link_ficon_themify-link-6";s:11:"fa-facebook";s:35:"setting-link_ficolor_themify-link-6";s:7:"#ec2e81";s:32:"setting-link_type_themify-link-5";s:9:"font-icon";s:33:"setting-link_title_themify-link-5";s:7:"Twitter";s:32:"setting-link_link_themify-link-5";s:27:"https://twitter.com/themify";s:33:"setting-link_ficon_themify-link-5";s:10:"fa-twitter";s:35:"setting-link_ficolor_themify-link-5";s:7:"#ec2e81";s:32:"setting-link_type_themify-link-8";s:9:"font-icon";s:33:"setting-link_title_themify-link-8";s:8:"Linkedin";s:32:"setting-link_link_themify-link-8";s:41:"https://www.linkedin.com/company/themify/";s:33:"setting-link_ficon_themify-link-8";s:11:"ti-linkedin";s:35:"setting-link_ficolor_themify-link-8";s:7:"#ec2e81";s:32:"setting-link_type_themify-link-7";s:9:"font-icon";s:33:"setting-link_title_themify-link-7";s:7:"Google+";s:32:"setting-link_link_themify-link-7";s:45:"https://plus.google.com/109280316400365629341";s:33:"setting-link_ficon_themify-link-7";s:14:"fa-google-plus";s:35:"setting-link_ficolor_themify-link-7";s:7:"#ec2e81";s:22:"setting-link_field_ids";s:307:"{"themify-link-0":"themify-link-0","themify-link-1":"themify-link-1","themify-link-2":"themify-link-2","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4","themify-link-6":"themify-link-6","themify-link-5":"themify-link-5","themify-link-8":"themify-link-8","themify-link-7":"themify-link-7"}";s:23:"setting-link_field_hash";s:2:"10";s:30:"setting-page_builder_is_active";s:6:"enable";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:4:"skin";s:98:"https://themify.me/demo/themes/shoppe-sport/wp-content/themes/themify-shoppe/skins/sport/style.css";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	
}
themify_do_demo_import();