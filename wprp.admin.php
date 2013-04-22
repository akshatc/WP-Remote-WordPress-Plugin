<?php

/**
 * Register the wpr_api_key settings
 *
 * @return null
 */
 
function wprp_admin_page() {
  include( 'wprp.admin.page.php' );
}

function wprp_admin_actions() {
	register_setting( 'wpr-settings', 'wpr_api_key' );
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

		<form method="post" action="options.php">

			<p>

				<strong>WP Remote is almost ready</strong>, <label style="vertical-align: baseline;" for="wpr_api_key">enter your API Key to continue</label>

				<input type="text" style="margin-left: 5px; margin-right: 5px; " class="code regular-text" id="wpr_api_key" name="wpr_api_key" />

				<input type="submit" value="Save API Key" class="button-primary" />

			</p>

			<style>#message { display : none; }</style>

			<?php settings_fields( 'wpr-settings' );

			// Output any sections defined for page sl-settings
			do_settings_sections( 'wpr-settings' ); ?>

		</form>

	</div>


<?php }

if ( ! get_option( 'wpr_api_key' ) && ! get_option( 'wpr_api_keychain' ) )
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

/**
 * Delete the API key on activate and deactivate
 *
 * @return null
 */
function wprp_deactivate() {
	delete_option( 'wpr_api_key' );
}
// Plugin activation and deactivation
add_action( 'activate_' . WPRP_PLUGIN_SLUG . '/plugin.php', 'wprp_deactivate' );
add_action( 'deactivate_' . WPRP_PLUGIN_SLUG . '/plugin.php', 'wprp_deactivate' );