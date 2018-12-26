<?php

class WPRP_Schedule {

    public static $hook = 'wprp_schedule_settings';
    public static $callback = 'wprp_daily_schedule';

    /**
     * Activate Function
     */
    public function activate()
    {
        if ( ! $this->is_active() && $this->enabled() )
            wp_schedule_event( time(), $this->get_recurrence(), self::$hook );
    }

    /**
     * Update Recurrence
     */
    public function update_recurrence()
    {
        $this->deactivate();
        $this->activate();
    }

    /**
     * Get the Recurrence of the scheduled event
     *
     * @return string
     */
    protected function get_recurrence()
    {
        $info = get_option('wprp_schedule_settings', false);
        if ( ! empty($info) && !empty($info['recurrence']) && in_array($info['recurrence'], ['hourly', 'daily', 'twicedaily'])) {
            return $info['recurrence'];
        }
        return 'daily';
    }

    /**
     * Check if enabled
     *
     * @return bool
     */
    protected function enabled()
    {
        $info = get_option('wprp_schedule_settings', false);
        if ( ! empty($info) && !empty($info['enabled'])) {
            return (bool) $info['enabled'];
        }
        return false;
    }

    /**
     * Deactivate Function
     */
    public function deactivate()
    {
        if ( $this->is_active() )
            wp_clear_scheduled_hook( self::$hook );
    }

    /**
     * @return bool
     */
    protected function is_active()
    {
        return (bool) wp_next_scheduled( self::$hook );
    }

    /**
     * Run Daily Schedule
     */
    public static function wprp_daily_schedule()
    {
        $a = new WPRP_Backup();
        $a->run_remote();
    }

}