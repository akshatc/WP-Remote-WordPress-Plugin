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

add_action( 'init', function() {
    if ( ! isset( $_GET['the_cron_test'] ) ) {
        return;
    }
    error_reporting( 1 );

    $a = wp_get_schedule(WPRP_Schedule::$hook);
    $b = get_option(WPRP_Schedule::$hook);
    var_dump($a);
    var_dump($b);
//    wp_schedule_event( time(), 'every_minute', 'wprp_backup_test' );
//    wp_clear_scheduled_hook( 'wprp_backup_test' );

//    do_action( 'wprp_backup_test' );
    die();
} );

/**
 * Use * for origin
 */
add_action( 'rest_api_init', function() {

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && strpos($_SERVER['REQUEST_URI'], 'json/' . WPRP_Api_Endpoints::$namespace)) {
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
        header( 'Access-Control-Allow-Credentials: true' );
        die(200);
    }
}, 15 );

/*add_filter('rest_post_dispatch', function (\WP_REST_Response $result) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $result->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN', true);
    }
    return $result;
});*/

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