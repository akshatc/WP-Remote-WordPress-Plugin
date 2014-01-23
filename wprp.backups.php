<?php

/**
 * WPRP_Backups
 *
 * Singleton class for creating backups, all scheduling is handled by WP Remote
 */
class WPRP_Backups extends WPRP_HM_Backup {

	/**
	 * Contains the current instance
	 *
	 * @static
	 * @access private
	 */
	private static $instance;

	/**
	 * Return the current instance of WPRP_Backups
	 *
	 * @static
	 * @access public
	 */
	public static function get_instance() {

		if ( empty( self::$instance ) ) {
			self::$instance = new WPRP_Backups();
		}

		return self::$instance;

	}

	/**
	 * Setup HM Backup
	 *
	 * @access publics
	 * @see HM_Backup
	 */
	public function __construct() {

		parent::__construct();

		// Set the backup path
		$this->set_path( $this->path() );

		// Set the excludes
		if ( class_exists( 'WPR_API_Request' ) &&  WPR_API_Request::get_arg( 'backup_excludes' ) )
			$backup_excludes = WPR_API_Request::get_arg( 'backup_excludes' );
		else if ( isset( $_GET['backup_excludes'] ) )
			$backup_excludes = $_GET['backup_excludes'];

		if ( ! empty( $backup_excludes ) )
			$this->set_excludes( apply_filters( 'wprp_backup_excludes', $backup_excludes ) );

		$this->filesize_transient = 'wprp_' . '_' . $this->get_type() . '_' . substr( md5( $this->exclude_string() ), 20 ) . '_filesize';

	}

	/**
	 * Perform a backup of the site
	 * @return bool|WP_Error
	 */
	public function do_backup() {

		@ignore_user_abort( true );

		$this->set_status( 'Starting backup...' );

		$this->set_start_timestamp();

		$this->backup();

		if ( ! file_exists( $this->get_archive_filepath() ) ) {

			$errors = $this->get_errors();
			if ( ! empty( $errors ) )
				return new WP_Error( 'backup-failed', implode( ', ', $errors ) );
			else
				return new WP_Error( 'backup-failed', __( 'Backup file is missing.', 'wpremote' ) );

		}

		return true;

	}

	/**
	 * Get the backup once it has run, will return status running as a WP Error
	 *
	 * @return WP_Error|string
	 */
	public function get_backup() {

		global $is_apache;

		// Restore the start timestamp to global scope so HM Backup recognizes the proper archive file
		$this->restore_start_timestamp();

		if ( $status = $this->get_status() ) {

			if ( $this->is_backup_still_running() )
				return new WP_Error( 'error-status', $status );
			else
				return new WP_Error( 'backup-process-killed', __( 'Backup process failed or was killed.', 'wpremote' ) );
		}

		$backup = $this->get_archive_filepath();

		if ( file_exists( $backup ) ) {

			// Append the secret key on apache servers
			if ( $is_apache && $this->key() ) {

				$backup = add_query_arg( 'key', $this->key(), $backup );

			    // Force the .htaccess to be rebuilt
			    if ( file_exists( $this->get_path() . '/.htaccess' ) )
			        unlink( $this->get_path() . '/.htaccess' );

			    $this->path();

			}

			$response = new stdClass;
			$response->url = str_replace( parent::conform_dir( WP_CONTENT_DIR ), WP_CONTENT_URL, $backup );
			$response->seconds_elapsed = time() - $this->start_timestamp;
			return $response;

		}

		return new WP_Error( 'backup-failed', __( 'No backup was found', 'wpremote' ) );

	}

	/**
	 * Remove the backups directoy and everything it contains
	 *
	 * @access public
	 * @return void
	 */
	public function cleanup() {

		$this->rmdir_recursive( $this->get_path() );

		delete_option( 'wprp_backup_path' );

	}

	/**
	 * Cleanup old ZipArchive partials that may have been left by old processes
	 */
	public function cleanup_ziparchive_partials() {

		foreach( glob( $this->get_path() . '/*.zip.*' ) as $ziparchive_partial ) {
			unlink( $ziparchive_partial );
		}

	}

	/**
	 * Get the estimated size of the sites files and database
	 *
	 * If the size hasn't been calculated yet then it fires an API request
	 * to calculate the size and returns string 'Calculating'
	 *
	 * @access public
	 * @return string $size|Calculating
	 */
	public function get_estimate_size() {

		// Check the cache
		if ( $size = get_transient( $this->filesize_transient ) ) {

			// If we have a number, format it and return
			if ( is_numeric( $size ) )
				return size_format( $size, null, '%01u %s' );

			// Otherwise the filesize must still be calculating
			else
				return __( 'Calculating', 'wpremote' );

		}

		// we dont know the size yet, fire off a remote request to get it for later
		// it can take some time so we have a small timeout then return "Calculating"
		global $wprp_noauth_nonce;
		wp_remote_get( add_query_arg( array( 'action' => 'wprp_calculate_backup_size', 'backup_excludes' => $this->get_excludes() ), add_query_arg( '_wpnonce', $wprp_noauth_nonce, admin_url( 'admin-ajax.php' ) ) ), array( 'timeout' => 0.1, 'sslverify' => false ) );

		return __( 'Calculating', 'wpremote' );

	}

