<?php

class WPRP_Plugin {

    protected $is_active;
    protected $is_active_network;
    protected $backupClass;
    protected $filename;

    /**
     * Get Currently Installed Plugins
     *
     * @return array
     */
	public function get_info()
	{

		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		// Get all plugins
		$plugins = get_plugins();

		// Get the list of active plugins
		$active  = get_option( 'active_plugins', array() );

		// Delete the transient so wp_update_plugins can get fresh data
		if ( function_exists( 'get_site_transient' ) )
			delete_site_transient( 'update_plugins' );

		else
			delete_transient( 'update_plugins' );

		// Force a plugin update check
		wp_update_plugins();

		// Different versions of wp store the updates in different places
		// TODO can we depreciate
		if( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_plugins' ) )
			$current = $transient;

		elseif( $transient = get_transient( 'update_plugins' ) )
			$current = $transient;

		else
			$current = get_option( 'update_plugins' );

		// Premium plugins that have adopted the ManageWP API report new plugins by this filter
		$manage_wp_updates = apply_filters( 'mwp_premium_update_notification', array() );

		foreach ( (array) $plugins as $plugin_file => $plugin ) {

			if ( is_plugin_active( $plugin_file ) )
				$plugins[$plugin_file]['active'] = true;
			else
				$plugins[$plugin_file]['active'] = false;

			$manage_wp_plugin_update = false;
			foreach( $manage_wp_updates as $manage_wp_update ) {

				if ( ! empty( $manage_wp_update['Name'] ) && $plugin['Name'] == $manage_wp_update['Name'] )
					$manage_wp_plugin_update = $manage_wp_update;

			}

			if ( $manage_wp_plugin_update ) {

				$plugins[$plugin_file]['latest_version'] = $manage_wp_plugin_update['new_version'];

			} else if ( isset( $current->response[$plugin_file] ) ) {

				$plugins[$plugin_file]['latest_version'] = $current->response[$plugin_file]->new_version;
				$plugins[$plugin_file]['latest_package'] = $current->response[$plugin_file]->package;
				$plugins[$plugin_file]['slug'] = $current->response[$plugin_file]->slug;

			} else {

				$plugins[$plugin_file]['latest_version'] = $plugin['Version'];

			}

		}

		return $plugins;
	}

	protected function backupClass()
    {
        if (empty($this->backupClass)) {
            return $this->backupClass = new WPRP_Backup();
        }
        return $this->backupClass;
    }

    /**
     * Do Plugin Update
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
	public function do_update ( WP_REST_Request $request )
    {
        $this->filename = $request->get_param('filename');

        // Perform backup of plugin
        $backup = $this->backupClass()->do_plugin_backup( $this->filename );
        if ( is_wp_error($backup) ) return $backup;

        $this->store_current_active_status();

        $response = $this->update_plugin_wrap( $request );

        $error = $this->check_plugin_exists();

        if (is_wp_error($error)) {
            return $error;
        }
        return $response;
    }


    /**
     * Wrap Update_Plugin in a try/catch for error handling
     * @param $request
     * @return array|WP_Error
     */
    protected function update_plugin_wrap( $request )
    {
        try {
            $response = $this->update_plugin( $request );
        } catch ( \Exception $e ) {}

        if (empty($response)) {
            $response = new WP_Error('unknown-error', 'An unknown error occurred');
        }

        return $response;
    }

    /**
     * Validate Plugin Update
     *
     * @return bool|WP_Error
     */
    protected function check_plugin_exists()
    {
        $archive = $this->backupClass()->get_path() . '/' . $this->backupClass()->get_archive_filename();
        $error = false;
        if ( ! file_exists(WP_PLUGIN_DIR . '/' . $this->filename) ){
            $plugin_path = rtrim(plugin_dir_path($this->filename), '/');

            $this->backupClass()->do_unzip($archive, WP_PLUGIN_DIR . '/' . $plugin_path);

            $this->reActivate();

            $error = new WP_Error('rollback','Plugin update failed. The update has been rolled back.');
        }
        unlink($archive);
        return $error;
    }

    /**
     * Update a plugin
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    protected function update_plugin( WP_REST_Request $request ) {
        if ( ! file_exists(WP_PLUGIN_DIR . '/' . $this->filename) ){
            return new WP_Error('404', 'Plugin not found');
        }

        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            return new WP_Error('disallow-file-mods',
                __("File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote'));
        }

        include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
        require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

        $this->store_current_active_status();

        $skin = new WPRP_Plugin_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );

        $this->refresh_plugins( $request );

        // Remove the Language Upgrader
        remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

        // Do the plugin upgrade
        ob_start();
        $result = $upgrader->upgrade( $this->filename );
        if ($data = ob_get_contents()) ob_end_clean();

        if (is_wp_error(
            $errors = $this->handleErrors($skin, $result, $data)
        )) {
            return $errors;
        }

        // Run the language upgrader
        ob_start();
        $skin = new WPRP_Automatic_Upgrader_Skin();
        $lang_upgrader = new Language_Pack_Upgrader( $skin );
        $result = $lang_upgrader->upgrade( $upgrader );
        if ($data2 = ob_get_contents()) ob_end_clean();

        $this->reActivate();

        return array(
            'status' => 'success',
            'active_status' => array(
                'was_active'            => $this->is_active,
                'was_active_network'    => $this->is_active_network,
                'is_active'             =>  is_plugin_active( $this->filename ),
                'is_active_network'     =>  is_plugin_active_for_network( $this->filename ),
            ),
            'data' => $data
        );
    }

    /**
     * Refresh plugins or attach the zip file
     *
     * @param WP_REST_Request $request
     */
    protected function refresh_plugins( WP_REST_Request $request )
    {
        if ( !empty($request->get_param('zip_url')) ) {
            global $wprp_zip_update;
            $wprp_zip_update = array(
                'plugin_file' => $this->filename,
                'package' => $request->get_param('zip_url'),
            );
            add_filter('pre_site_transient_update_plugins', [self::class, '_wprp_forcably_filter_update_plugins']);
        } else {
            wp_update_plugins();
        }
    }


    /**
     * Filter `update_plugins` to produce a response it will understand
     * so we can have the Upgrader skin handle the update
     */
    public function _wprp_forcably_filter_update_plugins() {
        global $wprp_zip_update;

        $current = new stdClass;
        $current->response = array();

        $plugin_file = $wprp_zip_update['plugin_file'];
        $current->response[$plugin_file] = new stdClass;
        $current->response[$plugin_file]->package = $wprp_zip_update['package'];

        return $current;
    }

    /**
     * Handle any update errors and return them
     *
     * @param $skin
     * @param $result
     * @param $data
     * @return WP_Error|null
     */
    protected function handleErrors($skin, $result, $data)
    {
        if (!empty($skin->error)) {
            if (is_wp_error($skin->error)) {
                return new WP_Error('update-error', $skin->error);
            }
            if ($skin->error == 'up_to_date') {
                return new WP_Error('up_to_date', __('Plugin already up to date.', 'wpremote'));
            }
            $msg = __('Unknown error updating plugin.', 'wpremote');
            if (is_string($skin->error)) {
                $msg = $skin->error;
            }
            return new WP_Error('plugin-upgrader-skin', $msg);
        } else {
            if (is_wp_error($result)) {
                return new WP_Error('update-error', $result);
            } else {
                if ((!$result && !is_null($result)) || $data && stripos($data, 'error')) {
                    return new WP_Error('plugin-update', __('Unknown error updating plugin.', 'wpremote'));
                }
            }
        }
        return null;
    }

    /**
     * @param string $root
     */
    protected function removeFolder(string $dirPath)
    {
        if (! is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->removeFolder($file);
            } else {
//                var_dump($file);exit;
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    /**
     * Reactive if active
     */
    protected function reActivate()
    {
        if ($this->is_active) {
            activate_plugin($this->filename, '', $this->is_active_network, true);
        }
    }

    /**
     * Set the is_active vars
     */
    protected function store_current_active_status()
    {
        $this->is_active = is_plugin_active($this->filename);
        $this->is_active_network = is_plugin_active_for_network($this->filename);
    }

}