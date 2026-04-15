<?php
/**
 * Plugin Name: GrabWP Tenancy
 * Plugin URI: https://grabwp.com/tenancy
 * Description: Foundation multi-tenant WordPress solution with shared MySQL database and separated uploads. Designed to be extended by GrabWP Tenancy Pro for advanced features.
 * Version: 1.0.9
 * Author: GrabWP
 * Author URI: https://grabwp.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: grabwp-tenancy
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Mainsite pseudo-ID used as clone source.
if ( ! defined( 'GRABWP_MAINSITE_ID' ) ) {
	define( 'GRABWP_MAINSITE_ID', '__mainsite__' );
}

// Define plugin constants
define( 'GRABWP_TENANCY_VERSION', '1.0.9' );
define( 'GRABWP_TENANCY_PLUGIN_FILE', __FILE__ );
define( 'GRABWP_TENANCY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
// Use content_url() to avoid symlink path resolution issues on some hosts
define( 'GRABWP_TENANCY_PLUGIN_URL', content_url( '/plugins/' . basename( __DIR__ ) . '/' ) );
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
		require_once $this->plugin_dir . 'includes/class-grabwp-tenancy-settings.php';
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

		// Apply tenant capability restrictions from settings.
		$this->apply_tenant_settings();

		// Hide Pro plugin from tenant admin dashboards
		$this->hide_pro_plugin_from_tenant_admin();

		// Hide GrabWP base plugin from tenant admin dashboards
		$this->hide_grabwp_plugin_from_tenant_admin();

		// Allow pro plugin to extend tenant functionality
		do_action( 'grabwp_tenancy_init_tenant_only', $this );

	}

	/**
	 * Apply tenant capability settings.
	 *
	 * Defines WordPress constants and hooks menus based on saved settings.
	 *
	 * @since 1.1.0
	 */
	private function apply_tenant_settings() {
		$settings = GrabWP_Tenancy_Settings::get_instance();

		// DISALLOW_FILE_MODS — controls plugin/theme install, update, and deletion.
		if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
			define( 'DISALLOW_FILE_MODS', $settings->get( 'disallow_file_mods' ) );
		}

		// DISALLOW_FILE_EDIT — controls the built-in theme/plugin editor.
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', $settings->get( 'disallow_file_edit' ) );
		}

		// Remove admin menus for plugin/theme management if configured.
		if ( $settings->get( 'hide_plugin_management' ) || $settings->get( 'hide_theme_management' ) ) {
			add_action( 'admin_menu', array( $this, 'remove_tenant_admin_menus' ), 999 );
			add_action( 'admin_bar_menu', array( $this, 'remove_tenant_admin_bar_nodes' ), 999 );
		}
	}

	/**
	 * Remove plugin and theme admin menus from tenant dashboards.
	 *
	 * @since 1.1.0
	 */
	public function remove_tenant_admin_menus() {
		$settings = GrabWP_Tenancy_Settings::get_instance();

		if ( $settings->get( 'hide_plugin_management' ) ) {
			remove_menu_page( 'plugins.php' );
		}

		if ( $settings->get( 'hide_theme_management' ) ) {
			remove_menu_page( 'themes.php' );
		}
	}

	/**
	 * Remove plugin/theme toolbar items on tenant sites (mirrors remove_tenant_admin_menus).
	 *
	 * Core registers node IDs `plugins` and `themes` on the admin bar (e.g. under the site name on the front end).
	 *
	 * @since 1.1.0
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function remove_tenant_admin_bar_nodes( $wp_admin_bar ) {
		if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
			return;
		}

		$settings = GrabWP_Tenancy_Settings::get_instance();

		if ( $settings->get( 'hide_plugin_management' ) ) {
			$wp_admin_bar->remove_node( 'plugins' );
		}

		if ( $settings->get( 'hide_theme_management' ) ) {
			$wp_admin_bar->remove_node( 'themes' );
		}
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

		// Register /site/[tenant-id] URL path routing
		add_action( 'init', array( $this, 'register_site_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_site_query_vars' ) );

		// Allow pro plugin to extend main site functionality
		do_action( 'grabwp_tenancy_init_main_site_full', $this );
	}

	/**
	 * Register rewrite rule for /site/[tenant-id] path routing.
	 * WordPress writes this to .htaccess automatically on flush_rewrite_rules().
	 * Fallback: use ?site=[tenant-id] when mod_rewrite is unavailable.
	 *
	 * @since 1.1.0
	 */
	public function register_site_rewrite_rules() {
		add_rewrite_rule(
			'^site/([a-z0-9]{6})(/.*)?$',
			'index.php?site=$matches[1]',
			'top'
		);
	}

	/**
	 * Register 'site' as a recognized WordPress query variable.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 * @since 1.1.0
	 */
	public function register_site_query_vars( $vars ) {
		$vars[] = 'site';
		return $vars;
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
		$settings = GrabWP_Tenancy_Settings::get_instance();
		if ( $settings->get( 'hide_grabwp_plugins' ) ) {
			add_filter( 'all_plugins', array( $this, 'filter_pro_plugin_from_list' ) );
		}
	}


	/**
	 * Hide GrabWP base plugin from tenant admin dashboards
	 *
	 * @since 1.0.0
	 */
	private function hide_grabwp_plugin_from_tenant_admin() {
		$settings = GrabWP_Tenancy_Settings::get_instance();
		if ( $settings->get( 'hide_grabwp_plugins' ) ) {
			add_filter( 'all_plugins', array( $this, 'filter_grabwp_plugin_from_list' ) );
		}
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
	 * Filter plugins list to hide GrabWP base plugin on tenant sites
	 *
	 * @since 1.0.0
	 * @param array $plugins All plugins list
	 * @return array Filtered plugins list
	 */
	public function filter_grabwp_plugin_from_list( $plugins ) {
		// Hide Pro plugin
		$grabwp_plugin_file = 'grabwp-tenancy/grabwp-tenancy.php';
		if ( isset( $plugins[ $grabwp_plugin_file ] ) ) {
			unset( $plugins[ $grabwp_plugin_file ] );
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
		if ( class_exists( 'GrabWP_Tenancy_Installer' ) ) {
			GrabWP_Tenancy_Installer::activate();
		}

		flush_rewrite_rules();
		do_action( 'grabwp_tenancy_activate' );
	}

	/**
	 * Plugin deactivation
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		if ( class_exists( 'GrabWP_Tenancy_Installer' ) ) {
			GrabWP_Tenancy_Installer::deactivate();
		}

		flush_rewrite_rules();
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


