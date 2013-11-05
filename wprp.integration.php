<?php
/**
 * Integration compatibility with hosts, plugins, and themes
 */

/**
 * Get the likely web host for this site.
 */
function _wprp_integration_get_web_host() {

	// WP Engine
	if ( defined( 'IS_WPE' ) && IS_WPE )
		return 'wpengine';

	return 'unknown';
}