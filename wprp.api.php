<?php

class WPR_API_Request {

	static $actions = array();
	static $args = array();

	static function verify_request() {

		// Check the API Key
		if ( ! wprp_get_api_keys() ) {

			echo json_encode( 'blank-api-key' );
			exit;

		} elseif ( isset( $_POST['wpr_verify_key'] ) ) {

			$verify = $_POST['wpr_verify_key'];
			unset( $_POST['wpr_verify_key'] );

			$hash = self::generate_hashes( $_POST );

			if ( ! in_array( $verify, $hash, true ) ) {
				echo json_encode( 'bad-verify-key' );
				exit;
			}

			if ( (int) $_POST['timestamp'] > time() + 360 || (int) $_POST['timestamp'] < time() - 360 ) {
				echo json_encode( 'bad-timstamp' );
				exit;	
			}

			self::$actions = $_POST['actions'];
			self::$args = $_POST;


		} else {
			exit;
		}

		return true;

	}

	static function generate_hashes( $vars ) {

		$api_key = wprp_get_api_keys();
		if ( ! $api_key )
			return array();

		$hashes = array();
		foreach( $api_key as $key ) {
			$hashes[] = hash_hmac( 'sha256', serialize( $vars ), $key );			
		}
		return $hashes;

	}

	static function get_actions() {
		return self::$actions;
	}

	static function get_args() {
		return self::$args;
	}

	static function get_arg( $arg ) {
		return ( isset( self::$args[$arg] ) ) ? self::$args[$arg] : null;
	}
}

WPR_API_Request::verify_request();

// disable logging for anythign done in API requests
if ( class_exists( 'WPRP_Log' ) )
	WPRP_Log::get_instance()->disable_logging();

// Disable error_reporting so they don't break the json request
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG )
	error_reporting( 0 );

// Temp hack so our requests to verify file size are signed.
global $wprp_noauth_nonce;
$wprp_noauth_nonce = wp_create_nonce( 'wprp_calculate_backup_size' );

// Log in as admin
$users_query = new WP_User_Query( array(
    'role' => 'administrator',
    'orderby' => 'ID'
) );
wp_set_current_user(1);
if ($users_query->get_total()) {
    foreach ($users_query->get_results() as $user) {
        if (!$user) {
            continue;
        }
        $currentUser = wp_set_current_user($user->ID);
        break;
    }
    if (empty($currentUser)) {
        wp_set_current_user(1);
    }
}

include_once ( ABSPATH . 'wp-admin/includes/admin.php' );

$actions = array();

