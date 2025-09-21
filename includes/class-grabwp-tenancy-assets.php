<?php
/**
 * GrabWP Tenancy Assets Class
 *
 * Handles frontend and admin asset loading for tenant sites.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Assets Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Assets {

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
		// Only load assets on tenant sites
		if ( $this->plugin->is_tenant() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
	}

	/**
	 * Enqueue frontend scripts
	 */
	public function enqueue_frontend_scripts() {
		// Load public.js
		$handle    = 'grabwp-tenancy-public';
		$src       = $this->plugin->plugin_url . 'assets/js/public.js';
		$file_path = $this->plugin->plugin_dir . 'assets/js/public.js';
		$version   = file_exists( $file_path ) ? filemtime( $file_path ) : $this->plugin->version;

		wp_register_script( $handle, $src, array(), $version, true );

		// Provide tenant context
		$data = array(
			'version'  => $this->plugin->version,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'isTenant' => true,
			'tenantId' => $this->plugin->get_tenant_id(),
		);

		wp_localize_script( $handle, 'grabwpTenancyPublic', $data );
		wp_enqueue_script( $handle );

	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts() {
		// Load admin.js
		$handle    = 'grabwp-tenancy-admin';
		$src       = $this->plugin->plugin_url . 'assets/js/admin.js';
		$file_path = $this->plugin->plugin_dir . 'assets/js/admin.js';
		$version   = file_exists( $file_path ) ? filemtime( $file_path ) : $this->plugin->version;

		wp_register_script( $handle, $src, array( 'jquery' ), $version, true );

		// Provide tenant context for admin
		$data = array(
			'version'    => $this->plugin->version,
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'isTenant'   => true,
			'tenantId'   => $this->plugin->get_tenant_id(),
			'automation' => false, // Can be configured later if needed
		);

		wp_localize_script( $handle, 'grabwpTenancyAdmin', $data );
		wp_enqueue_script( $handle );

	}


}
