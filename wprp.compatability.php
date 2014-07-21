<?php
/**
 * Function which takes active plugins and foreaches them though our list of security plugins
 * @return array
 */
function wprp_get_incompatible_plugins() {

	// Plugins to check for.
	$security_plugins = array(
		__( 'Wordfence Security', 'wpremote' ),
		__( 'iThemes Security', 'wpremote' ),
		__( 'Wordpress Firewall 2', 'wpremote' )
	);

	$active_plugins = get_option( 'active_plugins', array() );
	$dismissed_plugins = get_option( 'dismissed-plugins', array() );

	$plugin_matches = array();

	// foreach through activated plugins and split the string to have one name to check results against.
	foreach ( $active_plugins as $active_plugin ) {

		$plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . $active_plugin );

		if ( in_array( $plugin['Name'], $security_plugins ) && ! in_array( $active_plugin, $dismissed_plugins ) )
			$plugin_matches[$active_plugin] = $plugin['Name'];

	}

	return $plugin_matches;
}

/**
 * foreach through array of matched plugins and for each print the notice.
 */
function wprp_security_admin_notice() {

	if ( ! current_user_can( 'install_plugins' ) )
		return;

	foreach ( wprp_get_incompatible_plugins() as $plugin_path => $plugin_name ) :

		?>

		<div class="error">

			<a class="close-button button" style="float: right; margin-top: 4px; color: inherit; text-decoration: none; " href="<?php echo add_query_arg( 'wpr_dismiss_plugin_warning', $plugin_path ); ?>"><?php _e( 'Don\'t show again','wpremote' ); ?></a>

			<p>

				<?php _e( 'The plugin', 'wpremote' );?> <strong><?php echo esc_attr( $plugin_name ); ?></strong> <?php _e( 'may cause issues with WP Remote.', 'wpremote' ); ?>

				<a href="https://wpremote.com/support-center/troubleshooting/incorrect-version-numbers-false-positives/" target="_blank"> <?php _e( 'Click here for instructions on how to resolve this issue', 'wpremote' ); ?> </a>

			</p>

		</div>

	<?php endforeach;

}

add_action( 'admin_notices', 'wprp_security_admin_notice' );

/**
 * Function which checks to see if the plugin was dismissed.
 */
function wprp_dismissed_plugin_notice_check() {

	if ( current_user_can( 'install_plugins' ) && ! empty( $_GET['wpr_dismiss_plugin_warning'] ) ) {

		$dismissed = get_option( 'dismissed-plugins', array() );
		$dismissed[] = sanitize_text_field( $_GET['wpr_dismiss_plugin_warning'] );

		update_option( 'dismissed-plugins', $dismissed );

		wp_safe_redirect( remove_query_arg( 'wpr_dismiss_plugin_warning' ) );
		exit;

	}
}
add_action( 'admin_init', 'wprp_dismissed_plugin_notice_check' );