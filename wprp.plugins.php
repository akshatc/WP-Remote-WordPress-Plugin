<?php

/**
 * Return an array of installed plugins
 *
 * @return array
 */
function _wprp_get_plugins() {

	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	// Get all plugins
	$plugins = get_plugins();

	// Get the list of active plugins
	$active  = get_option( 'active_plugins', array() );
	
	// Delete the transient so wp_update_plugins can get fresh data
	if ( function_exists( 'get_site_transient' ) )
		delete_site_transient( 'update_plugins' );
	
	else
		delete_transient( 'update_plugins' );
	
	// Force a plugin update check
	wp_update_plugins();

	// Different versions of wp store the updates in different places
	// TODO can we depreciate
	if( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_plugins' ) )
		$current = $transient;

	elseif( $transient = get_transient( 'update_plugins' ) )
		$current = $transient;

	else
		$current = get_option( 'update_plugins' );

	foreach ( (array) $plugins as $plugin_file => $plugin ) {

	    $new_version = isset( $current->response[$plugin_file] ) ? $current->response[$plugin_file]->new_version : null;

	    if ( is_plugin_active( $plugin_file ) )
	    	$plugins[$plugin_file]['active'] = true;

	    else
	    	$plugins[$plugin_file]['active'] = false;

	    if ( $new_version ) {
	    	$plugins[$plugin_file]['latest_version'] = $new_version;
	    	$plugins[$plugin_file]['latest_package'] = $current->response[$plugin_file]->package;
	    	$plugins[$plugin_file]['slug'] = $current->response[$plugin_file]->slug;

	    } else {
	    	$plugins[$plugin_file]['latest_version'] = $plugin['Version'];

	    }

	}

	return $plugins;
}

/**
 * Update a plugin
 *
 * @access private
 * @param mixed $plugin
 * @return array
 */
function _wprp_upgrade_plugin( $plugin ) {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );

	if ( ! _wprp_supports_plugin_upgrade() )
		return array( 'status' => 'error', 'error' => 'WordPress version too old for plugin upgrades' );

	$skin = new WPRP_Plugin_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$is_active = is_plugin_active( $plugin );

	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade( $plugin );
	$data = ob_get_contents();
	ob_clean();

	if ( ( ! $result && ! is_null( $result ) ) || $data )
		return array( 'status' => 'error', 'error' => 'file_permissions_error' );

	elseif ( is_wp_error( $result ) )
		return array( 'status' => 'error', 'error' => $result->get_error_code() );

	if ( $skin->error )
		return array( 'status' => 'error', 'error' => $skin->error );

	// If the plugin was activited, we have to re-activate it
	if ( $is_active ) {
		
		// we do a remote request to activate, as we don;t want to kill any installs 
		$url = add_query_arg( 'wpr_api_key', $_GET['wpr_api_key'], get_bloginfo( 'url' ) );
		$url = add_query_arg( 'actions', 'activate_plugin', $url );
		$url = add_query_arg( 'plugin', $plugin, $url );
		
		$request = wp_remote_get( $url );

		if ( is_wp_error( $request ) ) {
			return array( 'status' => 'error', 'error' => $request->get_error_code() );
		}
		
		$body = wp_remote_retrieve_body( $request );
		
		
		if ( ! $json = @json_decode( $body ) )
			return array( 'status' => 'error', 'error' => 'The plugin was updated, but failed to re-activate.' );
		
		$json = $json->activate_plugin;
		
		if ( empty( $json->status ) )
			return array( 'status' => 'error', 'error' => 'The plugin was updated, but failed to re-activate. The activation reuest returned no response' );
		
		if ( $json->status != 'success' )
			return array( 'status' => 'error', 'error' => 'The plugin was updated, but failed to re-activate. The activation reuest returned response: ' . $json->status );
	}

	return array( 'status' => 'success' );
}

function _wprp_activate_plugin( $plugin ) {
	
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	$result = activate_plugin( $plugin );

	if ( is_wp_error( $result ) )
		return array( 'status' => 'error', 'error' => $result->get_error_code() );
	
	return array( 'status' => 'success' );
}

/**
 * Check if the site can support plugin upgrades
 *
 * @todo should probably check if we have direct filesystem access
 * @todo can we remove support for versions which don't support Plugin_Upgrader
 * @return bool
 */
function _wprp_supports_plugin_upgrade() {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );

	return class_exists( 'Plugin_Upgrader' );

}