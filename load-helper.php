<?php
/**
 * GrabWP Tenancy - Helper Functions
 *
 * Contains all utility functions for early tenant initialization.
 * This file is included by load.php before WordPress loads.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.3
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// PRO PLUGIN INTEGRATION
// =============================================================================

/**
 * Load pro plugin helper functions if available
 * Pro plugin functions take priority over base plugin functions
 */
function grabwp_tenancy_load_pro_helper() {
	$pro_helper_path = __DIR__ . '/../grabwp-tenancy-pro/load-helper-pro.php';
	if ( file_exists( $pro_helper_path ) && is_readable( $pro_helper_path ) ) {
		require_once $pro_helper_path;
		return true;
	}
	
	return false;
}

// Load pro helper immediately if available
$grabwp_tenancy_pro_loaded = grabwp_tenancy_load_pro_helper();



/**
 * Define GRABWP_TENANCY_BASE_DIR immediately when this file loads
 * This is required for early loading tenant config detection
 */
if ( ! defined( 'GRABWP_TENANCY_BASE_DIR' ) ) {
	// Use pro version if available
	if ( function_exists( 'grabwp_tenancy_pro_define_base_dir' ) ) {
		$base_dir = grabwp_tenancy_pro_define_base_dir();
		if ( $base_dir ) {
			define( 'GRABWP_TENANCY_BASE_DIR', $base_dir['grabwp_base_dir'] );
			return;
		}
	}
	if ( file_exists( ABSPATH . 'wp-content/grabwp/tenants.php' ) ) {
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/grabwp' );
	} else {
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/uploads/grabwp-tenancy' );
	}

	
}

// =============================================================================
// SECURITY & VALIDATION FUNCTIONS
// =============================================================================

/**
 * Remove slashes from a string or array of strings
 *
 * @param string|array $value String or array of strings to unslash
 * @return string|array Unslashed value
 */
function grabwp_tenancy_wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'grabwp_tenancy_wp_unslash', $value );
	}

	if ( is_string( $value ) ) {
		return stripslashes( $value );
	}

	return $value;
}

/**
 * Strip all HTML tags from a string (WordPress-compatible)
 *
 * @param string $string String to strip tags from
 * @param string $allowable_tags Optional allowed tags
 * @return string String with tags stripped
 */
function grabwp_tenancy_wp_strip_all_tags( $string, $allowable_tags = '' ) {
	if ( is_object( $string ) || is_array( $string ) ) {
		return '';
	}

	$string = (string) $string;

	// Remove null bytes and control characters
	$string = str_replace( "\0", '', $string );
	$string = preg_replace( '/[\x00-\x1F\x7F]/', '', $string );

	// Remove HTML tags
	$string = strip_tags( $string, $allowable_tags );

	return $string;
}

/**
 * Sanitize a string for safe use in text fields
 *
 * @param string $str String to sanitize
 * @return string Sanitized string
 */
function grabwp_tenancy_sanitize_text_field( $str ) {
	if ( is_object( $str ) || is_array( $str ) ) {
		return '';
	}

	$str = (string) $str;

	// Remove null bytes and control characters
	$str = str_replace( "\0", '', $str );
	$str = preg_replace( '/[\x00-\x1F\x7F]/', '', $str );

	// Remove HTML tags using our WordPress-compatible function
	$str = grabwp_tenancy_wp_strip_all_tags( $str );

	// Remove extra whitespace
	$str = trim( $str );

	return $str;
}

/**
 * Sanitize a URL for safe use
 *
 * @param string $url URL to sanitize
 * @return string Sanitized URL or empty string
 */
function grabwp_tenancy_sanitize_url( $url ) {
	if ( is_object( $url ) || is_array( $url ) ) {
		return '';
	}

	$url = (string) $url;

	// Remove null bytes and control characters
	$url = str_replace( "\0", '', $url );
	$url = preg_replace( '/[\x00-\x1F\x7F]/', '', $url );

	// Basic URL validation
	if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return $url;
	}

	return '';
}

/**
 * Validate tenant ID format
 *
 * @param string $tenant_id Tenant identifier
 * @return bool True if valid
 */
