<?php

/*
Plugin Name: BackUpWordPress
Plugin URI: http://humanmade.co.uk/
Description: Simple automated backups of your WordPress powered website. Once activated you'll find me under <strong>Tools &rarr; Backups</strong>.
Author: Human Made Limited
Version: 1.3
Author URI: http://humanmade.co.uk/
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

define( 'WPRP_HMBKP_PLUGIN_SLUG', end( explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) ) ) );
define( 'WPRP_HMBKP_PLUGIN_PATH', WPRP_PLUGIN_PATH . '/' . WPRP_HMBKP_PLUGIN_SLUG );
define( 'WPRP_HMBKP_PLUGIN_URL', WPRP_PLUGIN_URL . '/' . WPRP_HMBKP_PLUGIN_SLUG );
define( 'WPRP_HMBKP_REQUIRED_WP_VERSION', '3.1' );

// Don't activate on old versions of WordPress
if ( version_compare( get_bloginfo('version'), WPRP_HMBKP_REQUIRED_WP_VERSION, '<' ) ) {

	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	deactivate_plugins( ABSPATH . 'wp-content/plugins/' . WPRP_HMBKP_PLUGIN_SLUG . '/plugin.php' );

	if ( isset( $_GET['action'] ) && ( $_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' ) )
		die( sprintf( __( 'BackUpWordPress requires WordPress version %s.', 'wpr_hmbkp' ), WPRP_HMBKP_REQUIRED_WP_VERSION ) );

}

// Load the core functions
require_once( WPRP_HMBKP_PLUGIN_PATH . '/functions/core.functions.php' );
require_once( WPRP_HMBKP_PLUGIN_PATH . '/functions/backup.functions.php' );
require_once( WPRP_HMBKP_PLUGIN_PATH . '/functions/backup.mysql.functions.php' );
require_once( WPRP_HMBKP_PLUGIN_PATH . '/functions/backup.files.functions.php' );
require_once( WPRP_HMBKP_PLUGIN_PATH . '/functions/backup.mysql.fallback.functions.php' );
require_once( WPRP_HMBKP_PLUGIN_PATH . '/functions/backup.files.fallback.functions.php' );

// Add more cron schedules
add_filter( 'cron_schedules', 'wpr_hmbkp_more_reccurences' );

// Cron hook for backups
add_action( 'wpr_hmbkp_schedule_backup_hook', 'wpr_hmbkp_do_backup' );
add_action( 'wpr_hmbkp_schedule_single_backup_hook', 'wpr_hmbkp_do_backup' );