<?php
    /**
     * Plugin Name: Freemius Fixer
     * Plugin URI:  https://freemius.com/
     * Description: Back up and delete all Freemius DB records.
     * Version:     1.0.0
     * Author:      Freemius
     * Author URI:  https://freemius.com
     * License: GPL2
     */

    /**
     * @package     Freemius Fixer
     * @copyright   Copyright (c) 2016, Freemius, Inc.
     * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
     * @since       1.0.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    define( 'WP_FS_CLEANUP__PLUGIN_NAME', __( 'Freemius Fixer' ) );

    add_action( 'init', 'fs_cleanup_check_action' );

    function fs_cleanup_check_action() {
        if ( isset( $_POST['fs_cleanup_action'] ) && ! empty( $_POST['fs_cleanup_action'] ) ) {
            check_admin_referer( 'fs_cleanup_action_' . $_POST['fs_cleanup_action'] );

            if ( 'download' === $_POST['fs_cleanup_action'] ) {
                fs_download_data_dump();
            } else {
                fs_cleanup();
            }
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) { 
            
            require_once( plugin_dir_path(__FILE__) . '/cli/FreemiusFixerCli.php' );
            FreemiusFixerCli::initialize(__FILE__);
        }

    }

    add_action(
        ( is_multisite() && is_network_admin() ? 'network_' : '' ) . 'admin_menu',
        'fs_cleanup_add_settings_menu'
    );

    function fs_cleanup_add_settings_menu() {
        add_menu_page(
            WP_FS_CLEANUP__PLUGIN_NAME,
            WP_FS_CLEANUP__PLUGIN_NAME,
            'manage_options',
            'fs_cleanup',
            'fs_cleanup_render_settings_menu'
        );
    }

    function fs_cleanup_render_settings_menu() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ) ?></h1>
            <h2><?php _e( 'IMPORTANT: Please download the data dump first and only then click the Fix button.' ) ?></h2>
            <table class="widefat">
                <tbody>
                <tr>
                    <td>
                        <form action="" method="post">
                            <?php wp_nonce_field( 'fs_cleanup_action_download' ) ?>
                            <input type="hidden" name="fs_cleanup_action" value="download"/>
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e( '(1) Download Data' ) ?>">
                        </form>
                    </td>
                        <td><?php _e( 'Please download the corrupted data and send it to the Freemius team via sdk@freemius.com, along with the error description, for further investigation so the issue can be fixed for others.' ) ?></td>
                </tr>
                <tr class="alternate">
                    <td>
                        <form action="" method="post">
                            <?php wp_nonce_field( 'fs_cleanup_action_cleanup' ) ?>
                            <input type="hidden" name="fs_cleanup_action" value="cleanup"/>
                            <input
                                onclick="if ( confirm( '<?php _e( 'Are you sure you want to clean up the data now? If you have already downloaded the data dump, please proceed.' ) ?>' ) ) this.parentNode.submit(); return false;"
                                type="submit"
                                class="button button-secondary"
                                value="<?php esc_attr_e( '(2) Fix - Clean Up Data & Deactivate' ) ?>">
                        </form>
                    </td>
                    <td><?php _e( 'This will clean up the corrupted data and will auto deactivate this cleanup plugin. After the cleanup you are safe to activate the plugin or theme that had the issue.' ) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    function fs_get_site_freemius_data( $is_network = false ) {
        global $wpdb;

        $fs_option_names = ( "'" . implode( "','", array(
                'fs_accounts',
                'fs_dbg_accounts',
                'fs_active_plugins',
                'fs_api_cache',
                'fs_dbg_api_cache',
                'fs_debug_mode',
            ) ) . "'" );

        if ( $is_network ) {
            $network_id = get_current_network_id();
            $query      = "SELECT meta_key AS option_name, meta_value AS option_value FROM {$wpdb->sitemeta} WHERE meta_key IN ({$fs_option_names}) AND meta_value <> '' AND site_id = {$network_id}";
        } else {
            $query = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ({$fs_option_names}) AND option_value <> ''";
        }

        $fs_options_from_db = $wpdb->get_results( $query );

        $fs_options = array();

        foreach ( (array) $fs_options_from_db as $option ) {
            $fs_options[ $option->option_name ] = maybe_unserialize( $option->option_value );
        }

        return $fs_options;
    }

    function fs_get_sites() {
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

    function fs_download_data_dump() {
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

    function fs_network_cleanup() {
        delete_site_option( 'fs_accounts' );
        delete_site_option( 'fs_dbg_accounts' );
        delete_site_option( 'fs_active_plugins' );
        delete_site_option( 'fs_api_cache' );
        delete_site_option( 'fs_dbg_api_cache' );
        delete_site_option( 'fs_debug_mode' );
    }

    function fs_site_cleanup() {
        delete_option( 'fs_accounts' );
        delete_option( 'fs_dbg_accounts' );
        delete_option( 'fs_active_plugins' );
        delete_option( 'fs_api_cache' );
        delete_option( 'fs_dbg_api_cache' );
        delete_option( 'fs_debug_mode' );
    }

    function fs_cleanup() {
        if ( ! is_multisite() ) {
            fs_site_cleanup();
        } else {
            $sites = fs_get_sites();

            foreach ( $sites as $site ) {
                $blog_id = ( $site instanceof WP_Site ) ?
                    $site->blog_id :
                    $site['blog_id'];

                switch_to_blog( $blog_id );

                fs_site_cleanup();
            }

            fs_network_cleanup();
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        deactivate_plugins( plugin_basename( __FILE__ ) );

        wp_die( __( 'Freemius records successfully cleared! You are now safe to activate any Freemius-powered plugin or theme.' ), __( 'Error' ) );
    }

    add_action( 'activated_plugin', 'fs_cleanup_activation_redirect_to_settings_page' );

    function fs_cleanup_activation_redirect_to_settings_page( $plugin ) {
        if ( $plugin == plugin_basename( __FILE__ ) ) {
            $path = 'admin.php?page=fs_cleanup';

            $url = is_network_admin() ?
                network_admin_url( $path ) :
                admin_url( $path );

            exit( wp_redirect( $url ) );
        }
    }