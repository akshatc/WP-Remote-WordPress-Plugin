<?php

/*
Plugin Name: WP Remote
Description: Extends the functionality of <a href="http://www.wpremote.com">WP Remote</a>.
Version: 0.6.6
Author: Human Made Limited
Author URI: http://humanmade.co.uk/
*/

define( 'WPRP_PLUGIN_SLUG', end( explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) ) ) );
define( 'WPRP_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . WPRP_PLUGIN_SLUG );
define( 'WPRP_PLUGIN_URL', WP_PLUGIN_URL . '/' . WPRP_PLUGIN_SLUG );

require_once( 'wpr.admin.php' );
require_once( WPRP_PLUGIN_PATH . '/wprp.backups.php' );

//Backups require 3.1
if( version_compare( get_bloginfo('version'), '3.1', '>=' ) )
	require( WPRP_PLUGIN_PATH . '/wprhmbkp-backup/plugin.php' );

// Don't include when doing a core update
if ( empty( $_GET['action'] ) || $_GET['action'] != 'do-core-upgrade' ) :

	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	
	class WPRP_Upgrader_Skin extends Plugin_Installer_Skin {
	
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

function wpr_catch_api_call() {

	if ( empty( $_GET['wpr_api_key'] ) || !urldecode( $_GET['wpr_api_key'] ) || !isset( $_GET['actions'] ) ) {
		return;
	}
	
	require_once( 'wprp.api.php' );
	
	exit;

}
add_action( 'init', 'wpr_catch_api_call', 1 );