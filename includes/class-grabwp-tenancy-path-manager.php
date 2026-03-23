<?php
/**
 * GrabWP Tenancy - Path Manager
 *
 * Centralized path management with fallback logic for backward compatibility
 * and future extensibility for user-configurable paths.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback definition of grabwp_tenancy_validate_tenant_id()
 *
 * This ensures the Path Manager works even when load-helper.php (drop-in)
 * has not been loaded. If load-helper.php IS loaded first, its version
 * takes priority and this block is skipped entirely.
 *
 * @since 1.0.5
 */
if ( ! function_exists( 'grabwp_tenancy_validate_tenant_id' ) ) {
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
			'admin1', 'admin2', 'admin3', 'admin4', 'admin5',
			'root01', 'root02', 'root03', 'root04', 'root05',
			'test01', 'test02', 'test03', 'test04', 'test05',
			'guest1', 'guest2', 'guest3', 'guest4', 'guest5',
			'user01', 'user02', 'user03', 'user04', 'user05',
			'public', 'privat',
			'system', 'config', 'backup', 'upload', 'assets',
			'000000', '111111', '222222', '333333', '444444',
			'555555', '666666', '777777', '888888', '999999',
			'aaaaaa', 'bbbbbb', 'cccccc', 'dddddd', 'eeeeee',
			'ffffff', 'gggggg', 'hhhhhh', 'iiiiii', 'jjjjjj',
			'123456', '654321', 'abc123', '123abc', 'qwerty',
		);

		if ( in_array( $tenant_id, $reserved_ids, true ) ) {
			return false;
		}

		return true;
	}
}

/**
 * Path Manager class
 *
 * Handles all file and directory path resolution with support for:
 * - Backward compatibility (old wp-content/grabwp structure)
 * - New WordPress-compliant structure (uploads/grabwp-tenancy)
 * - Future user-configurable paths
 */
class GrabWP_Tenancy_Path_Manager {

	/**
	 * Get configured base path for tenant data storage
	 *
	 * Priority order:
	 * 1. User-configured path (future feature)
	 * 2. Legacy migration detection (minimal fallback)
	 * 3. Default new structure
	 *
	 * @return string Base directory path for tenant data
	 */
	public static function get_configured_base_path() {

		// 1. Check if GRABWP_TENANCY_BASE_DIR is defined (for plugin activated on tenant sites)
		if ( defined( 'GRABWP_TENANCY_BASE_DIR' ) ) {
			return GRABWP_TENANCY_BASE_DIR;
		}

		// 2. Simple legacy detection for existing installations
		if ( file_exists( WP_CONTENT_DIR . '/grabwp/tenants.php' ) ) {
			return WP_CONTENT_DIR . '/grabwp';
		}

		// 3. 
		if ( file_exists( WP_CONTENT_DIR . '/uploads/grabwp-tenancy/tenants.php' ) ) {
			return WP_CONTENT_DIR . '/uploads/grabwp-tenancy';
		}

		// 4. Default to new WordPress-compliant structure
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/grabwp-tenancy';
	}

	/**
	 * Get tenant mappings file path
	 *
	 * Uses the configured base path for consistency
	 *
	 * @return string Path to tenants file
	 */
	public static function get_tenants_file_path() {
		$base_path = self::get_configured_base_path();
		return $base_path . '/tenants.php';
	}

	/**
	 * Get base directory for tenant data storage
	 *
	 * Uses the configured base path for consistency
	 *
	 * @return string Base directory path
	 */
	public static function get_tenants_base_dir() {
		return self::get_configured_base_path();
	}

	/**
	 * Get tenant upload directory
	 *
	 * @param string $tenant_id Tenant ID
	 * @return string Tenant upload directory path
	 */
	public static function get_tenant_upload_dir( $tenant_id ) {
		if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
			return false;
		}

		$base_path = self::get_configured_base_path();
		return $base_path . '/' . $tenant_id . '/uploads';
	}

	/**
	 * Get tenant upload URL
	 *
	 * @param string $tenant_id Tenant ID
	 * @return string Tenant upload URL
	 */
	public static function get_tenant_upload_url( $tenant_id ) {
		if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
			return false;
		}

		$base_path = self::get_configured_base_path();

		// Prevent infinite loop: get upload directory without filters
		static $recursion_guard = false;
		if ( $recursion_guard ) {
			// Fallback to basic URL construction if we're in a recursive call
			$relative_path = str_replace( WP_CONTENT_DIR, '', $base_path );
			return content_url( $relative_path . '/' . $tenant_id . '/uploads' );
		}

		$recursion_guard = true;
		$upload_dir      = wp_upload_dir();
		$recursion_guard = false;

		// Check if using uploads directory structure
		if ( strpos( $base_path, $upload_dir['basedir'] ) === 0 ) {
			// Base is within uploads directory - use upload URL
			$relative_path = str_replace( $upload_dir['basedir'], '', $base_path );
			return $upload_dir['baseurl'] . $relative_path . '/' . $tenant_id . '/uploads';
		} else {
			// Base is in wp-content or custom location - use content URL
			$relative_path = str_replace( WP_CONTENT_DIR, '', $base_path );
			$relative_path = ltrim( $relative_path, '/' ); // Remove leading slash for content_url()
			return content_url( $relative_path . '/' . $tenant_id . '/uploads' );
		}
	}

	/**
	 * Get config file path
	 *
	 * @param string $filename Config filename
	 * @return string Config file path
	 */
	public static function get_config_file_path( $filename = '' ) {
		$base_path = self::get_configured_base_path();
		return $base_path . '/' . $filename;
	}

	/**
	 * Get tokens file path
	 *
	 * @return string Tokens file path
	 */
	public static function get_tokens_file_path() {
		return self::get_config_file_path( 'tokens.php' );
	}


	/**
	 * Check if old structure is being used
	 *
	 * @return bool True if old structure detected
	 */
	public static function is_using_old_structure() {
		$current_base = self::get_configured_base_path();
		return $current_base === WP_CONTENT_DIR . '/grabwp';
	}

	/**
	 * Ensure directory exists (create if needed)
	 *
	 * @param string $path Directory path
	 * @return bool Success status
	 */
	public static function ensure_directory_exists( $path ) {
		if ( is_dir( $path ) ) {
			return true;
		}

		return wp_mkdir_p( $path );
	}


	/**
	 * Get current structure type
	 *
	 * @return string 'old', 'new', or 'custom'
	 */
	public static function get_structure_type() {
		$current_base = self::get_configured_base_path();
		$upload_dir   = wp_upload_dir();

		if ( $current_base === WP_CONTENT_DIR . '/grabwp' ) {
			return 'old';
		} elseif ( $current_base === $upload_dir['basedir'] . '/grabwp-tenancy' ) {
			return 'new';
		} else {
			return 'custom';
		}
	}

	/**
	 * Get path status information for admin display
	 *
	 * @return array Path status information
	 */
	public static function get_path_status() {
		$current_base   = self::get_configured_base_path();
		$structure_type = self::get_structure_type();

		return array(
			'current_base'   => $current_base,
			'structure_type' => $structure_type,
			'tenants_file'   => file_exists( $current_base . '/tenants.php' ),
			'using_old'      => $structure_type === 'old',
			'is_custom'      => $structure_type === 'custom',
		);
	}
}
