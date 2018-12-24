<?php

class WPRP_Schedule {
    /*public function __construct()
    {
        add_action( 'wprp_backup_schedule', [self::class, 'backup_upload'] );
        wp_schedule_event( time(), 'daily', 'wprp_backup_schedule' );
    }*/

    public static function backup_upload()
    {
        $a = new WPRP_Backup();
        $a->run_remote();
    }

}