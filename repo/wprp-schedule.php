<?php

class WPRP_Schedule {
    public function __construct()
    {
        add_action( 'wprp_backup_schedule', [self::class, 'backup_upload'] );
        wp_schedule_event( time(), 'daily', 'wprp_backup_schedule' );
    }

    public static function backup_upload()
    {
        $backupClass = new WPRP_Backup();
        $backupClass->do_backup();

        $archive = $backupClass->get_path() . '/' . $backupClass->get_archive_filename();
        $contents = file_get_contents('zip://' . $archive);

        $remote = new WPRP_Remote_Backup();
        $remote->setConfig([

        ]);
        $remote->upload('/path', $contents);
    }

}