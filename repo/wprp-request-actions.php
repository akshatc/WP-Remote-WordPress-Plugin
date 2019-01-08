<?php

use WPRP_History_Database as History;
use WPRP_Update_Database as Update;

trait WPRP_Request_Actions {
    /**
     * @var WP_REST_Request
     */
    protected $request;
    protected $update_id;
    protected $class_type;
    protected $class_name;
    protected $method;
    protected $args;
    protected $instance;


    /**
     * @param $filename
     * @return string
     */
    protected function getVersionNumber($filename): string
    {
        $version = '';
        if ($this->class_type == 'plugin') {
            return $this->getPluginVersion($filename);
        }
        return $version;
    }


    protected function beforeAction()
    {
        if ($this->method == 'do_update') {
            $filename = $this->request->get_param('filename');

            $this->update_id = Update::add(
                $this->class_type,
                $filename,
                $this->getVersionNumber($filename),
                '',
                'incomplete'
            );
        }
    }

    /**
     * @param array|WP_Error $return
     */
    protected function handleAction($return)
    {
        if (is_wp_error($return)) {
            $this->handleFailure($return);
            return;
        }
        if ($this->method == 'do_update') {
            if (isset($return['status']) && $return['status'] == 'success') {
                $filename = $this->request->get_param('filename');

                if (!empty($this->update_id)) {
                    Update::update([
                        'version' => $this->getVersionNumber($filename),
                        'status' => 'complete'
                    ], $this->update_id);
                }
            }
        }
    }

    /**
     * @param $filename
     * @return string
     */
    protected function getPluginVersion($filename)
    {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $filename);
        return $data['Version'];
    }

    /**
     * @param $return
     */
    protected function handleFailure($return): void
    {
        if (!empty($this->update_id)) {
            Update::update([
                'status' => 'failed'
            ], $this->update_id);
        }

        History::add('There was an error: ' . $return->get_error_message());
    }
}