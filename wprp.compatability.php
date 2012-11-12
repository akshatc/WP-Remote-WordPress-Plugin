<?php
/**
 * Function which takes active plugins and foreaches them though our list of security plugins
 * @return array
 */
function wprp_get_incompatible_plugins() {

	// Plugins to check for.
	$security_plugins = array(
		'BulletProof Security',
		'Wordfence Security',
		'Better WP Security',
		'Wordpress Firewall 2'
	);

	$active_plugins = get_option( 'active_plugins', array() );
	$dismissed_plugins = get_option( 'dismissed-plugins', array() );

	//update_option( 'dismissed-plugins', array() );

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

	foreach ( wprp_get_incompatible_plugins() as $plugin_path => $plugin_name ) :

		?>

		<div class="error">

			<a class="close-button button" style="float: right; margin-top: 4px; color: inherit; text-decoration: none; " href="<?php echo add_query_arg( 'wpr_dismiss_plugin_warning', $plugin_path ); ?>">Don't show again</a>

			<p>

				The plugin <strong><?php echo esc_attr( $plugin_name ); ?></strong> may cause issues with WP Remote.

				<a href="https://wpremote.com/faq/#<?php echo esc_attr( $plugin_name ); ?>" alt="WPRemote FAQ"> Click here for instructions on how to resolve this issue </a>

			</p>

		</div>

	<?php endforeach;

}

add_action( 'admin_notices', 'wprp_security_admin_notice' );

/**
 * Function which checks to see if the plugin was dismissed.
 */
function wprp_dismissed_plugin_notice_check() {

	if ( ! empty( $_GET['wpr_dismiss_plugin_warning'] ) ) {

		$dismissed = get_option( 'dismissed-plugins', array() );
		$dismissed[] = $_GET['wpr_dismiss_plugin_warning'];

		update_option( 'dismissed-plugins', $dismissed );

		wp_redirect( remove_query_arg( 'wpr_dismiss_plugin_warning' ) );
		exit;

	}
}
add_action( 'init', 'wprp_dismissed_plugin_notice_check' );