<?php
/**
 * GrabWP Tenancy - Helper Functions
 *
 * Contains all utility functions for early tenant initialization.
 * This file is included by load.php before WordPress loads.
 * Function with boot prefix are used to initialize the tenant system, no need to override them in pro plugin
 * Functions with pro prefix are used to extend the tenant system, they can be overridden in pro plugin
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
	// Use pro function if available — single source of truth for all dir resolution.
	if ( function_exists( 'grabwp_tenancy_pro_define_base_dir' ) ) {
		$grabwp_tenancy_dirs = grabwp_tenancy_pro_define_base_dir();
		define( 'GRABWP_TENANCY_BASE_DIR', $grabwp_tenancy_dirs['grabwp_base_dir'] );
		define( 'GRABWP_TENANCY_DIRS_FROM_PLUGIN', true );
		return;
	}

	// Base-only fallback (no pro plugin installed).
	if ( file_exists( ABSPATH . 'wp-content/grabwp/tenants.php' ) ) {
		// Legacy path in 1.0.0
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/grabwp' );
	} else {
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/uploads/grabwp-tenancy' );
	}
	define( 'GRABWP_TENANCY_DIRS_FROM_PLUGIN', true );
}

// =============================================================================
// SECURITY & VALIDATION FUNCTIONS
// =============================================================================

/**
 * Strip null bytes and control characters from a string
 *
 * @param string $value String to clean
 * @return string Cleaned string
 */
function grabwp_tenancy_strip_control_chars( $value ) {
	$value = str_replace( "\0", '', $value );
	$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );
	return $value;
}

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
	$string = grabwp_tenancy_strip_control_chars( $string );

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

	// Remove HTML tags (strip_all_tags already handles control chars)
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
	$url = grabwp_tenancy_strip_control_chars( $url );

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
	if ( function_exists( 'grabwp_tenancy_pro_validate_tenant_id' ) ) {
		return grabwp_tenancy_pro_validate_tenant_id( $tenant_id );
	}

	if ( empty( $tenant_id ) || ! is_string( $tenant_id ) ) {
		return false;
	}

	// Remove null bytes and control characters for security
	$tenant_id = grabwp_tenancy_strip_control_chars( $tenant_id );

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
	if ( function_exists( 'grabwp_tenancy_pro_validate_domain' ) ) {
		return grabwp_tenancy_pro_validate_domain( $domain );
	}
	if ( empty( $domain ) || ! is_string( $domain ) ) {
		return false;
	}

	// Remove null bytes and control characters for security
	$domain = grabwp_tenancy_strip_control_chars( $domain );

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

/**
 * Restrict host to letters, digits, dots, and ASCII hyphens (local network / mDNS-style names).
 *
 * @param string $host Host string (e.g. sanitized HTTP_HOST).
 * @return string Sanitized host, or empty string if nothing usable remains.
 */
function grabwp_tenancy_sanitize_local_network_host( $host ) {
	if ( empty( $host ) || ! is_string( $host ) ) {
		return '';
	}

	$host = grabwp_tenancy_strip_control_chars( $host );
	// Alphanumeric, dot (labels), hyphen — no other characters.
	$host = preg_replace( '/[^a-zA-Z0-9.\-]/', '', $host );
	$host = strtolower( trim( $host, ".-\t\n\r\0\x0B" ) );

	if ( '' === $host || strlen( $host ) > 253 ) {
		return '';
	}

	if ( ! preg_match( '/[a-z0-9]/', $host ) ) {
		return '';
	}

	return $host;
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
	if ( function_exists( 'grabwp_tenancy_pro_get_server_info' ) ) {
		return grabwp_tenancy_pro_get_server_info();
	}
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
		} else {
			$fallback_host = grabwp_tenancy_sanitize_local_network_host( $host );
			$server_info['host'] = '' !== $fallback_host ? $fallback_host : 'localhost';
		}
	}

	// Determine protocol — check direct HTTPS flag first, then reverse-proxy headers
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Early bootstrap, sanitized immediately below
	$https_value = isset( $_SERVER['HTTPS'] ) ? grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $_SERVER['HTTPS'] ) ) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$forwarded_ssl = isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ? grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) : '';

	if (
		( ! empty( $https_value ) && in_array( strtolower( $https_value ), array( 'on', '1', 'true' ), true ) ) ||
		( strtolower( $forwarded_proto ) === 'https' ) ||
		( strtolower( $forwarded_ssl ) === 'on' )
	) {
		$server_info['protocol'] = 'https';
	}

	return $server_info;
}

