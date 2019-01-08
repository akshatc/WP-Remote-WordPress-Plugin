<?php

class WPRP_Update_Database {
    public static $table_postfix = 'updates';
    use WPRP_Database;

    public static function startUpdate($filename)
    {
        return self::insert([
            'type'          => 'plugin',
            'filename'      => $filename,
            'status'        => 'incomplete',
        ]);
    }

    public static function addPluginUpdate($plugin_file)
    {
        $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
        $status = 'incomplete';
        $version = $data['Version'];
        self::insert([
            'type'          => 'plugin',
            'filename'      => $plugin_file,
            'old_version'   => $version,
            'version'       => '',
            'status'        => $status,
        ]);
    }

    public static function add($type, $filename, $old_version, $version, $status)
    {
        return self::insert([
            'type'          => $type,
            'filename'      => $filename,
            'old_version'   => $old_version,
            'version'       => $version,
            'status'        => $status,
        ]);
    }

    public static function install()
    {
        self::createTable("
            type varchar(255) DEFAULT '' NOT NULL,
            filename varchar(255) DEFAULT '' NOT NULL,
            old_version varchar(255) DEFAULT '' NOT NULL,
            version varchar(255) DEFAULT '' NOT NULL,
            status varchar(255) DEFAULT '' NOT NULL,
        ");
    }
}