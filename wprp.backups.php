<?php

/**
 * WPRP_Backups
 *
 * Singleton class for creating backups, all scheduling is handled by WP Remote
 */
class WPRP_Backups {

	/**
	 * Contains the current instance
	 *
	 * @static
	 * @access private
	 */
	private static $instance;

	/**
	 * Contains the instance of HM Backup
	 *
	 * @access private
	 */
	private $backup;

	/**
	 * Setup HM Backup
	 *
	 * @access publics
	 * @see HM_Backup
	 */
	public function __construct() {

		$this->backup = new HM_Backup();

		// Set the backup path
		$this->backup->set_path( $this->path() );

		// Set the excludes
		if ( ! empty( $_GET['backup_excludes'] ) )
			$this->backup->set_excludes( $_GET['backup_excludes'] );

		$this->filesize_transient = 'wprp_' . '_' . $this->backup->get_type() . '_' . md5( $this->backup->exclude_string() ) . '_filesize';

	}

	/**
	 * Return the current instance of WPRP_Backups
	 *
	 * @static
	 * @access public
	 */
	public static function get_instance() {

		if ( empty( self::$instance ) )
			self::$instance = new WPRP_Backups();

		return self::$instance;
	
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

		$path = get_option( 'wprp_path' );

		// Create the backups directory if it doesn't exist
		if ( ! is_dir( $path ) && is_writable( dirname( $path ) ) )
			mkdir( $path, 0755 );

		// If the dir doesn't exist or isn't writable then use the default path instead instead
		if ( ( ! $path || ( is_dir( $path ) && ! is_writable( $path ) ) || ( ! is_dir( $path ) && ! is_writable( dirname( $path ) ) ) ) && ( get_option( 'wprp_path' ) && get_option( 'wprp_path' ) !== get_option( 'wprp_default_path' ) ) )
	    	$path = $this->path_default();

		// If the path has changed then cache it
		if ( get_option( 'wprp_path' ) !== $path )
			update_option( 'wprp_path', $path );

		// Protect against directory browsing by including a index.html file
		$index = $path . '/index.html';

		if ( ! file_exists( $index ) && is_writable( $path ) )
			file_put_contents( $index, '' );

		$htaccess = $path . '/.htaccess';

		// Protect the directory with a .htaccess file on Apache servers
		if ( $is_apache && function_exists( 'insert_with_markers' ) && ! file_exists( $htaccess ) && is_writable( $path ) ) {

			$contents[]	= '# ' . sprintf( __( 'This %s file ensures that other people cannot download your backup files.', 'wprp' ), '.htaccess' );
			$contents[] = '';
			$contents[] = '<IfModule mod_rewrite.c>';
			$contents[] = 'RewriteEngine On';
			$contents[] = 'RewriteCond %{QUERY_STRING} !key=' . WPRP_SECURE_KEY;
			$contents[] = 'RewriteRule (.*) - [F]';
			$contents[] = '</IfModule>';
			$contents[] = '';

			insert_with_markers( $htaccess, 'WP Remote Backup', $contents );

		}

	    return HM_Backup::conform_dir( $path );

	}

	/**
	 * Return the default backup path
	 *
	 * @access private
	 * @return string $path
	 */
	private function path_default() {

		$path = get_option( 'wprp_default_path' );

		if ( empty( $path ) ) {

			$path = HM_Backup::conform_dir( trailingslashit( WP_CONTENT_DIR ) . substr( $this->key(), 0, 10 ) . '-backups' );

			update_option( 'wprp_default_path', $path );

		}

		$upload_dir = wp_upload_dir();

		// If the backups dir can't be created in WP_CONTENT_DIR then fallback to uploads
		if ( ( ( ! is_dir( $path ) && ! is_writable( dirname( $path ) ) ) || ( is_dir( $path ) && ! is_writable( $path ) ) ) && strpos( $path, $upload_dir['basedir'] ) === false ) {

			$path = HM_Backup::conform_dir( trailingslashit( $upload_dir['basedir'] ) . substr( $this->key(), 0, 10 ) . '-backups' );

			update_option( 'wprp_default_path', $path );

		}

		return $path;
	}

	/**
	 * Calculate and generate the private key
	 *
	 * @access private
	 * @return string $key
	 */
	private function key() {

		if ( $this->key )
			return $this->key;

		$key = array( ABSPATH, time() );

		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT', 'SECRET_KEY' ) as $constant )
			if ( defined( $constant ) )
				$key[] = $constant;

 		return md5( shuffle( $key ) );
	
	}

