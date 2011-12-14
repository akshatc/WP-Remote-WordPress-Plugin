<?php

/**
 * Backup database and files
 *
 * Creates a temporary directory containing a copy of all files
 * and a dump of the database. Then zip that up and delete the temporary files
 *
 * @uses wpr_hmbkp_backup_mysql
 * @uses wpr_hmbkp_backup_files
 * @uses wpr_hmbkp_delete_old_backups
 */
function wpr_hmbkp_do_backup() {

	// Make sure it's possible to do a backup
	if ( !wpr_hmbkp_possible() )
		return false;

	// Clean up any mess left by the last backup
	wpr_hmbkp_cleanup();

    $time_start = date( 'Y-m-d-H-i-s' );

	$filename = sanitize_file_name( get_bloginfo( 'name' ) . '.backup.' . $time_start . '.zip' );
	$filepath = trailingslashit( wpr_hmbkp_path() ) . $filename;

	// Set as running for a max of 1 hour
	wpr_hmbkp_set_status();

	// Raise the memory limit
	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', '256M' ) );
	@set_time_limit( 0 );

    wpr_hmbkp_set_status( __( 'Dumping database', 'wpr_hmbkp' ) );

	// Backup database
	if ( ( defined( 'WPRP_HMBKP_FILES_ONLY' ) && !WPRP_HMBKP_FILES_ONLY ) || !defined( 'WPRP_HMBKP_FILES_ONLY' ) )
	    wpr_hmbkp_backup_mysql();

	wpr_hmbkp_set_status( __( 'Creating zip archive', 'wpr_hmbkp' ) );

	// Zip everything up
	wpr_hmbkp_archive_files( $filepath );

	// Delete the database dump file
	if ( ( defined( 'WPRP_HMBKP_FILES_ONLY' ) && !WPRP_HMBKP_FILES_ONLY ) || !defined( 'WPRP_HMBKP_FILES_ONLY' ) )
		unlink( wpr_hmbkp_path() . '/database_' . DB_NAME . '.sql' );

	// Email Backup
	wpr_hmbkp_email_backup( $filepath );

    wpr_hmbkp_set_status( __( 'Removing old backups', 'wpr_hmbkp' ) );

	// Delete any old backup files
    wpr_hmbkp_delete_old_backups();
    
    unlink( wpr_hmbkp_path() . '/.backup_running' );
    
	$file = wpr_hmbkp_path() . '/.backup_complete';
	
	if ( !$handle = @fopen( $file, 'w' ) )
		return false;
	
	fwrite( $handle );
	
	fclose( $handle );


}

/**
 * Deletes old backup files
 */
function wpr_hmbkp_delete_old_backups() {

    $files = wpr_hmbkp_get_backups();

    if ( count( $files ) <= wpr_hmbkp_max_backups() )
    	return;

    foreach( array_slice( $files, wpr_hmbkp_max_backups() ) as $file )
       	wpr_hmbkp_delete_backup( base64_encode( $file ) );

}

/**
 * Returns an array of backup files
 */
function wpr_hmbkp_get_backups() {

    $files = array();

    $wpr_hmbkp_path = wpr_hmbkp_path();

    if ( $handle = opendir( $wpr_hmbkp_path ) ) :

    	while ( false !== ( $file = readdir( $handle ) ) )
    		if ( strpos( $file, '.zip' ) !== false )
	   			$files[filemtime( trailingslashit( $wpr_hmbkp_path ) . $file )] = trailingslashit( $wpr_hmbkp_path ) . $file;

    	closedir( $handle );

    endif;

    // If there is a custom backups directory and it's not writable then include those backups as well
    if ( defined( 'WPRP_HMBKP_PATH' ) && WPRP_HMBKP_PATH && is_dir( WPRP_HMBKP_PATH ) && !is_writable( WPRP_HMBKP_PATH ) ) :

    	if ( $handle = opendir( WPRP_HMBKP_PATH ) ) :

    		while ( false !== ( $file = readdir( $handle ) ) )
    			if ( strpos( $file, '.zip' ) !== false )
		   			$files[filemtime( trailingslashit( WPRP_HMBKP_PATH ) . $file )] = trailingslashit( WPRP_HMBKP_PATH ) . $file;

    		closedir( $handle );

    	endif;

	endif;

    krsort( $files );

    return $files;
}

