<?php

/**
 * Return an array of installed plugins
 *
 * @return array
 */
function _wprp_get_plugins() {

	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	_wpr_add_non_extend_plugin_support_filter();

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

	_wpr_add_non_extend_plugin_support_filter();

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

function _wpr_add_non_extend_plugin_support_filter() {
    add_filter( 'pre_set_site_transient_update_plugins', '_wpr_add_non_extend_plugin_support' );
}

function _wpr_add_non_extend_plugin_support( $value ) {

    foreach( $non_extend_list = _wprp_get_non_extend_plugins_data() as $key => $anon_function ) {

        if ( ( $returned = call_user_func( $non_extend_list[$key] ) ) )
            $value->response[$returned->plugin_location] = $returned;
    }

    return $value;

}


function _wprp_get_non_extend_plugins_data() {

    return array(
        'gravity_forms' => '_wpr_get_gravity_form_plugin_data',
        'backupbuddy' => '_wpr_get_backupbuddy_plugin_data',
        'tribe_events_pro' => '_wpr_get_tribe_events_pro_plugin_data'
    );

}

function _wpr_get_gravity_form_plugin_data() {

    if ( ! class_exists('GFCommon') || ! method_exists( 'GFCommon', 'get_version_info' ) || ! method_exists( 'RGForms', 'premium_update_push' ) )
        return false;

    $version_data  = GFCommon::get_version_info();
    $plugin_data   = reset( RGForms::premium_update_push( array() ) );

    if ( empty( $version_data['url'] ) || empty( $version_data['is_valid_key'] ) || empty( $plugin_data['new_version'] ) || empty( $plugin_data['PluginURI'] ) || empty( $plugin_data['slug'] ) )
        return false;

    return (object) array(
        'plugin_location' => $plugin_data['slug'], //Not in standard structure but don't forget to include it!
        'id'              => 999999999,
        'slug'            => 'gravityforms',
        'url'             => $plugin_data['PluginURI'],
        'package'         => $version_data['url'],
        'new_version'     => $version_data['version']
    );

}

function _wpr_get_backupbuddy_plugin_data() {

	if ( !class_exists('pb_backupbuddy') )
		return false;

	require_once( pb_backupbuddy::plugin_path() . '/pluginbuddy/lib/updater/updater.php' );
	$preloader_class = 'pb_' . pb_backupbuddy::settings( 'slug' ) . '_updaterpreloader';
	$updater_preloader = new $preloader_class( pb_backupbuddy::settings( 'slug' ) );
	$updater_preloader->upgrader_register();
	$updater_preloader->upgrader_select();

	if ( !is_a( pb_backupbuddy::$_updater, 'pb_backupbuddy_updater' ) || !method_exists( pb_backupbuddy::$_updater, 'check_for_updates' ) )
		return false;

	$current_version = pb_backupbuddy::settings( 'version' );
	$update_data = pb_backupbuddy::$_updater->check_for_updates();

	if ( $update_data->key_status != 'ok' || version_compare( $update_data->new_version, $current_version, '<=' ) )
		return false;

	$update_data->plugin_location = plugin_basename( pb_backupbuddy::plugin_path() . '/backupbuddy.php' ); // needed in _wpr_add_non_extend_plugin_support()

	return $update_data;

}

function _wpr_get_tribe_events_pro_plugin_data() {

	if ( !class_exists( 'TribeEventsPro' ) || ! class_exists( 'PluginUpdateEngineChecker' ) )
		return false;

	$events = TribeEventsPro::instance();
	$updater = new PluginUpdateEngineChecker( $events->updateUrl, $events->pluginSlug, array(), plugin_basename( $events->pluginPath . 'events-calendar-pro.php' ) );
	$state = get_option( $updater->optionName );

	if ( !is_a( $state->update, 'PluginUpdateUtility' ) )
		return false;

	if ( version_compare( $state->update->version, $updater->getInstalledVersion(), '<=' ) )
		return false;

	$update_data = $state->update->toWpFormat();
	$update_data->plugin_location = $updater->pluginFile; // needed in _wpr_add_non_extend_plugin_support()

}