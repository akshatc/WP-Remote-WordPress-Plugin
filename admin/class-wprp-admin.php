<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    WPRP
 * @subpackage WPRP/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WPRP
 * @subpackage WPRP/admin
 * @author     Maekit <jera@mywork.com.au>
 */
class WPRP_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $wprp;
	private $loader;

	/**
	 * WPRP_Admin constructor.
	 *
	 * @param WPRP $wprp
	 */
	public function __construct( WPRP $wprp) {
		$this->wprp = $wprp;
		$this->loader = $wprp->get_loader();
		$this->plugin_name = $wprp->get_plugin_name();
		$this->version = $wprp->get_version();
	}

	/**
	 * Initialize core functions and hooks
	 */
	public function init()
	{
		$this->add_hooks();
	}

	/**
	 * Add Hooks
	 */
	public function add_hooks() {
//		$this->loader->add_action( 'admin_bar_menu', $this, 'add_plugins_link_to_admin_toolbar' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_scripts' );

		$plugin_settings = new WPRP_Admin_Settings( $this->plugin_name, $this->version );

		$this->loader->add_action( 'admin_menu', $plugin_settings, 'setup_plugin_options_menu' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_display_options' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_social_options' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_input_examples' );

		$plugin_settings = new WPRP_Admin_Settings( $this->plugin_name, $this->version );
		$this->loader->add_action( 'rest_api_init', $plugin_settings, 'initialize_input_examples' );
	}


	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    3.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wprp-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    3.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WPRP_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WPRP_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wprp-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function add_plugins_link_to_admin_toolbar( $wp_admin_bar ) {
		$args = array(
			'id'    => 'wprp',
			'title' => 'WP Remote',
			'href'  => admin_url('plugins.php'),
			'parent'=> 'appearance',
		);
		$wp_admin_bar->add_node( $args );

	}

}
