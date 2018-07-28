<?php
/***************************************************************************
 *
 * 	----------------------------------------------------------------------
 * 						DO NOT EDIT THIS FILE
 *	----------------------------------------------------------------------
 * 
 *  				     Copyright (C) Themify
 * 
 *	----------------------------------------------------------------------
 *
 ***************************************************************************/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Run this after the admin has been initialized so they appear as standard WordPress notices.
add_action( 'admin_notices', 'themify_check_version', 3, 1 );

/**
 * Clears the transients for version checking. It does it when it's a new theme activation or WP_DEBUG is enabled
 *
 * @since 1.9.4
 */
function themify_refresh_versions() {
        global $pagenow;
	// Clear transient to check for updates for the first time
	if ( ( isset($_GET['activated'] ) && isset( $pagenow ) && $pagenow === 'themes.php' ) || themify_minified() || ( isset( $_GET['update'] ) && 'check' === $_GET['update'] ) ) {
		// Get the theme name and hash it. It will be used in transient names.
		$theme = wp_get_theme();
		$theme_name = $theme->get_template();
		$theme_hash = md5( $theme_name );

		// Clear transients to force version checking.
		delete_transient( 'themify_new_theme' . $theme_hash );
		delete_transient( 'themify_update_check_theme' . $theme_hash );
		delete_transient( 'themify_new_framework' );
		delete_transient( 'themify_update_check_framework' );
		delete_transient( 'themify_widget_current_updates' );
	}
}
add_action( 'admin_init', 'themify_refresh_versions' );

/**
 * Set transient saving the current date and time of last version checking
 * @param String $type
 */
function themify_set_update_cookie($type, $hash = ''){
	$current = new stdClass();
	$current->lastChecked = time();
	set_transient( 'themify_update_check_'.$type . $hash, $current );
}

/**
 * Checks theme and framework versions
 *
 * @param string $area
 */