	/**
	 * Hook into the actions fired in HM Backup and set the status
	 *
	 * @param $action
	 */
	protected function do_action( $action ) {

		$this->update_heartbeat_timestamp();

		switch ( $action ) :

			case 'hmbkp_backup_started':

				$this->save_backup_process_id();

			break;

	    	case 'hmbkp_mysqldump_started' :

	    		$this->set_status( sprintf( __( 'Dumping Database %s', 'wpremote' ), '(<code>' . $this->get_mysqldump_method() . '</code>)' ) );

	    	break;

	    	case 'hmbkp_mysqldump_verify_started' :

	    		$this->set_status( sprintf( __( 'Verifying Database Dump %s', 'wpremote' ), '(<code>' . $this->get_mysqldump_method() . '</code>)' ) );

	    	break;

			case 'hmbkp_archive_started' :

				if ( $this->is_using_file_manifest() )
					$status = sprintf( __( '%d files remaining to archive %s', 'wpremote' ), $this->file_manifest_remaining, '(<code>' . $this->get_archive_method() . '</code>)' );
				else
					 $status = sprintf( __( 'Creating zip archive %s', 'wpremote' ), '(<code>' . $this->get_archive_method() . '</code>)' );

				$this->set_status( $status );

	    	break;

	    	case 'hmbkp_archive_verify_started' :

	    		$this->set_status( sprintf( __( 'Verifying Zip Archive %s', 'wpremote' ), '(<code>' . $this->get_archive_method() . '</code>)' ) );

	    	break;

	    	case 'hmbkp_backup_complete' :

	    		if ( file_exists( $this->get_schedule_running_path() ) )
	    			unlink( $this->get_schedule_running_path() );

				$this->clear_backup_process_id();

	    	break;

	    	case 'hmbkp_error' :

				if ( $this->get_errors() ) {

			    	$file = $this->get_path() . '/.backup_errors';

					if ( file_exists( $file ) )
						unlink( $file );

			    	if ( ! $handle = @fopen( $file, 'w' ) )
			    		return;

					fwrite( $handle, json_encode( $this->get_errors() ) );

			    	fclose( $handle );

			    }

			break;

			case 'hmbkp_warning' :

			    if ( $this->get_warnings() ) {

					$file = $this->get_path() . '/.backup_warnings';

					if ( file_exists( $file ) )
			  			unlink( $file );

					if ( ! $handle = @fopen( $file, 'w' ) )
			  	  		return;

			  		fwrite( $handle, json_encode( $this->get_warnings() ) );

					fclose( $handle );

				}

	    	break;

	    endswitch;

	}

	/**
	 * Get the path to the backups directory
	 *
	 * Will try to create it if it doesn't exist
	 * and will fallback to default if a custom dir
	 * isn't writable.
	 *
	 * @access private
	 * @see default_path()
	 * @return string $path
	 */
	private function path() {

		global $is_apache;

		$path = get_option( 'wprp_backup_path' );

		// If the dir doesn't exist or isn't writable then use the default path instead instead
		if ( ! $path || ( is_dir( $path ) && ! is_writable( $path ) ) || ( ! is_dir( $path ) && ! is_writable( dirname( $path ) ) ) )
	    	$path = $this->path_default();

		// Create the backups directory if it doesn't exist
		if ( ! is_dir( $path ) && is_writable( dirname( $path ) ) )
			mkdir( $path, 0755 );

		// If the path has changed then cache it
		if ( get_option( 'wprp_backup_path' ) !== $path )
			update_option( 'wprp_backup_path', $path );

		// Protect against directory browsing by including a index.html file
		$index = $path . '/index.html';

		if ( ! file_exists( $index ) && is_writable( $path ) )
			file_put_contents( $index, '' );

		$htaccess = $path . '/.htaccess';

		// Protect the directory with a .htaccess file on Apache servers
		if ( $is_apache && function_exists( 'insert_with_markers' ) && ! file_exists( $htaccess ) && is_writable( $path ) ) {

			$contents[]	= '# ' . sprintf( __( 'This %s file ensures that other people cannot download your backup files.', 'wpremote' ), '.htaccess' );
			$contents[] = '';
			$contents[] = '<IfModule mod_rewrite.c>';
			$contents[] = 'RewriteEngine On';
			$contents[] = 'RewriteCond %{QUERY_STRING} !key=' . $this->key();
			$contents[] = 'RewriteRule (.*) - [F]';
			$contents[] = '</IfModule>';
			$contents[] = '';

			insert_with_markers( $htaccess, __( 'WP Remote Backup', 'wpremote' ), $contents );

		}

	    return parent::conform_dir( $path );

	}

