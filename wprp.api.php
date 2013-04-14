<?php

class WPR_API_Request {

	static $actions = array();
	static $args = array();

	static function verify_request() {

		// Check the API Key
		if ( ! get_option( 'wpr_api_key' ) ) {

			echo json_encode( 'blank-api-key' );
			exit;

		} elseif ( isset( $_POST['wpr_verify_key'] ) ) {

			$verify = $_POST['wpr_verify_key'];
			unset( $_POST['wpr_verify_key'] );

			$hash = self::generate_hash( $_POST );

			if ( $hash !== $verify ) {
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

	static function generate_hash( $vars ) {

		$hash = hash_hmac( 'sha256', serialize( $vars ), get_option( 'wpr_api_key' ) );
		return $hash;

	}

	static function get_actions() {
		return self::$actions;
	}

	static function get_args() {
		return self::$args;
	}

	static function get_arg( $arg ) {
		return self::$args[$arg];
	}
}

WPR_API_Request::verify_request();

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

		case 'upgrade_plugin' :

			$actions[$action] = _wprp_upgrade_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'activate_plugin' :

			$actions[$action] = _wprp_activate_plugin( (string) sanitize_text_field( WPR_API_Request::get_arg( 'plugin' ) ) );

		break;

		case 'get_themes' :

			$actions[$action] = _wprp_supports_theme_upgrade() ? _wprp_get_themes() : 'not-implemented';

		break;

		case 'upgrade_theme' :

			$actions[$action] = _wprp_upgrade_theme( (string) sanitize_text_field( WPR_API_Request::get_arg( 'theme' ) ) );

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

		default :

			$actions[$action] = 'not-implemented';

		break;

	}

}

echo json_encode( $actions );

exit;