function grabwp_tenancy_validate_tenant_id( $tenant_id ) {
	if ( empty( $tenant_id ) || ! is_string( $tenant_id ) ) {
		return false;
	}

	// Remove null bytes and control characters for security
	$tenant_id = str_replace( "\0", '', $tenant_id );
	$tenant_id = preg_replace( '/[\x00-\x1F\x7F]/', '', $tenant_id );

	// Trim whitespace
	$tenant_id = trim( $tenant_id );

	// Validate format: exactly 6 characters, lowercase alphanumeric
	if ( ! preg_match( '/^[a-z0-9]{6}$/', $tenant_id ) ) {
		return false;
	}

	// Block reserved/problematic tenant IDs for security
	$reserved_ids = array(
		'admin1',
		'admin2',
		'admin3',
		'admin4',
		'admin5', // Admin variations
		'root01',
		'root02',
		'root03',
		'root04',
		'root05', // Root variations
		'test01',
		'test02',
		'test03',
		'test04',
		'test05', // Test variations
		'guest1',
		'guest2',
		'guest3',
		'guest4',
		'guest5', // Guest variations
		'user01',
		'user02',
		'user03',
		'user04',
		'user05', // User variations
		'public',
		'privat', // Public/private keywords
		'system',
		'config',
		'backup',
		'upload',
		'assets', // System keywords
		'000000',
		'111111',
		'222222',
		'333333',
		'444444', // Repetitive patterns
		'555555',
		'666666',
		'777777',
		'888888',
		'999999',
		'aaaaaa',
		'bbbbbb',
		'cccccc',
		'dddddd',
		'eeeeee', // Letter patterns
		'ffffff',
		'gggggg',
		'hhhhhh',
		'iiiiii',
		'jjjjjj',
		'123456',
		'654321',
		'abc123',
		'123abc',
		'qwerty', // Common sequences
	);

	if ( in_array( $tenant_id, $reserved_ids, true ) ) {
		return false;
	}

	return true;
}

/**
 * Validate domain name format
 *
 * @param string $domain Domain to validate
 * @return bool True if valid domain format
 */
function grabwp_tenancy_validate_domain( $domain ) {
	if ( empty( $domain ) || ! is_string( $domain ) ) {
		return false;
	}

	// Remove null bytes and control characters for security
	$domain = str_replace( "\0", '', $domain );
	$domain = preg_replace( '/[\x00-\x1F\x7F]/', '', $domain );

	// Trim and convert to lowercase for consistent validation
	$domain = strtolower( trim( $domain ) );

	// Check length (domain names max 253 characters)
	if ( strlen( $domain ) > 253 || strlen( $domain ) < 4 ) { // Minimum: a.co
		return false;
	}

	// Enhanced domain format validation
	if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/', $domain ) ) {
		return false;
	}

	// Check for valid TLD (at least 2 characters)
	$parts = explode( '.', $domain );
	if ( count( $parts ) < 2 ) {
		return false;
	}

	$tld = end( $parts );
	if ( strlen( $tld ) < 2 || strlen( $tld ) > 63 ) {
		return false;
	}

	// Block common invalid patterns for security
	$invalid_patterns = array(
		'/^[0-9]+$/',                                    // All numbers
		'/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/',           // IP address
		'/^localhost$/',                                 // localhost
		'/\.localhost$/',                                // subdomain.localhost
		'/^127\.0\.0\.1$/',                             // localhost IP
		'/^192\.168\./',                                 // Private IP range
		'/^10\./',                                       // Private IP range
		'/^172\.(1[6-9]|2[0-9]|3[0-1])\./',            // Private IP range
	);

	foreach ( $invalid_patterns as $pattern ) {
		if ( preg_match( $pattern, $domain ) ) {
			return false;
		}
	}

	return true;
}

// =============================================================================
// SERVER & ENVIRONMENT DETECTION
// =============================================================================

/**
 * Get sanitized and validated server information (cached for performance)
 *
 * @return array Array containing sanitized host and protocol
 */
function grabwp_tenancy_get_server_info() {
	// Cache server info to avoid multiple $_SERVER access
	static $server_info = null;

	if ( null !== $server_info ) {
		return $server_info;
	}

	$server_info = array(
		'host'     => '',
		'protocol' => 'http',
	);

	// Check if host is empty and HTTP_HOST is available
	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		// Sanitize and unslash the HTTP_HOST value immediately for WPCS compliance
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Early initialization requires direct $_SERVER access, immediately sanitized below
		$raw_host = $_SERVER['HTTP_HOST'];
		$host     = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_host ) );

		// Validate domain format before using
		if ( grabwp_tenancy_validate_domain( $host ) ) {
			$server_info['host'] = $host;
		}
	}

	// Determine protocol
	if ( isset( $_SERVER['HTTPS'] ) ) {
		// Sanitize and unslash HTTPS value immediately for WPCS compliance
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Early initialization requires direct $_SERVER access, immediately sanitized below
		$raw_https   = $_SERVER['HTTPS'];
		$https_value = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_https ) );

		// Validate HTTPS value and set protocol
		if ( ! empty( $https_value ) && ( $https_value === 'on' || $https_value === '1' || strtolower( $https_value ) === 'true' ) ) {
			$server_info['protocol'] = 'https';
		}
	}

	return $server_info;
}