function themify_check_version( $area = 'top' ) {
	
	// In case user has chosen to disable the upgrader
	if( !defined('THEMIFY_UPGRADER') ) define('THEMIFY_UPGRADER', true);
	if( !THEMIFY_UPGRADER ) return;

	// Setup variables to collect markup
	$notifications = '';
	$theme_notifications = '';
	$fw_notifications = '';

	// Setup variables for updater.
	$theme = wp_get_theme();
	$theme_name = $theme->get_template();
	$theme_hash = md5( $theme_name );
	$theme_version = is_child_theme() ? $theme->parent()->Version : $theme->display('Version');

	// Setup theme name for display purposes.
	$theme_label = is_child_theme() ? $theme->parent()->Name : $theme->display('Name');

	// If we already know there's a new version, we don't need to check, just use these objects:
	/**
	 * @var stdClass newF
	 * newF
	 * 		version
	 * 		url
	 * 		class
	 * 		target
	 */
	$newF = get_transient( 'themify_new_framework' );
	/**
	 * @var stdClass newT
	 * newT
	 * 		login
	 * 		version
	 * 		url
	 * 		class
	 * 		target
	 */
	$newT = get_transient( 'themify_new_theme' . $theme_hash );

	// If $newT exists, double check the ->login attribute to verify if user is or not logged
	if ( is_object( $newT ) ) {
		$newT->login = !empty($newT->free) ? '' : ($newT->login?$newT->login:'login');
	}

	// Check if one of them are objects. If they're not, the transient expired so we'll check again
	if ( is_object( $newF ) || is_object( $newT ) ) {
		
		if ( is_object( $newT ) && ( $theme_version < $newT->version ) ) {
			if ( isset( $_GET['page'] ) && $_GET['page'] === 'themify' ) {
				if ( isset( $area ) && 'tab' === $area ) {
					$theme_notifications = sprintf( __( '<p class="update %s"><a href="%s" title="" class="%s updateready" target="%s">Update Now</a></p> <p>%s version %s is
now available. View the <a href="%s" target="_blank"
 data-changelog="%s" class="themify_changelogs">change log</a> for details.</p>', 'themify' ),
						esc_attr( $newT->login ),
						esc_url( $newT->url ),
						esc_attr( $newT->class . ' button big-button'),
						esc_attr( $newT->target ),
						$theme_label,
						$newT->version,
						esc_url( 'https://themify.me/logs/' . $theme_name . '.txt' ),
						esc_url( 'https://themify.me/changelogs/' . $theme_name . '.txt' )
					);
				} else {
					$theme_notifications = sprintf( __( '<p class="update %s">%s version %s is now available. <a href="%s" class="%s" target="%s">Update Now</a> or view the
<a href="%s" target="_blank" data-changelog="%s" class="themify_changelogs">change
log</a> for details.</p>', 'themify' ),
						esc_attr( $newT->login ),
						$theme_label,
						$newT->version,
						esc_url( $newT->url ),
						esc_attr( $newT->class ),
						esc_attr( $newT->target ),
						esc_url( 'https://themify.me/logs/' . $theme_name . '.txt' ),
						esc_url( 'https://themify.me/changelogs/' . $theme_name . '.txt' )
					);
				}
			} else {
				$theme_notifications = '<div class="notice notice-info"><p class="update">' . sprintf( __( '%s version %s is now available. Go to the <a href="%s">Themify panel</a> to update.', 'themify' ),
					$theme_label,
					$newT->version,
					esc_url( add_query_arg( 'page', 'themify', admin_url( 'admin.php' ) ) )
				) . '</p></div>';
			}
		}

		if ( is_object( $newF ) && ( THEMIFY_VERSION < $newF->version ) ) {
			if ( isset( $_GET['page'] ) && $_GET['page'] === 'themify' ) {
				if ( isset( $area ) && 'tab' === $area ) {
					$fw_notifications = '';
				} else {
                                   
					$fw_notifications = '<p class="update">' . sprintf( __( 'Framework version %s is now available. <a href="%s" title="" class="%s" target="%s">Update Now</a> or view the <a href="%s" data-changelog="https://themify.me/changelogs/themify.txt" class="themify_changelogs" target="%s">change log</a> for details.', 'themify' ),
							$newF->version,
							esc_url( $newF->url ),
							esc_attr( ( isset( $area ) && 'tab' === $area ) ? $newF->class . ' button big-button' : $newF->class ),
							esc_attr( $newF->target ),
                                                        isset($_GET['action']) && $_GET['action']==='upgrade'?'https://themify.me/changelogs/themify.txt':'https://themify.me/logs/framework-changelogs',
                                                        isset($_GET['action']) && $_GET['action']==='upgrade'?'_blank':'_self'
						) . '</p>';
				}
			} else {
				$fw_notifications = '<div class="notice notice-info"><p class="update">' . sprintf( __( 'Framework version %s is now available. Go to the <a href="%s">Themify panel</a> to update.', 'themify' ),
					$newF->version,
					esc_url( add_query_arg( 'page', 'themify', admin_url( 'admin.php' ) ) )
				) . '</p></div>';
			}
		}
		$notifications .= '' != $theme_notifications?$theme_notifications:$fw_notifications;

		if ( !isset( $area ) || 'tab' !== $area ) {
			echo '<div class="notifications">'. $notifications . '</div>';
		} 
	}
	if ('tab' !== $area && (is_object( $newF ) || is_object( $newT )) ) {
		//we don't have to do anything else
		return;
	} else {
		$notifications = '';
	}
	//If we didn't knew there was a new version already, let's see if it's 24hs since last check
	$current_theme = get_transient( 'themify_update_check_theme' . $theme_hash );
	$current_framework = get_transient( 'themify_update_check_framework' );
        $framework_recently_checked = $newVersionFramework = $newVersionTheme = $theme_recently_checked = $is_free = false;
	if ( is_object( $current_theme ) && is_object( $current_framework ) ) {
		//if theme version was checked not long ago
                $t = time();
		$theme_recently_checked = 3600 > ( $t - $current_theme->lastChecked );
		//if framework version was checked not long ago
		$framework_recently_checked = 3600 > ( $t - $current_framework->lastChecked );
	}
	
	//theme and framework were recently checked and no version was available, return
	if ( !$theme_recently_checked || !$framework_recently_checked ) {
	
		/**
		 * Utilizes WordPress HTTP API
		 * http://codex.wordpress.org/Function_API/wp_remote_request
		 */
		$versions_url = 'https://themify.me/versions/versions.xml';
		$response = wp_remote_get( $versions_url, array( 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {
			//echo '<h4>Can\'t load ' . $versions_url . '</h4><p>' . $response->get_error_code(). '</p>';
			return;
		}
		//if xml was successfully retrieved, let's delete the transients for theme and framework
		delete_transient( 'themify_update_check_theme' . $theme_hash );
		delete_transient( 'themify_update_check_framework' );
		
		//Load string to be converted later into an array with themify_xml2array
		$versions = $response['body'];
		//Begin check
		if ( isset( $versions ) && '' != $versions ) {
			$versions = themify_xml2array( $versions );
			if(isset( $versions['versions']) && !is_array( $versions ) ){
				return;
                        }
			$theme_notifications = '';
			$fw_notifications = '';
			foreach($versions['versions']['_c']['version'] as $update){
				$latest = str_replace(".","",trim($update['_v']));
			
				if($update['_a']['name'] === 'themify' && !is_object($newF)){
					
					if ( isset( $update['_a']['free'] ) && 'true' == $update['_a']['free'] ) {
						$login = '';
                                                $is_free = 1;
					} else {
						$login = 'login';	
					}
					//Compares framework version
					if ( str_replace( '.', '', trim( THEMIFY_VERSION ) ) < $latest ) {
						/**
						 * Checks for WordPress' unzip_file
						 * http://codex.wordpress.org/Function_Reference/unzip_file
						 */
						if( function_exists('unzip_file') ){
							$url = '#';
							$class = 'upgrade-framework';
							$target = '';
						} else {
							$url = 'https://themify.me/files/themify/themify.zip';
							$class = '';
							$target = '_blank';
						}

						if ( isset( $_GET['page'] ) && $_GET['page'] === 'themify' ) {
							if ( isset( $area ) && 'tab' === $area ) {
								$fw_notifications = '';
							} else {
								$fw_notifications = sprintf( __( '<p class="update %s">Framework version %s is now available. <a href="%s" class="%s" target="%s">Update Now</a> or view the <a href="https://themify.me/changelogs/themify.txt" target="_blank" data-changelog="https://themify.me/changelogs/themify.txt" class="themify_changelogs">change log</a> for details.</p>', 'themify' ),
									esc_attr( $login ),
									$update['_v'],
									esc_url( $url ),
									esc_attr( $class ),
									esc_attr( $target )
								);
							}
						} else {
							$fw_notifications = '<div class="notice notice-info"><p class="update">' . sprintf( __( 'Framework version %s is now available. Go to the <a href="%s">Themify panel</a> to update.', 'themify' ),
								$update['_v'],
								esc_url( add_query_arg( 'page', 'themify', admin_url( 'admin.php' ) ) )
							) . '</p></div>';
						}

						//store variable indicating there is a new version of framework 
						$newFrameworkStore = new stdClass();
						$newFrameworkStore->version = $update['_v'];
						$newFrameworkStore->url = $url;
						$newFrameworkStore->class = $class;
						$newFrameworkStore->target = $target;
						set_transient( 'themify_new_framework', $newFrameworkStore );
						//echo 'new update for framework stored';
						$newVersionFramework = true;
					}
				} else if( $update['_a']['name'] === strtolower(trim($theme_name)) && !is_object($newT) ){
					if ( isset( $update['_a']['free'] ) && 'true' == $update['_a']['free'] ) {
						$login = '';
						$is_free = 1;
					} else {
						$login = 'login';	
					}

					//Compares theme version
					if(str_replace(".","",$theme_version) < $latest){
						/**
						 * Checks for WordPress' unzip_file
						 * http://codex.wordpress.org/Function_Reference/unzip_file
						 */
						if( function_exists('unzip_file') ){
							$url = '#';
							$class = 'upgrade-theme';
							$target = '';
						} else {
							$url = 'https://themify.me/files/'.$theme_name.'/'.$theme_name.'.zip';
							$class = '';
							$target = '_blank';
						}
						if ( isset( $_GET['page'] ) && 'themify' === $_GET['page'] ) {
							if ( isset( $area ) && 'tab' === $area ) {
								$theme_notifications = sprintf( __( "<a href='%s' title='' class='%s updateready' target='%s'>Update Now</a> <p class='update %s'>%s version %s is now
		available. View the <a href='%s' title='' class='themify_changelogs' target='_blank' data-changelog='%s'>change
		log</a> for details.</p>", 'themify' ),
									esc_url( $url ),
									esc_attr( $class ),
									esc_attr( $target ),
									esc_attr( $login ),
									$theme_label,
									$update['_v'],
									esc_url( '//themify.me/logs/' . $theme_name . '.txt' ),
									esc_url( '//themify.me/changelogs/' . $theme_name . '.txt' )
								);
							} else {
								$theme_notifications = sprintf( __( "<p class='update %s'>%s version %s is now available. <a href='%s' title='' class='%s' target='%s'>Update Now</a> or
		view the <a href='%s' title='' class='themify_changelogs' target='_blank' data-changelog='%s'>change log</a> for
		details.</p>", 'themify' ),
									esc_attr( $login ),
									$theme_label,
									$update['_v'],
									esc_url( $url ),
									esc_attr( $class ),
									esc_attr( $target ),
									esc_url( '//themify.me/logs/' . $theme_name . '.txt' ),
									esc_url( '//themify.me/changelogs/' . $theme_name . '.txt' )
								);
							}
						}else {
							$theme_notifications = '<div class="update-nag">' . sprintf( __( '%s version %s is now available. Go to the <a href="%s">Themify panel</a> to update.', 'themify' ),
								$theme_label,
								$update['_v'],
								esc_url( add_query_arg( 'page', 'themify', admin_url( 'admin.php' ) ) )
							) . '</div>';
						}

						//store variable indicating there is a new version of theme
						$newThemeStore = new stdClass();
						$newThemeStore->login = $login;
						$newThemeStore->version = $update['_v'];
						$newThemeStore->url = $url;
						$newThemeStore->class = $class;
						$newThemeStore->target = $target;
						$newThemeStore->free = $is_free;
						set_transient( 'themify_new_theme' . $theme_hash, $newThemeStore );
						//echo 'new update for theme stored';
						$newVersionTheme = true;
					}
				}
			}
		}
	}

	if(!$newVersionFramework && !$newVersionTheme){
		//echo 'new update scheduled';
		themify_set_update_cookie('theme', $theme_hash);
		themify_set_update_cookie('framework');
	}

	if( '' != $theme_notifications ){
		$notifications .= $theme_notifications;
	} else {
		$notifications .= $fw_notifications;
	}

	if ( isset( $area ) && 'tab' === $area ) {
		if ( '' == $theme_notifications ) {
			$login = $is_free ? '' : 'login';
			$latest_version = '';
                        if(! empty( $versions )){
                            foreach( $versions['versions']['_c']['version'] as $version ) {
                                if( isset( $version['_a']['name'] ) && $version['_a']['name'] === $theme_name) {
                                    if( isset( $version['_a']['free'] ) && 'true' == $version['_a']['free'] ) {
                                            $login = '';
                                    }
                                    $latest_version = trim( $version['_v'] );
                                }
                            }
			} elseif(is_object( $newT )){
				$latest_version = $newT->version;
                                if($newT->free){
                                    $login = '';
                                }
			} else {
				$latest_version = $theme_version;
			}
			$versions_options_html = get_latest_and_back_theme_versions_html( $latest_version, 5 );
			if ( function_exists( 'unzip_file' ) ) {
				$url = '#';
				$class = 'upgrade-theme';
				$target = '';
			} else {
				$url = 'https://themify.me/files/'.$theme_name.'/'.$theme_name.'.zip';
				$class = '';
				$target = '_blank';
			}
			$theme_notifications = sprintf( '<p class="update"><select id="themeversiontoreinstall">%s</select></p><p class="%s"><a href="%s" class="%s" target="%s">' . __( 'Re-install Theme', 'themify' ) . '</a></p><p>' . __( 'Re-install the theme to the selected version.', 'themify' ) . '</p>',
				$versions_options_html,
				esc_attr( isset( $login ) ? $login . ' reinstalltheme' : $newT->login . ' reinstalltheme' ),
				esc_url( isset( $url ) ? $url : $newT->url ),
				esc_attr( isset( $class ) ? $class . ' button big-button' : $newT->class . ' button big-button' ),
				esc_attr( isset( $target ) ? $target : $newT->target )
			);
			$notifications .= $theme_notifications;
		}

		echo wp_kses( $notifications, themify_updater_notice_allowed_tags() );
	} else {
		echo '<div class="notifications">'. wp_kses( $notifications, themify_updater_notice_allowed_tags() ) . '</div>';
	}

}

/**
 * Get latest and back theme versions in HTML/option
 */
function get_latest_and_back_theme_versions_html( $latest_version, $back_limit = 5 ) {
	$html = '<option selected="selected" value="latest">'. __( 'Latest version', 'themify' ) .'</option>';
	$i = 0;
	$versions = array();

	while ( $i < $back_limit ) {
		if ( $i === 0 ) {
			$versions[$i] = get_back_theme_version( $latest_version );
		}
		elseif ( ! empty( $versions[$i-1] ) ) {
			$versions[$i] = get_back_theme_version( $versions[$i-1] );
		}
		else {
			break;
		}

		++$i;
	}

	foreach ( $versions as $version ) {
		$html .= '<option value="'. $version .'">'. $version .'</option>';
	}

	return $html;
}
function get_back_theme_version( $version ) {
	$back_version = '';
	$parts = explode( '.', $version );

	if ( sizeof( $parts ) === 3 ) {
		if ( (int) $parts[2] > 0 ) {
			$parts[2]--;
		}
		elseif ( (int) $parts[1] > 0 ) {
			$parts[2] = '9';
			$parts[1]--;
		}
		elseif ( (int) $parts[0] > 1 ) {
			$parts[2] = '9';
			$parts[1] = '9';
			$parts[0]--;
		}
		else {
			$parts = NULL;
		}
	}

	if ( $parts ) {
		$back_version = implode( '.', $parts );
	}

	return $back_version;
}

/**
 * Updater called through wp_ajax_ action
 */
function themify_updater(){
	$theme = wp_get_theme();
	$theme_name = $theme->get_template();
	$theme_label = is_child_theme() ? $theme->parent()->Name : $theme->display('Name');
	$type = $_GET['type'];
	$themeversion = isset( $_GET['themeversion'] ) ? ( $_GET['themeversion'] === 'latest' ? '' : ( '-' . $_GET['themeversion'] ) ) : ''; // 'latest' > '' // 'x.x.x' > '-x.x.x'
	//are we going to update a theme?
	$url = $type === 'theme'?'https://themify.me/files/' . $theme_name . '/' . $theme_name . $themeversion . '.zip':'https://themify.me/files/themify/themify.zip';
	
	//If login is required
	if($_GET['login'] == 'true'){
			$amember_nr = false;
			if(isset($_POST['username'],$_POST['password'])){
				$response = wp_remote_post(
					'https://themify.me/member/login',
					array(
						'timeout' => 300,
						'headers' => array(),
						'body' => array(
							'amember_login' => $_POST['username'],
							'amember_pass'  => $_POST['password']
						),
						'sslverify' => false,
					)
				);

				//Was there some error connecting to the server?
				if( is_wp_error( $response ) ) {
					$errorCode = $response->get_error_code();
					echo 'Error: ' . $errorCode;
					die();
				}
				
                                if(isset( $response['response']['code'] ) && $response['response']['code'] !== 200 ) {
                                        die( 'Login URL failed. Please contact Themify (https://themify.me/contact). Error code: ' . $response['response']['code']);
                                }

				//Connection to server was successful. Test login cookie
				
				foreach($response['cookies'] as $cookie){
					if($cookie->name === 'amember_nr'){
                                            $amember_nr = true;
                                            break;
					}
                            }
			}
                         if(!$amember_nr){
                                _e('You are not a Themify Member.', 'themify');
                                die();
                        }

	}
	
	//remote request is executed after all args have been set
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once THEMIFY_DIR . '/class-themify-updater.php';
	$title = $type === 'framework'? __('Update Themify Framework', 'themify') : sprintf( __('Update %s Theme', 'themify'), $theme_label);
	$nonce = 'upgrade-themify_' . $type;
	/** 
	 * Changelog
	 * 19/09/2014
	 * Added cookies key/val to array passed to skin
	 */
	$response_cookies =  isset( $response ) && isset( $response['cookies'] ) ? $response['cookies'] : '';
	/** 
	 * Changelog
	 * 11/03
	 * Added cookies key/val to array passed to skin
	 */
	$upgrader = new Themify_Upgrader( new Themify_Upgrader_Skin(
			array(
				'title'	=> $title,
				'url'	=> $url,
				'nonce' => $nonce,
				'theme' => $theme_name,
				'type'	=> $type,
				'login' => $_GET['login'],
				'cookies' => $response_cookies,
			)
	) );
	$upgrader->upgrade($theme_name, $url, $response_cookies, $type);

	// Clear builder cache
	if ( class_exists( 'TFCache' ) && TFCache::check_version() ) {
		TFCache::removeDirectory( TFCache::get_cache_dir() );
	}
	
	//if we got this far, everything went ok!	
	die();
}

/**
 * Validate login credentials against Themify's membership system
 */
function themify_validate_login(){
	$response = wp_remote_post(
		'https://themify.me/files/themify-login.php',
		array(
			'timeout' => 300,
			'headers' => array(),
			'body' => array(
				'amember_login' => $_POST['username'],
				'amember_pass'  => $_POST['password']
			),
			'sslverify' => false,
	    )
	);

	//Was there some error connecting to the server?
	if( is_wp_error( $response ) ) {
		die('Error ' . $response->get_error_code() . ': ' . $response->get_error_message( $response->get_error_code() ));
	}

	//Connection to server was successful. Test login cookie
	$amember_nr = false;
	foreach($response['cookies'] as $cookie){
		if($cookie->name === 'amember_nr'){
                    $amember_nr = true;
                    break;
		}
	}
	if(!$amember_nr){
		die('invalid');
	}

	$subs = json_decode($response['body'], true);
	$sub_match = 'unsuscribed';
	$theme = wp_get_theme();
	$theme_name = ( is_child_theme() ) ? $theme->parent()->Name : $theme->display('Name');

	$theme_name = preg_replace( '/^Themify\s/', '', $theme_name );
	foreach ($subs as $key => $value) {
		if(stripos($value['title'], 'Lifetime Club') !== false || stripos($value['title'], 'Lifetime Master Club') !== false || stripos($value['title'], 'Master Club') !== false || stripos($value['title'], 'Developer Club') !== false || stripos( $value['title'], $theme_name ) !== false || stripos($value['title'], 'Standard Club') !== false){
			$sub_match = 'subscribed';
			break;
		}
	}
	echo esc_attr( $sub_match );
	die();
}

//Executes themify_updater function using wp_ajax_ action hook
add_action('wp_ajax_themify_validate_login', 'themify_validate_login');

/**
 * Returns allowed tags for updater notice markup
 *
 * @since 2.1.8
 *
 * @return mixed|void
 */
function themify_updater_notice_allowed_tags() {
	/**
	 * Filters allowed tags.
	 *
	 * @since 2.1.8
	 *
	 * @param array
	 */
	return apply_filters( 'themify_updater_notice_allowed_tags', array(
		'div' => array(
			'id'    => true,
			'class' => true,
		),
		'p'   => array( 'class' => true, ),
		'a'   => array(
			'href'           => true,
			'title'          => true,
			'class'          => true,
			'target'         => true,
			'data-changelog' => true,
		),
		'select' => array( 'id' => true, ),
		'option' => array(
			'selected' => true,
			'value'    => true,
		),
	) );
}
//Check Free if a theme is free for reinstall
function themify_check_theme_is_free(){
    check_admin_referer('ajax-nonce', 'nonce');
    $response = wp_remote_get( 'https://themify.me/versions/versions.xml', array( 'sslverify' => false ) );
    if ( is_wp_error( $response ) ) {
        die('error');
    }
  
    if(!empty($response['body'])){
        
        $versions = $response['body'];
        $theme = get_template();
        unset($response);
        $versions = themify_xml2array( $versions );
        if(isset( $versions['versions'] ) && !  is_array( $versions )){
            die('error');
        }
        foreach( $versions['versions']['_c']['version'] as $version ) {
            if( isset( $version['_a']['name'] ) && $version['_a']['name'] === $theme && isset( $version['_a']['free'] ) && 'true' == $version['_a']['free']) {
                    die('free');
            }
        }
    }
    die('login');
}
add_action('wp_ajax_themify_check_theme_is_free', 'themify_check_theme_is_free');