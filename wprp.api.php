<?php

class WPR_API_Request {

	static $actions = array();
	static $args = array();

	static function verify_request() {

		// Check the API Key
		if ( ! get_option( 'wpr_api_key' ) ) {

			return new WP_Error( 'blank-api-key' );

		} elseif ( isset( $_POST['wpr_verify_key'] ) ) {

			$verify = $_POST['wpr_verify_key'];
			unset( $_POST['wpr_verify_key'] );

			$hash = self::generate_hash( $_POST );

			if ( $hash !== $verify ) {
				return new WP_Error( 'bad-verify-key' );
			}

			if ( (int) $_POST['timestamp'] > time() + 360 || (int) $_POST['timestamp'] < time() - 360 ) {
				return new WP_Error( 'bad-timestamp' );
			}

			self::$actions = $_POST['actions'];
			self::$args = $_POST;

		} else {
			return new WP_Error( 'payload-not-present' );
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
		return ( isset( self::$args[$arg] ) ) ? self::$args[$arg] : '';
	}

	static function handle_request() {

		// Disable error_reporting so they don't break the json request
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG )
			error_reporting( 0 );

		// Log in as admin
		// TODO what about if admin use doesn't exists?
		wp_set_current_user( 1 );

		if ( is_wp_error( $error = self::verify_request() ) ) {
			echo json_encode( $error->get_error_code() );
			exit;
		}

		$response = array();

		foreach ( self::get_actions() as $action ) {

			$response[$action] = apply_filters( 'wpr_api_' . $action, 'not-implemented', self::get_args() );

		}

		echo json_encode( $actions );
		exit;

	}
}

add_filter( 'wpr_api_upgrade_core', 		'_wprp_upgrade_core' );
add_filter( 'wpr_api_get_site_info', 		'_wprp_get_site_info' );
add_filter( 'wpr_api_get_plugin_version', 	'_wprp_get_plugin_version' );
add_filter( 'wpr_api_get_filesystem_method','_wprp_get_filesystem_method' );
add_filter( 'wpr_api_get_supported_filesystem_methods', '_wprp_get_supported_filesystem_methods' );
add_filter( 'wpr_api_get_wp_version', 		'_wprp_get_wp_version' );
add_filter( 'wpr_api_get_plugins', 			'_wprp_get_plugins' );
add_filter( 'wpr_api_upgrade_plugin', 		'_wprp_update_plugin' );
add_filter( 'wpr_api_update_plugin', 		'_wprp_update_plugin' );
add_filter( 'wpr_api_install_plugin', 		'_wprp_install_plugin' );
add_filter( 'wpr_api_activate_plugin', 		'_wprp_activate_plugin' );
add_filter( 'wpr_api_deactivate_plugin', 	'_wprp_deactivate_plugin' );
add_filter( 'wpr_api_uninstall_plugin', 	'_wprp_uninstall_plugin' );
add_filter( 'wpr_api_get_themes',		 	'_wprp_get_themes' );
add_filter( 'wpr_api_upgrade_theme',		'_wprp_upgrade_theme' );
add_filter( 'wpr_api_do_backup',			'_wprp_do_backup' );
add_filter( 'wpr_api_delete_backup',		'_wprp_delete_backup' );
add_filter( 'wpr_api_supports_backups',		'_wprp_supports_backups' );
add_filter( 'wpr_api_get_backup',			'_wprp_get_backup' );
add_filter( 'wpr_api_upgrade_theme',		'_wprp_upgrade_theme' );