// =============================================================================
// PATH & DIRECTORY MANAGEMENT — SHARED HELPERS
// =============================================================================
// These helpers are extracted so the pro plugin can reuse them without duplication.

/**
 * Define ABSPATH if not already defined.
 * Safe to call from both base and pro plugins.
 */
function grabwp_tenancy_boot_define_abspath() {
	if ( ! defined( 'ABSPATH' ) ) {
		// dirname() required — WordPress functions unavailable at this stage
		define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
	}
}

/**
 * Define WP_SITEURL, WP_HOME, and cookie-path constants based on the active
 * routing method (domain / path / query).  Idempotent — skips already-defined
 * constants.
 *
 * @param array $server_info Return value of grabwp_tenancy_get_server_info().
 */
function grabwp_tenancy_boot_define_routing_constants( $server_info ) {
	if ( empty( $server_info['host'] ) ) {
		return;
	}

	$base_url = $server_info['protocol'] . '://' . $server_info['host'];

	if ( defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) && in_array( GRABWP_TENANCY_ROUTING_METHOD, array( 'path', 'query' ), true ) ) {
		// Path/query routing: append /site/{tenant_id}
		$tenant_path = defined( 'GRABWP_TENANCY_TENANT_ID' ) ? '/site/' . GRABWP_TENANCY_TENANT_ID : '';
		$site_url    = $base_url . $tenant_path;

		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', $site_url );
		}
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', $site_url );
		}

		// Cookie paths with tenant prefix
		if ( ! empty( $tenant_path ) ) {
			if ( ! defined( 'COOKIEPATH' ) ) {
				define( 'COOKIEPATH', $tenant_path . '/' );
			}
			if ( ! defined( 'SITECOOKIEPATH' ) ) {
				define( 'SITECOOKIEPATH', $tenant_path . '/' );
			}
			if ( ! defined( 'ADMIN_COOKIE_PATH' ) ) {
				define( 'ADMIN_COOKIE_PATH', $tenant_path . '/wp-admin' );
			}
		}
	} else {
		// Domain routing: the host itself identifies the tenant
		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', $base_url );
		}
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', $base_url );
		}
	}
}


// =============================================================================
// PATH & DIRECTORY MANAGEMENT
// =============================================================================

/**
 * Define essential WordPress constants for early loading (base plugin).
 * Orchestrates the shared helpers + base-specific defaults.
 */
function grabwp_tenancy_boot_define_constants() {
	grabwp_tenancy_boot_define_abspath();

	// Default content directory
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	}

	$server_info = grabwp_tenancy_get_server_info();

	// Default content URL
	if ( ! defined( 'WP_CONTENT_URL' ) && ! empty( $server_info['host'] ) ) {
		define( 'WP_CONTENT_URL', $server_info['protocol'] . '://' . $server_info['host'] . '/wp-content' );
	}

	// Default plugin directory
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
	}

	grabwp_tenancy_boot_define_routing_constants( $server_info );

	grabwp_tenancy_set_uploads_paths();

	// Configure database prefix
	grabwp_tenancy_set_database_prefix();
}

/**
 * Set content paths for tenant isolation
 */
