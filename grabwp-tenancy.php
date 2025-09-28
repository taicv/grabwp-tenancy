<?php
/**
 * Plugin Name: GrabWP Tenancy
 * Plugin URI: https://grabwp.com/tenancy
 * Description: Foundation multi-tenant WordPress solution with shared MySQL database and separated uploads. Designed to be extended by GrabWP Tenancy Pro for advanced features.
 * Version: 1.0.4-rc2
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
define( 'GRABWP_TENANCY_VERSION', '1.0.4-rc2' );
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

		// Load MU plugin functionality
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-path-manager.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-logger.php';
		// Load core classes
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-loader.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-tenant.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-admin.php';
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-admin-notice.php';

		
		
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

		// Single decision point - no more scattered checks
		if ( $this->is_tenant() ) {
			$this->init_tenant_only();
		} else {
			$this->init_main_site_full();
		}

		// Allow pro plugin to extend
		do_action( 'grabwp_tenancy_init', $this );
	}

	/**
	 * Initialize only what's needed on tenant sites
	 * Minimal footprint for tenant sites
	 *
	 * @since 1.0.0
	 */
	private function init_tenant_only() {
		// Only load loader for admin token handling
		if ( class_exists( 'GrabWP_Tenancy_Loader' ) ) {
			new GrabWP_Tenancy_Loader( $this );
		}

		// Hide Pro plugin from tenant admin dashboards
		$this->hide_pro_plugin_from_tenant_admin();

		// Allow pro plugin to extend tenant functionality
		do_action( 'grabwp_tenancy_init_tenant_only', $this );
	}

	/**
	 * Initialize full plugin functionality for main site
	 * Complete management interface
	 *
	 * @since 1.0.0
	 */
	private function init_main_site_full() {
		// Load all components for main site
		$this->init_loader();
		$this->init_admin();
		GrabWP_Tenancy_Admin_Notice::register();

		// Allow pro plugin to extend main site functionality
		do_action( 'grabwp_tenancy_init_main_site_full', $this );
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
	 * Hide Pro plugin from tenant admin dashboards
	 * Prevents tenants from accidentally deactivating the Pro plugin
	 *
	 * @since 1.0.0
	 */
	private function hide_pro_plugin_from_tenant_admin() {
		// Hide Pro plugin from the plugins list
		add_filter( 'all_plugins', array( $this, 'filter_pro_plugin_from_list' ) );
	}

	/**
	 * Filter plugins list to hide Pro plugin on tenant sites
	 *
	 * @since 1.0.0
	 * @param array $plugins All plugins list
	 * @return array Filtered plugins list
	 */
	public function filter_pro_plugin_from_list( $plugins ) {
		// Hide Pro plugin
		$pro_plugin_file = 'grabwp-tenancy-pro/grabwp-tenancy-pro.php';
		if ( isset( $plugins[ $pro_plugin_file ] ) ) {
			unset( $plugins[ $pro_plugin_file ] );
		}

		return $plugins;
	}

	/**
	 * Plugin loaded hook
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		// Check for pro plugin after all plugins are loaded
		add_action( 'init', array( $this, 'check_pro_plugin' ), 5 );
	}

	/**
	 * Check if pro plugin is active
	 *
	 * @since 1.0.0
	 */
	public function check_pro_plugin() {
		// Only define the constant if it hasn't been defined yet.
		if ( ! defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) ) {
			if ( class_exists( 'GrabWP_Tenancy_Pro' ) ) {
				define( 'GRABWP_TENANCY_PRO_ACTIVE', true );
			} else {
				define( 'GRABWP_TENANCY_PRO_ACTIVE', false );
			}
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


