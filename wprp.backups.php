<?php

/**
 * Is called when some asks for get_backups.
 *
 * @param string $call
 * @return mixed
 */
function _wprp_backups_api_call( $call ) {

	if( !defined( 'WPRP_HMBKP_PLUGIN_PATH' ) )
		return new WP_Error( 'Backups module not present' );
	
	switch( $call ) :
	
		case 'get_backups' :
			
			$backups = wpr_hmbkp_get_backups();
			$backups_info = array();

			foreach( $backups as $backup_file ) {
				
				$file_parts = explode(  '.', $backup_file );
				$timestamp = $file_parts[count( $file_parts ) - 2];
				$timestamp = preg_replace( "#([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)#", '$1-$2-$3 $4:$5:$6', $timestamp );
				
				$timestamp = strtotime( $timestamp );
				
				$backups_info[] = array( 'file_path' => $backup_file, 'date' => $timestamp, 'is_complete' => true, 'id' => $timestamp );
			}
			
			return array( 'backups' => $backups_info );
			break;
		
		case 'do_backup' :
			
			$backup_result = wpr_hmbkp_do_backup();
			
			if( $backup_result === false ) {
				return new WP_Error( 'backup-failed', 'The backup was not completed for an unknown reason.' );
			}
			
			$backup_file = end( wpr_hmbkp_get_backups() );
			
			$file_parts = explode(  '.', $backup_file );
			$timestamp = $file_parts[count( $file_parts ) - 2];
			$timestamp = strtotime( $timestamp );
				
			$backup = array( 'file_path' => $backup_file, 'date' => $timestamp, 'is_complete' => true, 'id' => $timestamp );

			return $backup;
			
			break;
		
		case 'download_backup' :
			
			$file_path = esc_attr( $_GET['file_path'] );
			
			wpr_hmbkp_send_file( $file_path );

			exit;
			
	endswitch;

}