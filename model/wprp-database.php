<?php

trait WPRP_Database
{
    public static function insert($data)
    {
        global $wpdb;
        $wpdb->insert( self::table_name(), array_merge($data, [
            'time' => current_time( 'mysql' ),
        ]) );
        return $wpdb->insert_id;
    }


    public static function update($data, $id)
    {
        global $wpdb;
        return $wpdb->update( self::table_name(), array_merge($data, [
            'time' => current_time( 'mysql' ),
        ]),  ['id' => $id]);
    }


    public static function info()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . self::table_name());
    }


    /**
     * Create Table from details
     *
     * @param string $sql_inner
     */
    protected static function createTable(string $sql_inner)
    {
        global $wpdb;

        $table_name = self::table_name();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            " . $sql_inner . "
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    abstract public static function install();

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'wprp_' .  self::$table_postfix;
    }
}