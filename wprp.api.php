<?php

// Check the API Key
if ( ! isset( $_GET['wpr_api_key'] ) || urldecode( $_GET['wpr_api_key'] ) !== get_option( 'wpr_api_key' ) || ! isset( $_GET['actions'] ) ) {
	echo json_encode( 'bad-api-key' );
	exit;
}

$actions = explode( ',', $_GET['actions'] );
$actions = array_flip( $actions );

// Disable error_reporting so they don't break the json request
error_reporting( 0 );

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
		
		case 'get_wp_version' :

			global $wp_version;

			$actions[$action] = (string) $wp_version;

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
	
			$actions[$action] = _wprp_backups_api_call( $action );

		break;

		default :

			$actions[$action] = 'not-implemented';
			
		break;

	}

}
echo json_encode( $actions );
exit;