function grabwp_tenancy_set_uploads_paths() {
	if ( function_exists( 'grabwp_tenancy_pro_set_uploads_paths' ) ) {
		grabwp_tenancy_pro_set_uploads_paths();
		return;
	}
	// Use GRABWP_TENANCY_BASE_DIR which is now properly defined
	$upload_dir = GRABWP_TENANCY_BASE_DIR . '/' . GRABWP_TENANCY_TENANT_ID . '/uploads';
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
		return grabwp_tenancy_pro_load_tenant_mappings();
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
function grabwp_tenancy_identify_tenant_from_domain( $domain, $mappings ) {
	if ( function_exists( 'grabwp_tenancy_pro_identify_tenant_from_domain' ) ) {
		return grabwp_tenancy_pro_identify_tenant_from_domain( $domain, $mappings );
	}
	if ( empty( $domain ) || ! is_array( $mappings ) ) {
		return false;
	}

	foreach ( $mappings as $tenant_id => $domains ) {
		if ( is_array( $domains ) ) {
			foreach ( $domains as $domain_entry ) {
				if ( $domain === $domain_entry ) {
					define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
					if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
						define( 'GRABWP_TENANCY_ROUTING_METHOD', 'domain' );
					}
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
function grabwp_tenancy_boot_set_tenant_context() {
	// Check if tenant context is already defined
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		if ( ! defined( 'GRABWP_TENANCY_IS_TENANT' ) ) {
			define( 'GRABWP_TENANCY_IS_TENANT', true );
		}
		return;
	}
}

/**
 * Set database prefix for tenant isolation
 */
function grabwp_tenancy_set_database_prefix() {
	if ( function_exists( 'grabwp_tenancy_pro_set_database_prefix' ) ) {
		grabwp_tenancy_pro_set_database_prefix();
		return;
	}
	global $table_prefix, $wpdb;

	// Store original prefix
	if ( ! defined( 'GRABWP_TENANCY_ORIGINAL_PREFIX' ) ) {
		define( 'GRABWP_TENANCY_ORIGINAL_PREFIX', $table_prefix );
	}

	// Set tenant-specific prefix
	$table_prefix = GRABWP_TENANCY_TENANT_ID . '_';

	// Define table prefix constant for reference
	if ( ! defined( 'GRABWP_TENANCY_TABLE_PREFIX' ) ) {
		define( 'GRABWP_TENANCY_TABLE_PREFIX', $table_prefix );
	}

	// Update $wpdb prefix if it exists (for CLI commands)
	if ( isset( $wpdb ) && is_object( $wpdb ) ) {
		$wpdb->prefix = GRABWP_TENANCY_TENANT_ID . '_';
		if ( method_exists( $wpdb, 'set_prefix' ) ) {
			$wpdb->set_prefix( GRABWP_TENANCY_TENANT_ID . '_' );
		}
	}
}



/**
 * Identify tenant from URL path (/site/[tenant-id])
 *
 * Parses REQUEST_URI for the /site/[tenant-id] pattern.
 * Strips the prefix from REQUEST_URI so WordPress routes the remaining path normally.
 *
 * @return string|false Tenant ID or false if not found
 */
function grabwp_tenancy_identify_tenant_from_path() {
	if ( function_exists( 'grabwp_tenancy_pro_identify_tenant_from_path' ) ) {
		return grabwp_tenancy_pro_identify_tenant_from_path();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Early bootstrap, sanitized immediately below
	$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	$uri     = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_uri ) );

	// Match /site/{6-char alphanumeric} at the start of the path
	if ( ! preg_match( '#^/site/([a-z0-9]{6})(/|$)#', $uri, $matches ) ) {
		return false;
	}

	$tenant_id = $matches[1];

	// Verify tenant exists in tenant mappings
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
		return false;
	}

	if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
	}

	// Do NOT strip /site/{tenant_id} from REQUEST_URI here.
	// The .htaccess rules handle Apache-level rewriting.
	// Keeping the original REQUEST_URI preserves the tenant prefix in
	// auth_redirect()'s redirect_to parameter, so the browser stays in
	// tenant context through the login flow.

	if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
		define( 'GRABWP_TENANCY_ROUTING_METHOD', 'path' );
	}

	return $tenant_id;
}

