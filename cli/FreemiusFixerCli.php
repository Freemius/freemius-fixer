<?php

if( ! defined( 'ABSPATH' ) ) {
    return;
}

class FreemiusFixerCli extends WP_CLI_Command
{
    
    public static function initialize($file = null) {
        static $instance = null;
  
          if ( ! $instance ) {
              $instance = new FreemiusFixerCli( $file );
              WP_CLI::add_command( 'FS_CLI', $instance, array('shortdesc'=>'Show Freemius Fixer Commands') );
          }
          return $instance;
      }
    
    /**
	 * Download json dump fs_download_data_dump
    */  
    public function fs_download_data_dump() {
        global $wpdb;

        $suppress = $wpdb->suppress_errors();

        $site_fs_options = array();

        if ( ! is_multisite() ) {
            $site_fs_options[] = fs_get_site_freemius_data();
        } else {
            $sites = fs_get_sites();

            foreach ( $sites as $site ) {
                $blog_id = ( $site instanceof WP_Site ) ?
                    $site->blog_id :
                    $site['blog_id'];

                switch_to_blog( $blog_id );

                $fs_options = fs_get_site_freemius_data();

                if ( ! empty( $fs_options ) ) {
                    $site_fs_options[ $blog_id ] = $fs_options;
                }
            }

            $site_fs_options['network'] = fs_get_site_freemius_data( true );
        }

        $wpdb->suppress_errors( $suppress );

        $filename     = ( 'fs-data-dump-' . date( 'YmdHis' ) . '.txt' );
        $file_content = json_encode( $site_fs_options );

        header( 'Content-Type: application/octet-stream' );
        header( "Content-disposition: attachment; filename={$filename}" );
        header( 'Content-Length: ' . strlen( $file_content ) );

        echo $file_content;

        exit;
    }

    private function fs_get_sites() {
        if ( function_exists( 'get_sites' ) ) {
            // For WP 4.6 and above.
            return get_sites();
        } else if ( function_exists( 'wp_get_sites' ) ) {
            // For WP 3.7 to WP 4.5.
            return wp_get_sites();
        } else {
            // For WP 3.6 and below.
            return get_blog_list( 0, 'all' );
        }
    }

    /**
     * Freemius cleanup fs_cleanup
    */  
    public function fs_cleanup() {
        if ( ! is_multisite() ) {
            $this->fs_site_cleanup();
        } else {
            $sites = $this->fs_get_sites();

            foreach ( $sites as $site ) {
                $blog_id = ( $site instanceof WP_Site ) ?
                    $site->blog_id :
                    $site['blog_id'];

                switch_to_blog( $blog_id );

                $this->fs_site_cleanup();
            }

            $this->fs_network_cleanup();
        }
      
        WP_CLI::success( __( 'Freemius records successfully cleared! You are now safe to activate any Freemius-powered plugin or theme.' ), __( 'Error' ) );
    }


    private function fs_network_cleanup() {
        delete_site_option( 'fs_accounts' );
        delete_site_option( 'fs_dbg_accounts' );
        delete_site_option( 'fs_active_plugins' );
        delete_site_option( 'fs_api_cache' );
        delete_site_option( 'fs_dbg_api_cache' );
        delete_site_option( 'fs_debug_mode' );
    }

    private function fs_site_cleanup() {
        delete_option( 'fs_accounts' );
        delete_option( 'fs_dbg_accounts' );
        delete_option( 'fs_active_plugins' );
        delete_option( 'fs_api_cache' );
        delete_option( 'fs_dbg_api_cache' );
        delete_option( 'fs_debug_mode' );
    }


}