<?php

/**
 * Generic file and database backup class
 *
 * @version 2.3
 */
class WPRP_HM_Backup {

	/**
	 * The path where the backup file should be saved
	 *
	 * @string
	 * @access private
	 */
	private $path = '';

	/**
	 * The backup type, must be either complete, file or database
	 *
	 * @string
	 * @access private
	 */
	private $type = '';

	/**
	 * The start timestamp of the backup
	 * 
	 * @int
	 * @access protected
	 */
	protected $start_timestamp;

	/**
	 * The filename of the backup file
	 *
	 * @string
	 * @access private
	 */
	private $archive_filename = '';

	/**
	 * The filename of the database dump
	 *
	 * @string
	 * @access private
	 */
	private $database_dump_filename = '';

	/**
	 * The path to the zip command
	 *
	 * @string
	 * @access private
	 */
	private $zip_command_path;

	/**
	 * The path to the mysqldump command
	 *
	 * @string
	 * @access private
	 */
	private $mysqldump_command_path;

	/**
	 * An array of exclude rules
	 *
	 * @array
	 * @access private
	 */
	private $excludes = array();

	/**
	 * The path that should be backed up
	 *
	 * @var string
	 * @access private
	 */
	private $root = '';

	/**
	 * Holds the current db connection
	 *
	 * @var resource
	 * @access private
	 */
	private $db;

	/**
	 * An array of all the files in root
	 * excluding excludes and unreadable files
	 *
	 * @var array
	 * @access private
	 */
	private $files = array();

	/**
	 * An array of all the files in root
	 * that match the exclude rules
	 *
	 * @var array
	 * @access private
	 */
	private $excluded_files = array();

	/**
	 * An array of all the files in root
	 * that are unreadable
	 *
	 * @var array
	 * @access private
	 */
	private $unreadable_files = array();

	/**
	 * Contains an array of errors
	 *
	 * @var mixed
	 * @access private
	 */
	private $errors = array();

	/**
	 * Contains an array of warnings
	 *
	 * @var mixed
	 * @access private
	 */
	private $warnings = array();

	/**
	 * The archive method used
	 *
	 * @var string
	 * @access private
	 */
	private $archive_method = '';

	/**
	 * The mysqldump method used
	 *
	 * @var string
	 * @access private
	 */
	private $mysqldump_method = '';

	/**
	 * Whether or not to use a file manifest (more write-intensive)
	 * 
	 * @var bool
	 * @access private
	 */
	private $using_file_manifest = false;

	/**
	 * The current file manifest file.
	 * 
	 * @access private
	 */
	private $current_file_manifest = false;


	/**
	 * Files of the file manifest that have already been archived
	 * 
	 * @access private
	 */
	private $file_manifest_already_archived = array();

	/**
	 * When using the file manifest, the number of files that should be
	 * archived per batch.
	 * 
	 * @access private
	 */
	private $file_manifest_per_batch = 200;

	/**
	 * Files remaining to be achived in the file manifest.
	 * 
	 * @access protected
	 */
	protected $file_manifest_remaining = 0;

	/**
	 * A ZipArchive instance for this instance
	 */
	private $ziparchive = false;

	/**
	 * A PclZip instance for this instance
	 */
	private $pclzip = false;

	/**
	 * Check whether safe mode is active or not
	 *
	 * @param string $ini_get_callback
	 * @return bool
	 */
	public static function is_safe_mode_active( $ini_get_callback = 'ini_get' ) {

		if ( ( $safe_mode = @call_user_func( $ini_get_callback, 'safe_mode' ) ) && strtolower( $safe_mode ) != 'off' )
			return true;

		return false;

	}

	/**
	 * Check whether shell_exec has been disabled.
	 *
	 * @access public
	 * @static
	 * @return bool
	 */
	public static function is_shell_exec_available() {

		// Are we in Safe Mode
		if ( self::is_safe_mode_active() )
			return false;

		// Is shell_exec or escapeshellcmd or escapeshellarg disabled?
		if ( array_intersect( array( 'shell_exec', 'escapeshellarg', 'escapeshellcmd' ), array_map( 'trim', explode( ',', @ini_get( 'disable_functions' ) ) ) ) )
			return false;

		// Functions can also be disabled via suhosin
		if ( array_intersect( array( 'shell_exec', 'escapeshellarg', 'escapeshellcmd' ), array_map( 'trim', explode( ',', @ini_get( 'suhosin.executor.func.blacklist' ) ) ) ) )
			return false;

		// Can we issue a simple echo command?
		if ( ! @shell_exec( 'echo backupwordpress' ) )
			return false;

		return true;

	}


	/**
	 * Attempt to work out the root directory of the site, that
	 * is, the path equivelant of home_url().
	 *
	 * @access public
	 * @static
	 * @return string $home_path
	 */
	public static function get_home_path() {

		$home_url = home_url();
		$site_url = site_url();

		$home_path = ABSPATH;

		// If site_url contains home_url and they differ then assume WordPress is installed in a sub directory
		if ( $home_url !== $site_url && strpos( $site_url, $home_url ) === 0 )
			$home_path = trailingslashit( substr( self::conform_dir( ABSPATH ), 0, strrpos( self::conform_dir( ABSPATH ), str_replace( $home_url, '', $site_url ) ) ) );

		return self::conform_dir( $home_path );

	}

	/**
	 * Sanitize a directory path
	 *
	 * @param string $dir
	 * @param bool   $recursive (default: false)
	 * @return string $dir
	 */
	public static function conform_dir( $dir, $recursive = false ) {

		// Assume empty dir is root
		if ( ! $dir )
			$dir = '/';

		// Replace single forward slash (looks like double slash because we have to escape it)
		$dir = str_replace( '\\', '/', $dir );
		$dir = str_replace( '//', '/', $dir );

		// Remove the trailing slash
		if ( $dir !== '/' )
			$dir = untrailingslashit( $dir );

		// Carry on until completely normalized
		if ( ! $recursive && self::conform_dir( $dir, true ) != $dir )
			return self::conform_dir( $dir );

		return (string) $dir;

	}

	/**
	 * Sets up the default properties
	 *
	 * @access public
	 */
	public function __construct() {

		// Raise the memory limit and max_execution time
		@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );
		@set_time_limit( 0 );

