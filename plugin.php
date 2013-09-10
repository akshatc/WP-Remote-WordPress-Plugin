<?php

/*
Plugin Name: WP Remote
Description: Manage your WordPress site with <a href="https://wpremote.com/">WP Remote</a>. <strong>Deactivate to clear your API Key.</strong>
Version: 2.6.3
Author: Human Made Limited
Author URI: http://hmn.md/
*/

/*  Copyright 2011 Human Made Limited  (email : hello@humanmade.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'WPRP_PLUGIN_SLUG', 'wpremote' );
define( 'WPRP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'WPR_URL' ) )
	define( 'WPR_URL', 'https://wpremote.com/' );

if ( ! defined( 'WPR_API_URL' ) )
	define( 'WPR_API_URL', 'https://wpremote.com/api/json/' );

if ( ! defined( 'WPR_LANG_DIR' ) )
	define( 'WPR_LANG_DIR', apply_filters( 'wpr_filter_lang_dir', trailingslashit( WPRP_PLUGIN_PATH ) . trailingslashit( 'languages' ) ) );

// Don't activate on anything less than PHP 5.2.4
if ( version_compare( phpversion(), '5.2.4', '<' ) ) {

	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	deactivate_plugins( WPRP_PLUGIN_SLUG . '/plugin.php' );

	if ( isset( $_GET['action'] ) && ( $_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' ) )
		die( __( 'WP Remote requires PHP version 5.2.4 or greater.', 'wpremote' ) );

}

require_once( WPRP_PLUGIN_PATH . '/wprp.admin.php' );
require_once( WPRP_PLUGIN_PATH . '/wprp.compatability.php' );

// Backups require 3.1
if ( version_compare( get_bloginfo( 'version' ), '3.1', '>=' ) ) {

	require_once( WPRP_PLUGIN_PATH . '/wprp.hm.backup.php' );
	require_once( WPRP_PLUGIN_PATH . '/wprp.backups.php' );

}

// Don't include when doing a core update
if ( empty( $_GET['action'] ) || $_GET['action'] != 'do-core-upgrade' ) :

	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	// Custom upgrader skins
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-plugin-upgrader-skin.php';
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-theme-upgrader-skin.php';
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-core-upgrader-skin.php';

endif;

/**
 * Get a needed URL on the WP Remote site
 *
 * @param string      $uri     URI for the URL (optional)
 * @return string     $url     Fully-qualified URL to WP Remote
 */
function wprp_get_wpr_url( $uri = '' ) {

	if ( empty( $uri ) )
		return WPR_URL;

	$url = rtrim( WPR_URL, '/' );
	$uri = trim( $uri, '/' );
	return $url . '/' . $uri . '/';
}

/**
 * Catch the API calls and load the API
 *
 * @return null
 */
function wprp_catch_api_call() {

	if ( empty( $_POST['wpr_verify_key'] ) )
		return;

	require_once( WPRP_PLUGIN_PATH . '/wprp.plugins.php' );
	require_once( WPRP_PLUGIN_PATH . '/wprp.themes.php' );

	require_once( WPRP_PLUGIN_PATH . '/wprp.api.php' );

	exit;

}
add_action( 'init', 'wprp_catch_api_call', 1 );

/**
 * Get the stored WPR API key
 *
 * @return mixed
 */
function wprp_get_api_keys() {
	$keys = apply_filters( 'wpr_api_keys', get_option( 'wpr_api_key' ) );
	if ( ! empty( $keys ) )
		return (array)$keys;
	else
		return array();
}

function wprp_plugin_update_check() {

	$plugin_data = get_plugin_data( __FILE__ );

	// define the plugin version
	define( 'WPRP_VERSION', $plugin_data['Version'] );

	// Fire the update action
	if ( WPRP_VERSION !== get_option( 'wprp_plugin_version' ) )
		wprp_update();

}
add_action( 'admin_init', 'wprp_plugin_update_check' );

/**
 * Run any update code and update the current version in the db
 *
 * @access public
 * @return void
 */
