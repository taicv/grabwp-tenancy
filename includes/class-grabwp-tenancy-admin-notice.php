<?php
/**
 * GrabWP Tenancy Admin Notice Class
 *
 * Handles global admin notices for environment/configuration issues.
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GrabWP_Tenancy_Admin_Notice {
    /**
     * Register admin notice hook
     */
    public static function register() {
        add_action( 'admin_notices', [ __CLASS__, 'show_notices' ] );
    }

    /**
     * Show admin notices for global plugin issues
     */
    public static function show_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Check if load.php is included
        if ( ! defined( 'GRABWP_TENANCY_LOADED' ) ) {
            $snippet = 'require_once WP_CONTENT_DIR . "/plugins/grabwp-tenancy/load.php";';
            echo '<div class="notice notice-error"><p>'
                . '<strong>GrabWP Tenancy:</strong> Plugin is activated but <code>load.php</code> is not included in <code>wp-config.php</code>.'
                . '<br>Please add the following line before <code>/* That\'s all, stop editing! */</code> in <code>wp-config.php</code>:'
                . '<pre id="grabwp-load-snippet" style="user-select:all;">' . esc_html( $snippet ) . '</pre>'
                . '<textarea id="grabwp-load-textarea" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;">' . esc_html( $snippet ) . '</textarea>'
                . '<button class="button" id="grabwp-copy-btn" type="button">Copy to Clipboard</button>'
                . '<script>(function(){
                    var btn = document.getElementById("grabwp-copy-btn");
                    var ta = document.getElementById("grabwp-load-textarea");
                    if(btn && ta){
                        btn.addEventListener("click", function(){
                            ta.style.display = "block";
                            ta.select();
                            try {
                                var successful = document.execCommand("copy");
                                if(successful){
                                    btn.innerText = "Copied!";
                                    setTimeout(function(){btn.innerText = "Copy to Clipboard";}, 1500);
                                }
                            } catch(e) {}
                            ta.style.display = "none";
                        });
                    }
                })();</script>'
                . '</p></div>';
        }
        // Check if grabwp dir exists
        if ( ! file_exists( WP_CONTENT_DIR . '/grabwp' ) ) {
            echo '<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> <code>grabwp</code> directory is missing in <code>wp-content</code>. Please activate the plugin again or create the directory manually.</p></div>';
        }
        // Check if tenants.php exists
        if ( ! file_exists( WP_CONTENT_DIR . '/grabwp/tenants.php' ) ) {
            echo '<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> <code>tenants.php</code> is missing in <code>wp-content/grabwp</code>. Please activate the plugin again or create the file manually.</p></div>';
        }
    }
}
