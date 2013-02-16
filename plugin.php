<?php

/*
Plugin Name: WP Remote
Description: Manage your WordPress site with <a href="https://wpremote.com/">WP Remote</a>. <strong>Deactivate to clear your API Key.</strong>
Version: 2.4.13
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

if ( ! defined( 'WPR_API_URL' ) )
	define( 'WPR_API_URL', 'https://wpremote.com/api/json/' );

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

	// deactivate backupwordpress
	if ( defined( 'HMBKP_PLUGIN_PATH' ) ) {

		$plugin_file = dirname( plugin_dir_path( HMBKP_PLUGIN_PATH ) ) . 'plugin.php';

		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins( array( 'backupwordpress/plugin.php' ), true );

		function wprp_backupwordpress_deactivated_notice() {

			echo '<div class="updated fade"><p><strong>The BackUpWordPress Plugin has been de-activated</strong> The WP Remote Plugin includes BackUpWordPress.</p></div>';

		}
		add_action( 'admin_notices', 'wprp_backupwordpress_deactivated_notice' );

	} else {

		define( 'HMBKP_PLUGIN_PATH', trailingslashit( WPRP_PLUGIN_PATH ) . 'backupwordpress' );
		define( 'HMBKP_PLUGIN_URL', trailingslashit( plugins_url( WPRP_PLUGIN_SLUG ) ) . 'backupwordpress' );

		require( WPRP_PLUGIN_PATH . '/backupwordpress/plugin.php' );

		// Set the correct path for the BackUpWordPress language files.
		load_plugin_textdomain( 'hmbkp', false, '/wpremote/' . HMBKP_PLUGIN_SLUG . '/languages/' );

		require_once( WPRP_PLUGIN_PATH . '/wprp.backups.php' );

	}

	// unhook default schedules from being created
	remove_action( 'admin_init', 'hmbkp_setup_default_schedules' );
	remove_filter( 'all_plugins', 'hmbkp_plugin_row', 10 );
	remove_filter( 'plugin_action_links', 'hmbkp_plugin_action_link', 10, 2 );
	remove_action( 'admin_head', 'hmbkp_admin_notices' );


}

// Don't include when doing a core update
if ( empty( $_GET['action'] ) || $_GET['action'] != 'do-core-upgrade' ) :

	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	class WPRP_Plugin_Upgrader_Skin extends Plugin_Installer_Skin {

		var $feedback;
		var $error;

		function error( $error ) {
			$this->error = $error;
		}

		function feedback( $feedback ) {
			$this->feedback = $feedback;
		}

		function before() { }

		function after() { }

		function header() { }

		function footer() { }

	}

	class WPRP_Theme_Upgrader_Skin extends Theme_Installer_Skin {

		var $feedback;
		var $error;

		function error( $error ) {
			$this->error = $error;
		}

		function feedback( $feedback ) {
			$this->feedback = $feedback;
		}

		function before() { }

		function after() { }

		function header() { }

		function footer() { }

	}

	class WPRP_Core_Upgrader_Skin extends WP_Upgrader_Skin {

		var $feedback;
		var $error;

		function error( $error ) {
			$this->error = $error;
		}

		function feedback( $feedback ) {
			$this->feedback = $feedback;
		}

		function before() { }

		function after() { }

		function header() { }

		function footer() { }

	}

endif;

/**
 * Catch the API calls and load the API
 *
 * @return null
 */
function wprp_catch_api_call() {

	if ( empty( $_GET['wpr_api_key'] ) || ! urldecode( $_GET['wpr_api_key'] ) || ! isset( $_GET['actions'] ) )
		return;

	require_once( WPRP_PLUGIN_PATH . '/wprp.plugins.php' );
	require_once( WPRP_PLUGIN_PATH . '/wprp.themes.php' );

	require_once( WPRP_PLUGIN_PATH . '/wprp.api.php' );

	exit;

}
add_action( 'init', 'wprp_catch_api_call', 1 );

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

	if ( file_exists( $old_wpremote_dir ) && function_exists( 'hmbkp_rmdirtree' ) )
		hmbkp_rmdirtree( $old_wpremote_dir );

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
		return array( 'status' => 'error', 'error' => 'The filesystem is not writable with the supplied credentials' );

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

// we need the calculate filesize to work on no priv too
add_action( 'wp_ajax_nopriv_wprp_calculate_backup_size', 'wprp_ajax_calculate_backup_size' );

function wprp_ajax_calculate_backup_size() {
	require_once( WPRP_PLUGIN_PATH . '/wprp.backups.php' );
	WPRP_Backups::getInstance()->calculateEstimatedSize();
	exit;
}