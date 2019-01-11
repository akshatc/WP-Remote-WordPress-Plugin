<?php

/**
 * Return an array of installed themes
 *
 * @return array
 */
function _wprp_get_themes() {

	require_once( ABSPATH . '/wp-admin/includes/theme.php' );

	// Get all themes
	if ( function_exists( 'wp_get_themes' ) )
		$themes = wp_get_themes();
	else
		$themes = get_themes();

	// Get the active theme
	$active  = get_option( 'current_theme' );

	// Delete the transient so wp_update_themes can get fresh data
	if ( function_exists( 'get_site_transient' ) )
		delete_site_transient( 'update_themes' );

	else
		delete_transient( 'update_themes' );

	// Force a theme update check
	wp_update_themes();

	// Different versions of wp store the updates in different places
	// TODO can we depreciate
	if ( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_themes' ) )
		$current = $transient;

	elseif ( $transient = get_transient( 'update_themes' ) )
		$current = $transient;

	else
		$current = get_option( 'update_themes' );

	foreach ( (array) $themes as $key => $theme ) {

		// WordPress 3.4+
		if ( is_object( $theme ) && is_a( $theme, 'WP_Theme' ) ) {

			/* @var $theme WP_Theme */
			$new_version = isset( $current->response[$theme->get_stylesheet()] ) ? $current->response[$theme->get_stylesheet()]['new_version'] : null;

			$theme_array = array(
				'Name'           => $theme->get( 'Name' ),
				'active'         => $active == $theme->get( 'Name' ),
				'Template'       => $theme->get_template(),
				'Stylesheet'     => $theme->get_stylesheet(),
				'Screenshot'     => $theme->get_screenshot(),
				'AuthorURI'      => $theme->get( 'AuthorURI' ),
				'Author'         => $theme->get( 'Author' ),
				'latest_version' => $new_version ? $new_version : $theme->get( 'Version' ),
				'Version'        => $theme->get( 'Version' ),
				'ThemeURI'       => $theme->get( 'ThemeURI' )
			);

			$themes[$key] = $theme_array;

		} else {

			$new_version = isset( $current->response[$theme['Stylesheet']] ) ? $current->response[$theme['Stylesheet']]['new_version'] : null;

			if ( $active == $theme['Name'] )
				$themes[$key]['active'] = true;

			else
				$themes[$key]['active'] = false;

			if ( $new_version ) {

				$themes[$key]['latest_version'] = $new_version;
				$themes[$key]['latest_package'] = $current->response[$theme['Template']]['package'];

			} else {

				$themes[$key]['latest_version'] = $theme['Version'];

			}
		}
	}

	return $themes;
}

/**
 * Install a theme
 *
 * @param  mixed $theme
 * @param array $args
 * @return array|bool
 */
function _wprp_install_theme( $theme, $args = array() ) {

	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return new WP_Error( 'disallow-file-mods', __( "File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote' ) );

	if ( wp_get_theme( $theme )->exists() )
		return new WP_Error( 'theme-installed', __( 'Theme is already installed.' ) );

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';
	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-theme-upgrader-skin.php';

	// Access the themes_api() helper function
	include_once ABSPATH . 'wp-admin/includes/theme-install.php';
	$api_args = array(
		'slug' => $theme,
		'fields' => array( 'sections' => false )
		);
	$api = themes_api( 'theme_information', $api_args );

	if ( is_wp_error( $api ) )
		return $api;

	$skin = new WPRP_Theme_Upgrader_Skin();
	$upgrader = new Theme_Upgrader( $skin );

	// The best way to get a download link for a specific version :(
	// Fortunately, we can depend on a relatively consistent naming pattern
	if ( ! empty( $args['version'] ) && 'stable' != $args['version'] )
		$api->download_link = str_replace( $api->version . '.zip', $args['version'] . '.zip', $api->download_link );

	$result = $upgrader->install( $api->download_link );
	if ( is_wp_error( $result ) )
		return $result;
	else if ( ! $result )
		return new WP_Error( 'unknown-install-error', __( 'Unknown error installing theme.', 'wpremote' ) );

	return array( 'status' => 'success' );
}

/**
 * Activate a theme
 *
 * @param mixed $theme
 * @return array
 */
function _wprp_activate_theme( $theme ) {

	if ( ! wp_get_theme( $theme )->exists() )
		return new WP_Error( 'theme-not-installed', __( 'Theme is not installed.', 'wpremote' ) );

	switch_theme( $theme );
	return array( 'status' => 'success' );
}

/**
 * Update a theme
 *
 * @param mixed $theme
 * @return array|WP_Error
 */
function _wprp_update_theme( $theme ) {

	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return new WP_Error( 'disallow-file-mods', __( "File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote' ) );

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
	require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
	require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-theme-upgrader-skin.php';
    require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-automatic-upgrader-skin.php';

    // check for filesystem access
	if ( ! _wpr_check_filesystem_access() )
		return new WP_Error( 'filesystem-not-writable', __( 'The filesystem is not writable with the supplied credentials', 'wpremote' ) );

	$skin = new WPRP_Theme_Upgrader_Skin();
	$upgrader = new Theme_Upgrader( $skin );

    remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade( $theme );
	$data = ob_get_contents();
	ob_clean();

    // Run the language upgrader
    try {
        ob_start();
        $skin2 = new WPRP_Automatic_Upgrader_Skin();
        $lang_upgrader = new Language_Pack_Upgrader($skin2);
        $result2 = $lang_upgrader->upgrade($upgrader);
        if ($data2 = ob_get_contents()) {
            ob_end_clean();
        }
    } catch (\Exception $e) {} // Fail silently

	if ( ! empty( $skin->error ) ){
        return new WP_Error( 'theme-upgrader-skin', $upgrader->strings[$skin->error] );
    } elseif ( is_wp_error( $result ) ) {
        return $result;
    } elseif ( ( ! $result && ! is_null( $result ) ) || $data ) {
        return new WP_Error('theme-update', __('Unknown error updating theme.', 'wpremote'));
    }

	return array( 'status' => 'success' );

}

/**
 * Delete a theme.
 *
 * @param mixed $theme
 * @return array
 */
function _wprp_delete_theme( $theme ) {
	global $wp_filesystem;

	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return new WP_Error( 'disallow-file-mods', __( "File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote' ) );

	if ( ! wp_get_theme( $theme )->exists() )
		return new WP_Error( 'theme-missing', __( 'Theme is not installed.', 'wpremote' ) );

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';

	if ( ! _wpr_check_filesystem_access() || ! WP_Filesystem() )
		return new WP_Error( 'filesystem-not-writable', __( 'The filesystem is not writable with the supplied credentials', 'wpremote' ) );

	$themes_dir = $wp_filesystem->wp_themes_dir();
	if ( empty( $themes_dir ) )
		return new WP_Error( 'theme-dir-missing', __( 'Unable to locate WordPress theme directory', 'wpremote' ) );

	$themes_dir = trailingslashit( $themes_dir );
	$theme_dir = trailingslashit( $themes_dir . $theme );
	$deleted = $wp_filesystem->delete( $theme_dir, true );

	if ( ! $deleted )
		return new WP_Error( 'theme-delete', sprintf( __( 'Could not fully delete the theme: %s.', 'wpremote' ), $theme ) );

	// Force refresh of theme update information
	delete_site_transient('update_themes');

	return array( 'status' => 'success' );
}