	/**
	 * Return the default backup path
	 *
	 * @access private
	 * @return string $path
	 */
	private function path_default() {

		$dirname = substr( $this->key(), 0, 10 ) . '-wprbackups';
		$path = parent::conform_dir( trailingslashit( WP_CONTENT_DIR ) . $dirname );

		$upload_dir = wp_upload_dir();

		// If the backups dir can't be created in WP_CONTENT_DIR then fallback to uploads
		if ( ( ( ! is_dir( $path ) && ! is_writable( dirname( $path ) ) ) || ( is_dir( $path ) && ! is_writable( $path ) ) ) && strpos( $path, $upload_dir['basedir'] ) === false )
			$path = parent::conform_dir( trailingslashit( $upload_dir['basedir'] ) . $dirname );

		return $path;
	}

	/**
	 * Calculate and generate the private key
	 *
	 * @access private
	 * @return string $key
	 */
	private function key() {

		if ( ! empty( $this->key ) )
			return $this->key;

		$key = array( ABSPATH, time() );

		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT', 'SECRET_KEY' ) as $constant )
			if ( defined( $constant ) )
				$key[] = constant( $constant );

		shuffle( $key );

		$this->key = md5( serialize( $key ) );
		return $this->key;
	}

	/**
	 * Get the status of the running backup.
	 *
	 * @access public
	 * @return string
	 */
	private function get_status() {

		if ( ! file_exists( $this->get_schedule_running_path() ) )
			return '';

		$status = file_get_contents( $this->get_schedule_running_path() );

		return $status;

	}

	/**
	 * Get the path to the backup running file that stores the running backup status
	 *
	 * @access private
	 * @return string
	 */
	private function get_schedule_running_path() {
		return $this->get_path() . '/.backup-running';
	}

	/**
	 * Set the status of the running backup
	 *
	 * @access public
	 * @param string $message
	 * @return void
	 */
	private function set_status( $message ) {

		if ( ! $handle = fopen( $this->get_schedule_running_path(), 'w' ) )
			return;

		fwrite( $handle, $message );

		fclose( $handle );

	}

	/**
	 * Set the start timestamp for the backup
	 */
	private function set_start_timestamp() {
		$this->start_timestamp = current_time( 'timestamp' );
		file_put_contents( $this->get_path() . '/.start-timestamp', $this->start_timestamp );
	}

	/**
	 * Restore the start timestamp for the backup
	 */
	private function restore_start_timestamp() {
		if ( $start_timestamp = file_get_contents( $this->get_path() . '/.start-timestamp' ) )
			$this->start_timestamp = (int) $start_timestamp;
	}

	/**
	 * Update the heartbeat timestamp to the current time.
	 */
	private function update_heartbeat_timestamp() {
		file_put_contents( $this->get_path() . '/.heartbeat-timestamp', time() );
	}

	/**
	 * Get the heartbeat timestamp.
	 */
	private function get_heartbeat_timestamp() {

		$heartbeat = $this->get_path() . '/.heartbeat-timestamp';

		if ( file_exists( $heartbeat ) )
			return (int) file_get_contents( $heartbeat );

		return false;
	}

	/**
	 * Get the file path to the backup process ID log
	 * 
	 * @access private
	 */
	private function get_backup_process_id_path() {
		return $this->get_path() . '/.backup-process-id';
	}

	/**
	 * Get the current backup process ID
	 * 
	 * @access private
	 */
	private function get_backup_process_id() {
		$file = $this->get_backup_process_id_path();
		if ( file_exists( $file ) )
			return (int) trim( file_get_contents( $file ) );
		else
			return false;
	}

	/**
	 * Save this current backup process ID in case
	 * we need to check later whether it was killed in action
	 *
	 * @access private
	 */
	private function save_backup_process_id() {

		if ( ! $handle = fopen( $this->get_backup_process_id_path(), 'w' ) )
			return;

		fwrite( $handle, getmypid() );

		fclose( $handle );

	}

	/**
	 * Clear the backup process ID
	 * 
	 * @access private
	 */
	private function clear_backup_process_id() {

		if ( file_exists( $this->get_backup_process_id_path() ) )
			unlink( $this->get_backup_process_id_path() );
	}

