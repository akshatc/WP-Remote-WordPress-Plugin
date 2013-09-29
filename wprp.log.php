<?php

class WPRP_Log {

	static $instance;

	private $disable_logging = false;

	static function get_instance() {

		if ( empty( self::$instance ) )
			self::$instance = new WPRP_Log();

		return self::$instance;
	}

	public function __construct() {

		$this->setup_actions();
	}

	public function disable_logging() {
		$this->is_disabled = true;
	}

	public function setup_actions() {

		if ( $this->disable_logging )
			return;

		add_action( 'wp_login', array( $this, 'action_wp_login' ), 10, 2 );
		add_action( 'user_register', array( $this, 'action_user_register' ) );
		add_action( 'profile_update', array( $this, 'action_profile_updated' ), 10, 2 );


		add_action( 'update_option_current_theme', array( $this, 'updated_option_current_theme' ), 10, 2 );
	}

	public function action_wp_login( $user_login, $user ) {

		// we are only interested in administators
		if ( ! array_intersect( $user->roles, array( 'administrator' ) ) )
			return;

		$this->add_item( array(
			'type' => 'user',
			'action' => 'login',
			'remote_user' => array( 'user_login' => $user_login, 'display_name' => $user->display_name ),
		));
	}

	public function action_user_register( $user_id ) {

		$user = get_userdata();

		// we are only interested in administators
		if ( ! array_intersect( $user->roles, array( 'administator' ) ) )
			return;

		$this->add_item( array(
			'type' => 'user',
			'action' => 'create',
			'remote_user' => array( 'user_login' => $user->user_login, 'display_name' => $user->display_name ),
		));
	}

	public function action_profile_updated( $user_data, $old_user_data ) {

		// we are only interested in administators
		if ( ! array_intersect( $user_data->roles, array( 'administator' ) ) )
			return;

		if ( $user_data->user_email !== $old_user_data->user_email ) {
			$this->add_item( array(
				'type' => 'user',
				'action' => 'email-update',
				'remote_user' => array( 'user_login' => $user_data->user_login, 'display_name' => $user_data->display_name ),
				'old_email' => $old_user_data->user_email,
				'new_email' => $user_data->user_email,
			));
		}

		if ( $user_data->user_pass !== $old_user_data->user_pass ) {
			$this->add_item( array(
				'type' => 'user',
				'action' => 'password-update',
				'remote_user' => array( 'user_login' => $user_data->user_login, 'display_name' => $user_data->display_name ),
			));
		}
	}

	public function updated_option_current_theme( $old_theme, $new_theme ) {
		$this->add_item( array(
			'type' => 'theme',
			'action' => 'switch',
			'remote_user' => array( 'user_login' => $user_data->user_login, 'display_name' => $user_data->display_name ),
			'old_theme' => $old_theme,
			'new_theme' => $new_theme
		));
	}

	public function add_item( $item ) {

		if ( $this->disable_logging )
			return;
		
		$item = wp_parse_args( $item, array(
			'date' => time(),
			'remote_user' => is_user_logged_in() ? array( 'user_login' => wp_get_current_user()->user_login, 'display_name' => wp_get_current_user()->display_name ) : array(),
		));

		$items = $this->get_items();
		$items[] = $item;

		// only store the last 100 items
		if ( count( $items ) > 100 )
			$items = array_slice( $items, 0, 100 );

		update_option( 'wprp_log', $items );
	}

	public function get_items() {

		return get_option( 'wprp_log', array() );
	}

	public function delete_items() {

		delete_option( 'wprp_log' );
	}
}

add_action( 'plugins_loaded', array( 'WPRP_Log', 'get_instance' ) );