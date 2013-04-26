<?php

/**
 * Register the wpr_api_keychain settings
 *
 * @return null
 */
 
function wprp_admin_page() {
  include( 'wprp.admin.page.php' );
}

function wprp_admin_actions() {
  register_setting( 'wpr-settings', 'wpr_api_keychain' );
  add_options_page( 'WP Remote Settings', 'WP Remote', 'activate_plugins', 'wprp_admin', 'wprp_admin_page');
}

add_action( 'admin_menu', 'wprp_admin_actions' );


/**
 * Add API Key form
 *
 * Only shown if no API Key
 *
 * @return null
 */
function wprp_add_api_key_admin_notice() { ?>

	<div id="wprp-message" class="updated">
    <p><strong>WP Remote is almost ready</strong>, go to the <a href="options-general.php?page=wprp_admin">WP Remote Settings Page</a> to enter your API Key(s)</p>
	</div>

<?php }

if ( ! get_option( 'wpr_api_keychain' ) )
	add_action( 'admin_notices', 'wprp_add_api_key_admin_notice' );

/**
 * Success message for a newly added API Key
 *
 * @return null
 */
function wprp_api_key_added_admin_notice() {

	if ( function_exists( 'get_current_screen' ) && get_current_screen()->base != 'plugins' || empty( $_GET['settings-updated'] ) || ! get_option( 'wpr_api_key' ) )
		return; ?>

	<div id="wprp-message" class="updated">
		<p><strong>WP Remote API Key successfully added</strong>, close this window to go back to <a href="https://wpremote.com/app/">WP Remote</a>.</p>
	</div>

<?php }
add_action( 'admin_notices', 'wprp_api_key_added_admin_notice' );