	/**
	 * Whether or not a backup appears to be in progress
	 * 
	 * @access private
	 */
	private function is_backup_still_running( $context = 'get_backup' ) {

		// Check whether there's supposed to be a backup in progress
		if ( false === ( $process_id = $this->get_backup_process_id() ) )
			return false;

		// When safe mode is enabled, WPRP can't modify max_execution_time
		if ( self::is_safe_mode_active() && ini_get( 'max_execution_time' ) )
			$time_to_wait = ini_get( 'max_execution_time' );
		else
			$time_to_wait = 90;

		// Give heartbeat requests a little bit of time to restart
		if ( 'get_backup' == $context )
			$time_to_wait += 15;

		// If the heartbeat has been modified in the last 90 seconds, we might not be dead
		if ( ( time() - $this->get_heartbeat_timestamp() ) < $time_to_wait )
			return true;

		// Check if there's any file being modified.
		$backup_file_dirs = array( $this->get_path() );

		if ( $this->is_using_file_manifest() ) {
			$backup_file_dirs[] = $this->get_file_manifest_dirpath();
		}

		foreach ( $backup_file_dirs as $backup_file_dir ) {
			$backup_files = glob( $backup_file_dir . '/*' );

			$file_mtimes = array();
			foreach( $backup_files as $backup_file ) {
				$file_mtimes[] = filemtime( $backup_file );
		}
			if ( ! empty( $file_mtimes ) ) {
				$latest_file_mtime = max( $file_mtimes );
				if ( ( time() - $latest_file_mtime ) < $time_to_wait )
				return true;
		}
		} 

		return false;
		
	}

	/**
	 * Check if there's a backup in progress, whether it's running,
	 * and restart it if it's not running
	 *
	 * @todo support checking whether the database should exist
	 */
	public function backup_heartbeat() {

		// Restore the start timestamp to global scope so HM Backup recognizes the proper archive file
		$this->restore_start_timestamp();

		// No process means no backup in progress
		if ( ! $this->get_backup_process_id() )
			return false;

		// No file manifest directory means this wasn't a file manifest approach
		if ( ! is_dir( $this->get_file_manifest_dirpath() ) )
			return false;

		// Check whether there's supposed to be a backup in progress
		if ( $this->get_backup_process_id() && $this->is_backup_still_running( 'backup_heartbeat' ) )
			return false;

		// Uh oh, needs to be restarted
		$this->cleanup_ziparchive_partials();

		$this->save_backup_process_id();

		$this->restart_archive();

	}

	/**
	 * Calculate the size of the backup
	 *
	 * Doesn't account for compression
	 *
	 * @access public
	 * @return string
	 */
	public function get_filesize() {

		$filesize = 0;

		// Only try to calculate once per hour
		set_transient( $this->filesize_transient, 'Calculating', time() + 60 * 60 );

    	// Don't include database if file only
		if ( $this->get_type() != 'file' ) {

    		global $wpdb;

    		$res = $wpdb->get_results( 'SHOW TABLE STATUS FROM `' . DB_NAME . '`', ARRAY_A );

    		foreach ( $res as $r )
    			$filesize += (float) $r['Data_length'];

    	}

    	// Don't include files if database only
   		if ( $this->get_type() != 'database' ) {

    		// Get rid of any cached filesizes
    		clearstatcache();

			$excludes = $this->exclude_string( 'regex' );

			foreach ( $this->get_files() as $file ) {

				// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
				if ( method_exists( $file, 'isDot' ) && $file->isDot() )
					continue;

				if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
					continue;

			    // Excludes
			    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', parent::conform_dir( $file->getPathname() ) ) ) )
			        continue;

			    $filesize += (float) $file->getSize();

			}

		}

		// Cache for a day
		set_transient( $this->filesize_transient, $filesize, time() + 60 * 60 * 24 );

	}

}

/*
 * Return an array of back meta information
 *
 * @return array
 */
function _wprp_get_backups_info() {

	$hm_backup = new WPRP_HM_Backup();

	return array(
		'mysqldump_path' 	=> $hm_backup->get_mysqldump_command_path(),
		'zip_path' 			=> $hm_backup->get_zip_command_path(),
		'estimated_size'	=> WPRP_Backups::get_instance()->get_estimate_size()
	);

}

/**
 * Calculate the filesize of the site
 *
 * The calculated size is stored in a transient
 */
function wprp_ajax_calculate_backup_size() {

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wprp_calculate_backup_size' ) )
		exit;

	WPRP_Backups::get_instance()->get_filesize();

	exit;
}
add_action( 'wp_ajax_nopriv_wprp_calculate_backup_size', 'wprp_ajax_calculate_backup_size' );
