<?php

class WPRP_tasks extends WP_CLI_Command {

    /**
     * Find work for this process, will make this process now block
     *
     * @subcommand set-api
     */
    public function send_emails( $args, $assoc_args ) {
        $count = count($args);
        if ($count == 0 || $count > 1) {
            WP_CLI::line( 'Please set the args correctly' );
            die();
        }
        delete_option( 'wpr_api_key' );
        add_option( 'wpr_api_key', $args[0]);
        WP_CLI::line( 'API Key Set' );

//		WP_CLI::line( sprintf( "[%s] Worker %d completed its work.", date( 'Y-m-d H:i:s' ), getmypid() ) );
    }

}

WP_CLI::add_command( 'wpremote', 'WPRP_tasks' );