/**
 * Delete a backup file
 *
 * @param $file base64 encoded filename
 */
function wpr_hmbkp_delete_backup( $file ) {

	$file = base64_decode( $file );

	// Delete the file
	if ( strpos( $file, wpr_hmbkp_path() ) !== false || strpos( $file, WP_CONTENT_DIR . '/backups' ) !== false )
	  unlink( $file );

}

/**
 * Check if a backup is running
 *
 * @return bool
 */
function wpr_hmbkp_is_in_progress() {
	return file_exists( wpr_hmbkp_path() . '/.backup_running' );
}

/**
  * Email backup.
  *
  *	@param $file
  * @return bool
  */
function wpr_hmbkp_email_backup( $file ) {

	if ( !defined('WPRP_HMBKP_EMAIL' ) || !WPRP_HMBKP_EMAIL || !is_email( WPRP_HMBKP_EMAIL ) )
		return;

	// Raise the memory and time limit
	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', '256M' ) );
	@set_time_limit( 0 );

	$download = get_bloginfo( 'wpurl' ) . '/wp-admin/tools.php?page=' . WPRP_HMBKP_PLUGIN_SLUG . '&wpr_hmbkp_download=' . base64_encode( $file );
	$domain = parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ) . parse_url( get_bloginfo( 'url' ), PHP_URL_PATH );

	$subject = sprintf( __( 'Backup of %s', 'wpr_hmbkp' ), $domain );
	$message = sprintf( __( "BackUpWordPress has completed a backup of your site %s.\n\nThe backup file should be attached to this email.\n\nYou can also download the backup file by clicking the link below:\n\n%s\n\nKind Regards\n\n The Happy BackUpWordPress Backup Emailing Robot", 'wpr_hmbkp' ), get_bloginfo( 'url' ), $download );
	$headers = 'From: BackUpWordPress <' . get_bloginfo( 'admin_email' ) . '>' . "\r\n";

	// Try to send the email
	$sent = wp_mail( WPRP_HMBKP_EMAIL, $subject, $message, $headers, $file );

	// If it failed- Try to send a download link - The file was probably too large.
	if ( !$sent ) :

		$subject = sprintf( __( 'Backup of %s', 'wpr_hmbkp' ), $domain );
		$message = sprintf( __( "BackUpWordPress has completed a backup of your site %s.\n\nUnfortunately the backup file was too large to attach to this email.\n\nYou can download the backup file by clicking the link below:\n\n%s\n\nKind Regards\n\n The Happy BackUpWordPress Backup Emailing Robot", 'wpr_hmbkp' ), get_bloginfo( 'url' ), $download );

		$sent = wp_mail( WPRP_HMBKP_EMAIL, $subject, $message, $headers );

	endif;

	// Set option for email not sent error
	if ( !$sent )
		update_option( 'wpr_hmbkp_email_error', 'wpr_hmbkp_email_failed' );
	else
		delete_option( 'wpr_hmbkp_email_error' );

	return true;

}

/**
 * Set the status of the running backup
 * 
 * @param string $message. (default: '')
 * @return void
 */
function wpr_hmbkp_set_status( $message = '' ) {
	
	$file = wpr_hmbkp_path() . '/.backup_running';
	
	if ( !$handle = @fopen( $file, 'w' ) )
		return false;
	
	fwrite( $handle, $message );
	
	fclose( $handle );
	
}

/**
 * Get the status of the running backup
 * 
 * @return string
 */
function wpr_hmbkp_get_status() {
	
	if ( !file_exists( wpr_hmbkp_path() . '/.backup_running' ) )
		return false;
		
	return file_get_contents( wpr_hmbkp_path() .'/.backup_running' );
	
}