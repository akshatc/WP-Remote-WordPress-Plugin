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

	// Premium plugins that have adopted the ManageWP API report new plugins by this filter
	$manage_wp_updates = apply_filters( 'mwp_premium_update_notification', array() );

	foreach ( (array) $plugins as $plugin_file => $plugin ) {

	    if ( is_plugin_active( $plugin_file ) )
	    	$plugins[$plugin_file]['active'] = true;
	    else
	    	$plugins[$plugin_file]['active'] = false;

	    $manage_wp_plugin_update = false;
	    foreach( $manage_wp_updates as $manage_wp_update ) {

			if ( ! empty( $manage_wp_update['Name'] ) && $plugin['Name'] == $manage_wp_update['Name'] )
				$manage_wp_plugin_update = $manage_wp_update;

	    }

	    if ( $manage_wp_plugin_update ) {

			$plugins[$plugin_file]['latest_version'] = $manage_wp_plugin_update['new_version'];

	    } else if ( isset( $current->response[$plugin_file] ) ) {

			$plugins[$plugin_file]['latest_version'] = $current->response[$plugin_file]->new_version;
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
function _wprp_update_plugin( $plugin_file, $args ) {
	global $wprp_zip_update;

	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return new WP_Error( 'disallow-file-mods', __( "File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote' ) );

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-plugin-upgrader-skin.php';

	// check for filesystem access
	if ( ! _wpr_check_filesystem_access() )
		return new WP_Error( 'filesystem-not-writable', __( 'The filesystem is not writable with the supplied credentials', 'wpremote' ) );

	$is_active         = is_plugin_active( $plugin_file );
	$is_active_network = is_plugin_active_for_network( $plugin_file );

	foreach( get_plugins() as $path => $maybe_plugin ) {

		if ( $path == $plugin_file ) {
			$plugin = $maybe_plugin;
			break;
		}

	}

	// Permit specifying a zip URL to update the plugin with
	if ( ! empty( $args['zip_url'] ) ) {

		$zip_url = $args['zip_url'];

	} else {

		// Check to see if this is a premium plugin that supports the ManageWP implementation
		$manage_wp_updates = apply_filters( 'mwp_premium_perform_update', array() );
		$manage_wp_plugin_update = false;
		foreach( $manage_wp_updates as $manage_wp_update ) {

			if ( ! empty( $manage_wp_update['Name'] )
				&& $plugin['Name'] == $manage_wp_update['Name']
				&& ! empty( $manage_wp_update['url'] ) ) {
				$zip_url = $manage_wp_update['url'];
				break;
			}

		}

	}

	$skin = new WPRP_Plugin_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );

	// Fake out the plugin upgrader with our package url
	if ( ! empty( $zip_url ) ) {
		$wprp_zip_update = array(
			'plugin_file'    => $plugin_file,
			'package'        => $zip_url,
		);
		add_filter( 'pre_site_transient_update_plugins', '_wprp_forcably_filter_update_plugins' );
	} else {
		wp_update_plugins();
	}

	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade( $plugin_file );
	$data = ob_get_contents();
	ob_clean();

	if ( $manage_wp_plugin_update )
		remove_filter( 'pre_site_transient_update_plugins', '_wprp_forcably_filter_update_plugins' );

	if ( ! empty( $skin->error ) )

		return new WP_Error( 'plugin-upgrader-skin', $upgrader->strings[$skin->error] );

	else if ( is_wp_error( $result ) )

		return $result;

	else if ( ( ! $result && ! is_null( $result ) ) || $data )

		return new WP_Error( 'plugin-update', __( 'Unknown error updating plugin.', 'wpremote' ) );

	// If the plugin was activited, we have to re-activate it
	// but if activate_plugin() fatals, then we'll just have to return 500
	if ( $is_active )
		activate_plugin( $plugin_file, '', $is_active_network, true );

	return array( 'status' => 'success' );
}

/**
 * Filter `update_plugins` to produce a response it will understand
 * so we can have the Upgrader skin handle the update
 */
function _wprp_forcably_filter_update_plugins() {
	global $wprp_zip_update;

	$current = new stdClass;
	$current->response = array();

	$plugin_file = $wprp_zip_update['plugin_file'];
	$current->response[$plugin_file] = new stdClass;
	$current->response[$plugin_file]->package = $wprp_zip_update['package'];

	return $current;
}

/**
 * Install a plugin on this site
 */
function _wprp_install_plugin( $plugin, $args = array() ) {

	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return new WP_Error( 'disallow-file-mods', __( "File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote' ) );

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';
	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-plugin-upgrader-skin.php';

	if ( ! empty( $args['zip_url'] ) ) {

		$zip_url = $args['zip_url'];

	} else {

		// Access the plugins_api() helper function
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$api_args = array(
			'slug' => $plugin,
			'fields' => array( 'sections' => false )
			);
		$api = plugins_api( 'plugin_information', $api_args );

		if ( is_wp_error( $api ) )
			return $api;

		$zip_url = $api->download_link;

		// The best way to get a download link for a specific version :(
		// Fortunately, we can depend on a relatively consistent naming pattern
		if ( ! empty( $args['version'] ) && 'stable' != $args['version'] )
			$zip_url = str_replace( $api->version . '.zip', $args['version'] . '.zip', $zip_url );

	}

	$skin = new WPRP_Plugin_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );

	$result = $upgrader->install( $zip_url );

	if ( is_wp_error( $result ) )
		return $result;
	else if ( ! $result )
		return new WP_Error( 'plugin-install', __( 'Unknown error installing plugin.', 'wpremote' ) );

	return array( 'status' => 'success' );
}

function _wprp_activate_plugin( $plugin ) {

	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	$result = activate_plugin( $plugin );

	if ( is_wp_error( $result ) )
		return $result;

	return array( 'status' => 'success' );
}

/**
 * Deactivate a plugin on this site.
 */
function _wprp_deactivate_plugin( $plugin ) {

	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	if ( is_plugin_active( $plugin ) )
		deactivate_plugins( $plugin );

	return array( 'status' => 'success' );
}

/**
 * Uninstall a plugin on this site.
 */
function _wprp_uninstall_plugin( $plugin ) {
	global $wp_filesystem;

	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return new WP_Error( 'disallow-file-mods', __( "File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote' ) );

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';

	if ( ! _wpr_check_filesystem_access() || ! WP_Filesystem() )
		return new WP_Error( 'filesystem-not-writable', __( 'The filesystem is not writable with the supplied credentials', 'wpremote' ) );

	$plugins_dir = $wp_filesystem->wp_plugins_dir();
	if ( empty( $plugins_dir ) )
		return new WP_Error( 'missing-plugin-dir', __( 'Unable to locate WordPress Plugin directory.' , 'wpremote' ) );

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
		return new WP_Error( 'plugin-uninstall', __( 'Plugin uninstalled, but not deleted.', 'wpremote' ) );
	}

}
