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
define( 'GRABWP_TENANCY_LOADED', true );

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
    if(defined('GRABWP_TENANCY_TENANT_ID')){
        define( 'GRABWP_TENANCY_IS_TENANT', true );
        return;
    }
    else if ( grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
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
    if ( ! defined( 'GRABWP_TENANCY_TABLE_PREFIX' ) ) {
        define( 'GRABWP_TENANCY_TABLE_PREFIX', $table_prefix );
    }
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

    // Do NOT create directory here; defer to loader class after WP loads
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
 * 1. Check for pre-defined tenant ID (from --exec)
 * 2. Load tenant mappings and get current domain
 * 3. Set tenant context and configure isolation
 * 
 * If no tenant is detected, the function does nothing.
 */
function grabwp_tenancy_early_init() {
    global $table_prefix;
    $tenant_id = null;
    $current_domain = '';
    
    // Step 1: Check for pre-defined tenant ID (from --exec)
    if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) && GRABWP_TENANCY_TENANT_ID !== '' ) {
        $tenant_id = GRABWP_TENANCY_TENANT_ID;
        // Set debug & Upload constants 
        if ( !defined('DISALLOW_FILE_MODS') ) {
            define('DISALLOW_FILE_MODS', false );
        }
        if ( !defined('WP_DEBUG') ) {
            define('WP_DEBUG', false );
        }
        if ( !defined('WP_DEBUG_LOG') ) {
            define('WP_DEBUG_LOG', true );
        }
        if ( !defined('WP_DEBUG_DISPLAY') ) {
            define('WP_DEBUG_DISPLAY', false );
        }
        
        // Get current domain from mappings for CLI
        $tenant_mappings = grabwp_tenancy_load_tenant_mappings();
        
        if ( isset( $tenant_mappings[ $tenant_id ] ) && ! empty( $tenant_mappings[ $tenant_id ][0] ) ) {
            $current_domain = $tenant_mappings[ $tenant_id ][0]; // Primary domain
        } else {
            $current_domain = $tenant_id . '.grabwp.local'; // Fallback domain
        }
    } else {
        // Step 2: Extract domain from HTTP request
        $current_domain = grabwp_tenancy_get_http_host();
        
        // Step 3: Load tenant mappings from file
        $tenant_mappings = grabwp_tenancy_load_tenant_mappings();
        
        // Step 4: Search for tenant by domain
        $tenant_id = grabwp_tenancy_identify_tenant( $current_domain, $tenant_mappings );
    }

    // If no tenant is detected, do nothing
    if ( ! $tenant_id ) {
        return;
    }else{

        // Step 5: Define WordPress constants with proper domain context
        grabwp_tenancy_define_wordpress_constants( $current_domain );
        
        // Step 6: Set tenant context
        grabwp_tenancy_set_tenant_context( $tenant_id );
        
        // Step 7: Configure tenant-specific settings
        grabwp_tenancy_configure_tenant( $tenant_id );
    }
}

// Initialize early loading system
grabwp_tenancy_early_init(); 