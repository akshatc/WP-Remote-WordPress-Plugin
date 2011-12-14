<?php
/**
 * Displays a row in the manage backups table
 *
 * @param string $file
 */
function wpr_hmbkp_get_backup_row( $file ) {

	$encode = base64_encode( $file ); ?>

	<tr class="wpr_hmbkp_manage_backups_row<?php if ( file_exists( wpr_hmbkp_path() . '/.backup_complete' ) ) : ?> completed<?php unlink( wpr_hmbkp_path() . '/.backup_complete' ); endif; ?>">

		<th scope="row">
			<?php echo date( get_option('date_format'), filemtime( $file ) ) . ' ' . date( 'H:i', filemtime($file ) ); ?>
		</th>

		<td>
			<?php echo wpr_hmbkp_size_readable( filesize( $file ) ); ?>
		</td>

		<td>

			<a href="tools.php?page=<?php echo WPRP_HMBKP_PLUGIN_SLUG; ?>&amp;wpr_hmbkp_download=<?php echo $encode; ?>"><?php _e( 'Download', 'wpr_hmbkp' ); ?></a> |
			<a href="tools.php?page=<?php echo WPRP_HMBKP_PLUGIN_SLUG; ?>&amp;wpr_hmbkp_delete=<?php echo $encode ?>" class="delete"><?php _e( 'Delete', 'wpr_hmbkp' ); ?></a>

		</td>

	</tr>

<?php }

/**
 * Displays admin notices for various error / warning 
 * conditions
 * 
 * @return void
 */
