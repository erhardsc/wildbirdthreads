<?php
/**
* Enqueues child theme stylesheet, loading first the parent theme stylesheet.
*/
function themify_custom_enqueue_child_theme_styles() {
    wp_enqueue_style( 'parent-theme-css', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'themify_custom_enqueue_child_theme_styles', 11 );


function custom_styles() { 

	wp_register_style( 'animate-css', get_stylesheet_directory_uri() . '/css/animate.min.css', array(), '20120725', 'screen' ); 
	
	wp_enqueue_style( 'animate-css' ); 
	
	wp_enqueue_style( 'bootstrap_css', get_stylesheet_directory_uri() . '/css/bootstrap.min.css' );

}
add_action('wp_enqueue_scripts', 'custom_styles', 11);

function bootstrap_js() {
	
	global $wp_scripts;
	
	wp_register_script( 'html5_shiv', 'https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js', '', '', false ); //last parameter says to load in header of site
	
	wp_register_script( 'respond_js', 'https://oss.maxcdn.com/respond/1.4.2/respond.min.js', '', '', false );

	$wp_scripts->add_data( 'html5_shiv', 'conditional', 'lt IE 9' );
	$wp_scripts->add_data( 'respond_js', 'conditional', 'lt IE 9' );

	wp_enqueue_script( 'bootstrap_js', get_stylesheet_directory_uri() . '/js/bootstrap.min.js', array('jquery'), true );

}

add_action( 'wp_enqueue_scripts', 'bootstrap_js' );

//Dequeue Styles
function project_dequeue_unnecessary_styles() {

	if(!is_page(array('contact', 'home'))){
		wp_dequeue_style( 'owl_style' );
		wp_dequeue_style( 'owl_style_2' );
		wp_dequeue_style( 'owl_style_3' );
		wp_dequeue_style( 'swipebox_css' );
	}
	
}
add_action( 'wp_print_styles', 'project_dequeue_unnecessary_styles' );

//Dequeue JavaScripts
function project_dequeue_unnecessary_scripts() {
	if(!is_page(array('contact', 'home'))){
		wp_dequeue_script( 'owl' );
		wp_dequeue_script( 'gridrotator' );
	}
}
add_action( 'wp_print_scripts', 'project_dequeue_unnecessary_scripts' );

//*WAYPOINTS
function waypoints_init() {
	
    wp_enqueue_script( 'waypointsJS', get_stylesheet_directory_uri() . '/js/waypoints/lib/jquery.waypoints.min.js', array('jquery'), true);
    
}
add_action('wp_enqueue_scripts', 'waypoints_init');

function waypoints_js() {
	
wp_enqueue_script( 'wildbird', get_stylesheet_directory_uri() . '/js/waypoints.js', array('jquery'), true );

}

add_action('wp_enqueue_scripts', 'waypoints_js');

function is_even($index) {
	$even = false;
	
	if ( $index % 2 == 0 ) : $even = true;
	endif;
	
	return $even;
}

// Custom Excerpt function for Advanced Custom Fields
function custom_field_excerpt($text) {
	global $post;
	if ( '' != $text ) {
		$text = strip_shortcodes( $text );
		
		$text = apply_filters('the_content', $text);
		$text = str_replace(']]&gt;', ']]&gt;', $text);
		$allowed_tags = '<em>,<i>,<a>,<b>,<br>,<strong>';
		$text = strip_tags( $text, $allowed_tags );
		$excerpt_length = 50; // 50 words
		$excerpt_more = new_excerpt_more(apply_filters('excerpt_more', ' ' . '[...]')); //apply_filters('excerpt_more', ' ' . '[...]');
		
		
		$words = explode(' ', $text, $excerpt_length + 1);
		if (count($words)> $excerpt_length) {
			array_pop($words);
			array_push($words, $excerpt_more);
			$text = implode(' ', $words);
		}
		//$text = wp_trim_words( $text, $excerpt_length, $excerpt_more );
	}
	return apply_filters('the_excerpt', $text);


}

remove_filter('get_the_excerpt', 'wp_trim_excerpt');
add_filter('get_the_excerpt', 'custom_field_excerpt', 5);

// Changing excerpt more
function new_excerpt_more($more) {
   global $post;
   return '…</br></br> <button class="read-on">' . 'Read On &raquo;' . '</button>';
   }
   add_filter('excerpt_more', 'new_excerpt_more');
   
   
function app_js() {
	
wp_enqueue_script( 'app', get_stylesheet_directory_uri() . '/js/app.js', array('jquery'), true );

}

add_action('wp_enqueue_scripts', 'app_js');

// Allow SVG file upload
function cc_mime_types($mimes){
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
	
}
add_action( 'upload_mimes', 'cc_mime_types' );



/*
add_action( 'wp_ajax_my_ajax_action', 'my_ajax_action_callback' );
add_action( 'wp_ajax_nopriv_my_ajax_action', 'my_ajax_action_callback' );
function my_ajax_action_callback() {
    if(isset($_POST['newRowId'])){
    
        $response = array(
            'new_row_id' => $_POST['newRowId']
        );
        echo json_encode($response);
        exit;
    }
    else {
        echo "its null mannn";
    }
}
*/
   
   
   

//'. get_permalink($post->ID) . '

/*
function rgblaster_js() {
wp_enqueue_script( 'color_theif', get_stylesheet_directory_uri() . '/js/rgbaster.min.js', array('jquery'), true );
}
add_action('wp_enqueue_scripts', 'rgblaster_js');

function color_js() {
wp_enqueue_script( 'color', get_stylesheet_directory_uri() . '/js/color.js', array('jquery'));
}
add_action('wp_enqueue_scripts', 'color_js');
*/


/*
function wpse_setup_theme() {
   add_theme_support( 'post-thumbnails' );
   add_image_size( 'required-demension', 400, 9999, true);
}

add_action( 'after_setup_theme', 'wpse_setup_theme' );
*/


///////////////// WOOCOMMERCE FUNCTIONS ////////////////////

remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );

