<?php

class WPRP_Plugins {

    /**
     * Get Currently Installed Plugins
     *
     * @return array
     */
	public function get_plugins()
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

	public function do_zip_update ( WP_REST_Request $request )
    {
        $plugin_file = $request->get_param('plugin');

        $backupClass = new WPRP_Backup();
        $backupClass->do_plugin_backup();

        try {
            $response = $this->update_plugin( $request );
        } catch (\Exception $e) {
            if (empty($response)) {
                $response = new WP_Error('unknown-error', 'An unknown error occurred');
            }
        }

        // == if plugin no longer exists, restore == //
        $archive = $backupClass->get_path() . '/' . $backupClass->get_archive_filename();
        if ( ! file_exists(WP_PLUGIN_DIR . '/' . $plugin_file) ){
            $plugin_path = rtrim(plugin_dir_path($_GET['plugin']), '/');
            $root = WP_PLUGIN_DIR . '/' . $plugin_path;
            $backupClass->do_unzip($archive, $root);
            unlink($archive);
            return new WP_Error('rollback','Plugin update failed. The update has been rolled back.');
        }
        unlink($archive);

        return $response;
    }

    /**
     * Do Plugin Update
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
	public function do_plugin_update ( WP_REST_Request $request )
    {
        $plugin_file = $request->get_param('plugin');

        $backupClass = new WPRP_Backup();
        $backupClass->do_plugin_backup();

        try {
            $response = $this->update_plugin( $request );
        } catch (\Exception $e) {
            if (empty($response)) {
                $response = new WP_Error('unknown-error', 'An unknown error occurred');
            }
        }

        // == if plugin no longer exists, restore == //
        $archive = $backupClass->get_path() . '/' . $backupClass->get_archive_filename();
        if ( ! file_exists(WP_PLUGIN_DIR . '/' . $plugin_file) ){
            $plugin_path = rtrim(plugin_dir_path($_GET['plugin']), '/');
            $root = WP_PLUGIN_DIR . '/' . $plugin_path;
            $backupClass->do_unzip($archive, $root);
            unlink($archive);
            return new WP_Error('rollback','Plugin update failed. The update has been rolled back.');
        }
        unlink($archive);

        return $response;
    }

    /**
     * Update a plugin
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function update_plugin( WP_REST_Request $request ) {
        $plugin_file = $request->get_param('plugin');

        if ( ! file_exists(WP_PLUGIN_DIR . '/' . $plugin_file) ){
            return new WP_Error('404', 'Plugin not found');
        }

        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            return new WP_Error('disallow-file-mods',
                __("File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote'));
        }

        include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
        require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

        $is_active         = is_plugin_active( $plugin_file );
        $is_active_network = is_plugin_active_for_network( $plugin_file );

        $skin = new WPRP_Plugin_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );

        $this->refresh_plugins( $request );

        // Remove the Language Upgrader
        remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

        // Do the plugin upgrade
        ob_start();
        $result = $upgrader->upgrade( $plugin_file );
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

        // If the plugin was activited, we have to re-activate it
        // but if activate_plugin() fatals, then we'll just have to return 500
        if ( $is_active ) {
            activate_plugin( $plugin_file, '', $is_active_network, true );
        }

        return array(
            'status' => 'success',
            'active_status' => array(
                'was_active'            => $is_active,
                'was_active_network'    => $is_active_network,
                'is_active'             =>  is_plugin_active( $plugin_file ),
                'is_active_network'     =>  is_plugin_active_for_network( $plugin_file ),
            ),
            'data' => $data
        );
    }

    protected function refresh_plugins( WP_REST_Request $request )
    {
        if ( !empty($request->get_param('zip_url')) ) {
            global $wprp_zip_update;
            $wprp_zip_update = array(
                'plugin_file' => $request->get_param('plugin'),
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
     * Validate Plugin Update
     *
     * @param $plugin_file
     * @return array|WP_Error
     */
    public function validate( $plugin_file )
    {
        $plugin_status = false;
        foreach( get_plugins() as $path => $maybe_plugin ) {
            if ( $path == $plugin_file ) {
                $plugin_status = true;
                break;
            }
        }
        if (!$plugin_status) {
            return new WP_Error('plugin-missing', __('Plugin has gone missing.', 'wpremote'));
        }
        return array(
            'status' => 'success',
            'plugin_status' => is_plugin_active( $plugin_file )
        );
    }

    /**
     * @param $skin
     * @param $result
     * @param $data
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

}