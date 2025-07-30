<?php
/**
 * Plugin Name: GrabWP Tenancy
 * Plugin URI: https://grabwp.com/tenancy
 * Description: Foundation multi-tenant WordPress solution with shared MySQL database and separated uploads. Designed to be extended by GrabWP Tenancy Pro for advanced features.
 * Version: 1.0.0
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
define( 'GRABWP_TENANCY_VERSION', '1.0.0' );
define( 'GRABWP_TENANCY_PLUGIN_FILE', __FILE__ );
define( 'GRABWP_TENANCY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRABWP_TENANCY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRABWP_TENANCY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
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
        // Create necessary directories
        $this->create_directories();
        
        // Create default tenant mappings file
        $this->create_tenant_mappings_file();
        
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
     * Create necessary directories
     * 
     * @since 1.0.0
     */
    private function create_directories() {
        $grabwp_dir = WP_CONTENT_DIR . '/grabwp';
        
        if ( ! file_exists( $grabwp_dir ) ) {
            $result = wp_mkdir_p( $grabwp_dir );
            if ( ! $result ) {
                // Handle directory creation failure silently
                // Directory creation failure will be handled by the calling code
            }
        }
    }
    
    /**
     * Create default tenant mappings file
     * 
     * @since 1.0.0
     */
    private function create_tenant_mappings_file() {
        $mappings_file = WP_CONTENT_DIR . '/grabwp/tenants.php';
        
        if ( ! file_exists( $mappings_file ) ) {
            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * Tenant Domain Mappings\n";
            $content .= " * \n";
            $content .= " * This file contains domain mappings for tenant identification.\n";
            $content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
            $content .= " */\n\n";
            $content .= "\$tenant_mappings = array(\n";
            $content .= "    // Example: 'abc123' => array( 'tenant1.grabwp.local' ),\n";
            $content .= ");\n";
            
            $result = file_put_contents( $mappings_file, $content );
            if ( false === $result ) {
                // Handle file creation failure silently
                // File creation failure will be handled by the calling code
            }
        }
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
            'version' => $this->version,
            'plugin_dir' => $this->plugin_dir,
            'plugin_url' => $this->plugin_url,
            'tenant_id' => $this->tenant_id,
            'is_tenant' => $this->is_tenant,
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
grabwp_tenancy(); 