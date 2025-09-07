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
	 * @param array  $data Tenant data
	 */
	public function __construct( $id = '', $data = array() ) {
		$this->id            = $id;
		$this->domains       = isset( $data['domains'] ) ? $data['domains'] : array();
		$this->status        = isset( $data['status'] ) ? $data['status'] : 'active';
		$this->created_date  = isset( $data['created_date'] ) ? $data['created_date'] : current_time( 'mysql' );
		$this->configuration = isset( $data['configuration'] ) ? $data['configuration'] : array();
	}

	/**
	 * Generate unique tenant ID
	 *
	 * @return string Unique tenant ID
	 */
	public static function generate_id() {
		$characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$id         = '';

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
			'id'            => $this->id,
			'domains'       => $this->domains,
			'status'        => $this->status,
			'created_date'  => $this->created_date,
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
			'id'             => $this->id,
			'primary_domain' => $this->get_primary_domain(),
			'domain_count'   => count( $this->domains ),
			'status'         => $this->status,
			'created_date'   => $this->created_date,
			'is_active'      => $this->is_active(),
		);
	}

	/**
	 * Generate domain hash for token security
	 *
	 * @param string $domain Domain name
	 * @param string $tenant_id Tenant ID
	 * @return string Hash
	 */
	public static function generate_domain_hash( $domain, $tenant_id ) {
		// Normalize domain (lowercase, remove www)
		$normalized_domain = strtolower( $domain );
		$normalized_domain = preg_replace( '/^www\./', '', $normalized_domain );

		// Generate secure hash using domain + tenant_id + WordPress salt
		return hash( 'sha256', $normalized_domain . $tenant_id . AUTH_SALT );
	}

	/**
	 * Generate or get global admin access token
	 *
	 * @return string|false Token on success, false on failure
	 */
	public static function get_global_admin_token() {
		$config_file = GrabWP_Tenancy_Path_Manager::get_tokens_file_path();
		$tokens_dir = dirname( $config_file );

		// Check if valid token exists
		if ( file_exists( $config_file ) ) {
			$admin_token = null;
			include $config_file;

			if ( isset( $admin_token ) &&
				isset( $admin_token['token'] ) &&
				isset( $admin_token['expires'] ) &&
				$admin_token['expires'] > time() ) {
				return $admin_token['token'];
			}
		}

		// Generate new token if none exists or expired
		$token = wp_generate_password( 32, false );

		// Store token with expiration (24 hours)
		$token_data = array(
			'token'     => $token,
			'expires'   => time() + ( 24 * 60 * 60 ), // 24 hours
			'generated' => current_time( 'timestamp' ),
		);

		// Ensure directory exists
		if ( ! is_dir( $tokens_dir ) ) {
			wp_mkdir_p( $tokens_dir );
		}

		$content  = "<?php\n";
		$content .= "// Global admin access token for all tenants\n";
		$content .= '// Generated: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
		$content .= '// Expires: ' . gmdate( 'Y-m-d H:i:s', $token_data['expires'] ) . " UTC\n";
		$content .= '$admin_token = ' . self::format_php_array( $token_data ) . ";\n";

		if ( file_put_contents( $config_file, $content ) ) {
			return $token;
		}

		return false;
	}

	/**
	 * Get admin access URL with token and hash
	 *
	 * @return string|false URL on success, false on failure
	 */
	public function get_admin_access_url() {
		$token = self::get_global_admin_token();

		if ( ! $token || empty( $this->domains ) ) {
			return false;
		}

		$primary_domain = $this->domains[0];
		$protocol       = is_ssl() ? 'https' : 'http';

		// Generate domain hash for additional security
		$hash = self::generate_domain_hash( $primary_domain, $this->id );

		return $protocol . '://' . $primary_domain . '/wp-admin/?grabwp_token=' . $token . '&grabwp_hash=' . $hash;
	}

	/**
	 * Validate admin token and domain hash
	 *
	 * @param string $token Token to validate
	 * @param string $hash Hash to validate (optional for backward compatibility)
	 * @return bool True if valid, false otherwise
	 */
	public static function validate_admin_token( $token, $hash = '' ) {
		if ( empty( $token ) ) {
			return false;
		}

		// Check global token file
		$config_file = GrabWP_Tenancy_Path_Manager::get_tokens_file_path();
		if ( ! file_exists( $config_file ) ) {
			return false;
		}

		$admin_token = null;
		include $config_file;

		// Validate token first
		if ( ! isset( $admin_token ) ||
			! isset( $admin_token['token'] ) ||
			! isset( $admin_token['expires'] ) ||
			$admin_token['token'] !== $token ||
			$admin_token['expires'] <= time() ) {
			return false;
		}

		// If hash is provided, validate it (enhanced security)
		if ( ! empty( $hash ) ) {
			// Get current domain and tenant ID
			$current_domain = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$tenant_id      = defined( 'GRABWP_TENANCY_TENANT_ID' ) ? GRABWP_TENANCY_TENANT_ID : '';

			if ( empty( $current_domain ) || empty( $tenant_id ) ) {
				return false;
			}

			// Generate expected hash and compare
			$expected_hash = self::generate_domain_hash( $current_domain, $tenant_id );
			if ( $hash !== $expected_hash ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Format a PHP array for safe output in configuration files
	 *
	 * @param array $array Array to format
	 * @return string Formatted PHP array string
	 */
	private static function format_php_array( $array ) {
		if ( ! is_array( $array ) ) {
			if ( is_string( $array ) ) {
				return "'" . addslashes( $array ) . "'";
			} elseif ( is_bool( $array ) ) {
				return $array ? 'true' : 'false';
			} elseif ( is_null( $array ) ) {
				return 'null';
			} elseif ( is_numeric( $array ) ) {
				return (string) $array;
			} else {
				return "'" . addslashes( maybe_serialize( $array ) ) . "'";
			}
		}

		$output = "array(\n";
		foreach ( $array as $key => $value ) {
			$formatted_key = is_string( $key ) ? "'" . addslashes( $key ) . "'" : $key;
			if ( is_string( $value ) ) {
				$formatted_value = "'" . addslashes( $value ) . "'";
			} elseif ( is_bool( $value ) ) {
				$formatted_value = $value ? 'true' : 'false';
			} elseif ( is_null( $value ) ) {
				$formatted_value = 'null';
			} elseif ( is_numeric( $value ) ) {
				$formatted_value = (string) $value;
			} else {
				$formatted_value = "'" . addslashes( maybe_serialize( $value ) ) . "'";
			}
			$output .= "    {$formatted_key} => {$formatted_value},\n";
		}
		$output .= ')';

		return $output;
	}
}
