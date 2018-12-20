<?php

/**
 * Update Core Repo
 *
 * @version 2.3
 */
class WPRP_Core {

    public function do_core_upgrade() {
        $backup = new WPRP_Backup();
        $result = $backup->do_backup();
        if (is_wp_error($result)) {
            return $result;
        }
        return $this->upgrade_core();
    }

    public function upgrade_core()  {

        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            return new WP_Error('disallow-file-mods',
                __("File modification is disabled with the DISALLOW_FILE_MODS constant.", 'wpremote'));
        }

        include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
        include_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
        include_once ( ABSPATH . 'wp-includes/update.php' );
        require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
        require_once WPRP_PLUGIN_PATH . 'inc/class-wprp-core-upgrader-skin.php';

        // force refresh
        wp_version_check();

        $updates = get_core_updates();

        if ( is_wp_error( $updates ) || ! $updates ){
            return new WP_Error( 'no-update-available' );
        }

        $update = reset( $updates );

        if ( ! $update ) {
            return new WP_Error('no-update-available');
        }

        $skin = new WPRP_Core_Upgrader_Skin();

        $upgrader = new Core_Upgrader( $skin );
        $result = $upgrader->upgrade($update);

        if ( is_wp_error( $result ) ) {
            return new WP_Error($result->get_error_code(), $result->get_error_message());
        }

        global $wp_current_db_version, $wp_db_version;

        // we have to include version.php so $wp_db_version
        // will take the version of the updated version of wordpress
        require( ABSPATH . WPINC . '/version.php' );

        wp_upgrade();

        return true;
    }

}