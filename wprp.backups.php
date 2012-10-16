<?php

class WPRP_Backups {

	private static $instance;

	public static function getInstance() {

		if ( empty( self::$instance ) )
			self::$instance = new WPRP_Backups();

		return self::$instance;
	}

	/**
	 * Do a backup of the site
	 * 
	 * @return true|WP_Error
	 */
	public function doBackup() {
		@ignore_user_abort( true );

		$schedule = $this->getManualBackupSchedule();
		
		$schedule->run();		
		
		$filepath = $schedule->get_archive_filepath();

		if ( ! file_exists( $filepath ) ) {
			return new WP_Error( 'backup-failed', implode(', ', $schedule->get_errors() ) );
		}
		
		return true;
	}

	/**
	 * Get the backup once it has run, will return status running as a WP Error
	 * 
	 * @return WP_Error|string
	 */
	public function getBackup() {

		$schedule = $this->getManualBackupSchedule();

		if ( $status = $schedule->get_status() )
			return new WP_Error( 'error-status', $status );

		$backup = reset( $schedule->get_backups() );

		if ( file_exists( $backup ) )
			return str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $backup );

		return new WP_Error( 'backup-failed', 'No backup was found' );
	}

	public function cleanBackup() {
		$backup = reset( $schedule->get_backups() );

		if ( file_exists( $backup ) )
			unlink( $backup );
	}

	public function getEstimatedSize() {
		
		if ( $size = get_transient( 'hmbkp_schedule_manual_filesize' ) )
			return HMBKP_Scheduled_Backup::human_filesize( $size, null, '%01u %s' );

		// we dont know the size yet, fire off a remote request to get it for later
		// it can take some time so we have a small timeout then return "Calculating"
		wp_remote_get( 
			$url = add_query_arg( array( 'action' => 'wprp_calculate_backup_size' ), admin_url( 'admin-ajax.php' ) ), 
			array( 'timeout' => 0.1, 'sslverify' => false )
		);

		return 'Calculating';

	}

	public function calculateEstimatedSize() {
		$this->getManualBackupSchedule()->get_filesize();
	}

	/**
	 * Get the manual backup schedule from BackupWordPress
	 * @return HMBKP_Scheduled_Backup
	 */
	private function getManualBackupSchedule() {

		$schedule = new HMBKP_Scheduled_Backup( 'manual' );
		$schedule->set_type( 'complete' );
		$schedule->set_max_backups( 1 );

		// Excludes
		if ( ! empty( $_REQUEST['backup_excludes'] ) ) {
		
			$excludes = array_map( 'urldecode', (array) $_REQUEST['backup_excludes'] );
			$schedule->set_excludes( $excludes, true );
		}

		return $schedule;
	}
}

/**
 * Handle the backups API calls
 *
 * @param string $call
 * @return mixed
 */
function _wprp_backups_api_call( $action ) {

	switch( $action ) :
		
		// TODO in the future we should do some check here to make sure they do support backups
		case 'supports_backups' :
			return true;
			
		case 'do_backup' :

			return WPRP_Backups::getInstance()->doBackup();
					
		case 'get_backup' :
				
			return WPRP_Backups::getInstance()->getBackup();
										
		case 'delete_backup' :

			return WPRP_Backups::getInstance()->cleanBackup();
			
		break;

	endswitch;

}


function _wprp_get_backups_info() {

	$hm_backup = new HM_Backup();

	$info = array(
		'mysqldump_path' 	=> $hm_backup->get_mysqldump_command_path(),
		'zip_path' 			=> $hm_backup->get_zip_command_path(),
		'estimated_size'	=> WPRP_Backups::getInstance()->getEstimatedSize()
	);

	return $info;
}