<?php

/**
 * Register the wpr_api_key settings
 *
 * @return null
 */
function wprp_setup_admin() {
	register_setting( 'wpr-settings', 'wpr_api_key' );
}

add_action( 'admin_menu', 'wprp_setup_admin' );

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

				<strong><?php _e( 'WP Remote is almost ready', 'wpremote' ); ?></strong>, <label style="vertical-align: baseline;" for="wpr_api_key"><?php _e( 'enter your API key to continue', 'wpremote' ); ?></label>

				<input type="text" style="margin-left: 5px; margin-right: 5px; " class="code regular-text" id="wpr_api_key" name="wpr_api_key" />

				<input type="submit" value="<?php _e( 'Save API Key','wpremote' ); ?>" class="button-primary" />

			</p>

			<p>

				<strong><?php _e( 'Don\'t have a WP Remote account yet?','wpremote' ); ?></strong> <a href="<?php echo esc_url( wprp_get_wpr_url( '/register/' ) ); ?>" target="_blank"><?php _e( 'Sign up','wpremote' ); ?></a>, <?php _e( 'register your site, and report back once you\'ve grabbed your API key.','wpremote' ); ?>

			</p>

			<style>#message { display : none; }</style>

			<?php settings_fields( 'wpr-settings' );

			// Output any sections defined for page sl-settings
			do_settings_sections( 'wpr-settings' ); ?>

		</form>

	</div>


<?php }

if ( ! wprp_get_api_keys() )
	add_action( 'admin_notices', 'wprp_add_api_key_admin_notice' );

/**
 * Success message for a newly added API Key
 *
 * @return null
 */
function wprp_api_key_added_admin_notice() {

	if ( function_exists( 'get_current_screen' ) && get_current_screen()->base != 'plugins' || empty( $_GET['settings-updated'] ) || ! wprp_get_api_keys() )
		return; ?>

	<div id="wprp-message" class="updated">
		<p><strong><?php _e( 'WP Remote API Key successfully added' ); ?></strong>, close this window to go back to <a href="<?php echo esc_url( wprp_get_wpr_url( '/app/' ) ); ?>"><?php _e( 'WP Remote','wpremote' ); ?></a>.</p>
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