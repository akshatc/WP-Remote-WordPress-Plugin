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
		return ( isset( self::$args[$arg] ) ) ? self::$args[$arg] : '';
	}
}

WPR_API_Request::verify_request();

// disable logging for anythign done in API requests
if ( class_exists( 'WPRP_Log' ) )
	WPRP_Log::get_instance()->disable_logging();

// Disable error_reporting so they don't break the json request
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG )
	error_reporting( 0 );

// Log in as admin
// TODO what about if admin use doesn't exists?
wp_set_current_user( 1 );

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

		case 'upgrade_core' :

			$actions[$action] = _wprp_upgrade_core();

		break;

		case 'get_plugins' :

			$actions[$action] = _wprp_supports_plugin_upgrade() ? _wprp_get_plugins() : 'not-implemented';

		break;

		case 'update_plugin' :
		case 'upgrade_plugin' :

			$actions[$action] = _wprp_update_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'install_plugin' :

			$api_args = array(
					'version'      => sanitize_text_field( (string)WPR_API_Request::get_arg( 'version' ) ),
				);
			$actions[$action] = _wprp_install_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ), $api_args );

		break;

		case 'activate_plugin' :

			$actions[$action] = _wprp_activate_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'deactivate_plugin' :

			$actions[$action] = _wprp_deactivate_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'uninstall_plugin' :

			$actions[$action] = _wprp_uninstall_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'get_themes' :

			$actions[$action] = _wprp_supports_theme_upgrade() ? _wprp_get_themes() : 'not-implemented';

		break;

		case 'install_theme':

			$api_args = array(
					'version'      => sanitize_text_field( (string)WPR_API_Request::get_arg( 'version' ) ),
				);
			$actions[$action] = _wprp_install_theme( (string) sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ), $api_args );

		break;

		case 'activate_theme':

			$actions[$action] = _wprp_activate_theme( (string) sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ), $api_args );

		break;

		case 'update_theme' :
		case 'upgrade_theme' : // 'upgrade' is deprecated

			$actions[$action] = _wprp_update_theme( (string) sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ) );

		break;

		case 'delete_theme':

			$actions[$action] = _wprp_delete_theme( (string) sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ) );

		break;

		case 'do_backup' :
		case 'delete_backup' :
		case 'supports_backups' :
		case 'get_backup' :
			$actions[$action] = function_exists( '_wprp_get_backups_info' ) ? _wprp_backups_api_call( $action ) : 'not-implemented';

		break;

		case 'get_site_info' :

			$actions[$action] = array(
				'site_url'	=> get_site_url(),
				'home_url'	=> get_home_url(),
				'admin_url'	=> get_admin_url(),
				'backups'	=> function_exists( '_wprp_get_backups_info' ) ? _wprp_get_backups_info() : array()
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
				if ( $new_value = WPR_API_Request::get_arg( $key ) )
					$args[$key] = $new_value;
			}

			if ( ! $args['user_pass'] ) {
				$args['user_pass'] = $generated_password = wp_generate_password();
			} else {
				$generated_password = false;
			}

			$user_id = wp_insert_user( $args );

			if ( is_wp_error( $user_id ) ) {
				$actions[$action] =  array( 'status' => 'error', 'error' => $user_id->get_error_message() );
			} else {
				$actions[$action] = new WP_Error( 'log-not-enabled', 'Logging is not enabled' );
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

echo json_encode( $actions );

exit;