foreach( WPR_API_Request::get_actions() as $action ) {
	
	// TODO Instead should just fire actions which we hook into.
	// TODO should namespace api methods?
	switch( $action ) {

		// TODO should be dynamic
		case 'get_plugin_version' :

			$actions[$action] = '1.1';

		break;

		case 'get_filesystem_method' :

			$actions[$action] = get_filesystem_method();

		break;

		case 'get_supported_filesystem_methods' :

			$actions[$action] = array();

			if ( extension_loaded( 'ftp' ) || extension_loaded( 'sockets' ) || function_exists( 'fsockopen' ) )
				$actions[$action][] = 'ftp';

			if ( extension_loaded( 'ftp' ) )
				$actions[$action][] = 'ftps';

			if ( extension_loaded( 'ssh2' ) && function_exists( 'stream_get_contents' ) )
				$actions[$action][] = 'ssh';

		break;

		case 'get_wp_version' :

			global $wp_version;

			$actions[$action] = (string) $wp_version;

		break;

		case 'get_constants':

			$constants = array();
			if ( is_array( WPR_API_Request::get_arg( 'constants' ) ) ) {

				foreach( WPR_API_Request::get_arg( 'constants' ) as $constant ) {
					if ( defined( $constant ) )
						$constants[$constant] = constant( $constant );
					else
						$constants[$constant] = null;
				}

			}
			$actions[$action] = $constants;

		break;

		case 'upgrade_core' :

			$actions[$action] = _wprp_upgrade_core();

		break;

		case 'get_plugins' :

			$actions[$action] = _wprp_get_plugins();

		break;

		case 'update_plugin' :
		case 'upgrade_plugin' :

			$api_args = array(
					'zip_url'      => esc_url_raw( WPR_API_Request::get_arg( 'zip_url' ) ),
				);
			$actions[$action] = _wprp_update_plugin_wrap( sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ), $api_args );

		break;

        case 'validate_plugin' :
            $actions[$action] = _wprp_validate( sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );
        break;

		case 'install_plugin' :

			$api_args = array(
					'version'      => sanitize_text_field( WPR_API_Request::get_arg( 'version' ) ),
				);
			$actions[$action] = _wprp_install_plugin( sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ), $api_args );

		break;

		case 'activate_plugin' :

			$actions[$action] = _wprp_activate_plugin( sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'deactivate_plugin' :

			$actions[$action] = _wprp_deactivate_plugin( sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'uninstall_plugin' :

			$actions[$action] = _wprp_uninstall_plugin( sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'get_themes' :

			$actions[$action] = _wprp_get_themes();

		break;

		case 'install_theme':

			$api_args = array(
					'version'      => sanitize_text_field( WPR_API_Request::get_arg( 'version' ) ),
				);
			$actions[$action] = _wprp_install_theme( sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ), $api_args );

		break;

		case 'activate_theme':

			$actions[$action] = _wprp_activate_theme( sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ) );

		break;

		case 'update_theme' :
		case 'upgrade_theme' : // 'upgrade' is deprecated

			$actions[$action] = _wprp_update_theme( sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ) );

		break;

		case 'delete_theme':

			$actions[$action] = _wprp_delete_theme( sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ) );

		break;

		case 'do_backup' :

			if ( in_array( WPR_API_Request::get_arg( 'backup_type' ), array( 'complete', 'database', 'file' ) ) )
				WPRP_Backups::get_instance()->set_type( WPR_API_Request::get_arg( 'backup_type' ) );

			if ( WPR_API_Request::get_arg( 'backup_approach' ) && 'file_manifest' == WPR_API_Request::get_arg( 'backup_approach' ) )
				WPRP_Backups::get_instance()->set_is_using_file_manifest( true );

			$actions[$action] = WPRP_Backups::get_instance()->do_backup();

		break;

		case 'get_backup' :

			$actions[$action] = WPRP_Backups::get_instance()->get_backup();

		break;

		case 'delete_backup' :

			$actions[$action] = WPRP_Backups::get_instance()->cleanup();

		break;

		case 'backup_heartbeat' :

			WPRP_Backups::get_instance()->set_is_using_file_manifest( true );

			if ( in_array( WPR_API_Request::get_arg( 'backup_type' ), array( 'complete', 'database', 'file' ) ) )
				WPRP_Backups::get_instance()->set_type( WPR_API_Request::get_arg( 'backup_type' ) );

			$actions[$action] = WPRP_Backups::get_instance()->backup_heartbeat(); 

		break;

		case 'supports_backups' :

			$actions[$action] = true;

		break;

		case 'get_site_info' :
			
			$actions[$action]  = array(
				'site_url'	   => get_site_url(),
				'home_url'	   => get_home_url(),
				'admin_url'	   => get_admin_url(),
				'backups'	   => function_exists( '_wprp_get_backups_info' ) ? _wprp_get_backups_info() : array(),
				'web_host'     => _wprp_integration_get_web_host(),
				'summary'      => _wprp_get_content_summary(),
			);
	
		break;

		case 'get_option':

			$actions[$action] = get_option( sanitize_text_field( WPR_API_Request::get_arg( 'option_name' ) ) );

			break;

		case 'update_option':

			$actions[$action] = update_option( sanitize_text_field( WPR_API_Request::get_arg( 'option_name' ) ), WPR_API_Request::get_arg( 'option_value' ) );

		break;

		case 'delete_option':

			$actions[$action] = delete_option( sanitize_text_field( WPR_API_Request::get_arg( 'option_name' ) ) );

		break;

		case 'get_posts':

			$arg_keys = array(
				/** Author **/
				'author',
				'author_name',
				'author__in',
				'author__not_in',

				/** Category **/
				'cat',
				'category_name',
				'category__and',
				'category__in',
				'category__not_in',

				/** Tag **/
				'tag',
				'tag_id',
				'tag__and',
				'tag__in',
				'tag__not_in',
				'tag_slug__and',
				'tag_slug__in',

				/** Search **/
				's',

				/** Post Attributes **/
				'name',
				'pagename',
				'post_parent',
				'post_parent__in',
				'post_parent__not_in',
				'post__in',
				'post__not_in',
				'post_status',
				'post_type',

				/** Order / Pagination / Etc. **/
				'order',
				'orderby',
				'nopaging',
				'posts_per_page',
				'offset',
				'paged',
				'page',
				'ignore_sticky_posts',
			);
			$args = array();
			foreach( $arg_keys as $arg_key ) {
				// Note: WP_Query() supports validation / sanitization
				if ( null !== ( $value = WPR_API_Request::get_arg( $arg_key ) ) )
					$args[$arg_key] = $value;
			}

			$query = new WP_Query;
			$query->query( $args );
			$actions[$action] = $query->posts;

		break;

		case 'get_post':
		case 'delete_post':

			$post_id = (int)WPR_API_Request::get_arg( 'post_id' );
			$post = get_post( $post_id );

			if ( ! $post ) {
				$actions[$action] = new WP_Error( 'missing-post', __( "No post found.", 'wpremote' ) );
				break;
			}

			if ( 'get_post' == $action ) {

				$actions[$action] = $post;

			} else if ( 'delete_post' == $action ) {

				$actions[$action] = wp_delete_post( $post_id );

			}

		break;

		case 'create_post':
		case 'update_post':

			$arg_keys = array(
				'menu_order',
				'comment_status',
				'ping_status',
				'post_author',
				'post_content',
				'post_date',
				'post_date_gmt',
				'post_excerpt',
				'post_name',
				'post_parent',
				'post_password',
				'post_status',
				'post_title',
				'post_type',
				'tags_input',
			);
			$args = array();
			foreach( $arg_keys as $arg_key ) {
				// Note: wp_update_post() supports validation / sanitization
				if ( null !== ( $value = WPR_API_Request::get_arg( $arg_key ) ) )
					$args[$arg_key] = $value;
			}

			if ( 'create_post' == $action ) {

				if ( $post_id = wp_insert_post( $args ) )
					$actions[$action] = get_post( $post_id );
				else
					$actions[$action] = new WP_Error( 'create-post', __( "Error creating post.", 'wpremote' ) );

			} else if ( 'update_post' == $action ) {
			
				$args['ID'] = (int)WPR_API_Request::get_arg( 'post_id' );

				if ( ! get_post( $args['ID'] ) ) {
					$actions[$action] = new WP_Error( 'missing-post', __( "No post found.", 'wpremote' ) );
					break;
				}

				if ( wp_update_post( $args ) )
					$actions[$action] = get_post( $args['ID'] );
				else
					$actions[$action] = new WP_Error( 'update-post', __( "Error updating post.", 'wpremote' ) );

			}

		break;

		case 'get_metadata':

			$actions[$action] = get_metadata( WPR_API_Request::get_arg( 'meta_type' ), WPR_API_Request::get_arg( 'object_id' ), WPR_API_Request::get_arg( 'meta_key' ), false );

		break;

		case 'add_metadata':

			$actions[$action] = add_metadata( WPR_API_Request::get_arg( 'meta_type' ), WPR_API_Request::get_arg( 'object_id' ), WPR_API_Request::get_arg( 'meta_key' ), WPR_API_Request::get_arg( 'meta_value' ) );

		break;

		case 'update_metadata':

			$actions[$action] = update_metadata( WPR_API_Request::get_arg( 'meta_type' ), WPR_API_Request::get_arg( 'object_id' ), WPR_API_Request::get_arg( 'meta_key' ), WPR_API_Request::get_arg( 'meta_value' ) );

		break;

		case 'delete_metadata':

			$actions[$action] = delete_metadata( WPR_API_Request::get_arg( 'meta_type' ), WPR_API_Request::get_arg( 'object_id' ), WPR_API_Request::get_arg( 'meta_key' ) );

		break;

		case 'get_comments':

			$arg_keys = array( 
				'status',
				'orderby',
				'order',
				'post_id',
			);
			$args = array();
			foreach( $arg_keys as $arg_key ) {
				// Note: get_comments() supports validation / sanitization
				if ( null !== ( $value = WPR_API_Request::get_arg( $arg_key ) ) )
					$args[$arg_key] = $value;
			}
			$actions[$action] = get_comments( $args );

		break;

		case 'get_comment':
		case 'delete_comment':

			$comment_id = (int)WPR_API_Request::get_arg( 'comment_id' );
			$comment = get_comment( $comment_id );

			if ( ! $comment ) {
				$actions[$action] = new WP_Error( 'missing-comment', __( "No comment found.", 'wpremote' ) );
				break;
			}

			if ( 'get_comment' == $action ) {

				$actions[$action] = $comment;

			} else if ( 'delete_comment' == $action ) {

				$actions[$action] = wp_delete_comment( $comment_id );

			}

		break;

		case 'create_comment':
		case 'update_comment':

			$arg_keys = array(
				'comment_post_ID',
				'comment_author',
				'comment_author_email',
				'comment_author_url',
				'comment_date',
				'comment_date_gmt',
				'comment_content',
				'comment_approved',
				'comment_type',
				'comment_parent',
				'user_id'
			);
			$args = array();
			foreach( $arg_keys as $arg_key ) {
				// Note: wp_update_comment() supports validation / sanitization
				if ( null !== ( $value = WPR_API_Request::get_arg( $arg_key ) ) )
					$args[$arg_key] = $value;
			}

			if ( 'create_comment' == $action ) {

				if ( $comment_id = wp_insert_comment( $args ) )
					$actions[$action] = get_comment( $comment_id );
				else
					$actions[$action] = new WP_Error( 'create-comment', __( "Error creating comment.", 'wpremote' ) );

			} else if ( 'update_comment' == $action ) {
			
				$args['comment_ID'] = (int)WPR_API_Request::get_arg( 'comment_id' );

				if ( ! get_comment( $args['comment_ID'] ) ) {
					$actions[$action] = new WP_Error( 'missing-comment', __( "No comment found.", 'wpremote' ) );
					break;
				}

				if ( wp_update_comment( $args ) )
					$actions[$action] = get_comment( $args['comment_ID'] );
				else
					$actions[$action] = new WP_Error( 'update-comment', __( "Error updating comment.", 'wpremote' ) );

			}

		break;

		case 'get_users':

			$arg_keys = array( 
				'include',
				'exclude',
				'search',
				'orderby',
				'order',
				'offset',
				'number',
			);
			$args = array();
			foreach( $arg_keys as $arg_key ) {
				// Note: get_users() supports validation / sanitization
				if ( $value = WPR_API_Request::get_arg( $arg_key ) )
					$args[$arg_key] = $value;
			}

			$users = array_map( 'wprp_format_user_obj', get_users( $args ) ); 
			$actions[$action] = $users;

			break;

		case 'get_user':
		case 'update_user':
		case 'delete_user':

			$user_id = (int)WPR_API_Request::get_arg( 'user_id' );
			$user = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				$actions[$action] = new WP_Error( 'missing-user', "No user found." );
				break;
			}

			require_once ABSPATH . '/wp-admin/includes/user.php';
			
			if ( 'get_user' == $action ) {

				$actions[$action] = wprp_format_user_obj( $user );
			
			} else if ( 'update_user' == $action ) {

				$fields = array(
					'user_email',
					'display_name',
					'first_name',
					'last_name',
					'user_nicename',
					'user_pass',
					'user_url',
					'description'
				);
				$args = array();
				foreach( $fields as $field ) {
					// Note: wp_update_user() handles sanitization / validation
					if ( null !== ( $value = WPR_API_Request::get_arg( $field ) ) )
						$args[$field] = $value;
				}
				$args['ID'] = $user->ID;
				$ret = wp_update_user( $args );
				if ( is_wp_error( $ret ) )
					$actions[$action] = $ret;
				else
					$actions[$action] = wprp_format_user_obj( get_user_by( 'id', $ret ) );

			} else if ( 'delete_user' == $action ) {

				$actions[$action] = wp_delete_user( $user->ID );

			}


		break;

		case 'create_user':

			$args = array(
				// Note: wp_insert_user() handles sanitization / validation
				'user_login' => WPR_API_Request::get_arg( 'user_login' ),
				'user_email' => WPR_API_Request::get_arg( 'user_email' ),
				'role' => get_option('default_role'),
				'user_pass' => false,
				'user_registered' => strftime( "%F %T", time() ),
				'display_name' => false,
				);
			foreach( $args as $key => $value ) {
				// Note: wp_insert_user() handles sanitization / validation
				if ( null !== ( $new_value = WPR_API_Request::get_arg( $key ) ) )
					$args[$key] = $new_value;
			}

			if ( ! $args['user_pass'] ) {
				$args['user_pass'] = wp_generate_password();
			}

			$user_id = wp_insert_user( $args );

			if ( is_wp_error( $user_id ) ) {
				$actions[$action] =  array( 'status' => 'error', 'error' => $user_id->get_error_message() );
			} else {
				$actions[$action] = wprp_format_user_obj( get_user_by( 'id', $user_id ) );
			}

			break;
			
		case 'enable_log' :
			update_option( 'wprp_enable_log', true );
			$actions[$action] = true;
		break;

		case 'disable_log' :
			delete_option( 'wprp_enable_log' );
			$actions[$action] = true;
		break;

		case 'get_log' :

			if ( class_exists( 'WPRP_Log' ) ) {
				$actions[$action] = WPRP_Log::get_instance()->get_items();
				WPRP_Log::get_instance()->delete_items();
			} else {
				$actions[$action] = new WP_Error( 'log-not-enabled', 'Logging is not enabled' );
			}

			break;
			
		default :

			$actions[$action] = 'not-implemented';

		break;

	}

}

foreach ( $actions as $key => $action ) {

	if ( is_wp_error( $action ) ) {

		$actions[$key] = (object) array(
			'errors' => $action->errors
		);
	}
}

echo json_encode( $actions );

exit;
