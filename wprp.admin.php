<?php

function wprp_setup_admin() {
	wprp_register_settings();
	add_options_page( 'WP Remote Settings', 'WP Remote', 'manage_options', 'wp-remote-options', 'wprp_options_page' );
}
add_action( 'admin_menu', 'wprp_setup_admin' );

function wprp_register_settings() {
	register_setting( 'wpr-settings', 'wpr_api_key' );
}

function wprp_options_page() { ?>

	<?php require_once( WPRP_PLUGIN_PATH . '/wprp.backups.php' ); ?>

	<div class="wrap">

		<h2>WP Remote Settings</h2>

		<form method="post" action="options.php">
			<table class="form-table">

				<tr valign="top">

					<th scope="row"><strong>API Key</strong></th>

					<td>
						<input type="text" name="wpr_api_key" class="regular-text" value="<?php echo get_option( 'wpr_api_key', '' ); ?>" /><br />
					</td>

				</tr>

			</table>

			<input type="hidden" name="action" value="update" />

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>

			<?php settings_fields( 'wpr-settings' );

			// Output any sections defined for page sl-settings
			do_settings_sections('wpr-settings'); ?>

		</form>

	</div>

<?php }