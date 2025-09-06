<?php
/**
 * GrabWP Tenancy - Early Loading System
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double loading
if ( defined( 'GRABWP_TENANCY_LOADED' ) ) {
	return;
}

define( 'GRABWP_TENANCY_LOADED', true );

// Include helper functions
require_once __DIR__ . '/load-helper.php';

/**
 * Detect tenant from CLI or domain
 */
function grabwp_tenancy_detect_tenant() {
	// CLI: Check for pre-defined tenant ID
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) && GRABWP_TENANCY_TENANT_ID !== '' ) {
		grabwp_tenancy_configure_cli_environment();
		return GRABWP_TENANCY_TENANT_ID;
	}

	// Web: Get domain and find tenant
	$server_info     = grabwp_tenancy_get_server_info();
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();

	return grabwp_tenancy_identify_tenant( $server_info['host'], $tenant_mappings );
}

/**
 * Initialize tenant system
 */
function grabwp_tenancy_early_init() {
	$tenant_id = grabwp_tenancy_detect_tenant();

	if ( ! $tenant_id ) {
		return;
	}

	grabwp_tenancy_define_constants();
	grabwp_tenancy_set_tenant_context( $tenant_id );
	grabwp_tenancy_configure_tenant( $tenant_id );
}

// Initialize
grabwp_tenancy_early_init();
