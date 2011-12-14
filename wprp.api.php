<?php

if( !isset( $_GET['wpr_api_key'] ) || urldecode( $_GET['wpr_api_key'] ) !== get_option( 'wpr_api_key' ) || !isset( $_GET['actions'] ) ) {
	echo json_encode( 'bad-api-key' );
	exit;
}

$actions = explode( ',', $_GET['actions'] );
$actions = array_flip( $actions );

error_reporting(0);
wp_set_current_user( 1 );

foreach( $actions as $action => $value ) :

	switch( $action ) :
	
		//Plugin version
		case 'get_plugin_version' :
			$actions[$action] = '1.1';
			break;
		
		case 'get_wp_version' :
			
			global $wp_version;
			$actions[$action] = (string) $wp_version;
			
			break;
		
		case 'get_plugins' :
			$actions[$action] = _wpr_supports_plugin_upgrade() ? _wpr_get_plugins() : 'not-implemented';
			break;
		
		case 'upgrade_plugin' :
			$actions[$action] = _wpr_upgrade_plugin( (string) $_GET['plugin'] );
			break;
		
		case 'get_backups' :
		case 'do_backup' :
		case 'download_backup' :
			error_log( 'get_bakcUp, dobackup' );
			$actions[$action] = _wprp_backups_api_call( $action );
			break;
		default :
			$actions[$action] = 'not-implemented';
	
	endswitch;

endforeach;

echo json_encode( $actions );
exit;
// functions

function _wpr_get_plugins() {
	
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' ); 
	
	$plugins = get_plugins();
	$active  = get_option( 'active_plugins', array() );
	
	wp_update_plugins();
	
	//different versions of wp store the updates in different places
	if( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_plugins' ) )
		$current = $transient;
	elseif( $transient = get_transient( 'update_plugins' ) )
		$current = $transient;
	else
		$cuurrent = get_option( 'update_plugins' );
		
	foreach ( (array) $plugins as $plugin_file => $plugin ) {
	
	    $new_version = isset( $current->response[$plugin_file] ) ? $current->response[$plugin_file]->new_version : null;
	    
	    if ( is_plugin_active( $plugin_file ) ) {
	    	$plugins[$plugin_file]['active'] = true;
	    } else {
	    	$plugins[$plugin_file]['active'] = false;
	    }
	
	    if ( $new_version ) {
	    	$plugins[$plugin_file]['latest_version'] = $new_version;
	    	$plugins[$plugin_file]['latest_package'] = $current->response[$plugin_file]->package;
	    	$plugins[$plugin_file]['slug'] = $current->response[$plugin_file]->slug;
	    } else {
	    	$plugins[$plugin_file]['latest_version'] = $plugin['Version'];
	    }
	}
	
	global $wp_version;
	$plugins_args = (object) compact( 'plugins' );

		
	return $plugins;
}

function _wpr_upgrade_plugin( $plugin ) {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
	
	if( !class_exists( 'Plugin_Upgrader' ) )
		return array( 'status' => 'error', 'error' => 'WordPress version too old for plugin upgrades' );
	
	$skin = new WPRP_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$is_active = is_plugin_active( $plugin );
	
	ob_start();
	$result = $upgrader->upgrade( $plugin );
	$data = ob_get_contents();
	ob_clean();
	
	if( ( !$result && !is_null( $result ) ) || $data )
		return array( 'status' => 'error', 'error' => 'file_permissions_error' );
	
	elseif( is_wp_error( $result ) )
		return array( 'status' => 'error', 'error' => $result->get_error_code() );
	
	if( $skin->error )
		return array( 'status' => 'error', 'error' => $skin->error );
	
	//if the plugin was activited, we have to re-activate it
	if( $is_active ) {
		$current = get_option( 'active_plugins', array() );
		$current[] = plugin_basename( trim( $plugin ) );;
		sort($current);
		update_option('active_plugins', $current);
	}

	return array( 'status' => 'success' );
	
}

function _wpr_supports_plugin_upgrade() {
	
	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
	return class_exists( 'Plugin_Upgrader' );

}