		// Set a custom error handler so we can track errors
		set_error_handler( array( &$this, 'error_handler' ) );

	}

	/**
	 * Get the full filepath to the archive file
	 *
	 * @access public
	 * @return string
	 */
	public function get_archive_filepath() {

		return trailingslashit( $this->get_path() ) . $this->get_archive_filename();

	}

	/**
	 * Get the filename of the archive file
	 *
	 * @access public
	 * @return string
	 */
	public function get_archive_filename() {

		if ( empty( $this->archive_filename ) ) {

			if ( empty( $this->start_timestamp ) )
				$this->start_timestamp = current_time( 'timestamp' );

			$this->set_archive_filename( implode( '-', array( sanitize_title( str_ireplace( array( 'http://', 'https://', 'www' ), '', home_url() ) ), 'backup', date( 'Y-m-d-H-i-s', $this->start_timestamp ) ) ) . '.zip' );
		}

		return $this->archive_filename;

	}

	/**
	 * Set the filename of the archive file
	 *
	 * @param string $filename
	 * @throws Exception
	 */
	public function set_archive_filename( $filename ) {

		if ( empty( $filename ) || ! is_string( $filename ) )
			throw new Exception( __( 'archive filename must be a non empty string', 'wpremote' ) );

		if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'zip' )
			throw new Exception( __( 'invalid file extension for archive filename', 'wpremote' ) .  '<code>' . $filename . '</code>' );

		$this->archive_filename = strtolower( sanitize_file_name( remove_accents( $filename ) ) );

	}

	/**
	 * Get the full filepath to the database dump file.
	 *
	 * @access public
	 * @return string
	 */
	public function get_database_dump_filepath() {

		return trailingslashit( $this->get_path() ) . $this->get_database_dump_filename();

	}

	/**
	 * Get the filename of the database dump file
	 *
	 * @access public
	 * @return string
	 */
	public function get_database_dump_filename() {

		if ( empty( $this->database_dump_filename ) )
			$this->set_database_dump_filename( 'database_' . DB_NAME . '.sql' );

		return $this->database_dump_filename;

	}

	/**
	 * Set the filename of the database dump file
	 *
	 * @param string $filename
	 * @throws Exception
	 */
	public function set_database_dump_filename( $filename ) {

		if ( empty( $filename ) || ! is_string( $filename ) )
			throw new Exception( __( 'database dump filename must be a non empty string', 'wpremote' ) );

		if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'sql' )
			throw new Exception( __( 'invalid file extension for database dump filename', 'wpremote' ) . '<code>' . $filename . '</code>' );

		$this->database_dump_filename = strtolower( sanitize_file_name( remove_accents( $filename ) ) );

	}

    /**
     * Get the root directory to backup from
     *
     * Defaults to the root of the path equivalent of your home_url
     *
     * @access public
     * @return string
     */
    public function get_root() {

		if ( empty( $this->root ) )
			$this->set_root( self::conform_dir( self::get_home_path() ) );

        return $this->root;

    }

	/**
	 * Set the root directory to backup from
	 *
	 * @param string $path
	 * @throws Exception
	 */
	public function set_root( $path ) {

    	if ( empty( $path ) || ! is_string( $path ) || ! is_dir ( $path ) )
    		throw new Exception( sprintf( __( 'Invalid root path %s must be a valid directory path', 'wpremote' ), '<code>' . $path . '</code>' ) );

    	$this->root = self::conform_dir( $path );

    }

    /**
     * Get the directory backups are saved to
     *
     * @access public
     * @return string
     */
    public function get_path() {

		if ( empty( $this->path ) )
			$this->set_path( self::conform_dir( self::get_path_default() ) );

        return $this->path;

    }

	/**
	 * Get default backup path to save to.
	 *
	 * @return string
	 */
	protected function get_path_default() {
		return WP_CONTENT_DIR . '/backups';
	}

	/**
	 * Set the directory backups are saved to
	 *
	 * @param string $path
	 * @throws Exception
	 */
	public function set_path( $path ) {

    	if ( empty( $path ) || ! is_string( $path ) )
    		throw new Exception( sptrinf( __( 'Invalid backup path %s must be a non empty (string)', wpremote ), '<code>' . $path . '</code>' ) );

    	$this->path = self::conform_dir( $path );

    }

	/**
	 * Get the archive method that was used for the backup
	 *
	 * Will be either zip, ZipArchive or PclZip
	 *
	 * @access public
	 */
	public function get_archive_method() {
		return $this->archive_method;
	}

	/**
	 * Get the database dump method that was used for the backup
	 *
	 * Will be either mysqldump or mysqldump_fallback
	 *
	 * @access public
	 */
	public function get_mysqldump_method() {
		return $this->mysqldump_method;
	}

	/**
	 * Whether or not to use the file manifest
	 * 
	 * @access public
	 */
	public function is_using_file_manifest() {
		return apply_filters( 'hmbkp_use_file_manifest', (bool)$this->using_file_manifest );
	}

	/**
	 * Set whether or not to use the file manifest
	 * 
	 * @access public
	 */
	public function set_is_using_file_manifest( $val ) {
		$this->using_file_manifest = (bool)$val;
	}

	/**
	 * Create a series of file manifests for the backup
	 * 
	 * @access private
	 */
	private function create_file_manifests() {

		if ( is_dir( $this->get_file_manifest_dirpath() ) )
			$this->rmdir_recursive( $this->get_file_manifest_dirpath() );

		mkdir( $this->get_file_manifest_dirpath(), 0755 );

		// Protect against directory browsing by including a index.html file
		$index = $this->get_file_manifest_dirpath() . '/index.html';
		if ( ! file_exists( $index ) && is_writable( $this->get_file_manifest_dirpath() ) )
			file_put_contents( $index, '' );

		$excludes = $this->exclude_string( 'regex' );

		$file_manifest = array();
		$this->file_manifest_remaining = 0;
		$file_manifest_file_count = 0;
		$current_batch = 0;
		foreach( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			// Skip unreadable files
			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
				continue;

		    // Excludes
		    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) )
		        continue;

			if ( $file->isDir() )
				$line = trailingslashit( str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) );

			elseif ( $file->isFile() )
				$line = str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) );

			// File manifest is full
			if ( ! empty( $current_file ) && $current_batch >= $this->file_manifest_per_batch ) {

				@fclose( $current_file );
				$current_file = false;

			}

			// Create a new file manifest
			if ( empty( $current_file ) ) {

				$file_manifest_file_count++;
				$file_manifest_filename = str_pad( $file_manifest_file_count, 10, "0", STR_PAD_LEFT );
				if ( ! $current_file = @fopen( $this->get_file_manifest_dirpath() . '/' . $file_manifest_filename . '.txt', 'w' ) )
					return false;

				$current_batch = 0;
			}

			// Write the line to the file manifest if it isn't empty for some reason
			if ( ! empty( $line ) ) {
				@fwrite( $current_file, $line . PHP_EOL );
				unset( $line );
				$this->file_manifest_remaining++;
				$current_batch++;
			}

		}

		@file_put_contents( $this->get_path() . '/.file-manifest-remaining', $this->file_manifest_remaining );

		return true;
	}

	/**
	 * Delete the current file manifest
	 * 
	 * @access private
	 */
	private function delete_current_file_manifest() {

		if ( ! file_exists( $this->current_file_manifest ) )
			return false;

		// Remove the file manifest because it's already been archived
		unlink( $this->current_file_manifest );

		// Update the count of remaining files.
		$this->file_manifest_remaining = $this->file_manifest_remaining - count( $this->file_manifest_already_archived );
		if ( $this->file_manifest_remaining < 0 )
			$this->file_manifest_remaining = 0;
		file_put_contents( $this->get_path() . '/.file-manifest-remaining', $this->file_manifest_remaining );

		$this->file_manifest_already_archived = array();

	}


	/**
	 * Get the path to the file manifest directory
	 * 
	 * @access private
	 */
	protected function get_file_manifest_dirpath() {
		return $this->get_path() . '/.file-manifests';
	}

	/**
	 * Get batch of files to archive from the file manifest
	 * Ignore any files that already have been archived
	 * 
	 * @access private
	 */
	private function get_next_files_from_file_manifest() {

		if ( ! is_dir( $this->get_file_manifest_dirpath() ) )
			return array();

		$files = glob( $this->get_file_manifest_dirpath() . '/*.txt' );
		if ( empty( $files ) )
			return array();

		$this->current_file_manifest = array_shift( $files );

		$files = file_get_contents( $this->current_file_manifest );
		$files = array_map( 'trim', explode( PHP_EOL, $files ) );
		if ( empty( $files ) )
			return array();

		$this->file_manifest_remaining = (int)file_get_contents( $this->get_path() . '/.file-manifest-remaining' );

		return $files;
	}

	/**
	 * Get the backup type
	 *
	 * Defaults to complete
	 *
	 * @access public
	 */
	public function get_type() {

		if ( empty( $this->type ) )
			$this->set_type( 'complete' );

		return $this->type;

	}

	/**
	 * Set the backup type
	 *
	 * $type must be one of complete, database or file
	 * @param string $type
	 * @throws Exception
	 */
	public function set_type( $type ) {

		if ( ! is_string( $type ) || ! in_array( $type, array( 'file', 'database', 'complete' ) ) )
			throw new Exception( sprintf( __( 'Invalid backup type %s must be one of (string) file, database or complete', 'wpremote' ), '<code>' . $type . '</code>' ) );

		$this->type = $type;

	}

	/**
	 * Get the path to the mysqldump bin
	 *
	 * If not explicitly set will attempt to work
	 * it out by checking common locations
	 *
	 * @access public
	 * @return string
	 */
	public function get_mysqldump_command_path() {

		// Check shell_exec is available
		if ( ! self::is_shell_exec_available() )
			return '';

		// Return now if it's already been set
		if ( isset( $this->mysqldump_command_path ) )
			return $this->mysqldump_command_path;

		$this->mysqldump_command_path = '';

		// Does mysqldump work
		if ( is_null( shell_exec( 'hash mysqldump 2>&1' ) ) ) {

			// If so store it for later
			$this->set_mysqldump_command_path( 'mysqldump' );

			// And return now
			return $this->mysqldump_command_path;

		}

		// List of possible mysqldump locations
		$mysqldump_locations = array(
			'/usr/local/bin/mysqldump',
			'/usr/local/mysql/bin/mysqldump',
			'/usr/mysql/bin/mysqldump',
			'/usr/bin/mysqldump',
			'/opt/local/lib/mysql6/bin/mysqldump',
			'/opt/local/lib/mysql5/bin/mysqldump',
			'/opt/local/lib/mysql4/bin/mysqldump',
			'/xampp/mysql/bin/mysqldump',
			'/Program Files/xampp/mysql/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 6.0/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.5/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.4/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.1/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.0/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 4.1/bin/mysqldump',
			'/opt/local/bin/mysqldump',
		);

		// Find the one which works
		foreach ( $mysqldump_locations as $location )
		    if ( @is_executable( self::conform_dir( $location ) ) )
	 	    	$this->set_mysqldump_command_path( $location );

		return $this->mysqldump_command_path;

	}

	/**
	 * Set the path to the mysqldump bin
	 *
	 * Setting the path to false will cause the database
	 * dump to use the php fallback
	 *
	 * @access public
	 * @param mixed $path
	 */
	public function set_mysqldump_command_path( $path ) {

		$this->mysqldump_command_path = $path;

	}

	/**
	 * Get the path to the zip bin
	 *
	 * If not explicitly set will attempt to work
	 * it out by checking common locations
	 *
	 * @access public
	 * @return string
	 */
	public function get_zip_command_path() {

		// Check shell_exec is available
		if ( ! self::is_shell_exec_available() )
			return '';

		// Return now if it's already been set
		if ( isset( $this->zip_command_path ) )
			return $this->zip_command_path;

		$this->zip_command_path = '';

		// Does zip work
		if ( is_null( shell_exec( 'hash zip 2>&1' ) ) ) {

			// If so store it for later
			$this->set_zip_command_path( 'zip' );

			// And return now
			return $this->zip_command_path;

		}

		// List of possible zip locations
		$zip_locations = array(
			'/usr/bin/zip',
			'/opt/local/bin/zip',
		);

		// Find the one which works
		foreach ( $zip_locations as $location )
			if ( @is_executable( self::conform_dir( $location ) ) )
				$this->set_zip_command_path( $location );

		return $this->zip_command_path;

	}

	/**
	 * Set the path to the zip bin
	 *
	 * Setting the path to false will cause the database
	 * dump to use the php fallback
	 *
	 * @access public
	 * @param mixed $path
	 */
	public function set_zip_command_path( $path ) {

		$this->zip_command_path = $path;

	}

	/**
	 * Set up the ZipArchive instance if ZipArchive is available
	 */
	protected function &setup_ziparchive() {

		// Instance is already open
		if ( ! empty( $this->ziparchive ) ) {
			$this->ziparchive->open( $this->get_archive_filepath(), ZIPARCHIVE::CREATE );
			return $this->ziparchive;
		}

		$ziparchive = new ZipArchive;

		// Try opening ZipArchive
		if ( ! file_exists( $this->get_archive_filepath() ) )
			$ret = $ziparchive->open( $this->get_archive_filepath(), ZIPARCHIVE::CREATE );
		else
			$ret = $ziparchive->open( $this->get_archive_filepath() );

		// File couldn't be opened
		if ( ! $ret )
			return false;

		// Try closing ZipArchive
		$ret = $ziparchive->close();

		// File couldn't be closed
		if ( ! $ret )
			return false;

		// Open it once more
		if ( ! file_exists( $this->get_archive_filepath() ) )
			$ziparchive->open( $this->get_archive_filepath(), ZIPARCHIVE::CREATE );
		else
			$ziparchive->open( $this->get_archive_filepath() );

		$this->ziparchive = $ziparchive;
		return $this->ziparchive;
	}

	/**
	 * Set up the PclZip instance
	 *
	 * @access protected
	 */
	protected function &setup_pclzip() {

		if ( empty( $this->pclzip ) ) {
			$this->load_pclzip();
			$this->pclzip = new PclZip( $this->get_archive_filepath() );
		}
		return $this->pclzip;
	}

	protected function do_action( $action ) {

		do_action( $action, $this );

	}

	/**
	 * Kick off a backup
	 *
	 * @access public
	 * @return bool
	 */
	public function backup() {

		$this->do_action( 'hmbkp_backup_started' );

		// Backup database
		if ( $this->get_type() !== 'file' )
		    $this->dump_database();

		// Zip everything up
		$this->archive();

		$this->do_action( 'hmbkp_backup_complete' );

	}

	/**
	 * Create the mysql backup
	 *
	 * Uses mysqldump if available, falls back to PHP
	 * if not.
	 *
	 * @access public
	 */
	public function dump_database() {

		if ( $this->get_mysqldump_command_path() )
			$this->mysqldump();

		if ( empty( $this->mysqldump_verified ) )
			$this->mysqldump_fallback();

		$this->do_action( 'hmbkp_mysqldump_finished' );

	}

	public function mysqldump() {

		$this->mysqldump_method = 'mysqldump';

		$this->do_action( 'hmbkp_mysqldump_started' );

		$host = explode( ':', DB_HOST );

		$host = reset( $host );
		$port = strpos( DB_HOST, ':' ) ? end( explode( ':', DB_HOST ) ) : '';

		// Path to the mysqldump executable
		$cmd = escapeshellarg( $this->get_mysqldump_command_path() );

		// We don't want to create a new DB
		$cmd .= ' --no-create-db';

		// Allow lock-tables to be overridden
		if ( ! defined( 'HMBKP_MYSQLDUMP_SINGLE_TRANSACTION' ) || HMBKP_MYSQLDUMP_SINGLE_TRANSACTION !== false )
			$cmd .= ' --single-transaction';

		// Make sure binary data is exported properly
		$cmd .= ' --hex-blob';

		// Username
		$cmd .= ' -u ' . escapeshellarg( DB_USER );

		// Don't pass the password if it's blank
		if ( DB_PASSWORD )
		    $cmd .= ' -p'  . escapeshellarg( DB_PASSWORD );

		// Set the host
		$cmd .= ' -h ' . escapeshellarg( $host );

		// Set the port if it was set
		if ( ! empty( $port ) && is_numeric( $port ) )
		    $cmd .= ' -P ' . $port;

		// The file we're saving too
		$cmd .= ' -r ' . escapeshellarg( $this->get_database_dump_filepath() );

		// The database we're dumping
		$cmd .= ' ' . escapeshellarg( DB_NAME );

		// Pipe STDERR to STDOUT
		$cmd .= ' 2>&1';

		// Store any returned data in an error
		$stderr = shell_exec( $cmd );

		// Skip the new password warning that is output in mysql > 5.6 (@see http://bugs.mysql.com/bug.php?id=66546)
		if ( trim( $stderr ) === 'Warning: Using a password on the command line interface can be insecure.' ) {
			$stderr = '';
		}

		if ( $stderr ) {
			$this->error( $this->get_mysqldump_method(), $stderr );
		}

		$this->verify_mysqldump();

	}

	/**
	 * PHP mysqldump fallback functions, exports the database to a .sql file
	 *
	 * @access public
	 */
	public function mysqldump_fallback() {

		$this->errors_to_warnings( $this->get_mysqldump_method() );

		$this->mysqldump_method = 'mysqldump_fallback';

		$this->do_action( 'hmbkp_mysqldump_started' );

	    $this->db = @mysqli_connect( 'p:'.DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );

	    if ( ! $this->db )
	    	$this->db = mysqli_connect( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );

	    if ( ! $this->db )
	    	return;

	   // mysql_select_db( DB_NAME, $this->db );

	    if ( function_exists( 'mysqli_set_charset') )
	    	mysqli_set_charset( $this->db, DB_CHARSET );

	    // Begin new backup of MySql
	    $tables = mysqli_query( $this->db, 'SHOW TABLES' );

	    $sql_file  = "# WordPress : " . get_bloginfo( 'url' ) . " MySQL database backup\n";
	    $sql_file .= "#\n";
	    $sql_file .= "# Generated: " . date( 'l j. F Y H:i T' ) . "\n";
	    $sql_file .= "# Hostname: " . DB_HOST . "\n";
	    $sql_file .= "# Database: " . $this->sql_backquote( DB_NAME ) . "\n";
	    $sql_file .= "# --------------------------------------------------------\n";

	    while ( $row = mysqli_fetch_array($tables) ) {
	    	$curr_table = $row[0];

	    	// Create the SQL statements
	    	$sql_file .= "# --------------------------------------------------------\n";
	    	$sql_file .= "# Table: " . $this->sql_backquote( $curr_table ) . "\n";
	    	$sql_file .= "# --------------------------------------------------------\n";

	    	$this->make_sql( $sql_file, $curr_table );

	    }

	}

	/**
	 * Zip up all the files.
	 *
	 * Attempts to use the shell zip command, if
	 * thats not available then it falls back to
	 * PHP ZipArchive and finally PclZip.
	 *
	 * @access public
	 */
	public function archive() {

		// If using a manifest, perform the backup in chunks
		if ( 'database' !== $this->get_type()
			&& $this->is_using_file_manifest()
			&& $this->create_file_manifests() ) {
			
			$this->archive_via_file_manifest();

		} else {

			$this->archive_via_single_request();

		}

	}

	/**
	 * Archive with a file manifest
	 *
	 * @access private
	 */
	private function archive_via_file_manifest() {

		$errors = array();

		// Back up files from the file manifest in chunks
		$next_files = $this->get_next_files_from_file_manifest();
		do {

			$this->do_action( 'hmbkp_archive_started' );

			// `zip` is the most performant archive method
			if ( $this->get_zip_command_path() ) {
				$this->archive_method = 'zip_files';
				$error = $this->zip_files( $next_files );
			}

			// ZipArchive is also pretty fast for chunked backups
			else if ( class_exists( 'ZipArchive' ) && empty( $this->skip_zip_archive ) ) {
				$this->archive_method = 'zip_archive_files';

				$ret = $this->zip_archive_files( $next_files );
				if ( ! $ret ) {
					$this->skip_zip_archive = true;
					continue;
				}
			}

			// Last opportunity
			else {
				$this->archive_method = 'pcl_zip_files';
				$error = $this->pcl_zip_files( $next_files );
			}

			if ( ! empty( $error ) ) {
				$errors[] = $error;
				unset( $error );
			}

			// Update the file manifest with these files that were archived
			$this->file_manifest_already_archived = array_merge( $this->file_manifest_already_archived, $next_files );
			$this->delete_current_file_manifest();

			// Get the next set of files to archive
			$next_files = $this->get_next_files_from_file_manifest();

		} while( ! empty( $next_files ) );

		// If the database should be included in the backup, it's included last
		if ( 'file' !== $this->get_type() && file_exists( $this->get_database_dump_filepath() ) ) {

			switch ( $this->archive_method ) {

				case 'zip_archive_files':

					$zip = &$this->setup_ziparchive();

					$zip->addFile( $this->get_database_dump_filepath(), $this->get_database_dump_filename() );

					$zip->close();

					break;

				case 'zip_files':

					$error = shell_exec( 'cd ' . escapeshellarg( $this->get_path() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -uq ' . escapeshellarg( $this->get_archive_filepath() ) . ' ' . escapeshellarg( $this->get_database_dump_filename() ) . ' 2>&1' );

					break;

				case 'pcl_zip_files':

					$pclzip = &$this->setup_pclzip();

					if ( ! $pclzip->add( $this->get_database_dump_filepath(), PCLZIP_OPT_REMOVE_PATH, $this->get_path() ) )
						$this->warning( $this->get_archive_method(), $pclzip->errorInfo( true ) );
			
					break;
			}

			if ( ! empty( $error ) ) {
				$errors[] = $error;
				unset( $error );
			}
		}

		// If the methods produced any errors, log them
		if ( ! empty( $errors ) )
			$this->warning( $this->get_archive_method(), implode( ', ', $errors ) );

		// ZipArchive has some special reporting requirements
		if ( ! empty( $this->ziparchive ) ) {

			if ( $this->ziparchive->status )
				$this->warning( $this->get_archive_method(), $this->ziparchive->status );

			if ( $this->ziparchive->statusSys )
				$this->warning( $this->get_archive_method(), $this->ziparchive->statusSys );

		}

		// Verify and remove if errors
		$this->verify_archive();

		// Remove the file manifest
		if ( is_dir( $this->get_file_manifest_dirpath() ) )
			$this->rmdir_recursive( $this->get_file_manifest_dirpath() );

		// Delete the database dump file
		if ( file_exists( $this->get_database_dump_filepath() ) )
			unlink( $this->get_database_dump_filepath() );

		$this->do_action( 'hmbkp_archive_finished' );

	}

	/**
	 * Archive using our traditional method of one request
	 *
	 * @access private
	 */
	private function archive_via_single_request() {

		// Do we have the path to the zip command
		if ( $this->get_zip_command_path() )
			$this->zip();

		// If not or if the shell zip failed then use ZipArchive
		if ( empty( $this->archive_verified ) && class_exists( 'ZipArchive' ) && empty( $this->skip_zip_archive ) )
			$this->zip_archive();

		// If ZipArchive is unavailable or one of the above failed
		if ( empty( $this->archive_verified ) )
			$this->pcl_zip();

		// Delete the database dump file
		if ( file_exists( $this->get_database_dump_filepath() ) )
			unlink( $this->get_database_dump_filepath() );

		$this->do_action( 'hmbkp_archive_finished' );

	}

	/**
	 * Restart a failed archive process
	 * 
	 * @access public
	 */
	public function restart_archive() {

		if ( $this->is_using_file_manifest() ) {

			$this->archive_via_file_manifest();

		} else {

			$this->archive_via_single_request();

		}

		$this->do_action( 'hmbkp_backup_complete' );
	}

	/**
	 * Zip using the native zip command
	 *
	 * @access public
	 */
	public function zip() {

		$this->archive_method = 'zip';

		$this->do_action( 'hmbkp_archive_started' );

		// Zip up $this->root with excludes
		if ( $this->get_type() !== 'database' && $this->exclude_string( 'zip' ) ) {
		    $stderr = shell_exec( 'cd ' . escapeshellarg( $this->get_root() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -rq ' . escapeshellarg( $this->get_archive_filepath() ) . ' ./' . ' -x ' . $this->exclude_string( 'zip' ) . ' 2>&1' );

		// Zip up $this->root without excludes
		} elseif ( $this->get_type() !== 'database' ) {
		    $stderr = shell_exec( 'cd ' . escapeshellarg( $this->get_root() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -rq ' . escapeshellarg( $this->get_archive_filepath() ) . ' ./' . ' 2>&1' );

		}

		// Add the database dump to the archive
		if ( $this->get_type() !== 'file' && file_exists( $this->get_database_dump_filepath() ) )
		    $stderr = shell_exec( 'cd ' . escapeshellarg( $this->get_path() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -uq ' . escapeshellarg( $this->get_archive_filepath() ) . ' ' . escapeshellarg( $this->get_database_dump_filename() ) . ' 2>&1' );

		if ( ! empty( $stderr ) )
			$this->warning( $this->get_archive_method(), $stderr );

		$this->verify_archive();
	}

	/**
	 * Zip one or more files
	 *
	 * @access private
	 */
	private function zip_files( $files ) {

		// Not necessary to include directories when using `zip`
		foreach( $files as $key => $file ) {

			if ( ! is_dir( $file ) )
				continue;

			unset( $files[$key] );
		}

		return shell_exec( 'cd ' . escapeshellarg( $this->get_root() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' ' . escapeshellarg( $this->get_archive_filepath() ) . ' ' . implode( ' ', $files ) . ' -q 2>&1' );
	}

	/**
	 * Fallback for creating zip archives if zip command is
	 * unavailable.
	 */
	public function zip_archive() {

		$this->errors_to_warnings( $this->get_archive_method() );
		$this->archive_method = 'ziparchive';

		$this->do_action( 'hmbkp_archive_started' );

		if ( false === ( $zip = &$this->setup_ziparchive() ) )
			return;

		$excludes = $this->exclude_string( 'regex' );

		if ( $this->get_type() !== 'database' ) {

			$files_added = 0;

			foreach ( $this->get_files() as $file ) {

				// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
				if ( method_exists( $file, 'isDot' ) && $file->isDot() )
					continue;

				// Skip unreadable files
				if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
					continue;

			    // Excludes
			    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) )
			        continue;

			    if ( $file->isDir() )
					$zip->addEmptyDir( trailingslashit( str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) );

			    elseif ( $file->isFile() )
					$zip->addFile( $file->getPathname(), str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) );

				if ( ++$files_added % 500 === 0 )
					if ( ! $zip->close() || ! $zip->open( $this->get_archive_filepath(), ZIPARCHIVE::CREATE ) )
						return;

			}

		}

		// Add the database
		if ( $this->get_type() !== 'file' && file_exists( $this->get_database_dump_filepath() ) )
			$zip->addFile( $this->get_database_dump_filepath(), $this->get_database_dump_filename() );

		if ( $zip->status )
			$this->warning( $this->get_archive_method(), $zip->status );

		if ( $zip->statusSys )
			$this->warning( $this->get_archive_method(), $zip->statusSys );

		$zip->close();

		$this->verify_archive();

	}

	/**
	 * Zip Archive one or more files
	 * 
	 * @access private
	 */
	private function zip_archive_files( $files ) {

		if ( false === ( $zip = &$this->setup_ziparchive() ) )
			return false;

		foreach( $files as $file ) {

			$full_path = trailingslashit( $this->get_root() ) . $file;
			if ( is_dir( $full_path ) )
				$zip->addEmptyDir( $file );
			else
				$zip->addFile( $full_path, $file );

		}

		// Avoid limitations in ZipArchive by making sure we save each batch to disk
		$zip->close();
		return true;
	}

	/**
	 * Fallback for creating zip archives if zip command and ZipArchive are
	 * unavailable.
	 *
	 * Uses the PclZip library that ships with WordPress
	 */
	public function pcl_zip() {

		$this->errors_to_warnings( $this->get_archive_method() );
		$this->archive_method = 'pclzip';

		$this->do_action( 'hmbkp_archive_started' );

		global $_wprp_hmbkp_exclude_string;

		$_wprp_hmbkp_exclude_string = $this->exclude_string( 'regex' );

		$archive = &$this->setup_pclzip();

		// Zip up everything
		if ( $this->get_type() !== 'database' )
			if ( ! $archive->add( $this->get_root(), PCLZIP_OPT_REMOVE_PATH, $this->get_root(), PCLZIP_CB_PRE_ADD, 'wprp_hmbkp_pclzip_callback' ) )
				$this->warning( $this->get_archive_method(), $archive->errorInfo( true ) );

		// Add the database
		if ( $this->get_type() !== 'file' && file_exists( $this->get_database_dump_filepath() ) )
			if ( ! $archive->add( $this->get_database_dump_filepath(), PCLZIP_OPT_REMOVE_PATH, $this->get_path() ) )
				$this->warning( $this->get_archive_method(), $archive->errorInfo( true ) );

		unset( $GLOBALS['_wprp_hmbkp_exclude_string'] );

		$this->verify_archive();

	}

	/**
	 * Use PclZip to archive batches of files
	 */
	private function pcl_zip_files( $files ) {

		$this->errors_to_warnings( $this->get_archive_method() );

		$pclzip = &$this->setup_pclzip();

		foreach( $files as $file ) {

			$full_path = trailingslashit( $this->get_root() ) . $file;
			if ( is_dir( $full_path ) )
				continue;
			
			if ( ! $pclzip->add( $full_path, PCLZIP_OPT_REMOVE_PATH, $this->get_root() ) )
				$this->warning( $this->get_archive_method(), $pclzip->errorInfo( true ) );

		}

	}

	public function verify_mysqldump() {

		$this->do_action( 'hmbkp_mysqldump_verify_started' );

		// If we've already passed then no need to check again
		if ( ! empty( $this->mysqldump_verified ) )
			return true;

		// If there are mysqldump errors delete the database dump file as mysqldump will still have written one
		if ( $this->get_errors( $this->get_mysqldump_method() ) && file_exists( $this->get_database_dump_filepath() ) )
			unlink( $this->get_database_dump_filepath() );

		// If we have an empty file delete it
		if ( @filesize( $this->get_database_dump_filepath() ) === 0 )
			unlink( $this->get_database_dump_filepath() );

		// If the file still exists then it must be good
		if ( file_exists( $this->get_database_dump_filepath() ) )
			return $this->mysqldump_verified = true;

		return false;


	}

	/**
	 * Verify that the archive is valid and contains all the files it should contain.
	 *
	 * @access public
	 * @return bool
	 */
	public function verify_archive() {

		$this->do_action( 'hmbkp_archive_verify_started' );

		// If we've already passed then no need to check again
		if ( ! empty( $this->archive_verified ) )
			return true;

		// If there are errors delete the backup file.
		if ( $this->get_errors( $this->get_archive_method() ) && file_exists( $this->get_archive_filepath() ) )
			unlink( $this->get_archive_filepath() );

		// If the archive file still exists assume it's good
		if ( file_exists( $this->get_archive_filepath() ) )
			return $this->archive_verified = true;

		return false;

	}

	/**
	 * Return an array of all files in the filesystem
	 *
	 * @access public
	 * @return array
	 */
	public function get_files() {

		if ( ! empty( $this->files ) )
			return $this->files;

		$this->files = array();

		// We only want to use the RecursiveDirectoryIterator if the FOLLOW_SYMLINKS flag is available
		if ( defined( 'RecursiveDirectoryIterator::FOLLOW_SYMLINKS' ) ) {

			$this->files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->get_root(), RecursiveDirectoryIterator::FOLLOW_SYMLINKS ), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );

			// Skip dot files if the SKIP_Dots flag is available
			if ( defined( 'RecursiveDirectoryIterator::SKIP_DOTS' ) )
				$this->files->setFlags( RecursiveDirectoryIterator::SKIP_DOTS + RecursiveDirectoryIterator::FOLLOW_SYMLINKS );


		// If RecursiveDirectoryIterator::FOLLOW_SYMLINKS isn't available then fallback to a less memory efficient method
		} else {

			$this->files = $this->get_files_fallback( $this->get_root() );

		}

		return $this->files;

	}

	/**
	 * Fallback function for generating a filesystem
	 * array
	 *
	 * Used if RecursiveDirectoryIterator::FOLLOW_SYMLINKS isn't available
	 *
	 * @access private
	 * @param string $dir
	 * @param array $files. (default: array())
	 * @return array
	 */
	private function get_files_fallback( $dir, $files = array() ) {

	    $handle = opendir( $dir );

	    $excludes = $this->exclude_string( 'regex' );

	    while ( $file = readdir( $handle ) ) :

	    	// Ignore current dir and containing dir
	    	if ( $file === '.' || $file === '..' )
	    		continue;

	    	$filepath = self::conform_dir( trailingslashit( $dir ) . $file );
	    	$file = str_ireplace( trailingslashit( $this->get_root() ), '', $filepath );

	    	$files[] = new SplFileInfo( $filepath );

	    	if ( is_dir( $filepath ) )
	    		$files = $this->get_files_fallback( $filepath, $files );

		endwhile;

		return $files;

	}

	/**
	 * Returns an array of files that will be included in the backup.
	 *
	 * @access public
	 * @return array
	 */
	public function get_included_files() {

		if ( ! empty( $this->included_files ) )
			return $this->included_files;

		$this->included_files = array();

		$excludes = $this->exclude_string( 'regex' );

		foreach ( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			// Skip unreadable files
			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
				continue;

		    // Excludes
		    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) )
		    	continue;

		    $this->included_files[] = $file;

		}

		return $this->included_files;

	}

	/**
	 * Return the number of files included in the backup
	 *
	 * @access public
	 * @return array
	 */
	public function get_included_file_count() {

		if ( ! empty( $this->included_file_count ) )
			return $this->included_file_count;

		$this->included_file_count = 0;

		$excludes = $this->exclude_string( 'regex' );

		foreach ( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			// Skip unreadable files
			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
				continue;

		    // Excludes
		    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) )
		    	continue;

		    $this->included_file_count++;

		}

		return $this->included_file_count;

	}

	/**
	 * Returns an array of files that match the exclude rules.
	 *
	 * @access public
	 * @return array
	 */
	public function get_excluded_files() {

		if ( ! empty( $this->excluded_files ) )
			return $this->excluded_files;

		$this->excluded_files = array();

		$excludes = $this->exclude_string( 'regex' );

		foreach ( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			// Skip unreadable files
			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
				continue;

		    // Excludes
		    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) )
		    	$this->excluded_files[] = $file;

		}

		return $this->excluded_files;

	}

	/**
	 * Return the number of files excluded from the backup
	 *
	 * @access public
	 * @return array
	 */
	public function get_excluded_file_count() {

		if ( ! empty( $this->excluded_file_count ) )
			return $this->excluded_file_count;

		$this->excluded_file_count = 0;

		$excludes = $this->exclude_string( 'regex' );

		foreach ( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			// Skip unreadable files
			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
				continue;

		    // Excludes
		    if ( $excludes && preg_match( '(' . $excludes . ')', str_ireplace( trailingslashit( $this->get_root() ), '', self::conform_dir( $file->getPathname() ) ) ) )
		    	$this->excluded_file_count++;

		}

		return $this->excluded_file_count;

	}

	/**
	 * Returns an array of unreadable files.
	 *
	 * @access public
	 * @return array
	 */
	public function get_unreadable_files() {

		if ( ! empty( $this->unreadable_files ) )
			return $this->unreadable_files;

		$this->unreadable_files = array();

		foreach ( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
			  	$this->unreadable_files[] = $file;

		}

		return $this->unreadable_files;

	}

	/**
	 * Return the number of unreadable files.
	 *
	 * @access public
	 * @return array
	 */
	public function get_unreadable_file_count() {

		if ( ! empty( $this->get_unreadable_file_count ) )
			return $this->get_unreadable_file_count;

		$this->get_unreadable_file_count = 0;

		foreach ( $this->get_files() as $file ) {

			// Skip dot files, they should only exist on versions of PHP between 5.2.11 -> 5.3
			if ( method_exists( $file, 'isDot' ) && $file->isDot() )
				continue;

			if ( ! @realpath( $file->getPathname() ) || ! $file->isReadable() )
			  	$this->get_unreadable_file_count++;

		}

		return $this->get_unreadable_file_count;

	}

	private function load_pclzip() {

		// Load PclZip
		if ( ! defined( 'PCLZIP_TEMPORARY_DIR' ) )
			define( 'PCLZIP_TEMPORARY_DIR', trailingslashit( $this->get_path() ) );

		require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

	}

	/**
	 * Get an array of exclude rules
	 *
	 * The backup path is automatically excluded
	 *
	 * @access public
	 * @return array
	 */
	public function get_excludes() {

		$excludes = array();

		if ( isset( $this->excludes ) )
			$excludes = $this->excludes;

		// If path() is inside root(), exclude it
		if ( strpos( $this->get_path(), $this->get_root() ) !== false )
			array_unshift( $excludes, trailingslashit( $this->get_path() ) );

		return array_unique( $excludes );

	}

	/**
	 * Set the excludes, expects and array
	 *
	 * @access public
	 * @param  Array $excludes
	 * @param Bool $append
	 */
	public function set_excludes( $excludes, $append = false ) {

		if ( is_string( $excludes ) )
			$excludes = explode( ',', $excludes );

		if ( $append )
			$excludes = array_merge( $this->excludes, $excludes );

		$this->excludes = array_filter( array_unique( array_map( 'trim', $excludes ) ) );

	}

	/**
	 * Generate the exclude param string for the zip backup
	 *
	 * Takes the exclude rules and formats them for use with either
	 * the shell zip command or pclzip
	 *
	 * @access public
	 * @param string $context. (default: 'zip')
	 * @return string
	 */
	public function exclude_string( $context = 'zip' ) {

		// Return a comma separated list by default
		$separator = ', ';
		$wildcard = '';

		// The zip command
		if ( $context === 'zip' ) {
			$wildcard = '*';
			$separator = ' -x ';

		// The PclZip fallback library
		} elseif ( $context === 'regex' ) {
			$wildcard = '([\s\S]*?)';
			$separator = '|';

		}

		$excludes = $this->get_excludes();

		foreach( $excludes as $key => &$rule ) {

			$file = $absolute = $fragment = false;

			// Files don't end with /
			if ( ! in_array( substr( $rule, -1 ), array( '\\', '/' ) ) )
				$file = true;

			// If rule starts with a / then treat as absolute path
			elseif ( in_array( substr( $rule, 0, 1 ), array( '\\', '/' ) ) )
				$absolute = true;

			// Otherwise treat as dir fragment
			else
				$fragment = true;

			// Strip $this->root and conform
			$rule = str_ireplace( $this->get_root(), '', untrailingslashit( self::conform_dir( $rule ) ) );

			// Strip the preceeding slash
			if ( in_array( substr( $rule, 0, 1 ), array( '\\', '/' ) ) )
				$rule = substr( $rule, 1 );

			// Escape string for regex
			if ( $context === 'regex' )
				$rule = str_replace( '.', '\.', $rule );

			// Convert any existing wildcards
			if ( $wildcard !== '*' && strpos( $rule, '*' ) !== false )
				$rule = str_replace( '*', $wildcard, $rule );

			// Wrap directory fragments and files in wildcards for zip
			if ( $context === 'zip' && ( $fragment || $file ) )
				$rule = $wildcard . $rule . $wildcard;

			// Add a wildcard to the end of absolute url for zips
			if ( $context === 'zip' && $absolute )
				$rule .= $wildcard;

			// Add and end carrot to files for pclzip but only if it doesn't end in a wildcard
			if ( $file && $context === 'regex' )
				$rule .= '$';

			// Add a start carrot to absolute urls for pclzip
			if ( $absolute && $context === 'regex' )
				$rule = '^' . $rule;

		}

		// Escape shell args for zip command
		if ( $context === 'zip' )
			$excludes = array_map( 'escapeshellarg', array_unique( $excludes ) );

		return implode( $separator, $excludes );

	}

	/**
	 * Add backquotes to tables and db-names in SQL queries. Taken from phpMyAdmin.
	 *
	 * @param $a_name
	 * @return array|string
	 */
	private function sql_backquote( $a_name ) {

	    if ( ! empty( $a_name ) && $a_name !== '*' ) {

	    	if ( is_array( $a_name ) ) {

	    		$result = array();

	    		reset( $a_name );

	    		while ( list( $key, $val ) = each( $a_name ) )
	    			$result[$key] = '`' . $val . '`';

	    		return $result;

	    	} else {

	    		return '`' . $a_name . '`';

	    	}

	    } else {

	    	return $a_name;

	    }

	}

	/**
	 * Reads the Database table in $table and creates
	 * SQL Statements for recreating structure and data
	 * Taken partially from phpMyAdmin and partially from
	 * Alain Wolf, Zurich - Switzerland
	 * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
	 *
	 * @access private
	 * @param string $sql_file
	 * @param string $table
	 */
	private function make_sql( $sql_file, $table ) {

	    // Add SQL statement to drop existing table
	    $sql_file .= "\n";
	    $sql_file .= "\n";
	    $sql_file .= "#\n";
	    $sql_file .= "# Delete any existing table " . $this->sql_backquote( $table ) . "\n";
	    $sql_file .= "#\n";
	    $sql_file .= "\n";
	    $sql_file .= "DROP TABLE IF EXISTS " . $this->sql_backquote( $table ) . ";\n";

	    /* Table Structure */

	    // Comment in SQL-file
	    $sql_file .= "\n";
	    $sql_file .= "\n";
	    $sql_file .= "#\n";
	    $sql_file .= "# Table structure of table " . $this->sql_backquote( $table ) . "\n";
	    $sql_file .= "#\n";
	    $sql_file .= "\n";

	    // Get table structure
	    $query = 'SHOW CREATE TABLE ' . $this->sql_backquote( $table );
	    $result = mysqli_query( $this->db, $query );

	    if ( $result ) {

	    	if ( mysqli_num_rows( $result ) > 0 ) {
	    		$sql_create_arr = mysqli_fetch_array( $result );
	    		$sql_file .= $sql_create_arr[1];
	    	}

	    	mysqli_free_result( $result );
	    	$sql_file .= ' ;';

	    }

	    /* Table Contents */

	    // Get table contents
	    $query = 'SELECT * FROM ' . $this->sql_backquote( $table );
	    $result = mysqli_query( $this->db, $query );

	    if ( $result ) {
	    	$fields_cnt = mysqli_num_fields( $result );
	    	$rows_cnt   = mysqli_num_rows( $result );
	    }

	    // Comment in SQL-file
	    $sql_file .= "\n";
	    $sql_file .= "\n";
	    $sql_file .= "#\n";
	    $sql_file .= "# Data contents of table " . $table . " (" . $rows_cnt . " records)\n";
	    $sql_file .= "#\n";

	    // Checks whether the field is an integer or not
	    for ( $j = 0; $j < $fields_cnt; $j++ ) {

	    	$field_obj = mysqli_fetch_field_direct( $result, $j );

	    	$field_set[$j] = $this->sql_backquote( $field_obj->name );
	    	$type = $field_obj->type;

	    	if ( $type === 'tinyint' || $type === 'smallint' || $type === 'mediumint' || $type === 'int' || $type === 'bigint' )
	    		$field_num[$j] = true;

	    	else
	    		$field_num[$j] = false;

	    }

	    // Sets the scheme
	    $entries = 'INSERT INTO ' . $this->sql_backquote( $table ) . ' VALUES (';
	    $search   = array( '\x00', '\x0a', '\x0d', '\x1a' );  //\x08\\x09, not required
	    $replace  = array( '\0', '\n', '\r', '\Z' );
	    $current_row = 0;
	    $batch_write = 0;

	    while ( $row = mysqli_fetch_row( $result ) ) {

	    	$current_row++;

	    	// build the statement
	    	for ( $j = 0; $j < $fields_cnt; $j++ ) {

	    		if ( ! isset($row[$j] ) ) {
	    			$values[]     = 'NULL';

	    		} elseif ( $row[$j] === '0' || $row[$j] !== '' ) {

	    		    // a number
	    		    if ( $field_num[$j] )
	    		    	$values[] = $row[$j];

	    		    else
	    		    	$values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes( $row[$j] ) ) . "'";

	    		} else {
	    			$values[] = "''";

	    		}

	    	}

	    	$sql_file .= " \n" . $entries . implode( ', ', $values ) . ") ;";

	    	// write the rows in batches of 100
	    	if ( $batch_write === 100 ) {
	    		$batch_write = 0;
	    		$this->write_sql( $sql_file );
	    		$sql_file = '';
	    	}

	    	$batch_write++;

	    	unset( $values );

	    }

	    mysqli_free_result( $result );

	    // Create footer/closing comment in SQL-file
	    $sql_file .= "\n";
	    $sql_file .= "#\n";
	    $sql_file .= "# End of data contents of table " . $table . "\n";
	    $sql_file .= "# --------------------------------------------------------\n";
	    $sql_file .= "\n";

		$this->write_sql( $sql_file );

	}

	/**
	 * Better addslashes for SQL queries.
	 * Taken from phpMyAdmin.
	 *
	 * @param string $a_string
	 * @param bool   $is_like
	 * @return mixed
	 */
	private function sql_addslashes( $a_string = '', $is_like = false ) {

	    if ( $is_like )
	    	$a_string = str_replace( '\\', '\\\\\\\\', $a_string );

	    else
	    	$a_string = str_replace( '\\', '\\\\', $a_string );

	    $a_string = str_replace( '\'', '\\\'', $a_string );

	    return $a_string;
	}

	/**
	 * Write the SQL file
	 * @param string $sql
	 * @return bool
	 */
	private function write_sql( $sql ) {

	    $sqlname = $this->get_database_dump_filepath();

	    // Actually write the sql file
	    if ( is_writable( $sqlname ) || ! file_exists( $sqlname ) ) {

	    	if ( ! $handle = @fopen( $sqlname, 'a' ) )
	    		return;

	    	if ( ! @fwrite( $handle, $sql ) )
	    		return;

	    	@fclose( $handle );

	    	return true;

	    }

	}

	/**
	 * Get the errors
	 *
	 * @access public
	 */
	public function get_errors( $context = null ) {

		if ( ! empty( $context ) )
			return isset( $this->errors[$context] ) ? $this->errors[$context] : array();

		return $this->errors;

	}

	/**
	 * Add an error to the errors stack
	 *
	 * @access private
	 * @param string $context
	 * @param mixed $error
	 */
	public function error( $context, $error ) {

		if ( empty( $context ) || empty( $error ) )
			return;

		$this->errors[$context][$_key = md5( implode( ':' , (array) $error ) )] = $error;

		$this->do_action( 'hmbkp_error' );

	}

	/**
	 * Migrate errors to warnings
	 *
	 * @access private
	 * @param string $context. (default: null)
	 */
	private function errors_to_warnings( $context = null ) {

		$errors = empty( $context ) ? $this->get_errors() : array( $context => $this->get_errors( $context ) );

		if ( empty( $errors ) )
			return;

		foreach ( $errors as $error_context => $context_errors )
			foreach( $context_errors as $error )
				$this->warning( $error_context, $error );

		if ( $context )
			unset( $this->errors[$context] );

		else
			$this->errors = array();

	}

	/**
	 * Get the warnings
	 *
	 * @access public
	 */
	public function get_warnings( $context = null ) {

		if ( ! empty( $context ) )
			return isset( $this->warnings[$context] ) ? $this->warnings[$context] : array();

		return $this->warnings;

	}

	/**
	 * Add an warning to the warnings stack
	 *
	 * @access private
	 * @param string $context
	 * @param mixed $warning
	 */
	private function warning( $context, $warning ) {

		if ( empty( $context ) || empty( $warning ) )
			return;

		$this->do_action( 'hmbkp_warning' );

		$this->warnings[$context][$_key = md5( implode( ':' , (array) $warning ) )] = $warning;

	}

	/**
	 * Custom error handler for catching php errors
	 *
	 * @param string $type
	 * @return bool
	 */
	public function error_handler( $type ) {

		// Skip strict & deprecated warnings
		if ( ( defined( 'E_DEPRECATED' ) && $type === E_DEPRECATED ) || ( defined( 'E_STRICT' ) && $type === E_STRICT ) || error_reporting() === 0 )
			return false;

		$args = func_get_args();

		array_shift( $args );

		$this->warning( 'php', implode( ', ', array_splice( $args, 0, 3 ) ) );

		return false;

	}

	/**
	 * Recursively delete a directory including
	 * all the files and sub-directories.
	 *
	 * @param string $dir
	 * @return bool
	 */
	public static function rmdir_recursive( $dir ) {

		if ( is_file( $dir ) )
			@unlink( $dir );

	    if ( ! is_dir( $dir ) )
	    	return false;

	    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );

		foreach ( $files as $file ) {

			if ( $file->isDir() )
				@rmdir( $file->getPathname() );

			else
				@unlink( $file->getPathname() );

		}

		@rmdir( $dir );

	}

}

/**
 * Add file callback for PclZip, excludes files
 * and sets the database dump to be stored in the root
 * of the zip
 *
 * @access private
 * @param string $event
 * @param array &$file
 * @return bool
 */
function wprp_hmbkp_pclzip_callback( $event, &$file ) {

	global $_wprp_hmbkp_exclude_string;

    // Don't try to add unreadable files.
    if ( ! is_readable( $file['filename'] ) || ! file_exists( $file['filename'] ) )
    	return false;

    // Match everything else past the exclude list
    elseif ( $_wprp_hmbkp_exclude_string && preg_match( '(' . $_wprp_hmbkp_exclude_string . ')', $file['stored_filename'] ) )
    	return false;

    return true;

}