	/**
	 * Perform a backup of the site
	 *
	 * @return true|WP_Error
	 */
	public function do_backup() {
		
		@ignore_user_abort( true );

		$this->backup->backup();

		if ( ! file_exists( $this->backup->get_archive_filepath() ) )
			return new WP_Error( 'backup-failed', implode( ', ', $this->backup->get_errors() ) );

		return true;
	
	}

	/**
	 * Get the backup once it has run, will return status running as a WP Error
	 *
	 * @return WP_Error|string
	 */
	public function get_backup() {

		global $is_apache;

		$backup = glob( $this->backup->get_path() . '/*.zip' );
		$backup = reset( $backup );

		if ( file_exists( $backup ) ) {

			// Append the secret key on apache servers
			if ( $is_apache && $this->key() ) {

				$backup = add_query_arg( 'key', $this->key(), $backup );

			    // Force the .htaccess to be rebuilt
			    if ( file_exists( $this->backup->get_path() . '/.htaccess' ) )
			        unlink( $this->backup->get_path() . '/.htaccess' );

			    $this->path();

			}

			return str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $backup );

		}

		return new WP_Error( 'backup-failed', 'No backup was found' );
	
	}

	/**
	 * Remove the backups directoy and everything it contains
	 *
	 * @access public
	 * @return void
	 */
	public function cleanup() {

		$zips = $glob( $this->backup->get_path() . '/*.zip' );

		// Remove any .zip files
		foreach ( $zips as $zip )
			unlink( $zip );

		if ( file_exists( trailingslashit( $this->backup->get_path() ) . 'index.html' ) )
			unlink( trailingslashit( $this->backup->get_path() ) . 'index.html' );
		
		if ( file_exists( trailingslashit( $this->backup->get_path() ) . '.htaccess' ) )
			unlink( trailingslashit( $this->backup->get_path() ) . '.htaccess' );

		unlink( $this->backup->get_path() );
	
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

		if ( $size = get_transient( $this->filesize_transient ) )
			return size_format( $size, null, '%01u %s' );

		// we dont know the size yet, fire off a remote request to get it for later
		// it can take some time so we have a small timeout then return "Calculating"
		wp_remote_get( add_query_arg( array( 'action' => 'wprp_calculate_backup_size', 'backup_excludes' => $this->backup->get_excludes() ), admin_url( 'admin-ajax.php' ) ), array( 'timeout' => 0.1, 'sslverify' => false ) );

		return 'Calculating';

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

    	// Don't include database if file only
		if ( $this->backup->get_type() != 'file' ) {

    		global $wpdb;

    		$res = $wpdb->get_results( 'SHOW TABLE STATUS FROM `' . DB_NAME . '`', ARRAY_A );

    		foreach ( $res as $r )
    			$filesize += (float) $r['Data_length'];

    	}

    	// Don't include files if database only
   		if ( $this->backup->get_type() != 'database' ) {

    		// Get rid of any cached filesizes
    		clearstatcache();

			$excludes = $this->backup->exclude_string( 'regex' );

			foreach ( $this->backup->get_files() as $file ) {

				// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
				if ( method_exists( $file, 'isDot' ) && $file->isDot() )
					continue;

				if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
					continue;

			    // Excludes
			    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->backup->get_root() ), '', HM_Backup::conform_dir( $file->getPathname() ) ) ) )
			        continue;

			    $filesize += (float) $file->getSize();

			}

		}

		// Cache for a day
		set_transient( $this->filesize_transient, $filesize, time() + 60 * 60 * 24 );

	}

}

/**
 * Handle the backups API calls
 *
 * @param string $call
 * @return mixed
 */
function _wprp_backups_api_call( $action ) {

	switch( $action ) {

		case 'supports_backups' :
			return true;

		case 'do_backup' :
			return WPRP_Backups::get_instance()->do_backup();

		case 'get_backup' :
			return WPRP_Backups::get_instance()->get_backup();
		
		case 'delete_backup' :
			return WPRP_Backups::get_instance()->cleanup();

	}

}

/**
 * Return an array of back meta information
 *
 * @return array
 */
function _wprp_get_backups_info() {

	$hm_backup = new HM_Backup();

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
	
	WPRP_Backups::get_instance()->get_filesize();
	
	exit;
}
add_action( 'wp_ajax_nopriv_wprp_calculate_backup_size', 'wprp_ajax_calculate_backup_size' );