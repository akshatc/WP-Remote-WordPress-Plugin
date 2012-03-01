<?php

/*
Plugin Name: WP Remote
Description: Manage your WordPress site with <a href="https://wpremote.com/">WP Remote</a>. Deactivate to clear your API Key.
Version: 2.1.0
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

define( 'WPRP_PLUGIN_SLUG', basename( dirname( __FILE__ ) ) );
define( 'WPRP_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . WPRP_PLUGIN_SLUG );
define( 'WPRP_PLUGIN_URL', WP_PLUGIN_URL . '/' . WPRP_PLUGIN_SLUG );

// Don't activate on anything less than PHP 5.2.4
if ( version_compare( phpversion(), '5.2.4', '<' ) ) {

	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	deactivate_plugins( WPRP_PLUGIN_SLUG . '/plugin.php' );

	if ( isset( $_GET['action'] ) && ( $_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' ) )
		die( __( 'WP Remote requires PHP version 5.2.4 or greater.', 'wpremote' ) );

}

require_once( WPRP_PLUGIN_PATH  .'/wprp.admin.php' );

// Backups require 3.1
if ( version_compare( get_bloginfo( 'version' ), '3.1', '>=' ) && ! class_exists( 'HM_Backup' ) )
	require( WPRP_PLUGIN_PATH . '/hm-backup/hm-backup.php' );

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

		function before() {

		}

		function after() {

		}

		function header() {

		}

		function footer() {

		}

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

		function before() {

		}

		function after() {

		}

		function header() {

		}

		function footer() {

		}

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

	require_once( WPRP_PLUGIN_PATH . '/wprp.backups.php' );
	require_once( WPRP_PLUGIN_PATH . '/wprp.plugins.php' );
	require_once( WPRP_PLUGIN_PATH . '/wprp.themes.php' );

	require_once( WPRP_PLUGIN_PATH . '/wprp.api.php' );

	exit;

}
add_action( 'init', 'wprp_catch_api_call', 1 );