<?php

/**
 * Handle the backups API calls
 *
 * @param string $call
 * @return mixed
 */
function _wprp_backups_api_call( $action ) {

	if ( ! class_exists( 'hm_backup' ) )
		return new WP_Error( 'Backups module not present' );

	switch( $action ) :
		
		// TODO in the future we should do some check here to make sure they do support backups
		case 'supports_backups' :
			return true;
			
		case 'do_backup' :
		
			@ignore_user_abort(true);
			
			$backup = new HM_Backup();
			$upload_dir = wp_upload_dir();
			
			// Store the backup file in the uploads dir
			$backup->path = $upload_dir['basedir'] . '/_wpremote_backups';
			
			$running_file = $backup->path . '/.backup_running';
			
			// delete the backups folder to cleanup old backups
			_wprp_backups_rmdirtree( $backup->path );
			
			if ( ! @mkdir( $backup->path ) )
				return new WP_Error( 'unable-to-create-backups-directory', 'Unable to write the .backup_running file - check your permissions on wp-content/uploads' );
				
			// write the backup runing file for tracking...
			if ( ! $handle = @fopen( $running_file, 'w' ) )
				return new WP_Error( 'unable-to-write-backup-running-file' );
	
			fwrite( $handle, $backup->archive_filename() );
	
			fclose( $handle );
			
			if ( ! file_exists( $running_file ) )
				return new WP_Error( 'backup-running-file-was-not-created' );
			
			// Set a random backup filename
			$backup->archive_filename = md5( time() ) . '.zip';
			
			// Excludes
			if ( ! empty( $_REQUEST['backup_excludes'] ) ) {
			
				$excludes = array_map( 'urldecode', (array) $_REQUEST['backup_excludes'] );
				$backup->excludes = $excludes;
			}
			
			$backup->backup();
			
			unlink( $backup->path . '/.backup_completed' );
			unlink( $backup->path . '/.backup_running' );
			
			// write the backup runing file for tracking...
			$completed_file = $backup->path . '/.backup_completed';

			if ( ! $handle = @fopen( $completed_file, 'w' ) )
				return new WP_Error( 'unable-to-write-backup-completed-file' );
			
			if ( $backup->errors() || ( $backup->warnings() && ! file_exists( $backup->archive_filepath() ) ) ) {
				
				$errors = array_merge( $backup->errors(), $backup->warnings() );
				fwrite( $handle, json_encode( $errors ) );
				
			} else {
			
				fwrite( $handle, 'file:' . $backup->archive_filename() );
			}
			
			fclose( $handle );
			
			return true;
					
		case 'get_backup' :
			
			$upload_dir = wp_upload_dir();

			// Store the backup file in the uploads dir
			$path = $upload_dir['basedir'] . '/_wpremote_backups';
			$url = $upload_dir['baseurl'] . '/_wpremote_backups';
			
			if ( ! is_dir( $path ) )
				return new WP_Error( 'backups-dir-does-not-exist' );

			if ( file_exists( $path . '/.backup_running' ) )
				return new WP_Error( 'backup-running' );

			if ( ! file_exists( $path . '/.backup_completed' ) )
				return new WP_Error( 'backup-not-started' );
			
			$file = file_get_contents( $path . '/.backup_completed' );
			
			if ( strpos( $file, 'file:' ) === 0 )
				return $url . '/' . substr( $file, 5 );
			
			// must have errored, return errors in a WP_Error
			return new WP_Error( 'backup-failed', json_decode( $file ) );
										
		case 'delete_backup' :

			$upload_dir = wp_upload_dir();
			
			_wprp_backups_rmdirtree( $upload_dir['basedir'] . '/_wpremote_backups' );
			
		break;

	endswitch;

}

function _wprp_backups_rmdirtree( $dir ) {

	if ( is_file( $dir ) )
		unlink( $dir );

    if ( ! is_dir( $dir ) )
    	return false;

    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );

	foreach ( $files as $file ) {

		if ( $file->isDir() )
			@rmdir( $file->getPathname() );

		else
			@unlink( $file->getPathname() );

	}

	return @rmdir( $dir );

}