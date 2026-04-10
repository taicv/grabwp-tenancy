<?php
/**
 * Clone Admin Handler
 *
 * Registers clone row action, hidden admin page, and AJAX endpoints for base plugin.
 * When Pro plugin is active, this class defers to Pro's clone handler via class_exists() check.
 * Registration runs on plugins_loaded (not in the constructor immediately) so Pro loads first
 * (WordPress loads plugins alphabetically: grabwp-tenancy before grabwp-tenancy-pro).
 *
 * All identifiers (AJAX actions, page slugs, nonces) use 'grabwp_tenancy_clone_*' prefix,
 * distinct from Pro's 'grabwp_tenancy_pro_clone_*' to avoid collisions.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin handler for base tenant clone feature.
 */
class GrabWP_Tenancy_Clone_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Pro must be loadable before we test class_exists; base plugin loads before Pro alphabetically.
		if ( did_action( 'plugins_loaded' ) ) {
			$this->maybe_init_base_clone();
		} else {
			add_action( 'plugins_loaded', [ $this, 'maybe_init_base_clone' ], 20 );
		}
	}

	/**
	 * Register base clone hooks only when Pro plugin is not active.
	 *
	 * Checks for GrabWP_Tenancy_Pro (loaded at plugins_loaded) instead of
	 * GrabWP_Tenancy_Pro_Clone_Admin which loads later during init, causing
	 * a race condition where both base and Pro clone hooks would register.
	 */
	public function maybe_init_base_clone() {
		if ( class_exists( 'GrabWP_Tenancy_Pro' ) ) {
			return;
		}
		$this->init();
	}

	private function init() {
		// Row action icon.
		add_filter( 'grabwp_tenancy_tenant_row_actions', [ $this, 'add_clone_row_action' ], 15, 2 );

		// Hidden admin page.
		add_action( 'grabwp_tenancy_admin_menu', [ $this, 'register_pages' ] );

		// AJAX endpoints (base-specific action names).
		add_action( 'wp_ajax_grabwp_tenancy_clone_init', [ $this, 'ajax_clone_init' ] );
		add_action( 'wp_ajax_grabwp_tenancy_clone_step', [ $this, 'ajax_clone_step' ] );
		add_action( 'wp_ajax_grabwp_tenancy_clone_eligible_targets', [ $this, 'ajax_clone_eligible_targets' ] );
	}

	// -------------------------------------------------------------------------
	// Admin pages
	// -------------------------------------------------------------------------

	public function register_pages() {
		add_submenu_page(
			null,
			__( 'Clone Tenant', 'grabwp-tenancy' ),
			__( 'Clone Tenant', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-clone',
			[ $this, 'clone_page' ]
		);
	}

	public function clone_page() {
		$tenant_id = $this->verify_page_access( 'grabwp_tenancy_clone' );
		if ( ! $tenant_id ) {
			return;
		}

		// Base plugin: always shared MySQL, no Pro config needed.
		$source_db_type = 'shared';

		// Localize nonces for inline JS.
		$clone_init_nonce = wp_create_nonce( 'grabwp_tenancy_clone_' . $tenant_id );
		$clone_step_nonce = wp_create_nonce( 'grabwp_tenancy_clone_step' );
		$targets_nonce    = wp_create_nonce( 'grabwp_tenancy_clone_eligible_targets' );

		$is_mainsite = ( defined( 'GRABWP_MAINSITE_ID' ) && GRABWP_MAINSITE_ID === $tenant_id );

		$template = GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/tenant-clone.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	// -------------------------------------------------------------------------
	// Row action
	// -------------------------------------------------------------------------

	public function add_clone_row_action( $actions, $item ) {
		$tenant_id = $item->get_id();

		$clone_url = add_query_arg( [
			'page'      => 'grabwp-tenancy-clone',
			'tenant_id' => $tenant_id,
			'_wpnonce'  => wp_create_nonce( 'grabwp_tenancy_clone_' . $tenant_id ),
		], admin_url( 'admin.php' ) );

		// Insert before the last action (Delete button).
		$delete    = array_pop( $actions );
		$actions[] = '<a href="' . esc_url( $clone_url ) . '" title="' . esc_attr__( 'Clone Tenant', 'grabwp-tenancy' ) . '"><span class="dashicons dashicons-admin-page"></span></a>';
		if ( null !== $delete ) {
			$actions[] = $delete;
		}

		return $actions;
	}

	// -------------------------------------------------------------------------
	// AJAX: Eligible Targets
	// -------------------------------------------------------------------------

	public function ajax_clone_eligible_targets() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'grabwp_tenancy_clone_eligible_targets' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'grabwp-tenancy' ) ] );
		}

		$source_id = isset( $_POST['source_tenant_id'] ) ? sanitize_key( wp_unslash( $_POST['source_tenant_id'] ) ) : '';

		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		if ( ! file_exists( $mappings_file ) ) {
			wp_send_json_success( [] );
		}

		$tenant_mappings = [];
		ob_start();
		include $mappings_file;
		ob_end_clean();

		$result = [];
		foreach ( $tenant_mappings as $tid => $domains ) {
			if ( $tid === $source_id ) {
				continue;
			}

			$result[] = [
				'id'       => $tid,
				'domains'  => is_array( $domains ) ? $domains : [],
				'db_type'  => 'shared',
				'has_data' => $this->tenant_has_data( $tid ),
			];
		}

		wp_send_json_success( $result );
	}

	// -------------------------------------------------------------------------
	// AJAX: Clone Init
	// -------------------------------------------------------------------------

	public function ajax_clone_init() {
		$source_tenant_id = isset( $_POST['source_tenant_id'] ) ? sanitize_key( wp_unslash( $_POST['source_tenant_id'] ) ) : '';
		$this->check_ajax_auth( 'grabwp_tenancy_clone_' . $source_tenant_id );

		$target_tenant_id = isset( $_POST['target_tenant_id'] ) ? sanitize_key( wp_unslash( $_POST['target_tenant_id'] ) ) : '';

		if ( empty( $target_tenant_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Please select a target tenant.', 'grabwp-tenancy' ) ] );
		}

		if ( $target_tenant_id === $source_tenant_id ) {
			wp_send_json_error( [ 'message' => __( 'Cannot clone a tenant into itself.', 'grabwp-tenancy' ) ] );
		}

		// Verify target exists in mappings.
		$mappings_file   = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		$tenant_mappings = [];
		if ( file_exists( $mappings_file ) ) {
			ob_start();
			include $mappings_file;
			ob_end_clean();
		}

		if ( ! isset( $tenant_mappings[ $target_tenant_id ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Target tenant not found.', 'grabwp-tenancy' ) ] );
		}

		$target_domains = $tenant_mappings[ $target_tenant_id ];

		require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/backup/class-grabwp-tenancy-clone.php';
		$clone  = new GrabWP_Tenancy_Clone();
		$job_id = $clone->init_job( $source_tenant_id, $target_tenant_id, $target_domains );

		wp_send_json_success( [
			'job_id'        => $job_id,
			'total_steps'   => GrabWP_Tenancy_Clone::TOTAL_STEPS,
			'new_tenant_id' => $target_tenant_id,
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Clone Step
	// -------------------------------------------------------------------------

	public function ajax_clone_step() {
		$this->check_ajax_auth( 'grabwp_tenancy_clone_step' );
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';

		require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/backup/class-grabwp-tenancy-clone.php';
		$clone  = new GrabWP_Tenancy_Clone();
		$result = $clone->run_step( $job_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}
		wp_send_json_success( $result );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify nonce + manage_options for AJAX requests.
	 *
	 * @param string $nonce_action Nonce action string.
	 */
	private function check_ajax_auth( $nonce_action ) {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'grabwp-tenancy' ) ] );
		}
	}

	/**
	 * Verify nonce + capability for page access, return tenant ID or die.
	 *
	 * @param string $nonce_base Nonce action base (without tenant suffix).
	 * @return string|false Tenant ID or false (after wp_die).
	 */
	private function verify_page_access( $nonce_base ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}
		$tenant_id = isset( $_GET['tenant_id'] ) ? sanitize_key( wp_unslash( $_GET['tenant_id'] ) ) : '';
		$nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $tenant_id || ! wp_verify_nonce( $nonce, $nonce_base . '_' . $tenant_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'grabwp-tenancy' ) );
		}
		return $tenant_id;
	}

	/**
	 * Check whether a tenant has existing data (options table exists) via shared MySQL.
	 *
	 * @param string $tid Tenant ID.
	 * @return bool
	 */
	private function tenant_has_data( $tid ) {
		global $wpdb;
		$table = $tid . '_options';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
