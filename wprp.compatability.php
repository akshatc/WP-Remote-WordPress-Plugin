<?php 
/**
 * Function which takes active plugins and foreaches them though our list of security plugins
 * @return array
 */
function wprp_get_incompatible_plugins() {

	// Temporary array of plugins.
	// TODO: Handle this array. Hardcoded in the wprp? or should it ping the app?
	$security_plugin = array( 'better-wp-security', 'Bulletproof security', 'bulletproof-security', 'wordfence', 'wordpress-firewall-2' );

	$active = get_option( 'active_plugins', array() );

	$plugin_matches = array();

	// foreach through activated plugins and split the string to have one name to check results against.
	foreach ( $active as $key => $single_active ) {
		
		$plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . $single_active );

		foreach ( $security_plugin as $plugin_path ) {
			
			if ( strpos( $single_active, $plugin_path ) !== false || stripos( $plugin['Name'], $plugin_path ) !== false ) {

				if ( ! in_array( $single_active, get_option( 'dismissed-plugins', array() ) ) )
					$plugin_matches[$single_active] = $plugin['Name'];

			}
		}
	}

	return $plugin_matches;
}

/**
 * foreach through array of matched plugins and for each print the notice.  
 */
function wprp_security_admin_notice() { 

	foreach ( wprp_get_incompatible_plugins() as $plugin_path => $plugin_name ) { ?>

		<div class="error">
			
			<div class="close-button" style="float: right; margin-top: 15px; margin-right: 8px;"><a href="?wpr_dismiss_plugin_warning=<?php echo $plugin_path; ?>">Don't show again</a></div>

			<h4> You are running <a href="https://wpremote.com/faq/#<?php echo $matched_plugins; ?>"><em><?php echo $plugin_name; ?></em></a> plugin, this may cause issues with WP Remote. 
			
			<a href="https://wpremote.com/faq/#<?php echo $plugin_name; ?>" alt="WPRemote FAQ"> Click here for instructions on how to solve this issue </a></h4>

		</div>

<?php } }

add_action( 'admin_notices', 'wprp_security_admin_notice' );

/**
 * Function which checks to see if the plugin was dismissed.
 */
function wprp_dismissed_plugin_notice_check() {
	
	if ( !empty( $_GET['wpr_dismiss_plugin_warning'] ) ) {

		$dismissed = get_option( 'dismissed-plugins', array() );

		$dismissed[] = $_GET['wpr_dismiss_plugin_warning'];

		update_option( 'dismissed-plugins', $dismissed );

	}
}

add_action( 'init', 'wprp_dismissed_plugin_notice_check' );