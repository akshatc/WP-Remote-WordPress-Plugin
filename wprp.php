<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://wpremote.com/
 * @since             3.0.0
 * @package           WPRP
 *
 * @wordpress-plugin
 * Plugin Name:       WP Remote
 * Plugin URI:        https://github.com/MyWorkAus/WP-Remote-WordPress-Plugin
 * Description:       Update and backup manager
 * Version:           3.0.0
 * Author:            MyWork
 * Author URI:        https://mywork.com.au/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wprp
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 * Use SemVer - https://semver.org
 * Given a version number MAJOR.MINOR.PATCH, increment the:
 * - MAJOR version when you make incompatible API changes,
 * - MINOR version when you add functionality in a backwards-compatible manner, and
 * - PATCH version when you make backwards-compatible bug fixes.
 */
define( 'WPRP_VERSION', '3.0.0' );

/**
 * Autoload classes
 */
require_once 'vendor/autoload.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wprp-activator.php
 */
function activate_wprp() {
	WPRP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wprp-deactivator.php
 */
function deactivate_wprp() {
	WPRP_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wprp' );
register_deactivation_hook( __FILE__, 'deactivate_wprp' );


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    3.0.0
 */
function run_wprp() {

	$plugin = new WPRP();
	$plugin->run();

}
run_wprp();

/**
 * Handle Errors
 *
 * @param $code
 * @param $error
 */
function wprp_handle_errors( $code, $error = '')
{
    $errors = ['error' =>
        [
            'code' => $code,
            'msg' => $error
        ]
    ];
    echo json_encode( $errors );
    exit;
}

function wprp_json_success()
{
    echo json_encode([
        'success' => true
    ]);
    exit;
}