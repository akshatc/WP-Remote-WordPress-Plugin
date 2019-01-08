<?php

class WPRP_Backup_Database {
    use WPRP_Database;
    public static $table_postfix = 'backups';

    /**
     * Add Backup to Database
     *
     * @param $filename
     * @param $type
     * @param $storage
     * @param $size
     */
    public static function addBackup($filename, $type, $storage, $size)
    {
        self::insert([
            'filename'  => $filename,
            'type'      => $type,
            'storage'   => $storage,
            'size'      => $size,
        ]);
    }

    /**
     * Install Database Table
     */
    public static function install()
    {
        self::createTable("
            filename varchar(255) DEFAULT '' NOT NULL,
            type varchar(255) DEFAULT '' NOT NULL,
            storage varchar(255) DEFAULT '' NOT NULL,
            size varchar(255) DEFAULT '' NOT NULL,
        ");
    }

}