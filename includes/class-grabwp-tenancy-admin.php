<?php
/**
 * GrabWP Tenancy Admin Class
 *
 * Handles WordPress admin interface for tenant management.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Admin Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Admin {

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
		// Form processing - must be early to avoid headers already sent
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );

		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Allow pro plugin to extend
		do_action( 'grabwp_tenancy_admin_init', $this );
	}

	/**
	 * Handle form submissions before any output - only on main site
	 */
	public function handle_form_submissions() {
		// Don't handle form submissions on tenant sites
		if ( $this->plugin->is_tenant() ) {
			return;
		}

		// Check capabilities first
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		// Sanitize action
		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		// Only process on our admin pages
		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'grabwp-tenancy' ) === false ) {
			return;
		}

		switch ( $action ) {
			case 'create_tenant':
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_create' ) ) {
					$domains = array();
					if ( isset( $_POST['domains'] ) && is_array( $_POST['domains'] ) ) {
						// Sanitize and unslash first for WPCS compliance
						$raw_domains = array_map( 'sanitize_text_field', wp_unslash( $_POST['domains'] ) );

						// Additional security: limit array size to prevent DoS
						if ( count( $raw_domains ) > 10 ) {
							$raw_domains = array_slice( $raw_domains, 0, 10 );
						}

						$domains = array_filter( $raw_domains );
					}
					$result = $this->handle_create_tenant( $domains );

					if ( $result['type'] === 'success' ) {
						$success_nonce = wp_create_nonce( 'grabwp_tenancy_notice' );
						wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy&message=created&_wpnonce=' . urlencode( $success_nonce ) ) );
						exit;
					} else {
						// Store error for display
						set_transient( 'grabwp_tenancy_error', $result['message'], 60 );
						$error_nonce = wp_create_nonce( 'grabwp_tenancy_error' );
						wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy-create&error=1&_wpnonce=' . urlencode( $error_nonce ) ) );
						exit;
					}
				}
				break;

			case 'update_tenant':
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_update' ) ) {
					$tenant_id = isset( $_POST['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ) : '';
					$domains   = array();
					if ( isset( $_POST['domains'] ) && is_array( $_POST['domains'] ) ) {
						// Sanitize and unslash first for WPCS compliance
						$raw_domains = array_map( 'sanitize_text_field', wp_unslash( $_POST['domains'] ) );

						// Additional security: limit array size to prevent DoS
						if ( count( $raw_domains ) > 10 ) {
							$raw_domains = array_slice( $raw_domains, 0, 10 );
						}

						$domains = array_filter( $raw_domains );
					}
					$result = $this->handle_update_tenant( $tenant_id, $domains );

					if ( $result['type'] === 'success' ) {
						$success_nonce = wp_create_nonce( 'grabwp_tenancy_notice' );
						wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy&message=updated&_wpnonce=' . urlencode( $success_nonce ) ) );
						exit;
					} else {
						// Store error for display
						set_transient( 'grabwp_tenancy_error', $result['message'], 60 );
						$error_nonce = wp_create_nonce( 'grabwp_tenancy_error' );
						wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy-edit&tenant_id=' . urlencode( $tenant_id ) . '&error=1&_wpnonce=' . urlencode( $error_nonce ) ) );
						exit;
					}
				}
				break;

			case 'delete_tenant':
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_delete' ) ) {
					$tenant_id = isset( $_POST['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ) : '';
					$result    = $this->handle_delete_tenant( $tenant_id );

					if ( $result['type'] === 'success' ) {
						$success_nonce = wp_create_nonce( 'grabwp_tenancy_notice' );
						wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy&message=deleted&_wpnonce=' . urlencode( $success_nonce ) ) );
						exit;
					} else {
						// Store error for display
						set_transient( 'grabwp_tenancy_error', $result['message'], 60 );
						$error_nonce = wp_create_nonce( 'grabwp_tenancy_error' );
						wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy&error=1&_wpnonce=' . urlencode( $error_nonce ) ) );
						exit;
					}
				}
				break;
		}
	}

	/**
	 * Add admin menu - only on main site, not on tenant sites
	 */
	public function add_admin_menu() {
		// Don't show admin UI on tenant sites
		if ( $this->plugin->is_tenant() ) {
			return;
		}

		add_menu_page(
			__( 'GrabWP Tenancy', 'grabwp-tenancy' ),
			__( 'Tenancy', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy',
			array( $this, 'admin_page' ),
			'dashicons-admin-multisite',
			30
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'All Tenants', 'grabwp-tenancy' ),
			__( 'All Tenants', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'Add New Tenant', 'grabwp-tenancy' ),
			__( 'Add New', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-create',
			array( $this, 'create_page' )
		);

		// Edit page is hidden from menu, accessed via links
		add_submenu_page(
			null, // Hidden from menu
			__( 'Edit Tenant', 'grabwp-tenancy' ),
			__( 'Edit Tenant', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-edit',
			array( $this, 'edit_page' )
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'Settings', 'grabwp-tenancy' ),
			__( 'Settings', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles - only on main site
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Don't load admin assets on tenant sites
		if ( $this->plugin->is_tenant() ) {
			return;
		}

		// Check if we need to show admin notices (plugin not properly configured)
		$needs_notice = ! defined( 'GRABWP_TENANCY_LOADED' );

		// Only enqueue on GrabWP admin pages or when notices need to be shown
		if ( strpos( $hook, 'grabwp-tenancy' ) === false && ! $needs_notice ) {
			return;
		}

		wp_enqueue_style(
			'grabwp-tenancy-admin',
			$this->plugin->plugin_url . 'admin/css/grabwp-admin.css',
			array(),
			$this->plugin->version
		);

		wp_enqueue_script(
			'grabwp-tenancy-admin',
			$this->plugin->plugin_url . 'admin/js/grabwp-admin.js',
			array(),
			$this->plugin->version,
			true
		);

		// Localize script with translatable strings
		wp_localize_script(
			'grabwp-tenancy-admin',
			'grabwpTenancyAdmin',
			array(
				'enterDomainPlaceholder' => __( 'Enter domain (e.g., tenant1.grabwp.local)', 'grabwp-tenancy' ),
				'removeText'             => __( 'Remove', 'grabwp-tenancy' ),
			)
		);
	}

	/**
	 * Main admin page
	 */
	public function admin_page() {
		$tenants = $this->get_tenants();
		$this->render_admin_page( 'tenants', array( 'tenants' => $tenants ) );
	}

	/**
	 * Create tenant page
	 */
	public function create_page() {
		$this->render_admin_page( 'tenant-create' );
	}

	/**
	 * Edit tenant page
	 */
	public function edit_page() {
		// Verify nonce for security
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_tenancy_edit' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'grabwp-tenancy' ) );
		}

		$tenant_id = isset( $_GET['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_GET['tenant_id'] ) ) : '';

		if ( ! $tenant_id ) {
			wp_die( esc_html__( 'Tenant ID is required.', 'grabwp-tenancy' ) );
		}

		$tenant = $this->get_tenant( $tenant_id );
		if ( ! $tenant ) {
			wp_die( esc_html__( 'Tenant not found.', 'grabwp-tenancy' ) );
		}

		$this->render_admin_page( 'tenant-edit', array( 'tenant' => $tenant ) );
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		$this->render_admin_page( 'settings' );
	}

	/**
	 * Render admin page
	 *
	 * @param string $template Template name
	 * @param array  $data Template data
	 */
	private function render_admin_page( $template, $data = array() ) {
		$template_file = $this->plugin->plugin_dir . 'admin/views/' . $template . '.php';

		if ( file_exists( $template_file ) ) {
			extract( $data );
			include $template_file;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'GrabWP Tenancy', 'grabwp-tenancy' ) . '</h1><p>' . esc_html__( 'Template not found.', 'grabwp-tenancy' ) . '</p></div>';
		}
	}

	/**
	 * Get tenant mappings file path
	 *
	 * @return string Mappings file path
	 */
	private function get_mappings_file_path() {
		return GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
	}

	/**
	 * Get all tenants
	 *
	 * @return array
	 */
	private function get_tenants() {
		$mappings_file = $this->get_mappings_file_path();

		if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
			// Clear any file system cache
			clearstatcache( true, $mappings_file );

			// Read file content safely
			$content = file_get_contents( $mappings_file );
			if ( $content !== false ) {
				// Create a safe execution environment
				$tenant_mappings = array();

				// Use include instead of eval for safer execution
				ob_start();
				include $mappings_file;
				ob_end_clean();

				$tenants = array();
				if ( is_array( $tenant_mappings ) ) {
					foreach ( $tenant_mappings as $tenant_id => $domains ) {
						$tenant    = new GrabWP_Tenancy_Tenant(
							$tenant_id,
							array(
								'domains' => $domains,
							)
						);
						$tenants[] = $tenant;
					}
				}

				return $tenants;
			}
		}

		return array();
	}

	/**
	 * Get single tenant
	 *
	 * @param string $tenant_id Tenant ID
	 * @return GrabWP_Tenancy_Tenant|null
	 */
	private function get_tenant( $tenant_id ) {
		$mappings_file = $this->get_mappings_file_path();

		if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
			// Clear any file system cache
			clearstatcache( true, $mappings_file );

			// Create a safe execution environment
			$tenant_mappings = array();

			// Use include instead of eval for safer execution
			ob_start();
			include $mappings_file;
			ob_end_clean();

			if ( is_array( $tenant_mappings ) && isset( $tenant_mappings[ $tenant_id ] ) ) {
				return new GrabWP_Tenancy_Tenant(
					$tenant_id,
					array(
						'domains' => $tenant_mappings[ $tenant_id ],
					)
				);
			}
		}

		return null;
	}

	/**
	 * Save tenant mappings
	 *
	 * @param array $tenant_mappings Tenant mappings
	 * @return bool Success status
	 */
	private function save_tenant_mappings( $tenant_mappings ) {
		$mappings_file = $this->get_mappings_file_path();

		$content  = "<?php\n";
		$content .= "/**\n";
		$content .= " * Tenant Domain Mappings\n";
		$content .= " * \n";
		$content .= " * This file contains domain mappings for tenant identification.\n";
		$content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
		$content .= " */\n\n";
		$content .= "\$tenant_mappings = array(\n";

		foreach ( $tenant_mappings as $tenant_id => $domains ) {
			$content .= "    '" . $tenant_id . "' => array(\n";
			foreach ( $domains as $domain ) {
				$content .= "        '" . $domain . "',\n";
			}
			$content .= "    ),\n";
		}

		$content .= ");\n";

		$result = file_put_contents( $mappings_file, $content ) !== false;

		// Clear any file system cache and PHP OpCache
		if ( $result ) {
			clearstatcache( true, $mappings_file );
			if ( function_exists( 'opcache_invalidate' ) ) {
				opcache_invalidate( $mappings_file, true );
			}
		}

		return $result;
	}

	/**
	 * Handle create tenant form submission
	 */
	public function handle_create_tenant( $domains ) {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'message' => __( 'Insufficient permissions.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		if ( empty( $domains ) ) {
			return array(
				'message' => __( 'Please enter at least one domain.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		// Validate and sanitize domains
		$validated_domains = array();
		$invalid_domains   = array();

		foreach ( $domains as $domain ) {
			$domain = trim( $domain );
			if ( empty( $domain ) ) {
				continue;
			}

			// Additional security: reject excessively long domain strings
			if ( strlen( $domain ) > 253 ) {
				$invalid_domains[] = substr( $domain, 0, 50 ) . '...'; // Truncate for display
				continue;
			}

			if ( ! $this->validate_domain_format( $domain ) ) {
				$invalid_domains[] = $domain;
				continue;
			}

			$validated_domains[] = $domain;
		}

		if ( ! empty( $invalid_domains ) ) {
			return array(
				'message' => sprintf(
					/* translators: %s: comma-separated list of invalid domain names */
					__( 'Invalid domain format(s): %s. Please use valid domain names (e.g., example.com, subdomain.example.com).', 'grabwp-tenancy' ),
					implode( ', ', $invalid_domains )
				),
				'type'    => 'error',
			);
		}

		if ( empty( $validated_domains ) ) {
			return array(
				'message' => __( 'Please enter at least one valid domain.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		// Check for duplicate domains
		$duplicate_check = $this->check_domain_uniqueness( $validated_domains );
		if ( ! $duplicate_check['unique'] ) {
			return array(
				'message' => sprintf(
					/* translators: %s: comma-separated list of duplicate domain names */
					__( 'Domain(s) already in use: %s. Each domain can only be assigned to one tenant.', 'grabwp-tenancy' ),
					implode( ', ', $duplicate_check['duplicates'] )
				),
				'type'    => 'error',
			);
		}

		$tenant_id = GrabWP_Tenancy_Tenant::generate_id();

		// Load existing mappings
		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();

		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

		// Add new tenant
		$tenant_mappings[ $tenant_id ] = $validated_domains;

		// Save mappings
		if ( $this->save_tenant_mappings( $tenant_mappings ) ) {
			// Create tenant directories
			$loader = new GrabWP_Tenancy_Loader( $this->plugin );
			$loader->create_tenant_directories( $tenant_id );

			return array(
				'message' => __( 'Tenant created successfully.', 'grabwp-tenancy' ),
				'type'    => 'success',
			);
		} else {
			return array(
				'message' => __( 'Failed to create tenant.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}
	}

	/**
	 * Handle delete tenant form submission
	 */
	public function handle_delete_tenant( $tenant_id ) {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'message' => __( 'Insufficient permissions.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return array(
				'message' => __( 'Invalid tenant ID.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		// Load existing mappings
		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();

		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

		// Remove tenant
		if ( isset( $tenant_mappings[ $tenant_id ] ) ) {
			unset( $tenant_mappings[ $tenant_id ] );

			// Save mappings
			if ( $this->save_tenant_mappings( $tenant_mappings ) ) {
				// Remove tenant directories
				$loader = new GrabWP_Tenancy_Loader( $this->plugin );
				$loader->remove_tenant_directories( $tenant_id );

				return array(
					'message' => __( 'Tenant deleted successfully.', 'grabwp-tenancy' ),
					'type'    => 'success',
				);
			} else {
				return array(
					'message' => __( 'Failed to delete tenant.', 'grabwp-tenancy' ),
					'type'    => 'error',
				);
			}
		} else {
			return array(
				'message' => __( 'Tenant not found.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}
	}

	/**
	 * Handle update tenant form submission
	 */
	public function handle_update_tenant( $tenant_id, $domains ) {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'message' => __( 'Insufficient permissions.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return array(
				'message' => __( 'Invalid tenant ID.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		if ( empty( $domains ) ) {
			return array(
				'message' => __( 'Please enter at least one domain.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		// Validate and sanitize domains
		$validated_domains = array();
		$invalid_domains   = array();

		foreach ( $domains as $domain ) {
			$domain = trim( $domain );
			if ( empty( $domain ) ) {
				continue;
			}

			// Additional security: reject excessively long domain strings
			if ( strlen( $domain ) > 253 ) {
				$invalid_domains[] = substr( $domain, 0, 50 ) . '...'; // Truncate for display
				continue;
			}

			if ( ! $this->validate_domain_format( $domain ) ) {
				$invalid_domains[] = $domain;
				continue;
			}

			$validated_domains[] = $domain;
		}

		if ( ! empty( $invalid_domains ) ) {
			return array(
				'message' => sprintf(
					/* translators: %s: comma-separated list of invalid domain names */
					__( 'Invalid domain format(s): %s. Please use valid domain names (e.g., example.com, subdomain.example.com).', 'grabwp-tenancy' ),
					implode( ', ', $invalid_domains )
				),
				'type'    => 'error',
			);
		}

		if ( empty( $validated_domains ) ) {
			return array(
				'message' => __( 'Please enter at least one valid domain.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}

		// Check for duplicate domains (excluding current tenant)
		$duplicate_check = $this->check_domain_uniqueness( $validated_domains, $tenant_id );
		if ( ! $duplicate_check['unique'] ) {
			return array(
				'message' => sprintf(
					/* translators: %s: comma-separated list of domain names already in use by other tenants */
					__( 'Domain(s) already in use by other tenants: %s. Each domain can only be assigned to one tenant.', 'grabwp-tenancy' ),
					implode( ', ', $duplicate_check['duplicates'] )
				),
				'type'    => 'error',
			);
		}

		// Load existing mappings
		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();

		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

		// Update tenant
		if ( isset( $tenant_mappings[ $tenant_id ] ) ) {
			$tenant_mappings[ $tenant_id ] = $validated_domains;

			// Save mappings
			if ( $this->save_tenant_mappings( $tenant_mappings ) ) {
				return array(
					'message' => __( 'Tenant updated successfully.', 'grabwp-tenancy' ),
					'type'    => 'success',
				);
			} else {
				return array(
					'message' => __( 'Failed to update tenant.', 'grabwp-tenancy' ),
					'type'    => 'error',
				);
			}
		} else {
			return array(
				'message' => __( 'Tenant not found.', 'grabwp-tenancy' ),
				'type'    => 'error',
			);
		}
	}

	/**
	 * Enhanced domain format validation
	 *
	 * @param string $domain Domain to validate
	 * @return bool Valid status
	 */
	private function validate_domain_format( $domain ) {
		// Basic format check
		if ( ! filter_var( $domain, FILTER_VALIDATE_DOMAIN ) ) {
			return false;
		}

		// Additional validation rules
		$domain = strtolower( trim( $domain ) );

		// Check for valid characters
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/', $domain ) ) {
			return false;
		}

		// Check for valid TLD (at least 2 characters)
		$parts = explode( '.', $domain );
		if ( count( $parts ) < 2 ) {
			return false;
		}

		$tld = end( $parts );
		if ( strlen( $tld ) < 2 ) {
			return false;
		}

		// Check for common invalid patterns
		$invalid_patterns = array(
			'/^[0-9]+$/', // All numbers
			'/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', // IP address
			'/^localhost$/', // localhost
			'/^127\.0\.0\.1$/', // localhost IP
		);

		foreach ( $invalid_patterns as $pattern ) {
			if ( preg_match( $pattern, $domain ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check domain uniqueness across all tenants
	 *
	 * @param array  $domains Domains to check
	 * @param string $exclude_tenant_id Tenant ID to exclude from check (for updates)
	 * @return array Array with 'unique' boolean and 'duplicates' array
	 */
	private function check_domain_uniqueness( $domains, $exclude_tenant_id = '' ) {
		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();

		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

		$duplicates           = array();
		$all_existing_domains = array();

		// Collect all existing domains
		foreach ( $tenant_mappings as $tenant_id => $tenant_domains ) {
			if ( $exclude_tenant_id && $tenant_id === $exclude_tenant_id ) {
				continue; // Skip current tenant for updates
			}

			if ( is_array( $tenant_domains ) ) {
				foreach ( $tenant_domains as $domain ) {
					$all_existing_domains[] = strtolower( trim( $domain ) );
				}
			}
		}

		// Check for duplicates
		foreach ( $domains as $domain ) {
			$domain_lower = strtolower( trim( $domain ) );
			if ( in_array( $domain_lower, $all_existing_domains ) ) {
				$duplicates[] = $domain;
			}
		}

		return array(
			'unique'     => empty( $duplicates ),
			'duplicates' => $duplicates,
		);
	}

	/**
	 * Admin notices - only on main site
	 */
	public function admin_notices() {
		// Don't show admin notices on tenant sites
		if ( $this->plugin->is_tenant() ) {
			return;
		}

		// Show notices for admin pages
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page && strpos( $page, 'grabwp-tenancy' ) !== false ) {

			// Handle success messages via URL parameters with nonce verification
			if ( isset( $_GET['message'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_tenancy_notice' ) ) {
				$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
				if ( in_array( $message, array( 'created', 'updated', 'deleted' ), true ) ) {
					$success_message = '';
					$type            = 'success';

					switch ( $message ) {
						case 'created':
							$success_message = __( 'Tenant created successfully.', 'grabwp-tenancy' );
							break;
						case 'updated':
							$success_message = __( 'Tenant updated successfully.', 'grabwp-tenancy' );
							break;
						case 'deleted':
							$success_message = __( 'Tenant deleted successfully.', 'grabwp-tenancy' );
							break;
					}

					if ( $success_message ) {
						$class = 'notice notice-' . $type . ' is-dismissible';
						printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $success_message ) );
					}
				}
			}

			// Handle error messages via transients
			$error_message = get_transient( 'grabwp_tenancy_error' );
			if ( $error_message ) {
				$class = 'notice notice-error is-dismissible';
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $error_message ) );
				delete_transient( 'grabwp_tenancy_error' );
			}
		}
	}
}
