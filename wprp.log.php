<?php

class WPRP_Log {

	static $instance;

	private $is_logging_enabled = true;

	static function get_instance() {

		if ( empty( self::$instance ) )
			self::$instance = new WPRP_Log();

		return self::$instance;
	}

	public function __construct() {

		$this->setup_actions();
	}

	public function disable_logging() {
		$this->is_logging_enabled = false;
	}

	public function setup_actions() {

		if ( ! $this->is_logging_enabled )
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

		$new_user = get_user_by( 'id', $user_id );

		// we are only interested in administators
		if ( ! array_intersect( $new_user->roles, array( 'administrator' ) ) )
			return;

		$this->add_item( array(
			'type'             => 'user',
			'action'           => 'create',
			'user_login'       => $new_user->user_login,
			'display_name'     => $new_user->display_name,
			'role'             => $new_user->roles[0],
			/** remote_user is added in the `add_item()` method **/
		));
	}

	public function action_profile_updated( $user_id, $old_user_data ) {

		$user_data = get_user_by( 'id', $user_id );

		// we are only interested in administators
		if ( ! array_intersect( $user_data->roles, array( 'administrator' ) ) )
			return;


		if ( $user_data->user_email !== $old_user_data->user_email ) {
			$this->add_item( array(
				'type' => 'user',
				'action' => 'email-update',
				'old_email' => $old_user_data->user_email,
				'new_email' => $user_data->user_email,
				/** remote_user is added in the `add_item()` method **/
			));
		}

		if ( $user_data->user_pass !== $old_user_data->user_pass ) {
			$this->add_item( array(
				'type' => 'user',
				'action' => 'password-update',
				/** remote_user is added in the `add_item()` method **/
			));
		}
	}

	public function updated_option_current_theme( $old_theme, $new_theme ) {

		$this->add_item( array(
			'type' => 'theme',
			'action' => 'switch',
			'old_theme' => $old_theme,
			'new_theme' => $new_theme,
			/** remote_user is added in the `add_item()` method **/
		));
	}

	public function add_item( $item ) {

		if ( ! $this->is_logging_enabled )
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