function wprp_update() {

	/**
	 * Remove the old _wpremote_backups directory
	 */
	$uploads_dir = wp_upload_dir();

	$old_wpremote_dir = trailingslashit( $uploads_dir['basedir'] ) . '_wpremote_backups';

	if ( file_exists( $old_wpremote_dir ) )
		WPRP_Backups::rmdir_recursive( $old_wpremote_dir );

	// If BackUpWordPress isn't installed then lets just delete the whole backups directory
	if ( ! defined( 'HMBKP_PLUGIN_PATH' ) && $path = get_option( 'hmbkp_path' ) ) {
		
		WPRP_Backups::rmdir_recursive( $path );

		delete_option( 'hmbkp_path' );
		delete_option( 'hmbkp_default_path' );
		delete_option( 'hmbkp_plugin_version' );

	}

	// Update the version stored in the db
	if ( get_option( 'wprp_plugin_version' ) !== WPRP_VERSION )
		update_option( 'wprp_plugin_version', WPRP_VERSION );

}

function _wprp_upgrade_core()  {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
	include_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
	include_once ( ABSPATH . 'wp-includes/update.php' );

	// check for filesystem access
	if ( ! _wpr_check_filesystem_access() )
		return array( 'status' => 'error', 'error' => __( 'The filesystem is not writable with the supplied credentials', 'wpremote' ) );

	// force refresh
	wp_version_check();

	$updates = get_core_updates();

	if ( is_wp_error( $updates ) || ! $updates )
		return new WP_Error( 'no-update-available' );

	$update = reset( $updates );

	if ( ! $update )
		return new WP_Error( 'no-update-available' );

	$skin = new WPRP_Core_Upgrader_Skin();

	$upgrader = new Core_Upgrader( $skin );
	$result = $upgrader->upgrade($update);

	if ( is_wp_error( $result ) )
		return $result;

	global $wp_current_db_version, $wp_db_version;

	// we have to include version.php so $wp_db_version
	// will take the version of the updated version of wordpress
	require( ABSPATH . WPINC . '/version.php' );

	wp_upgrade();

	return true;
}

function _wpr_check_filesystem_access() {

	ob_start();
	$success = request_filesystem_credentials( '' );
	ob_end_clean();

	return (bool) $success;
}

function _wpr_set_filesystem_credentials( $credentials ) {

	if ( empty( $_GET['filesystem_details'] ) )
		return $credentials;

	$_credentials = array(
		'username' => $_GET['filesystem_details']['credentials']['username'],
		'password' => $_GET['filesystem_details']['credentials']['password'],
		'hostname' => $_GET['filesystem_details']['credentials']['hostname'],
		'connection_type' => $_GET['filesystem_details']['method']
	);

	// check whether the credentials can be used
	if ( ! WP_Filesystem( $_credentials ) ) {
		return $credentials;
	}

	return $_credentials;
}
add_filter( 'request_filesystem_credentials', '_wpr_set_filesystem_credentials' );

/**
 *
 */
function wprp_translations_init() {

	if ( is_admin() ) {

		/** Set unique textdomain string */
		$wprp_textdomain = 'wpremote';

		/** The 'plugin_locale' filter is also used by default in load_plugin_textdomain() */
		$plugin_locale = apply_filters( 'plugin_locale', get_locale(), $wprp_textdomain );

		/** Set filter for WordPress languages directory */
		$wprp_wp_lang_dir = apply_filters(
			'wprp_filter_wp_lang_dir',
				trailingslashit( WP_LANG_DIR ) . trailingslashit( 'wp-remote' ) . $wprp_textdomain . '-' . $plugin_locale . '.mo'
		);

		/** Translations: First, look in WordPress' "languages" folder = custom & update-secure! */
		load_textdomain( $wprp_textdomain, $wprp_wp_lang_dir );

		/** Translations: Secondly, look in plugin's "languages" folder = default */
		load_plugin_textdomain( $wprp_textdomain, FALSE, WPR_LANG_DIR );
	}
}
add_action( 'plugins_loaded', 'wprp_translations_init' );