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

// Initialize
grabwp_tenancy_early_init();
