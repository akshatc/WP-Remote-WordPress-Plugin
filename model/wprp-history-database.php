<?php

class WPRP_History_Database {
    use WPRP_Database;
    public static $table_postfix = 'history';

    public static function add($message)
    {
        self::insert([
            'text' => $message
        ]);
    }

    public static function install()
    {
        self::createTable("
            text varchar(255) DEFAULT '' NOT NULL,
        ");
    }
}