<?php

class WPRP_Api_Endpoints {

    public static $namespace = 'wprp/v1';
    protected $base = [];

    /**
     * Register API Routes
     */
	public function wprp_register_routes() {
        $this->get_info_endpoint();
        $this->core_api_endpoints();
        $this->plugin_api_endpoints();
        $this->backup_api_endpoints();
        $this->theme_api_endpoints();
	}

    /**
     * Plugin API Endpoints
     */
    protected function plugin_api_endpoints()
    {
        $this->base = [
            'path' => 'plugin',
            'facade' => 'WPRP_PluginFacade'
        ];

        $this->route(
            'list',
            WP_REST_Server::ALLMETHODS,
            'get_plugins'
        );

        $this->route(
            'update',
            WP_REST_Server::ALLMETHODS,
            'do_plugin_update',
            [
                'plugin' => [
                    'required' => true
                ],
            ]
        );

        $this->route(
            'update/zip',
            WP_REST_Server::ALLMETHODS,
            'do_plugin_update',
            [
                'plugin' => [
                    'required' => true
                ],
                'zip' => [
                    'required' => true
                ],
            ]
        );
    }



    /**
     * Plugin API Endpoints
     */
    protected function theme_api_endpoints()
    {
        $this->base = [
            'path' => 'theme',
            'facade' => 'WPRP_ThemeFacade'
        ];

        $this->route(
            'list',
            WP_REST_Server::ALLMETHODS,
            'get_themes'
        );

        $this->route(
            'update',
            WP_REST_Server::ALLMETHODS,
            'do_theme_update',
            [
                'theme' => [
                    'required' => true
                ],
            ]
        );

    }


    /**
     * Backup API Endpoints
     */
    protected function backup_api_endpoints()
    {
        $this->base = [
            'path' => 'backup',
            'facade' => 'WPRP_BackupFacade'
        ];

        $this->route(
            'get',
            WP_REST_Server::ALLMETHODS,
            'get_backup'
        );

        $this->route(
            'run/remote',
            WP_REST_Server::ALLMETHODS,
            'run_remote'
        );

        $this->route(
            'run',
            WP_REST_Server::ALLMETHODS,
            'do_backup'
        );

        $this->route('plugin/run',
            WP_REST_Server::ALLMETHODS,
            'do_plugin_backup',
            [
                'plugin' => [
                    'required' => true
                ],
            ]
        );
    }

    /**
     * Core API Endpoints
     */
    protected function core_api_endpoints()
    {
        $this->base = [
            'path' => 'core',
            'facade' => 'WPRP_CoreFacade'
        ];

        $this->route(
            'info',
            WP_REST_Server::ALLMETHODS,
            'get_version_info'
        );

        $this->route(
            'update',
            WP_REST_Server::ALLMETHODS,
            'do_core_upgrade'
        );

    }

    /**
     * Register Rest Route
     *
     * @param $path
     * @param $methods
     * @param $callback
     * @param array $args
     */
    public function route($path, $methods, $callback, $args = []) {
        register_rest_route(self::$namespace, '/' . $this->base['path'] . '/' . $path, array(
            array(
                'methods' => $methods,
                'callback' => [$this->base['facade'], $callback],
                'permission_callback' => array($this, 'verify_request'),
                'args' => array_merge($this->get_args(), $args)
            )
        ));
    }

	/**
	 * We can use this function to contain our arguments for the example product endpoint.
	 */
	function get_args() {
		$args = array();
		// Here we are registering the schema for the filter argument.
		$args['data'] = array(
			// We are registering a basic validation callback for the data argument.
//			'validate_callback' => array(new self(), 'verify_request'),
			// Here we register the validation callback for the filter argument.
//			'sanitize_callback' => 'prefix_data_arg_sanitize_callback',
		);
		return $args;
	}

	/**
	 * Verify the request
	 * 
	 * @return bool|WP_Error
	 */
	public function verify_request() {
        @ignore_user_abort( true );

        return true; // always verify

//        if (!defined('XMLRPC_REQUEST')) {
//            define('XMLRPC_REQUEST', true);
//        }

        // Check the API Key
		if ( empty($this->get_api_key()) ) {
            return new WP_Error('blank-api-key');
		}

		if ( ! isset( $_POST['wpr_verify_key'] ) ) {
            return new WP_Error('api-key-empty' );
		}

		$verify = $_POST['wpr_verify_key'];
		unset( $_POST['wpr_verify_key'] );

		if ($verify != $this->get_api_key()) {
		    return new WP_Error('bad-key');
        }

		// TODO Reverify POST request with HMAC
		/*if ( ! in_array( $verify, $this->generate_hashes( $_POST ), true ) ) {
            return new WP_Error( 'bad-verify-key' );
		}*/

        wp_set_current_user(1);

        return true;
	}


	public function generate_hashes( $vars ) {

		if ( ! $api_key = $this->get_api_key() ) {
			return array();
		}

		$hashes = array();
		foreach( $api_key as $key ) {
			$hashes[] = hash_hmac( 'sha256', serialize( $vars ), $key );
		}
		return $hashes;
	}


	/**
	 * Get the stored WPR API key
	 *
	 * @return mixed
	 */
	function get_api_key() {
        $info = get_option( 'wpr_api_key', '' );
        $options = get_option( 'wprp_basic_settings', ['api_key' => ''] );
        if (empty($options['api_key']) && !empty($info)) {
            $key = $info;
        } else {
            $key = $options['api_key'] ?? '';
        }

        return $key;
	}

    /**
     * Get All Info
     */
    protected function get_info_endpoint()
    {
        register_rest_route(self::$namespace, '/info', array(
            array(
                'methods' => WP_REST_Server::ALLMETHODS,
                'callback' => function () {
                    return [
                        'core'      => WPRP_CoreFacade::get_version_info(),
                        'plugins'   => WPRP_PluginFacade::get_plugins(),
                        'themes'    => WPRP_ThemeFacade::get_themes(),
                    ];
                },
                'permission_callback' => array($this, 'verify_request'),
                'args' => $this->get_args()
            )
        ));
    }

}