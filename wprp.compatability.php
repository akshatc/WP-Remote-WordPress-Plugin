<?php 
/**
 * Function which takes active plugins and foreaches them though our list of security plugins
 */
function wprp_compatability_check() {

	// Temporary array of plugins.
	// TODO: Handle this array. Hardcoded in the wprp? or should it ping the app?
	$security_plugin = array( 'better-wp-security', 'bulletproof-security', 'wordfence', 'wordpress-firewall-2' );

	$active = get_option( 'active_plugins', array() );

	$plugin_matches = array();

	// foreach through activated plugins and split the string to have one name to check results against.
	
	foreach ( $active as $single_active ) {

		foreach ( $security_plugin as $plugin_name ) {

			$regex = '"' . $plugin_name . '"';

			if ( preg_match( $regex, $single_active, $match ) ) {

				$plugin_matches[] = $match[0];

			}
		}
	}

	return $plugin_matches;

}

/**
 * foreach through array of matched plugins and for each print the notice.
 */

function wprp_security_admin_notice() { 

	foreach ( wprp_compatability_check() as $matched_plugins ) { ?>
	
		<div class="error">

			<h4> You are running <a href="https://wpremote.com/faq/#<?php echo $matched_plugins; ?>"><em><?php echo $matched_plugins; ?></em></a> plugin, this may cause issues with WP Remote. 
			<a href="https://wpremote.com/faq/#<?php echo $matched_plugins; ?>" alt="WPRemote FAQ"> Click here for instructions on how to solve this issue </a></h4>

		</div>

<?php } }

add_action( 'admin_notices', 'wprp_security_admin_notice' );