function wpr_hmbkp_admin_notices() {

	// If the backups directory doesn't exist and can't be automatically created
	if ( !is_dir( wpr_hmbkp_path() ) ) :

	    function wpr_hmbkp_path_exists_warning() {
		    $php_user = exec( 'whoami' );
			$php_group = reset( explode( ' ', exec( 'groups' ) ) );
	    	echo '<div id="wpr_hmbkp-warning" class="updated fade"><p><strong>' . __( 'BackUpWordPress is almost ready.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( 'The backups directory can\'t be created because your %s directory isn\'t writable, run %s or %s or create the folder yourself.', 'wpr_hmbkp' ), '<code>wp-content</code>', '<code>chown ' . $php_user . ':' . $php_group . ' ' . WP_CONTENT_DIR . '</code>', '<code>chmod 777 ' . WP_CONTENT_DIR . '</code>' ) . '</p></div>';
	    }
	    add_action( 'admin_notices', 'wpr_hmbkp_path_exists_warning' );

	endif;

	// If the backups directory exists but isn't writable
	if ( is_dir( wpr_hmbkp_path() ) && !is_writable( wpr_hmbkp_path() ) ) :

	    function wpr_hmbkp_writable_path_warning() {
			$php_user = exec( 'whoami' );
			$php_group = reset( explode( ' ', exec( 'groups' ) ) );
	    	echo '<div id="wpr_hmbkp-warning" class="updated fade"><p><strong>' . __( 'BackUpWordPress is almost ready.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( 'Your backups directory isn\'t writable. run %s or %s or set the permissions yourself.', 'wpr_hmbkp' ), '<code>chown -R ' . $php_user . ':' . $php_group . ' ' . wpr_hmbkp_path() . '</code>', '<code>chmod -R 777 ' . wpr_hmbkp_path() . '</code>' ) . '</p></div>';
	    }
	    add_action( 'admin_notices', 'wpr_hmbkp_writable_path_warning' );

	endif;

	// If safe mode is active
	if ( wpr_hmbkp_is_safe_mode_active() ) :

	    function wpr_hmbkp_safe_mode_warning() {
	    	echo '<div id="wpr_hmbkp-warning" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( ' %s is running in %s. Please contact your host and ask them to disable %s.', 'wpr_hmbkp' ), '<code>PHP</code>', '<a href="http://php.net/manual/en/features.safe-mode.php"><code>Safe Mode</code></a>', '<code>Safe Mode</code>' ) . '</p></div>';
	    }
	    add_action( 'admin_notices', 'wpr_hmbkp_safe_mode_warning' );

	endif;

	// If both WPRP_HMBKP_FILES_ONLY & WPRP_HMBKP_DATABASE_ONLY are defined at the same time
	if ( defined( 'WPRP_HMBKP_FILES_ONLY' ) && WPRP_HMBKP_FILES_ONLY && defined( 'WPRP_HMBKP_DATABASE_ONLY' ) && WPRP_HMBKP_DATABASE_ONLY ) :

	    function wpr_hmbkp_nothing_to_backup_warning() {
	    	echo '<div id="wpr_hmbkp-warning" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( 'You have both %s and %s defined so there isn\'t anything to back up.', 'wpr_hmbkp' ), '<code>WPRP_HMBKP_DATABASE_ONLY</code>', '<code>WPRP_HMBKP_FILES_ONLY</code>' ) . '</p></div>';
	    }
	    add_action( 'admin_notices', 'wpr_hmbkp_nothing_to_backup_warning' );

	endif;

	// If the email address is invalid
	if ( defined( 'WPRP_HMBKP_EMAIL' ) && !is_email( WPRP_HMBKP_EMAIL ) ) :

		function wpr_hmbkp_email_invalid_warning() {
			echo '<div id="wpr_hmbkp-email_invalid" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( '%s is not a valid email address.', 'wpr_hmbkp' ), '<code>' . WPRP_HMBKP_EMAIL . '</code>' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'wpr_hmbkp_email_invalid_warning' );

	endif;

	// If the email failed to send
	if ( defined( 'WPRP_HMBKP_EMAIL' ) && get_option( 'wpr_hmbkp_email_error' ) ) :

		function wpr_hmbkp_email_failed_warning() {
			echo '<div id="wpr_hmbkp-email_invalid" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . __( 'The last backup email failed to send.', 'wpr_hmbkp' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'wpr_hmbkp_email_failed_warning' );

	endif;

	// If a custom backups directory is defined and it doesn't exist and can't be created
	if ( defined( 'WPRP_HMBKP_PATH' ) && WPRP_HMBKP_PATH && !is_dir( WPRP_HMBKP_PATH ) ) :

		function wpr_hmbkp_custom_path_exists_warning() {
			echo '<div id="wpr_hmbkp-email_invalid" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( 'Your custom backups directory %s doesn\'t exist and can\'t be created, your backups will be saved to %s instead.', 'wpr_hmbkp' ), '<code>' . WPRP_HMBKP_PATH . '</code>', '<code>' . wpr_hmbkp_path() . '</code>' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'wpr_hmbkp_custom_path_exists_warning' );

	endif;

	// If a custom backups directory is defined and exists but isn't writable
	if ( defined( 'WPRP_HMBKP_PATH' ) && WPRP_HMBKP_PATH && is_dir( WPRP_HMBKP_PATH ) && !is_writable( WPRP_HMBKP_PATH ) ) :

		function wpr_hmbkp_custom_path_writable_notice() {
			echo '<div id="wpr_hmbkp-email_invalid" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( 'Your custom backups directory %s isn\'t writable, new backups will be saved to %s instead.', 'wpr_hmbkp' ), '<code>' . WPRP_HMBKP_PATH . '</code>', '<code>' . wpr_hmbkp_path() . '</code>' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'wpr_hmbkp_custom_path_writable_notice' );

	endif;

	// If there are custom excludes defined and any of the files or directories don't exist
	if ( wpr_hmbkp_invalid_custom_excludes() ) :

		function wpr_hmbkp_invalid_exclude_notice() {
			echo '<div id="wpr_hmbkp-email_invalid" class="updated fade"><p><strong>' . __( 'BackUpWordPress has detected a problem.', 'wpr_hmbkp' ) . '</strong> ' . sprintf( __( 'You have defined a custom exclude list but the following paths don\'t exist %s, are you sure you entered them correctly?', 'wpr_hmbkp' ), '<code>' . implode( '</code>, <code>', (array) wpr_hmbkp_invalid_custom_excludes() ) . '</code>' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'wpr_hmbkp_invalid_exclude_notice' );

	endif;

}
add_action( 'admin_head', 'wpr_hmbkp_admin_notices' );

/**
 * Hook in an change the plugin description when BackUpWordPress is activated
 *
 * @param array $plugins
 * @return $plugins
 */
function wpr_hmbkp_plugin_row( $plugins ) {

	if ( isset( $plugins[WPRP_HMBKP_PLUGIN_SLUG . '/plugin.php'] ) )
		$plugins[WPRP_HMBKP_PLUGIN_SLUG . '/plugin.php']['Description'] = str_replace( 'Once activated you\'ll find me under <strong>Tools &rarr; Backups</strong>', 'Find me under <strong><a href="' . admin_url( 'tools.php?page=' . WPRP_HMBKP_PLUGIN_SLUG ) . '">Tools &rarr; Backups</a></strong>', $plugins[WPRP_HMBKP_PLUGIN_SLUG . '/plugin.php']['Description'] );

	return $plugins;

}
add_filter( 'all_plugins', 'wpr_hmbkp_plugin_row', 10 );