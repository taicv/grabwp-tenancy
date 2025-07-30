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
        if ( ! $this->plugin->is_tenant() ) {
            return $uploads;
        }
        
        $tenant_id = $this->plugin->get_tenant_id();
        $tenant_upload_dir = WP_CONTENT_DIR . '/grabwp/' . $tenant_id . '/uploads';
        
        // Create directory if it doesn't exist
        if ( ! file_exists( $tenant_upload_dir ) ) {
            wp_mkdir_p( $tenant_upload_dir );
        }
        
        // Update upload paths
        $uploads['basedir'] = $tenant_upload_dir;
        $uploads['baseurl'] = content_url( 'grabwp/' . $tenant_id . '/uploads' );
        
        // Update subdirectories
        $uploads['subdir'] = isset( $uploads['subdir'] ) ? $uploads['subdir'] : '';
        $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
        $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
        
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
                define( 'GRABWP_TENANCY_UPLOAD_DIR', WP_CONTENT_DIR . '/grabwp/' . $tenant_id . '/uploads' );
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
        return WP_CONTENT_DIR . '/grabwp/' . $tenant_id . '/uploads';
    }
    
    /**
     * Get tenant upload URL
     * 
     * @param string $tenant_id Tenant ID
     * @return string Upload directory URL
     */
    public function get_tenant_upload_url( $tenant_id ) {
        return content_url( 'grabwp/' . $tenant_id . '/uploads' );
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
} 