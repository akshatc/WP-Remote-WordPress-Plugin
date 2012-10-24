<?php

// Check the API Key
if ( ! get_option( 'wpr_api_key' ) ) {
	echo json_encode( 'blank-api-key' );
	exit;
} elseif ( ! isset( $_GET['wpr_api_key'] ) || urldecode( $_GET['wpr_api_key'] ) !== get_option( 'wpr_api_key' ) || ! isset( $_GET['actions'] ) ) {
	echo json_encode( 'bad-api-key' );
	exit;
}

$actions = explode( ',', $_GET['actions'] );
$actions = array_flip( $actions );

// Disable error_reporting so they don't break the json request
//error_reporting( 0 );

// Log in as admin
wp_set_current_user( 1 );

foreach( $actions as $action => $value ) {

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

			if ( extension_loaded('ftp') || extension_loaded('sockets') || function_exists('fsockopen') )
				$actions[$action][] = 'ftp';

			if ( extension_loaded('ftp') )
				$actions[$action][] = 'ftps';

			if ( extension_loaded('ssh2') && function_exists('stream_get_contents') )
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

			$actions[$action] = _wprp_upgrade_plugin( (string) $_GET['plugin'] );

		break;

		case 'activate_plugin' :

			$actions[$action] = _wprp_activate_plugin( (string) $_GET['plugin'] );

		break;

		case 'get_themes' :

			$actions[$action] = _wprp_supports_theme_upgrade() ? _wprp_get_themes() : 'not-implemented';

		break;

		case 'upgrade_theme' :

			$actions[$action] = _wprp_upgrade_theme( (string) $_GET['theme'] );

		break;

		case 'do_backup' :
		case 'delete_backup' :
		case 'supports_backups' :
		case 'get_backup' :
		case 'add_backup_schedule' :
		case 'remove_backup_schedule' :
		case 'get_backup_schedules' :
			$actions[$action] = _wprp_backups_api_call( $action );

		break;

		// get site info
		case 'get_site_info' :

			$actions[$action] = array(
				'site_url' => get_site_url(),
				'home_url' => get_home_url(),
				'admin_url' => get_admin_url(),
				'backups' => _wprp_get_backups_info()
			);

		break;

		default :

			$actions[$action] = 'not-implemented';

		break;

	}

}

echo json_encode( $actions );
exit;