add_action( 'woocommerce_after_single_product_summary', 'artist_content' );

function artist_content() {
	get_template_part( 'partials/content', 'artist');
}

//add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_stock', 10 );
//function woocommerce_template_loop_stock() {
//    global $product;
//    if ( ! $product->managing_stock() && ! $product->is_in_stock() )
//        echo '<p class="stock out-of-stock">Out of Stock (can be backordered)</p>';
//}

// Remove variation stock data from product page

add_filter( 'woocommerce_available_variation', 'remove_variation_stock_display', 99 );
function remove_variation_stock_display( $data ) {
	unset( $data['availability_html'] );
	return $data;
}


add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );

function custom_override_checkout_fields( $fields ) {
	unset($fields['billing']['billing_company']);
	unset($fields['billing']['billing_phone']);
	unset($fields['shipping']['shipping_company']);

	return $fields;
}

// WooCommerce Shipping //

add_filter( 'woocommerce_package_rates', 'set_shipping_rates', 10, 2 );

function set_shipping_rates( $rates ){

  // Shipping methods
  // 575 : domestic shirts
  // 579 : domestic hoody
  // 580 : domestic hoody + shirt

  // 689 : international shirt
  // 691 : international hoody
  // 690 : international hoody + shirt

  // 578 : fundraiser

  //Domestic
  if ( isset( $rates['579'] ) && isset( $rates['580'] ) ) {// Hoody + Hoody & Shirt
    unset( $rates['580'] ); // hoody & shirt

  }

  if ( isset( $rates['575'] ) && isset( $rates['580'] ) ) {// Hoody & shirt + Shirts
    unset( $rates['580'] ); // hoody & shirt
  }

  //International
  if ( isset( $rates['691'] ) && isset( $rates['690'] ) ) {// Hoody & Shirt + Hoody
    unset( $rates['690'] ); // hoody & shirt
    unset( $rates['578'] ); // Free Shipping
  }

  if ( isset( $rates['689'] ) && isset( $rates['690'] ) ) {// Hoody & shirt + Shirt
    unset( $rates['690'] ); // hoody & shirt
    unset( $rates['578'] ); // Free Shipping
  }

  // Unset Free Shipping if fundraiser is set

  if ( WC()->cart->subtotal < 100 ) {
    unset( $rates['578'] ); // free shipping
  }

  // set our flag to be false until we find a product in that category
  $cat_check = false;

  // check each cart item for our category
  foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

    $product = $cart_item['data'];

    // replace 'membership' with your category's slug
    if ( has_term( 'fundraiser', 'product_cat', $product->id ) ) {
      $cat_check = true;
      break;
    }
  }

  if ( $cat_check ) {
    unset( $rates['578'] );
  }


  return $rates;

}