// =============================================================================
// PATH & DIRECTORY MANAGEMENT
// =============================================================================


/**
 * Define essential WordPress constants for early loading
 *
 * @param string $tenant_id Optional tenant identifier
 */
function grabwp_tenancy_define_constants( $tenant_id = '' ) {
	// Use pro version if available
	if ( function_exists( 'grabwp_tenancy_pro_define_constants' ) ) {
		grabwp_tenancy_pro_define_constants( $tenant_id );
		return;
	}
	
	// Base plugin version
	// Define ABSPATH if not already defined
	if ( ! defined( 'ABSPATH' ) ) {
		// Note: Using dirname() here is necessary for early loading in wp-config.php
		// WordPress functions like plugin_dir_path() are not available at this stage
		define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
	}

	// Define WP_CONTENT_DIR if not already defined
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	}

	// Get server information once
	$server_info = grabwp_tenancy_get_server_info();

	// Define WP_CONTENT_URL if not already defined
	if ( ! defined( 'WP_CONTENT_URL' ) && ! empty( $server_info['host'] ) ) {
		define( 'WP_CONTENT_URL', $server_info['protocol'] . '://' . $server_info['host'] . '/wp-content' );
	}

	// Define WP_PLUGIN_DIR if not already defined
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
	}


	// Define WP_SITEURL if not already defined
	if ( ! defined( 'WP_SITEURL' ) && ! empty( $server_info['host'] ) ) {
		define( 'WP_SITEURL', $server_info['protocol'] . '://' . $server_info['host'] );
	}

	// Define WP_HOME if not already defined
	if ( ! defined( 'WP_HOME' ) && ! empty( $server_info['host'] ) ) {
		define( 'WP_HOME', $server_info['protocol'] . '://' . $server_info['host'] );
	}

	// Define security constants if not already defined
	if ( ! defined( 'DISABLE_FILE_EDIT' ) ) {
		define( 'DISABLE_FILE_EDIT', true );
	}

	if ( ! defined( 'DISABLE_FILE_MODS' ) ) {
		define( 'DISABLE_FILE_MODS', true );
	}

	grabwp_tenancy_set_uploads_paths( $tenant_id );
}

/**
 * Set content paths for tenant isolation
 *
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_set_uploads_paths( $tenant_id ) {
	// Base plugin version
	// Validate tenant ID
	if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
		return;
	}

	// Use GRABWP_TENANCY_BASE_DIR which is now properly defined
	$upload_dir = GRABWP_TENANCY_BASE_DIR . '/' . $tenant_id . '/uploads';
	$upload_relative = str_replace( ABSPATH, '', $upload_dir );
	
	define( 'GRABWP_TENANCY_UPLOAD_DIR', $upload_dir );

	if ( ! defined( 'UPLOADS' ) ) {
		define( 'UPLOADS', $upload_relative );
	}

}

// =============================================================================
// TENANT DETECTION & MAPPING
// =============================================================================

/**
 * Load tenant domain mappings from file (cached for performance)
 *
 * @return array Tenant mappings array
 */
function grabwp_tenancy_load_tenant_mappings() {
	// Use pro version if available
	if ( function_exists( 'grabwp_tenancy_pro_load_tenant_mappings' ) ) {
		grabwp_tenancy_pro_load_tenant_mappings();
		return;
	}
	// Cache mappings to avoid multiple file reads
	static $tenant_mappings = null;

	if ( null !== $tenant_mappings ) {
		return $tenant_mappings;
	}

	$mappings_file = GRABWP_TENANCY_BASE_DIR . '/tenants.php';

	if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
		$tenant_mappings = array();
		include $mappings_file;
		return $tenant_mappings;
	}

	$tenant_mappings = array();
	return $tenant_mappings;
}

