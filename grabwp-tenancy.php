<?php
/**
 * Plugin Name: GrabWP Tenancy
 * Plugin URI: https://grabwp.com/tenancy
 * Description: Foundation multi-tenant WordPress solution with shared MySQL database and separated uploads. Designed to be extended by GrabWP Tenancy Pro for advanced features.
 * Version: 1.0.3
 * Author: GrabWP
 * Author URI: https://grabwp.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: grabwp-tenancy
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'GRABWP_TENANCY_VERSION', '1.0.3' );
define( 'GRABWP_TENANCY_PLUGIN_FILE', __FILE__ );
define( 'GRABWP_TENANCY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRABWP_TENANCY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRABWP_TENANCY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin text domain loading
 *
 * Note: Since WordPress 4.6, load_plugin_textdomain() is no longer needed
 * for plugins hosted on WordPress.org. WordPress automatically loads
 * translations as needed.
 *
 * @since 1.0.0
 */

/**
 * Main GrabWP Tenancy Plugin Class
 *
 * @since 1.0.0
 */
final class GrabWP_Tenancy {

	/**
	 * Plugin instance
	 *
	 * @var GrabWP_Tenancy
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Plugin version
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $version = GRABWP_TENANCY_VERSION;

	/**
	 * Plugin directory
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $plugin_dir = GRABWP_TENANCY_PLUGIN_DIR;

	/**
	 * Plugin URL
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $plugin_url = GRABWP_TENANCY_PLUGIN_URL;

	/**
	 * Current tenant ID
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $tenant_id = '';

	/**
	 * Whether current request is for a tenant
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	public $is_tenant = false;

	/**
	 * Get plugin instance
	 *
	 * @since 1.0.0
	 * @return GrabWP_Tenancy
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_dependencies();
		$this->init();
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'init', array( $this, 'init' ) );

		// Activation and deactivation hooks
		require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-installer.php';
		// Removed activation/deactivation hook registration from here
	}

	/**
	 * Load plugin dependencies
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		// Load core classes
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-loader.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-tenant.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-admin.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-admin-notice.php';

		// Load MU plugin functionality
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-path-manager.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-config.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-assets.php';
	}

	/**
	 * Initialize plugin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Set tenant context from early loading
		$this->tenant_id = defined( 'GRABWP_TENANCY_TENANT_ID' ) ? GRABWP_TENANCY_TENANT_ID : '';
		$this->is_tenant = defined( 'GRABWP_TENANCY_IS_TENANT' ) ? GRABWP_TENANCY_IS_TENANT : false;

		// Initialize components
		$this->init_loader();
		$this->init_admin();
		GrabWP_Tenancy_Admin_Notice::register();

		// Initialize new components (from MU plugin)
		$this->init_config();
		$this->init_assets();

		// Allow pro plugin to extend
		do_action( 'grabwp_tenancy_init', $this );
	}

	/**
	 * Initialize loader component
	 *
	 * @since 1.0.0
	 */
	private function init_loader() {
		if ( class_exists( 'GrabWP_Tenancy_Loader' ) ) {
			new GrabWP_Tenancy_Loader( $this );
		}
	}

	/**
	 * Initialize admin component
	 *
	 * @since 1.0.0
	 */
	private function init_admin() {
		static $admin_initialized = false;

		if ( ! $admin_initialized && is_admin() && class_exists( 'GrabWP_Tenancy_Admin' ) ) {
			new GrabWP_Tenancy_Admin( $this );
			$admin_initialized = true;
		}
	}

	/**
	 * Initialize config component
	 *
	 * @since 1.0.0
	 */
	private function init_config() {
		if ( class_exists( 'GrabWP_Tenancy_Config' ) ) {
			GrabWP_Tenancy_Config::init();
		}
	}

	/**
	 * Initialize assets component
	 *
	 * @since 1.0.0
	 */
	private function init_assets() {
		if ( class_exists( 'GrabWP_Tenancy_Assets' ) ) {
			new GrabWP_Tenancy_Assets( $this );
		}
	}

	/**
	 * Plugin loaded hook
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		// Check for pro plugin
		$this->check_pro_plugin();
	}

	/**
	 * Check if pro plugin is active
	 *
	 * @since 1.0.0
	 */
	private function check_pro_plugin() {
		if ( class_exists( 'GrabWP_Tenancy_Pro' ) ) {
			define( 'GRABWP_TENANCY_PRO_ACTIVE', true );
		} else {
			define( 'GRABWP_TENANCY_PRO_ACTIVE', false );
		}
	}

