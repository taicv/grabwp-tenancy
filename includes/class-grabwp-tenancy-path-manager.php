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
		// 1. Check for user-configured path (placeholder for future feature)
		$config_path = self::get_user_configured_path();
		if ( $config_path ) {
			return $config_path;
		}

		// 2. Simple legacy detection for existing installations
		if ( file_exists( WP_CONTENT_DIR . '/grabwp/tenants.php' ) ) {
			return WP_CONTENT_DIR . '/grabwp';
		}

		// 3. Default to new WordPress-compliant structure
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/grabwp-tenancy';
	}

	/**
	 * Get user-configured base path (future feature placeholder)
	 *
	 * @return string|false User-configured path or false if not set
	 */
	private static function get_user_configured_path() {
		// TODO: Implement user configuration reading
		// This will read from your future config file/option
		// Example: return get_option('grabwp_tenancy_custom_path', false);
		return false;
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
	public static function get_config_file_path( $filename = 'default-tenant-option.php' ) {
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
	 * Check if old structure is being used
	 *
	 * @return bool True if old structure detected
	 */
	public static function is_using_old_structure() {
		$current_base = self::get_configured_base_path();
		return $current_base === WP_CONTENT_DIR . '/grabwp';
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