/**
 * Identify tenant by domain
 *
 * @param string $domain Current domain
 * @param array  $mappings Tenant domain mappings
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

// =============================================================================
// DATABASE & TENANT CONFIGURATION
// =============================================================================

/**
 * Set tenant context constants
 *
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_set_tenant_context( $tenant_id ) {
	// Use pro version if available
	if ( function_exists( 'grabwp_tenancy_pro_set_tenant_context' ) ) {
		grabwp_tenancy_pro_set_tenant_context( $tenant_id );
		return;
	}
	// Check if tenant context is already defined
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		if ( ! defined( 'GRABWP_TENANCY_IS_TENANT' ) ) {
			define( 'GRABWP_TENANCY_IS_TENANT', true );
		}
		return;
	}

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

	global $table_prefix, $wpdb;

	// Store original prefix
	if ( ! defined( 'GRABWP_TENANCY_ORIGINAL_PREFIX' ) ) {
		define( 'GRABWP_TENANCY_ORIGINAL_PREFIX', $table_prefix );
	}

	// Set tenant-specific prefix
	$table_prefix = $tenant_id . '_';

	// Define table prefix constant for reference
	if ( ! defined( 'GRABWP_TENANCY_TABLE_PREFIX' ) ) {
		define( 'GRABWP_TENANCY_TABLE_PREFIX', $table_prefix );
	}

	// Update $wpdb prefix if it exists (for CLI commands)
	if ( isset( $wpdb ) && is_object( $wpdb ) ) {
		$wpdb->prefix = $tenant_id . '_';
		if ( method_exists( $wpdb, 'set_prefix' ) ) {
			$wpdb->set_prefix( $tenant_id . '_' );
		}
	}
}

/**
 * Configure tenant-specific settings
 *
 * @param string $tenant_id Tenant identifier
 */
function grabwp_tenancy_configure_tenant( $tenant_id ) {
	// Use pro version if available
	if ( function_exists( 'grabwp_tenancy_pro_configure_tenant' ) ) {
		grabwp_tenancy_pro_configure_tenant( $tenant_id );
		return;
	}
	
	// Base plugin version
	if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
		return;
	}

	grabwp_tenancy_set_database_prefix( $tenant_id );
}

// =============================================================================
// CLI & DEVELOPMENT SUPPORT
// =============================================================================

/**
 * Configure CLI and development environment constants
 *
 * @param string $tenant_id Tenant identifier for CLI context
 */
function grabwp_tenancy_configure_cli_environment() {
	// Set debug & development constants for CLI
	if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
		define( 'DISALLOW_FILE_MODS', false );
	}
	if ( ! defined( 'WP_DEBUG' ) ) {
		define( 'WP_DEBUG', false );
	}
	if ( ! defined( 'WP_DEBUG_LOG' ) ) {
		define( 'WP_DEBUG_LOG', true );
	}
	if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
		define( 'WP_DEBUG_DISPLAY', false );
	}
}

/**
 * Get fallback domain for CLI operations
 *
 * @param string $tenant_id Tenant identifier
 * @param array  $tenant_mappings Available tenant mappings
 * @return string Domain for CLI context
 */
function grabwp_tenancy_get_cli_domain( $tenant_id, $tenant_mappings ) {
	// Get current domain from mappings for CLI
	if ( isset( $tenant_mappings[ $tenant_id ] ) && ! empty( $tenant_mappings[ $tenant_id ][0] ) ) {
		return $tenant_mappings[ $tenant_id ][0]; // Primary domain
	}

	return $tenant_id . '.grabwp.local'; // Fallback domain
}


/**
 * Detect tenant from CLI or domain
 */
function grabwp_tenancy_detect_tenant() {
	// CLI: Check for pre-defined tenant ID
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) && GRABWP_TENANCY_TENANT_ID !== '' ) {
		grabwp_tenancy_configure_cli_environment();
		return GRABWP_TENANCY_TENANT_ID;
	}

	// Web: Get domain and find tenant
	$server_info     = grabwp_tenancy_get_server_info();
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();

	return grabwp_tenancy_identify_tenant( $server_info['host'], $tenant_mappings );
}



/**
 * Initialize tenant system
 */
function grabwp_tenancy_early_init() {
	// Use pro version if available
	if ( function_exists( 'grabwp_tenancy_pro_early_init' ) ) {
		grabwp_tenancy_pro_early_init();
		return;
	}
	
	// Base plugin version
	$tenant_id = grabwp_tenancy_detect_tenant();

	if ( ! $tenant_id ) {
		return;
	}

	grabwp_tenancy_set_tenant_context( $tenant_id );
	grabwp_tenancy_define_constants( $tenant_id );
	grabwp_tenancy_configure_tenant( $tenant_id );
}