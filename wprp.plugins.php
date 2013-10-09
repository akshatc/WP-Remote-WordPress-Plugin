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
function _wprp_update_plugin( $plugin ) {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );

	if ( ! _wprp_supports_plugin_upgrade() )
		return array( 'status' => 'error', 'error' => 'WordPress version too old for plugin upgrades' );

	// check for filesystem access
	if ( ! _wpr_check_filesystem_access() )
		return array( 'status' => 'error', 'error' => 'The filesystem is not writable with the supplied credentials' );

	$skin = new WPRP_Plugin_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$is_active = is_plugin_active( $plugin );

	// Force a plugin update check
	wp_update_plugins();

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

		// we can not use the "normal" way or lazy activating, as thet requires wpremote to be activated
		if ( strpos( $plugin, 'wpremote' ) !== false ) {
			activate_plugin( $plugin, '', false, true );
			return array( 'status' => 'success' );
		}

		// we do a remote request to activate, as we don't want to kill any installs
		$data = array( 'actions' => array( 'activate_plugin' ), 'plugin' => $plugin, 'timestamp' => (string) time() );

		list( $hash ) = WPR_API_Request::generate_hashes( $data );

		$data['wpr_verify_key'] = $hash;

		$args = array( 'body' => $data );

		$request = wp_remote_post( get_bloginfo( 'url' ), $args );

		if ( is_wp_error( $request ) ) {
			return array( 'status' => 'error', 'error' => $request->get_error_code() );
		}

		$body = wp_remote_retrieve_body( $request );

		if ( ! $json = @json_decode( $body ) )
			return array( 'status' => 'error', 'error' => 'The plugin was updated, but failed to re-activate.' );

		$json = $json->activate_plugin;

		if ( empty( $json->status ) )
			return array( 'status' => 'error', 'error' => 'The plugin was updated, but failed to re-activate. The activation request returned no response' );

		if ( $json->status != 'success' )
			return array( 'status' => 'error', 'error' => 'The plugin was updated, but failed to re-activate. The activation request returned response: ' . $json->status );
	}

	return array( 'status' => 'success' );
}

/**
 * Install a plugin on this site
 */
function _wprp_install_plugin( $plugin, $args = array() ) {

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';

	// Access the plugins_api() helper function
	include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	$api_args = array(
		'slug' => $plugin,
		'fields' => array( 'sections' => false )
		);
	$api = plugins_api( 'plugin_information', $api_args );

	if ( is_wp_error( $api ) )
		return array( 'status' => 'error', 'error' => $api->get_error_code() );

	$skin = new WPRP_Plugin_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );

	// The best way to get a download link for a specific version :(
	// Fortunately, we can depend on a relatively consistent naming pattern
	if ( ! empty( $args['version'] ) && 'stable' != $args['version'] )
		$api->download_link = str_replace( $api->version . '.zip', $args['version'] . '.zip', $api->download_link );

	$result = $upgrader->install( $api->download_link );
	if ( is_wp_error( $result ) )
		return array( 'status' => 'error', 'error' => $result->get_error_code() );
	else if ( ! $result )
		return array( 'status' => 'error', 'error' => 'Unknown error installing plugin.' );

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
 * Deactivate a plugin on this site.
 */
function _wprp_deactivate_plugin( $plugin ) {

	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	$result = deactivate_plugins( $plugin );

	if ( is_wp_error( $result ) )
		return array( 'status' => 'error', 'error' => $result->get_error_code() );

	return array( 'status' => 'success' );
}

/**
 * Uninstall a plugin on this site.
 */
function _wprp_uninstall_plugin( $plugin ) {
	global $wp_filesystem;

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';

	if ( ! _wpr_check_filesystem_access() || ! WP_Filesystem() )
		return array( 'status' => 'error', 'error' => 'The filesystem is not writable with the supplied credentials' );

	$plugins_dir = $wp_filesystem->wp_plugins_dir();
	if ( empty( $plugins_dir ) )
		return array( 'status' => 'error', 'error' => 'Unable to locate WordPress Plugin directory.' );

	$plugins_dir = trailingslashit( $plugins_dir );

	if ( is_uninstallable_plugin( $plugin ) )
		uninstall_plugin( $plugin );

	$this_plugin_dir = trailingslashit( dirname( $plugins_dir . $plugin ) );
	// If plugin is in its own directory, recursively delete the directory.
	if ( strpos( $plugin, '/' ) && $this_plugin_dir != $plugins_dir ) //base check on if plugin includes directory separator AND that it's not the root plugin folder
		$deleted = $wp_filesystem->delete( $this_plugin_dir, true );
	else
		$deleted = $wp_filesystem->delete( $plugins_dir . $plugin );

	if ( $deleted ) {
		if ( $current = get_site_transient('update_plugins') ) {
			unset( $current->response[$plugin] );
			set_site_transient('update_plugins', $current);
		}
		return array( 'status' => 'success' );
	} else {
		return array( 'status' => 'error', 'error' => 'Plugin uninstalled, but not deleted.' );
	}

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
