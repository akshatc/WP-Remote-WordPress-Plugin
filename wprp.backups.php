<?php

/**
 * Handle the backups API calls
 *
 * @param string $call
 * @return mixed
 */
function _wprp_backups_api_call( $call ) {

	if ( ! class_exists( 'hm_backup' ) )
		return new WP_Error( 'Backups module not present' );

	switch( $call ) :

		case 'do_backup' :

			$backup = new HM_Backup();
			
			$upload_dir = wp_upload_dir();
			
			// Store the backup file in the uploads dir
			$backup->path = $upload_dir['basedir'];
			
			// Set a random backup filename
			$backup->archive_filename = md5( time() ) . '.zip';
			
			$backup->database_only = true;
			
			$backup->backup();
			
			return $backup->archive_filepath();

		break;

		case 'delete_backup' :
		
			$upload_dir = wp_upload_dir();

			if ( ! empty( $_REQUEST['filename'] ) && file_exists( $upload_dir['basedir'] . '/' . $_REQUEST['filename'] ) && substr( $_REQUEST['filename'], -4 ) == '.zip' )
				unlink( $upload_dir['basedir'] . '/' . $_REQUEST['filename'] );

		break;

	endswitch;

}