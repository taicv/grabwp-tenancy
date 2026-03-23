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
	 * MU-plugin filename.
	 */
	const MU_PLUGIN_FILE = 'mu-grabwp-tenancy.php';

	/**
	 * Register admin notice hooks and AJAX handler.
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );
		add_action( 'wp_ajax_grabwp_install_mu_plugin', array( __CLASS__, 'ajax_install_mu_plugin' ) );
		add_action( 'wp_ajax_grabwp_install_loader', array( __CLASS__, 'ajax_install_loader' ) );
	}

	/**
	 * Check if the current admin screen belongs to our plugin pages.
	 *
	 * @return bool
	 */
	private static function is_plugin_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Match the same pattern used in enqueue_admin_scripts().
		return ( strpos( $screen->id, 'grabwp-tenancy' ) !== false );
	}

	/**
	 * Get the expected mu-plugin file path.
	 *
	 * @return string
	 */
	private static function get_mu_plugin_path() {
		return ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( ABSPATH . 'wp-content/mu-plugins' ) )
			. '/' . self::MU_PLUGIN_FILE;
	}

	/**
	 * Show admin notices for global plugin issues.
	 */
	public static function show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// --- Global notices (shown on all admin pages) ---

		// Check if load.php is included.
		if ( ! defined( 'GRABWP_TENANCY_LOADED' ) ) {
			$wp_config_path = ABSPATH . 'wp-config.php';
			$is_writable    = is_writable( $wp_config_path );

			echo '<div class="notice notice-error" id="grabwp-loader-notice"><p>'
				. '<strong>GrabWP Tenancy:</strong> Plugin is activated but <code>load.php</code> is not included in <code>wp-config.php</code>.';

			if ( $is_writable ) {
				echo '<br>Click the button below to add it automatically.'
					. '</p><p>'
					. '<button class="button button-primary" id="grabwp-install-loader-btn" type="button">Auto Install to wp-config.php</button>'
					. '<span id="grabwp-loader-status" style="margin-left:10px;"></span>';
			} else {
				$snippet = 'require_once __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php";';
				echo '<br><em>Cannot install automatically &mdash; <code>wp-config.php</code> is not writable.</em>'
					. '<br>Please add the following line before <code>/* That\'s all, stop editing! Happy publishing. */</code> in <code>wp-config.php</code>:'
					. '<pre id="grabwp-load-snippet" style="user-select:all;">' . esc_html( $snippet ) . '</pre>'
					. '<textarea id="grabwp-load-textarea" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;">' . esc_html( $snippet ) . '</textarea>'
					. '<button class="button" id="grabwp-copy-btn" type="button">Copy to Clipboard</button>';
			}

			echo '</p></div>';
		}

		// Check path status and show appropriate notices.
		$path_status = GrabWP_Tenancy_Path_Manager::get_path_status();
		$base_dir    = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		// Check if base directory exists.
		if ( ! is_dir( $base_dir ) ) {
			echo '<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> Plugin base directory is missing. Please activate the plugin again or create the directory manually.</p></div>';
		}

		// Check if tenants file exists.
		if ( ! $path_status['tenants_file'] ) {
			echo '<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> <code>tenants.php</code> file is missing. Please activate the plugin again or create the file manually.</p></div>';
		}

		// Show info notice for legacy structure usage.
		// TODO: Add this back in from 1.1
		if ( $path_status['using_old'] ) {
			// echo '<div class="notice notice-info"><p><strong>GrabWP Tenancy:</strong> Using legacy path structure for backward compatibility. New tenants will use the same structure for consistency.</p></div>';
		} elseif ( $path_status['is_custom'] ) {
			// echo '<div class="notice notice-info"><p><strong>GrabWP Tenancy:</strong> Using custom path configuration.</p></div>';
		}

		// --- Plugin-page-only notices ---

		if ( ! self::is_plugin_page() ) {
			return;
		}

		// MU-Plugin notice.
		self::maybe_show_mu_plugin_notice();
	}

	/**
	 * Show notice if the mu-plugin is not installed.
	 */
	private static function maybe_show_mu_plugin_notice() {
		$mu_path = self::get_mu_plugin_path();

		if ( file_exists( $mu_path ) ) {
			return;
		}

		$mu_dir      = dirname( $mu_path );
		$is_writable = is_dir( $mu_dir ) ? is_writable( $mu_dir ) : is_writable( dirname( $mu_dir ) );

		echo '<div class="notice notice-warning" id="grabwp-mu-plugin-notice"><p>'
			. '<strong>GrabWP Tenancy:</strong> '
			. 'The must-use plugin <code>' . esc_html( self::MU_PLUGIN_FILE ) . '</code> is not installed. '
			. 'This file is required for tenancy features (settings sync, dashboard access, plugin/theme control) to work inside tenant sites.';

		if ( $is_writable ) {
			echo '<br>Click the button below to install it automatically.'
				. '</p><p>'
				. '<button class="button button-primary" id="grabwp-install-mu-btn" type="button">Auto Install MU-Plugin</button>'
				. '<span id="grabwp-mu-status" style="margin-left:10px;"></span>';
		} else {
			$content = self::get_mu_plugin_content();
			echo '<br><em>Cannot install automatically &mdash; directory is not writable.</em>'
				. '<br>Please create <code>' . esc_html( $mu_path ) . '</code> with the following content:'
				. '<pre id="grabwp-mu-snippet" style="user-select:all;background:#f0f0f1;padding:10px;overflow:auto;max-height:200px;">' . esc_html( $content ) . '</pre>'
				. '<textarea id="grabwp-mu-textarea" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;">' . esc_html( $content ) . '</textarea>'
				. '<button class="button" id="grabwp-copy-mu-btn" type="button">Copy to Clipboard</button>';
		}

		echo '</p></div>';
	}

	/**
	 * AJAX handler: create the mu-plugin file.
	 */
	public static function ajax_install_mu_plugin() {
		check_ajax_referer( 'grabwp_install_mu_plugin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$mu_dir  = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( ABSPATH . 'wp-content/mu-plugins' );
		$mu_path = $mu_dir . '/' . self::MU_PLUGIN_FILE;

		// Already installed.
		if ( file_exists( $mu_path ) ) {
			wp_send_json_success( 'MU-Plugin is already installed.' );
		}

		// Create mu-plugins directory if it does not exist.
		if ( ! is_dir( $mu_dir ) ) {
			if ( ! wp_mkdir_p( $mu_dir ) ) {
				wp_send_json_error( 'Could not create mu-plugins directory. Please check file permissions.' );
			}
		}

		// Build file content.
		$content = self::get_mu_plugin_content();

		// Write file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $mu_path, $content );

		if ( false === $result ) {
			wp_send_json_error( 'Could not write MU-Plugin file. Please check file permissions.' );
		}

		wp_send_json_success( 'MU-Plugin installed successfully.' );
	}

	/**
	 * AJAX handler: inject require_once load.php into wp-config.php.
	 */
	public static function ajax_install_loader() {
		check_ajax_referer( 'grabwp_install_loader' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$wp_config_path = ABSPATH . 'wp-config.php';

		if ( ! is_writable( $wp_config_path ) ) {
			wp_send_json_error( 'wp-config.php is not writable. Please check file permissions.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$config_content = file_get_contents( $wp_config_path );

		if ( false === $config_content ) {
			wp_send_json_error( 'Could not read wp-config.php.' );
		}

		// Check if already present.
		if ( strpos( $config_content, 'grabwp-tenancy/load.php' ) !== false ) {
			wp_send_json_success( 'Loader is already present in wp-config.php.' );
		}

		// Find the stop-editing marker.
		$marker = "/* That's all, stop editing! Happy publishing. */";
		$pos    = strpos( $config_content, $marker );

		if ( false === $pos ) {
			// Try alternate marker without "Happy publishing."
			$marker = "/* That's all, stop editing! */";
			$pos    = strpos( $config_content, $marker );
		}

		if ( false === $pos ) {
			wp_send_json_error( 'Could not find the stop-editing marker in wp-config.php. Please add the line manually.' );
		}

		// Inject the require_once line before the marker.
		$inject      = 'require_once __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php";' . "\n\n";
		$new_content = substr( $config_content, 0, $pos ) . $inject . substr( $config_content, $pos );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $wp_config_path, $new_content );

		if ( false === $result ) {
			wp_send_json_error( 'Could not write to wp-config.php. Please check file permissions.' );
		}

		wp_send_json_success( 'Loader installed to wp-config.php successfully.' );
	}

	/**
	 * Generate the mu-plugin file content.
	 *
	 * Uses __DIR__ for portable paths that work even if wp-content is relocated.
	 *
	 * @return string PHP file content.
	 */
	private static function get_mu_plugin_content() {
		return <<<'PHP'
<?php
// GrabWP Tenancy MU-Plugin — auto-generated.
$mu_grabwp_base = __DIR__ . '/../plugins/grabwp-tenancy/grabwp-tenancy.php';
$mu_grabwp_pro  = __DIR__ . '/../plugins/grabwp-tenancy-pro/grabwp-tenancy-pro.php';
if ( file_exists( $mu_grabwp_base ) ) { require_once $mu_grabwp_base; }
if ( file_exists( $mu_grabwp_pro ) )  { require_once $mu_grabwp_pro; }

PHP;
	}
}