/**
 * Identify tenant from query string (?site=[tenant-id])
 *
 * Fallback for servers without mod_rewrite enabled.
 *
 * @return string|false Tenant ID or false if not found
 */
function grabwp_tenancy_identify_tenant_from_query() {
	if ( function_exists( 'grabwp_tenancy_pro_identify_tenant_from_query' ) ) {
		return grabwp_tenancy_pro_identify_tenant_from_query();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Early bootstrap, sanitized immediately below
	$raw_site  = isset( $_GET['site'] ) ? $_GET['site'] : '';
	$tenant_id = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_site ) );

	if ( empty( $tenant_id ) ) {
		return false;
	}

	// Verify tenant exists in tenant mappings
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
		return false;
	}

	if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
	}

	if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
		define( 'GRABWP_TENANCY_ROUTING_METHOD', 'query' );
	}

	return $tenant_id;
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
	if ( function_exists( 'grabwp_tenancy_pro_configure_cli_environment' ) ) {
		grabwp_tenancy_pro_configure_cli_environment();
		return;
	}
	// Set debug & development constants for CLI
	if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
		define( 'DISALLOW_FILE_MODS', false );
	}
	if ( ! defined( 'WP_DEBUG' ) ) {
		define( 'WP_DEBUG', false );
	}
	if ( ! defined( 'WP_DEBUG_LOG' ) ) {
		define( 'WP_DEBUG_LOG', false );
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
	if ( function_exists( 'grabwp_tenancy_pro_get_cli_domain' ) ) {
		return grabwp_tenancy_pro_get_cli_domain( $tenant_id, $tenant_mappings );
	}
	// Get current domain from mappings for CLI
	if ( isset( $tenant_mappings[ $tenant_id ] ) && ! empty( $tenant_mappings[ $tenant_id ][0] ) ) {
		return $tenant_mappings[ $tenant_id ][0]; // Primary domain
	}

	return $tenant_id . '.grabwp.local'; // Fallback domain
}


/**
 * Detect tenant from CLI, domain mapping, URL path, or query string.
 * Priority: CLI → domain mapping → URL path (/site/id) → query string (?site=id)
 *
 * Domain mapping runs before path/query so that a request like
 * tenantdomain.example/site/otherid correctly resolves to the domain-mapped tenant,
 * not the path-based one.
 */
function grabwp_tenancy_boot_detect_tenant() {
	// CLI: Check for pre-defined tenant ID
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) && GRABWP_TENANCY_TENANT_ID !== '' ) {
		grabwp_tenancy_configure_cli_environment();
		return GRABWP_TENANCY_TENANT_ID;
	}

	// Cron: use main site context for now
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}

	// Domain mapping (highest web priority — authoritative signal)
	$server_info     = grabwp_tenancy_get_server_info();
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	$tenant_id       = grabwp_tenancy_identify_tenant_from_domain( $server_info['host'], $tenant_mappings );

	if ( $tenant_id ) {
		return $tenant_id;
	}

	// URL path fallback: /site/[tenant-id] (shared domain only)
	$tenant_id = grabwp_tenancy_identify_tenant_from_path();
	if ( $tenant_id ) {
		return $tenant_id;
	}

	// Query string fallback: ?site=[tenant-id] (no mod_rewrite)
	$tenant_id = grabwp_tenancy_identify_tenant_from_query();
	if ( $tenant_id ) {
		return $tenant_id;
	}

	return false;
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
	
	// Detect tenant and set tenant ID
	$tenant_id = grabwp_tenancy_boot_detect_tenant();

	if ( ! $tenant_id || ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
		return;
	}
	// Set tenant context (Is Tenant)
	grabwp_tenancy_boot_set_tenant_context();

	// Define constants (Directories, URLs, Home Constants, Security Constants) and configure DB
	grabwp_tenancy_boot_define_constants();
}