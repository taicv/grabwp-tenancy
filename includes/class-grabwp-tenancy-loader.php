<?php
/**
 * GrabWP Tenancy Loader Class
 *
 * Handles WordPress integration, content path management, and upload directory isolation.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Loader Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Loader {

	/**
	 * Plugin instance
	 *
	 * @var GrabWP_Tenancy
	 */
	private $plugin;

	/**
	 * Constructor
	 *
	 * @param GrabWP_Tenancy $plugin Plugin instance
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Admin access token handling - early priority for tenant sites
		if ( $this->plugin->is_tenant() ) {
			add_action( 'init', array( $this, 'handle_admin_token' ), 5 );
		}
		// Allow pro plugin to extend
		do_action( 'grabwp_tenancy_loader_init', $this );
	}



	/**
	 * Create tenant directories
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function create_tenant_directories( $tenant_id ) {
		$upload_dir = GrabWP_Tenancy_Path_Manager::get_tenant_upload_dir( $tenant_id );

		if ( ! file_exists( $upload_dir ) ) {
			return wp_mkdir_p( $upload_dir );
		}

		return true;
	}

	/**
	 * Remove tenant directories
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function remove_tenant_directories( $tenant_id ) {
		$base_path = GrabWP_Tenancy_Path_Manager::get_configured_base_path();
		$tenant_dir = $base_path . '/' . $tenant_id;

		if ( file_exists( $tenant_dir ) ) {
			return $this->recursive_rmdir( $tenant_dir );
		}

		return true;
	}

	/**
	 * Remove tenant database tables
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function remove_tenant_database_tables( $tenant_id ) {
		global $wpdb;

		// Validate tenant ID
		if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
			return false;
		}

		// Get all tables with tenant prefix
		$tenant_prefix = $tenant_id . '_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for tenant table cleanup, no caching needed for administrative operation
		$tables = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$tenant_prefix . '%'
			),
			ARRAY_N
		);

		if ( empty( $tables ) ) {
			return true; // No tables to remove
		}

		$success = true;
		foreach ( $tables as $table ) {
			$table_name = $table[0];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for tenant table cleanup, table name cannot be prepared, no caching needed for administrative operation
			$result = $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
			
			if ( false === $result ) {
				$success = false;
				GrabWP_Tenancy_Logger::log( GRABWP_TENANCY_TENANT_ID.' - Failed to drop table '.$table_name );
			}
		}

		return $success;
	}

	/**
	 * Recursively remove directory
	 *
	 * @param string $dir Directory path
	 * @return bool Success status
	 */
	private function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		// Use WordPress filesystem API
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem && $wp_filesystem->is_dir( $dir ) ) {
			return $wp_filesystem->rmdir( $dir, true );
		}

		// If filesystem API is not available, return false
		// This ensures we don't use direct PHP filesystem calls
		return false;
	}

	/**
	 * Handle admin access token for auto-login on tenant sites
	 */
	public function handle_admin_token() {
		// Only handle on login page or admin pages
		if ( ! is_admin() && ! $this->is_login_page() ) {
			return;
		}

		// Only process on tenant sites
		if ( ! $this->plugin->is_tenant() ) {
			return;
		}

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Auto-login tokens from main site, validated via hash
		$token = isset( $_GET['grabwp_token'] ) ? sanitize_text_field( wp_unslash( $_GET['grabwp_token'] ) ) : '';

		$hash = isset( $_GET['grabwp_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['grabwp_hash'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $token ) ) {
			return;
		}

		// Validate global token and hash using tenant class methods
		$is_valid_token = GrabWP_Tenancy_Tenant::validate_admin_token( $token, $hash );
		if ( ! $is_valid_token ) {
			$this->handle_token_error( 'Invalid or expired admin access token.' );
			return;
		}

		// Get tenant ID
		$tenant_id = $this->plugin->get_tenant_id();
		if ( ! $tenant_id ) {
			$this->handle_token_error( 'Tenant identification failed.' );
			return;
		}

		// Get admin user with lowest ID
		$admin_user = $this->get_lowest_admin_user();
		if ( ! $admin_user ) {
			$this->handle_token_error( 'No admin user found for tenant access.' );
			return;
		}

		// Log the user in
		wp_set_current_user( $admin_user->ID, $admin_user->user_login );
		wp_set_auth_cookie( $admin_user->ID, true );

		GrabWP_Tenancy_Logger::log( GRABWP_TENANCY_TENANT_ID.' - Admin user logged in: ' . $admin_user->user_login );

		// Redirect to wp-admin to remove token from URL
		wp_redirect( admin_url() );
		exit;
	}

	/**
	 * Handle token authentication errors gracefully
	 *
	 * @param string $message Error message to display
	 */
	private function handle_token_error( $message ) {
		// Add admin notice for next page load
		add_option( 'grabwp_tenancy_token_error', $message );

		// Add hook to display notice
		add_action( 'admin_notices', array( $this, 'display_token_error_notice' ) );
		add_action( 'login_message', array( $this, 'display_login_error_message' ) );

		// Redirect to login page with error parameter
		$login_url = wp_login_url();
		$login_url = add_query_arg( 'grabwp_token_error', '1', $login_url );

		wp_redirect( $login_url );
		exit;
	}

	/**
	 * Display token error notice in admin
	 */
	public function display_token_error_notice() {
		$error = get_option( 'grabwp_tenancy_token_error' );
		if ( $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
			delete_option( 'grabwp_tenancy_token_error' );
		}
	}

	/**
	 * Display token error message on login page
	 *
	 * @param string $message Existing login message
	 * @return string Modified login message
	 */
	public function display_login_error_message( $message ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only check for error display parameter
		if ( isset( $_GET['grabwp_token_error'] ) ) {
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
			$error = get_option( 'grabwp_tenancy_token_error' );
			if ( $error ) {
				$message .= '<div id="login_error">' . esc_html( $error ) . '</div>';
				delete_option( 'grabwp_tenancy_token_error' );
			}
		}
		return $message;
	}

	/**
	 * Check if current page is login page
	 */
	private function is_login_page() {
		return in_array( $GLOBALS['pagenow'], array( 'wp-login.php' ) );
	}

	/**
	 * Get admin user with lowest ID
	 *
	 * @return WP_User|false Admin user object or false if not found
	 */
	private function get_lowest_admin_user() {
		$admin_users = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
			)
		);

		return ! empty( $admin_users ) ? $admin_users[0] : false;
	}
}
