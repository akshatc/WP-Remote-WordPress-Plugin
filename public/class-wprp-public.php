<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    WPRP
 * @subpackage WPRP/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    WPRP
 * @subpackage WPRP/public
 * @author     Maekit <jera@mywork.com.au>
 */
class WPRP_Public {

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
	private $loader;

    /**
     * Initialize the class and set its properties.
     *
     * @since    3.0.0
     * @param WPRP $wprp
     */
	public function __construct( WPRP $wprp )
    {
		$this->plugin_name = $wprp->get_plugin_name();
		$this->version = $wprp->get_version();
		$this->loader = $wprp->get_loader();
	}


	/**
	 * Initialize core functions and hooks
	 */
	public function init()
	{
		$this->add_hooks();
	}

	public function add_hooks()
	{
		$api_endpoints = new WPRP_Api_Endpoints();
		$this->loader->add_action( 'rest_api_init', $api_endpoints, 'wprp_register_routes' );
	}
}