	/**
	 * Plugin activation
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Run installer activation logic first
		if ( class_exists( 'GrabWP_Tenancy_Installer' ) ) {
			GrabWP_Tenancy_Installer::activate();
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Allow pro plugin to extend activation
		do_action( 'grabwp_tenancy_activate' );
	}

	/**
	 * Plugin deactivation
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();

		// Allow pro plugin to extend deactivation
		do_action( 'grabwp_tenancy_deactivate' );
	}

	/**
	 * Get current tenant ID
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_tenant_id() {
		return $this->tenant_id;
	}

	/**
	 * Check if current request is for a tenant
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_tenant() {
		return $this->is_tenant;
	}

	/**
	 * Get plugin info
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_plugin_info() {
		return array(
			'version'    => $this->version,
			'plugin_dir' => $this->plugin_dir,
			'plugin_url' => $this->plugin_url,
			'tenant_id'  => $this->tenant_id,
			'is_tenant'  => $this->is_tenant,
			'pro_active' => defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) ? GRABWP_TENANCY_PRO_ACTIVE : false,
		);
	}
}

/**
 * Get main plugin instance
 *
 * @since 1.0.0
 * @return GrabWP_Tenancy
 */
function grabwp_tenancy() {
	return GrabWP_Tenancy::instance();
}

// Initialize plugin
$grabwp_tenancy_instance = grabwp_tenancy();
register_activation_hook( __FILE__, array( $grabwp_tenancy_instance, 'activate' ) );
register_deactivation_hook( __FILE__, array( $grabwp_tenancy_instance, 'deactivate' ) );

/**
 * Legacy function compatibility (from MU plugin)
 *
 * These functions maintain backward compatibility for any external dependencies
 */

if ( ! function_exists( 'grabwp_client_is_tenant' ) ) {
	/**
	 * Check if current site is a tenant
	 *
	 * @return bool True if tenant site, false otherwise
	 */
	function grabwp_client_is_tenant() {
		return grabwp_tenancy()->is_tenant();
	}
}

if ( ! function_exists( 'grabwp_client_get_tenant_id' ) ) {
	/**
	 * Get current tenant ID
	 *
	 * @return string|false Tenant ID or false if not a tenant
	 */
	function grabwp_client_get_tenant_id() {
		return grabwp_tenancy()->get_tenant_id();
	}
}

if ( ! function_exists( 'grabwp_client_get_tenant_domain' ) ) {
	/**
	 * Get current tenant domain
	 *
	 * @return string|false Current domain if tenant site, false otherwise
	 */
	function grabwp_client_get_tenant_domain() {
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$domain = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
			// Validate domain format for security
			if ( ! empty( $domain ) && preg_match( '/^[a-zA-Z0-9.-]+$/', $domain ) ) {
				return $domain;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'grabwp_client_get_tenant_upload_dir' ) ) {
	/**
	 * Get tenant upload directory path
	 *
	 * @return string|false Upload directory path if tenant site, false otherwise
	 */
	function grabwp_client_get_tenant_upload_dir() {
		return defined( 'GRABWP_TENANCY_UPLOAD_DIR' ) ? GRABWP_TENANCY_UPLOAD_DIR : false;
	}
}

if ( ! function_exists( 'grabwp_client_get_tenant_upload_url' ) ) {
	/**
	 * Get tenant upload directory URL
	 *
	 * @return string|false Upload directory URL if tenant site, false otherwise
	 */
	function grabwp_client_get_tenant_upload_url() {
		if ( ! grabwp_client_is_tenant() ) {
			return false;
		}

		$upload_dir = grabwp_client_get_tenant_upload_dir();
		if ( ! $upload_dir ) {
			return false;
		}

		$upload_dir_info = wp_upload_dir();
		$relative_path   = str_replace( $upload_dir_info['basedir'], '', $upload_dir );

		return $upload_dir_info['baseurl'] . $relative_path;
	}
}

if ( ! function_exists( 'grabwp_client_get_tenant_info' ) ) {
	/**
	 * Get all tenant information
	 *
	 * @return array|false Array with tenant information or false if not a tenant
	 */
	function grabwp_client_get_tenant_info() {
		if ( ! grabwp_client_is_tenant() ) {
			return false;
		}

		return array(
			'id'         => grabwp_client_get_tenant_id(),
			'domain'     => grabwp_client_get_tenant_domain(),
			'upload_dir' => grabwp_client_get_tenant_upload_dir(),
			'upload_url' => grabwp_client_get_tenant_upload_url(),
			'is_tenant'  => true,
		);
	}
}
