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
 * Get current protocol (http/https)
 * 
 * @return string Protocol
 */
function grabwp_tenancy_get_protocol() {
    return ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
}

/**
 * Validate tenant ID format
 * 
 * @param string $tenant_id Tenant identifier
 * @return bool True if valid
 */
function grabwp_tenancy_validate_tenant_id( $tenant_id ) {
    return ! empty( $tenant_id ) && preg_match( '/^[a-z0-9]{6}$/', $tenant_id );
}

/**
 * Get content directory path
 * 
 * @return string Content directory path
 */
function grabwp_tenancy_get_content_dir() {
    return defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
}

/**
 * Define WordPress constants with proper domain context
 * 
 * @param string $domain Domain to use for constants
 */
function grabwp_tenancy_define_wordpress_constants( $domain ) {
    $protocol = grabwp_tenancy_get_protocol();
    
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
        define( 'WP_CONTENT_URL', $protocol . '://' . $domain . '/wp-content' );
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
        define( 'WP_SITEURL', $protocol . '://' . $domain );
    }
    
    // Define WP_HOME if not already defined
    if ( ! defined( 'WP_HOME' ) ) {
        define( 'WP_HOME', $protocol . '://' . $domain );
    }
}

/**
 * Load tenant domain mappings from file
 * 
 * @return array Tenant mappings array
 */
function grabwp_tenancy_load_tenant_mappings() {
    $content_dir = grabwp_tenancy_get_content_dir();
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
 * Set tenant context constants
 * 
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_set_tenant_context( $tenant_id ) {
    if ( grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
        define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
        define( 'GRABWP_TENANCY_IS_TENANT', true );
    } else {
        define( 'GRABWP_TENANCY_IS_TENANT', false );
        define( 'GRABWP_TENANCY_TENANT_ID', '' );
    }
}

/**
 * Set database prefix for tenant isolation
 * 
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_set_database_prefix( $tenant_id ) {
    if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
        return;
    }
    
    global $table_prefix;
    
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
    if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
        return;
    }
    
    $content_dir = grabwp_tenancy_get_content_dir();
    $upload_dir = $content_dir . '/grabwp/' . $tenant_id . '/uploads';
    
    // Create directory if it doesn't exist
    if ( ! file_exists( $upload_dir ) && ! is_dir( $upload_dir ) ) {
        wp_mkdir_p( $upload_dir );
    }
    
    // Set upload directory constant
    define( 'GRABWP_TENANCY_UPLOAD_DIR', $upload_dir );
    
    // Define UPLOADS constant to redirect WordPress uploads to tenant directory
    if ( ! defined( 'UPLOADS' ) ) {
        define( 'UPLOADS', 'wp-content/grabwp/' . $tenant_id . '/uploads' );
    }
}

/**
 * Configure tenant-specific settings
 * 
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_configure_tenant( $tenant_id ) {
    if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
        return;
    }
    
    grabwp_tenancy_set_database_prefix( $tenant_id );
    grabwp_tenancy_set_content_paths( $tenant_id );
}

/**
 * Early tenant identification and configuration
 * 
 * This function follows the domain routing flow:
 * 1. Extract domain from HTTP request
 * 2. Load tenant mappings from file
 * 3. Search for tenant by domain
 * 4. Set tenant context and configure isolation
 */
function grabwp_tenancy_early_init() {
    // Step 1: Extract domain from HTTP request
    $current_domain = grabwp_tenancy_get_http_host();
    
    // Step 2: Load tenant mappings from file
    $tenant_mappings = grabwp_tenancy_load_tenant_mappings();
    
    // Step 3: Search for tenant by domain
    $tenant_id = grabwp_tenancy_identify_tenant( $current_domain, $tenant_mappings );
    
    // Step 4: Define WordPress constants with proper domain context
    grabwp_tenancy_define_wordpress_constants( $current_domain );
    
    // Step 5: Set tenant context
    grabwp_tenancy_set_tenant_context( $tenant_id );
    
    // Step 6: Configure tenant-specific settings if tenant found
    if ( $tenant_id ) {
        grabwp_tenancy_configure_tenant( $tenant_id );
    }
}

// Initialize early loading system
grabwp_tenancy_early_init(); 