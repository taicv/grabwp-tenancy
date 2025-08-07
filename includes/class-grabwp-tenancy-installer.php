<?php
/**
 * GrabWP Tenancy Installer
 * Handles plugin activation tasks (e.g., .htaccess creation)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GrabWP_Tenancy_Installer {
    public static function activate() {
        // Create .htaccess file for security
        self::create_htaccess();
        
        // Create necessary directories
        self::create_directories();
        
        // Create default tenant mappings file
        self::create_tenant_mappings_file();
    }
    
    /**
     * Create .htaccess file to block web access
     * 
     * @since 1.0.0
     */
    private static function create_htaccess() {
        $grabwp_dir = WP_CONTENT_DIR . '/grabwp';
        $htaccess_file = $grabwp_dir . '/.htaccess';
        $htaccess_content = "#GrabWP protection\nOptions -Indexes\n<FilesMatch \"\\.php$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n</FilesMatch>\n<FilesMatch \"^\\.\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n</FilesMatch>\n";

        if ( ! file_exists( $grabwp_dir ) ) {
            wp_mkdir_p( $grabwp_dir );
        }
        if ( ! file_exists( $htaccess_file ) ) {
            @file_put_contents( $htaccess_file, $htaccess_content );
        }
    }
    
    /**
     * Create necessary directories
     * 
     * @since 1.0.0
     */
    private static function create_directories() {
        $grabwp_dir = WP_CONTENT_DIR . '/grabwp';
        
        if ( ! file_exists( $grabwp_dir ) ) {
            $result = wp_mkdir_p( $grabwp_dir );
            if ( ! $result ) {
                // Handle directory creation failure silently
                // Directory creation failure will be handled by the calling code
            }
        }
    }
    
    /**
     * Create default tenant mappings file
     * 
     * @since 1.0.0
     */
    private static function create_tenant_mappings_file() {
        $mappings_file = WP_CONTENT_DIR . '/grabwp/tenants.php';
        
        if ( ! file_exists( $mappings_file ) ) {
            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * Tenant Domain Mappings\n";
            $content .= " * \n";
            $content .= " * This file contains domain mappings for tenant identification.\n";
            $content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
            $content .= " */\n\n";
            $content .= "\$tenant_mappings = array(\n";
            $content .= "    // Example: 'abc123' => array( 'tenant1.grabwp.local' ),\n";
            $content .= ");\n";
            
            $result = file_put_contents( $mappings_file, $content );
            if ( false === $result ) {
                // Handle file creation failure silently
                // File creation failure will be handled by the calling code
            }
        }
    }
}
