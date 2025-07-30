<?php
/**
 * GrabWP Tenancy Tenant Class
 * 
 * Handles tenant data structure, validation, and lifecycle management.
 * 
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GrabWP Tenancy Tenant Class
 * 
 * @since 1.0.0
 */
class GrabWP_Tenancy_Tenant {
    
    /**
     * Tenant ID
     * 
     * @var string
     */
    private $id;
    
    /**
     * Tenant domains
     * 
     * @var array
     */
    private $domains;
    
    /**
     * Tenant status
     * 
     * @var string
     */
    private $status;
    
    /**
     * Created date
     * 
     * @var string
     */
    private $created_date;
    
    /**
     * Configuration array
     * 
     * @var array
     */
    private $configuration;
    
    /**
     * Constructor
     * 
     * @param string $id Tenant ID
     * @param array $data Tenant data
     */
    public function __construct( $id = '', $data = array() ) {
        $this->id = $id;
        $this->domains = isset( $data['domains'] ) ? $data['domains'] : array();
        $this->status = isset( $data['status'] ) ? $data['status'] : 'active';
        $this->created_date = isset( $data['created_date'] ) ? $data['created_date'] : current_time( 'mysql' );
        $this->configuration = isset( $data['configuration'] ) ? $data['configuration'] : array();
    }
    
    /**
     * Generate unique tenant ID
     * 
     * @return string Unique tenant ID
     */
    public static function generate_id() {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = '';
        
        for ( $i = 0; $i < 6; $i++ ) {
            $id .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
        }
        
        return $id;
    }
    
    /**
     * Validate tenant ID format
     * 
     * @param string $id Tenant ID
     * @return bool Valid status
     */
    public static function validate_id( $id ) {
        return preg_match( '/^[a-z0-9]{6}$/', $id );
    }
    
    /**
     * Validate domain format
     * 
     * @param string $domain Domain name
     * @return bool Valid status
     */
    public static function validate_domain( $domain ) {
        return filter_var( $domain, FILTER_VALIDATE_DOMAIN ) !== false;
    }
    
    /**
     * Get tenant ID
     * 
     * @return string
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get tenant domains
     * 
     * @return array
     */
    public function get_domains() {
        return $this->domains;
    }
    
    /**
     * Get primary domain
     * 
     * @return string
     */
    public function get_primary_domain() {
        return isset( $this->domains[0] ) ? $this->domains[0] : '';
    }
    
    /**
     * Get tenant status
     * 
     * @return string
     */
    public function get_status() {
        return $this->status;
    }
    
    /**
     * Check if tenant is active
     * 
     * @return bool
     */
    public function is_active() {
        return $this->status === 'active';
    }
    
    /**
     * Get created date
     * 
     * @return string
     */
    public function get_created_date() {
        return $this->created_date;
    }
    
    /**
     * Get configuration
     * 
     * @return array
     */
    public function get_configuration() {
        return $this->configuration;
    }
    
    /**
     * Set domains
     * 
     * @param array $domains Domain array
     */
    public function set_domains( $domains ) {
        $this->domains = array_filter( $domains, array( $this, 'validate_domain' ) );
    }
    
    /**
     * Add domain
     * 
     * @param string $domain Domain name
     */
    public function add_domain( $domain ) {
        if ( $this->validate_domain( $domain ) && ! in_array( $domain, $this->domains ) ) {
            $this->domains[] = $domain;
        }
    }
    
    /**
     * Remove domain
     * 
     * @param string $domain Domain name
     */
    public function remove_domain( $domain ) {
        $key = array_search( $domain, $this->domains );
        if ( $key !== false ) {
            unset( $this->domains[ $key ] );
            $this->domains = array_values( $this->domains );
        }
    }
    
    /**
     * Set status
     * 
     * @param string $status Status value
     */
    public function set_status( $status ) {
        $valid_statuses = array( 'active', 'inactive' );
        if ( in_array( $status, $valid_statuses ) ) {
            $this->status = $status;
        }
    }
    
    /**
     * Set configuration
     * 
     * @param array $configuration Configuration array
     */
    public function set_configuration( $configuration ) {
        $this->configuration = $configuration;
    }
    
    /**
     * Get tenant data as array
     * 
     * @return array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'domains' => $this->domains,
            'status' => $this->status,
            'created_date' => $this->created_date,
            'configuration' => $this->configuration,
        );
    }
    
    /**
     * Check if domain belongs to tenant
     * 
     * @param string $domain Domain name
     * @return bool
     */
    public function has_domain( $domain ) {
        return in_array( $domain, $this->domains );
    }
    
    /**
     * Get tenant info for display
     * 
     * @return array
     */
    public function get_info() {
        return array(
            'id' => $this->id,
            'primary_domain' => $this->get_primary_domain(),
            'domain_count' => count( $this->domains ),
            'status' => $this->status,
            'created_date' => $this->created_date,
            'is_active' => $this->is_active(),
        );
    }
} 