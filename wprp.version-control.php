<?php

class WPRP_Version_Control {

	private static $instance;
	private $systems = array(
		'git' => 'WPRP_Version_Control_System_Git'
	);

	public static function get_instance() {
		if ( ! self::$instance )
			self::$instance = new WPRP_Version_Control();

		return self::$instance;
	}

	/**
	 * Get all of the vcs info for the current site
	 * 
	 * @retun [ root => [string] ]
	 */
	public function get_version_control_information() {

		$data = array(
			'root' => $this->get_root_data()
		);

		return $data;
	}

	/**
	 * Get the info about (if any) the root / full project repo.
	 * 
	 * @return array
	 */
	public function get_root_data() {

		// we want to support WP in a sub dir
		if ( file_exists( ABSPATH . 'wp-config.php') )
			$dir = ABSPATH;
	
		else if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) )
			$dir = dirname( ABSPATH );

		return $this->get_dir_data( $dir );
	}

	public function get_dir_data( $dir  ) {
		$data = array();

		// check the root for all the vcs'
		foreach ( $this->systems as $system_slug => $class ) {
			$system = new $class( $dir );

			if ( $system->is_valid() ) {
				$data['system'] = $system_slug;
				$data['dirty'] = $system->is_dirty();
				$data['branch'] = $system->get_branch();
				break;
			}

		}

		return $data;	
	}
}

class WPRP_Version_Control_System_Git {

	public function __construct( $dir ) {
		$this->dir = $dir;
	}

	public function is_valid() {
		
		return (bool) $this->get_status();
	}

	public function is_dirty() {
		$status = $this->get_status();

		if ( ! $status )
			return false;

		return $status['dirty'];
	}

	public function get_branch() {

		$status = $this->get_status();

		if ( ! $status )
			return null;

		return $status['branch'];
	}

	/**
	 * @return [ dirty => bool, branch => string, ref => string ]
	 */
	private function get_status() {

		if ( ! empty( $this->status ) )
			return $this->status;

		if ( WPRP_HM_Backup::is_shell_exec_available() )
			$this->status = $this->get_status_from_exec();

		else
			$this->status = $this->get_status_from_files();

		return $this->status;
		
	}

	/**
	 * Taken from the great Git Status plugin
	 * https://raw.github.com/johnbillion/wp-git-status
	 * 
	 * @author John Blackbourn
	 * @return [ dirty => bool, branch => string, ref => string ]
	 */
	private function get_status_from_exec() {

		$status = array_filter( explode( "\n", shell_exec( sprintf( 'cd %s; git status', escapeshellarg( $this->dir ) ) ) ) );

		if ( empty( $status ) or ( false !== strpos( $status[0], 'fatal' ) ) )
			return null;

		$end = end( $status );
		$return = array(
			'dirty'  => true,
			'branch' => 'detached',
			'ref' => '',
		);

		if ( preg_match( '/On branch (.+)$/', $status[0], $matches ) )
			$return['branch'] = trim( $matches[1] );

		if ( empty( $end ) or ( false !== strpos( $end, 'nothing to commit' ) ) )
			$return['dirty'] = false;

		$rev = explode( "\n", shell_exec( sprintf( 'cd %s; git rev-parse HEAD', escapeshellarg( $this->dir ) ) ) );

		if ( $rev )
			$return['ref'] = $rev[0];

		return $return;
	}

	/**
	 * Try to get the into about a git dir using file reading only
	 * 
	 * This is useful is shell_exec() is not available
	 * 
	 * @return [ dirty => bool, branch => string, ref => string ]
	 */
	private function get_status_from_files() {

		if ( ! is_dir( $this->dir . '/.git' ) )
			return null;

		$branch = str_replace( 'ref: refs/heads/', '', file_get_contents( $this->dir . '/.git/HEAD' ) );

		return array(
			'dirty' => false, // currently not supported in by reading the files
			'branch' => $branch,
			'ref' => ''
		);
	}
}