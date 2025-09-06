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

		// Content path management
		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ), 10, 1 );
		add_filter( 'wp_upload_dir', array( $this, 'filter_upload_dir' ), 10, 1 );

		// Database prefix management
		add_action( 'wp_loaded', array( $this, 'ensure_database_prefix' ) );

		// Content isolation
		add_action( 'init', array( $this, 'setup_content_isolation' ) );

		// Allow pro plugin to extend
		do_action( 'grabwp_tenancy_loader_init', $this );
	}

	/**
	 * Filter upload directory for tenant isolation
	 *
	 * @param array $uploads Upload directory array
	 * @return array Modified upload directory array
	 */
	public function filter_upload_dir( $uploads ) {
		// Prevent infinite recursion
		static $filtering = false;
		if ( $filtering ) {
			return $uploads;
		}

		if ( ! $this->plugin->is_tenant() ) {
			return $uploads;
		}

		$filtering = true;

		$tenant_id         = $this->plugin->get_tenant_id();
		$tenant_upload_dir = GrabWP_Tenancy_Path_Manager::get_tenant_upload_dir( $tenant_id );

		// Create directory if it doesn't exist
		if ( ! file_exists( $tenant_upload_dir ) ) {
			wp_mkdir_p( $tenant_upload_dir );
		}

		// Update upload paths
		$uploads['basedir'] = $tenant_upload_dir;
		$uploads['baseurl'] = GrabWP_Tenancy_Path_Manager::get_tenant_upload_url( $tenant_id );

		// Update subdirectories
		$uploads['subdir'] = isset( $uploads['subdir'] ) ? $uploads['subdir'] : '';
		$uploads['path']   = $uploads['basedir'] . $uploads['subdir'];
		$uploads['url']    = $uploads['baseurl'] . $uploads['subdir'];

		$filtering = false;

		return $uploads;
	}

	/**
	 * Ensure database prefix is set correctly
	 */
	public function ensure_database_prefix() {
		if ( $this->plugin->is_tenant() ) {
			$tenant_id = $this->plugin->get_tenant_id();
			global $wpdb;

			// Set table prefix if not already set
			if ( $wpdb->prefix !== $tenant_id . '_' ) {
				$wpdb->prefix = $tenant_id . '_';
				$wpdb->set_prefix( $tenant_id . '_' );
			}
		}
	}

	/**
	 * Setup content isolation
	 */
	public function setup_content_isolation() {
		if ( $this->plugin->is_tenant() ) {
			// Set upload directory constant
			if ( ! defined( 'GRABWP_TENANCY_UPLOAD_DIR' ) ) {
				$tenant_id = $this->plugin->get_tenant_id();
				define( 'GRABWP_TENANCY_UPLOAD_DIR', GrabWP_Tenancy_Path_Manager::get_tenant_upload_dir( $tenant_id ) );
			}

			// Allow pro plugin to extend content isolation
			do_action( 'grabwp_tenancy_setup_content_isolation', $this->plugin->get_tenant_id() );
		}
	}

	/**
	 * Get tenant upload directory
	 *
	 * @param string $tenant_id Tenant ID
	 * @return string Upload directory path
	 */
	public function get_tenant_upload_dir( $tenant_id ) {
		return GrabWP_Tenancy_Path_Manager::get_tenant_upload_dir( $tenant_id );
	}

	/**
	 * Get tenant upload URL
	 *
	 * @param string $tenant_id Tenant ID
	 * @return string Upload directory URL
	 */
	public function get_tenant_upload_url( $tenant_id ) {
		return GrabWP_Tenancy_Path_Manager::get_tenant_upload_url( $tenant_id );
	}

	/**
	 * Create tenant directories
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function create_tenant_directories( $tenant_id ) {
		$upload_dir = $this->get_tenant_upload_dir( $tenant_id );

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
		$upload_dir = $this->get_tenant_upload_dir( $tenant_id );

		if ( file_exists( $upload_dir ) ) {
			return $this->recursive_rmdir( $upload_dir );
		}

		return true;
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
