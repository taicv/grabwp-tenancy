<?php
/**
 * GrabWP Tenancy Admin Notice Class
 *
 * Handles global admin notices for environment/configuration issues.
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Admin_Notice {
	/**
	 * Register admin notice hook
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );
	}

	/**
	 * Show admin notices for global plugin issues
	 */
	public static function show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Check if load.php is included
		if ( ! defined( 'GRABWP_TENANCY_LOADED' ) ) {
			$snippet = 'require_once __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php";';
			echo '<div class="notice notice-error"><p>'
				. '<strong>GrabWP Tenancy:</strong> Plugin is activated but <code>load.php</code> is not included in <code>wp-config.php</code>.'
				. '<br>Please add the following line before <code>/* That\'s all, stop editing! */</code> in <code>wp-config.php</code>:'
				. '<pre id="grabwp-load-snippet" style="user-select:all;">' . esc_html( $snippet ) . '</pre>'
				. '<textarea id="grabwp-load-textarea" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;">' . esc_html( $snippet ) . '</textarea>'
				. '<button class="button" id="grabwp-copy-btn" type="button">Copy to Clipboard</button>'
				. '</p></div>';
		}
		// Check path status and show appropriate notices
		$path_status = GrabWP_Tenancy_Path_Manager::get_path_status();
		$base_dir    = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		// Check if base directory exists
		if ( ! is_dir( $base_dir ) ) {
			echo '<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> Plugin base directory is missing. Please activate the plugin again or create the directory manually.</p></div>';
		}

		// Check if tenants file exists
		if ( ! $path_status['tenants_file'] ) {
			echo '<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> <code>tenants.php</code> file is missing. Please activate the plugin again or create the file manually.</p></div>';
		}

		// Show info notice for legacy structure usage
		// TODO: Add this back in
		if ( $path_status['using_old'] ) {
			// echo '<div class="notice notice-info"><p><strong>GrabWP Tenancy:</strong> Using legacy path structure for backward compatibility. New tenants will use the same structure for consistency.</p></div>';
		} elseif ( $path_status['is_custom'] ) {
			// echo '<div class="notice notice-info"><p><strong>GrabWP Tenancy:</strong> Using custom path configuration.</p></div>';
		}
	}
}
