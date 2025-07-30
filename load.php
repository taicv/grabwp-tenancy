<?php
/**
 * GrabWP Tenancy - Early Loading System
 * 
 * This file is included in wp-config.php before WordPress loads
 * to handle tenant identification and database configuration.
 * 
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get sanitized HTTP host
 * 
 * @return string Sanitized HTTP host or empty string
 */
function grabwp_tenancy_get_http_host() {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    return isset( $_SERVER['HTTP_HOST'] ) ? stripslashes( $_SERVER['HTTP_HOST'] ) : '';
}

/**
 * Define essential WordPress constants for early loading
 * These are needed for pro plugin and sub-tenant functionality
 */
function grabwp_tenancy_define_constants() {
    // Define ABSPATH if not already defined
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', dirname( __FILE__, 4 ) . '/' );
    }
    
    // Define WP_CONTENT_DIR if not already defined
    if ( ! defined( 'WP_CONTENT_DIR' ) ) {
        define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
    }
    
    // Define WP_CONTENT_URL if not already defined
    if ( ! defined( 'WP_CONTENT_URL' ) ) {
        $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
        $host = grabwp_tenancy_get_http_host();
        define( 'WP_CONTENT_URL', $protocol . '://' . $host . '/wp-content' );
    }
    
    // Define WP_PLUGIN_DIR if not already defined
    if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
        define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
    }
    
    // Define WPMU_PLUGIN_DIR if not already defined
    if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
        define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );
    }
    
    // Define WP_SITEURL if not already defined
    if ( ! defined( 'WP_SITEURL' ) ) {
        $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
        $host = grabwp_tenancy_get_http_host();
        define( 'WP_SITEURL', $protocol . '://' . $host );
    }
    
    // Define WP_HOME if not already defined
    if ( ! defined( 'WP_HOME' ) ) {
        $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
        $host = grabwp_tenancy_get_http_host();
        define( 'WP_HOME', $protocol . '://' . $host );
    }
}

/**
 * Early tenant identification and configuration
 * 
 * This function runs before WordPress loads to:
 * 1. Identify tenant based on domain
 * 2. Set database prefix for tenant isolation
 * 3. Configure content paths for tenant separation
 */
function grabwp_tenancy_early_init() {
    // Define essential WordPress constants first
    grabwp_tenancy_define_constants();
    
    // Get current domain
    $current_domain = grabwp_tenancy_get_http_host();
    
    // Load tenant mappings
    $tenant_mappings = grabwp_tenancy_load_tenant_mappings();
    
    // Identify tenant by domain
    $tenant_id = grabwp_tenancy_identify_tenant( $current_domain, $tenant_mappings );
    
    if ( $tenant_id ) {
        // Set tenant context
        define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
        define( 'GRABWP_TENANCY_IS_TENANT', true );
        
        // Configure database prefix
        grabwp_tenancy_set_database_prefix( $tenant_id );
        
        // Configure content paths
        grabwp_tenancy_set_content_paths( $tenant_id );
    } else {
        // Main site context
        define( 'GRABWP_TENANCY_IS_TENANT', false );
        define( 'GRABWP_TENANCY_TENANT_ID', '' );
    }
}

/**
 * Load tenant domain mappings from file
 * 
 * @return array Tenant mappings array
 */
function grabwp_tenancy_load_tenant_mappings() {
    // Determine content directory
    $content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    $mappings_file = $content_dir . '/grabwp/tenants.php';
    
    if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
        $tenant_mappings = array();
        include $mappings_file;
        return $tenant_mappings;
    }
    
    return array();
}

/**
 * Identify tenant by domain
 * 
 * @param string $domain Current domain
 * @param array $mappings Tenant domain mappings
 * @return string|false Tenant ID or false if not found
 */
function grabwp_tenancy_identify_tenant( $domain, $mappings ) {
    if ( empty( $domain ) || ! is_array( $mappings ) ) {
        return false;
    }
    
    foreach ( $mappings as $tenant_id => $domains ) {
        if ( is_array( $domains ) ) {
            foreach ( $domains as $domain_entry ) {
                if ( $domain === $domain_entry ) {
                    return $tenant_id;
                }
            }
        }
    }
    
    return false;
}

/**
 * Set database prefix for tenant isolation
 * 
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_set_database_prefix( $tenant_id ) {
    global $table_prefix;
    
    // Validate tenant ID
    if ( empty( $tenant_id ) || ! preg_match( '/^[a-z0-9]{6}$/', $tenant_id ) ) {
        return;
    }
    
    // Store original prefix
    if ( ! defined( 'GRABWP_TENANCY_ORIGINAL_PREFIX' ) ) {
        define( 'GRABWP_TENANCY_ORIGINAL_PREFIX', $table_prefix );
    }
    
    // Set tenant-specific prefix
    $table_prefix = $tenant_id . '_';
}

/**
 * Set content paths for tenant isolation
 * 
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_set_content_paths( $tenant_id ) {
    // Validate tenant ID
    if ( empty( $tenant_id ) || ! preg_match( '/^[a-z0-9]{6}$/', $tenant_id ) ) {
        return;
    }
    
    // Determine content directory
    $content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    
    // Define tenant upload directory
    $upload_dir = $content_dir . '/grabwp/' . $tenant_id . '/uploads';
    
    // Create directory if it doesn't exist
    if ( ! file_exists( $upload_dir ) ) {
        if ( ! is_dir( $upload_dir ) ) {
            wp_mkdir_p( $upload_dir );
        }
    }
    
    // Set upload directory constant
    define( 'GRABWP_TENANCY_UPLOAD_DIR', $upload_dir );
    
    // Define UPLOADS constant to redirect WordPress uploads to tenant directory
    if ( ! defined( 'UPLOADS' ) ) {
        define( 'UPLOADS', 'wp-content/grabwp/' . $tenant_id . '/uploads' );
    }
}

// Initialize early loading system
grabwp_tenancy_early_init(); 