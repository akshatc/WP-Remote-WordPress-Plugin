<?php

require_once dirname( __FILE__ ) . '/inc/class-wprp-version-control-system-git.php';

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

