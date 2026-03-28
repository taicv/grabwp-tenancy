<?php
/**
 * GrabWP Tenancy Admin Notice Class
 *
 * Short admin notices that point to the status page for details and fixes.
 * AJAX handlers delegate to GrabWP_Tenancy_Installer.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Admin_Notice {

	/**
	 * Register admin notice hooks and AJAX handlers.
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );

		// AJAX actions (used by status page Fix Now buttons and admin notice auto-install).
		add_action( 'wp_ajax_grabwp_install_mu_plugin', array( __CLASS__, 'ajax_install_mu_plugin' ) );
		add_action( 'wp_ajax_grabwp_install_loader', array( __CLASS__, 'ajax_install_loader' ) );
		add_action( 'wp_ajax_grabwp_fix_root_htaccess', array( __CLASS__, 'ajax_fix_root_htaccess' ) );
		add_action( 'wp_ajax_grabwp_fix_data_htaccess', array( __CLASS__, 'ajax_fix_data_htaccess' ) );
		add_action( 'wp_ajax_grabwp_fix_index_protection', array( __CLASS__, 'ajax_fix_index_protection' ) );
	}

	// =========================================================================
	// Admin Notices (short — details on status page)
	// =========================================================================

	/**
	 * Show short admin notices for critical plugin issues.
	 */
	public static function show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status_url = admin_url( 'admin.php?page=grabwp-tenancy-status' );

		// wp-config.php loader not active.
		if ( ! defined( 'GRABWP_TENANCY_LOADED' ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'load.php is not included in wp-config.php.', 'grabwp-tenancy' ),
				esc_url( $status_url ),
				esc_html__( 'View Status →', 'grabwp-tenancy' )
			);
		}

		// MU-plugin not installed (only on plugin pages to avoid noise).
		if ( self::is_plugin_page() && ! file_exists( GrabWP_Tenancy_Installer::get_mu_plugin_path() ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'MU-plugin is not installed. Tenant features may not work.', 'grabwp-tenancy' ),
				esc_url( $status_url ),
				esc_html__( 'View Status →', 'grabwp-tenancy' )
			);
		}

		// Base directory missing.
		if ( class_exists( 'GrabWP_Tenancy_Path_Manager' ) && ! is_dir( GrabWP_Tenancy_Path_Manager::get_tenants_base_dir() ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Data directory is missing.', 'grabwp-tenancy' ),
				esc_url( $status_url ),
				esc_html__( 'View Status →', 'grabwp-tenancy' )
			);
		}

		// .htaccess check (only on plugin pages to avoid noise).
		global $is_apache;
		if ( self::is_plugin_page() && $is_apache ) {
			$root_htaccess_path = ABSPATH . '.htaccess';
			$htaccess_needs_fix = false;

			if ( file_exists( $root_htaccess_path ) && is_readable( $root_htaccess_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
				$root_htaccess_content = file_get_contents( $root_htaccess_path );
				if ( false !== $root_htaccess_content ) {
					$grabwp_pos = strpos( $root_htaccess_content, '# BEGIN GrabWP Tenancy' );
					$wp_pos     = strpos( $root_htaccess_content, '# BEGIN WordPress' );

					$has_rewrite_rule = false !== strpos( $root_htaccess_content, 'RewriteRule ^site/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]' );

					if ( false === $grabwp_pos || ! $has_rewrite_rule || ( false !== $wp_pos && $grabwp_pos > $wp_pos ) ) {
						$htaccess_needs_fix = true;
					}
				}
			} elseif ( ! file_exists( $root_htaccess_path ) ) {
				$htaccess_needs_fix = true;
			}

			if ( $htaccess_needs_fix ) {
				printf(
					'<div class="notice notice-warning"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
					esc_html__( 'Root .htaccess needs to be updated for tenant routing.', 'grabwp-tenancy' ),
					esc_url( $status_url ),
					esc_html__( 'View Status →', 'grabwp-tenancy' )
				);
			}
		}
	}

	// =========================================================================
	// AJAX Handlers (thin wrappers → Installer)
	// =========================================================================

	public static function ajax_install_mu_plugin() {
		check_ajax_referer( 'grabwp_install_mu_plugin' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::install_mu_plugin() );
	}

	public static function ajax_install_loader() {
		check_ajax_referer( 'grabwp_install_loader' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::install_loader() );
	}

	public static function ajax_fix_root_htaccess() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::fix_root_htaccess() );
	}

	public static function ajax_fix_data_htaccess() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::fix_data_htaccess() );
	}

	public static function ajax_fix_index_protection() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::fix_index_protection() );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private static function is_plugin_page() {
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, 'grabwp-tenancy' ) !== false;
	}

	private static function send_result( $result ) {
		$result['success'] ? wp_send_json_success( $result['message'] ) : wp_send_json_error( $result['message'] );
	}
}
