<?php
/**
 * Tenant Clone Orchestrator
 *
 * 6-step tenant cloning via AJAX polling for shared MySQL only.
 * Simplified from Pro's 7-step GrabWP_Tenancy_Pro_Clone — no symlink extensions step
 * since base tenants share main site's plugins/themes globally.
 *
 * Steps:
 *  1 - Validate source + target tenants, create temp staging directory
 *  2 - Export source database
 *  3 - Import database into target tenant (prefix replacement)
 *  4 - Copy uploads directory
 *  5 - Fix siteurl/home + strip GrabWP plugins if mainsite source
 *  6 - Cleanup temp staging directory
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-grabwp-tenancy-clone-fs-helper.php';
require_once __DIR__ . '/class-grabwp-tenancy-clone-db-exporter.php';
require_once __DIR__ . '/class-grabwp-tenancy-clone-db-importer.php';
require_once __DIR__ . '/class-grabwp-tenancy-clone-url-replacer.php';

/**
 * Orchestrates the 6-step tenant clone process (shared MySQL only).
 */
class GrabWP_Tenancy_Clone {

	const TOTAL_STEPS = 6;
	const JOB_TTL     = 1800; // 30 minutes.

	/**
	 * Create a new clone job and return its ID.
	 *
	 * @param string $source_tenant_id Source tenant ID.
	 * @param string $target_tenant_id Pre-existing target tenant ID.
	 * @param array  $target_domains   Domains assigned to target tenant.
	 * @return string 32-character hex job ID.
	 */
	public function init_job( $source_tenant_id, $target_tenant_id, $target_domains ) {
		$job_id = bin2hex( random_bytes( 16 ) );
		$state  = [
			'action'           => 'clone',
			'source_tenant_id' => sanitize_key( $source_tenant_id ),
			'target_tenant_id' => sanitize_key( $target_tenant_id ),
			'target_domains'   => $target_domains,
			'step'             => 0,
			'total'            => self::TOTAL_STEPS,
			'data'             => [],
		];
		set_transient( 'grabwp_clone_job_' . $job_id, $state, self::JOB_TTL );
		return $job_id;
	}

	/**
	 * Execute the next pending step for the given job.
	 *
	 * @param string $job_id Job ID returned by init_job().
	 * @return array|WP_Error Progress array or WP_Error on failure.
	 */
	public function run_step( $job_id ) {
		$state = get_transient( 'grabwp_clone_job_' . $job_id );
		if ( ! $state ) {
			return new WP_Error( 'invalid_job', __( 'Clone job not found or expired.', 'grabwp-tenancy' ) );
		}

		$next = (int) $state['step'] + 1;
		if ( $next > self::TOTAL_STEPS ) {
			return new WP_Error( 'already_done', __( 'Clone is already completed.', 'grabwp-tenancy' ) );
		}

		$result = $this->dispatch_step( $next, $state );
		if ( is_wp_error( $result ) ) {
			// Cleanup temp dir on failure.
			if ( ! empty( $state['data']['tmp_dir'] ) && is_dir( $state['data']['tmp_dir'] ) ) {
				GrabWP_Tenancy_Clone_Fs_Helper::remove_dir( $state['data']['tmp_dir'] );
			}
			delete_transient( 'grabwp_clone_job_' . $job_id );
			return $result;
		}

		$state['step'] = $next;
		$state['data'] = array_merge( $state['data'], $result['data'] ?? [] );
		set_transient( 'grabwp_clone_job_' . $job_id, $state, self::JOB_TTL );

		$done = ( $next === self::TOTAL_STEPS );
		return [
			'done'    => $done,
			'step'    => $next,
			'total'   => self::TOTAL_STEPS,
			'message' => $result['message'],
		];
	}

	/**
	 * Dispatch a specific step.
	 *
	 * @param int   $step  Step number (1-6).
	 * @param array $state Current job state.
	 * @return array|WP_Error
	 */
	private function dispatch_step( $step, $state ) {
		$src  = $state['source_tenant_id'];
		$dst  = $state['target_tenant_id'];
		$data = $state['data'];

		// Validate tenant IDs against path traversal (defense-in-depth).
		if ( ! preg_match( '/^[a-z0-9_]+$/', $src ) && ! $this->is_mainsite( $src ) ) {
			return new WP_Error( 'invalid_source', __( 'Invalid source tenant ID.', 'grabwp-tenancy' ) );
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $dst ) ) {
			return new WP_Error( 'invalid_target', __( 'Invalid target tenant ID.', 'grabwp-tenancy' ) );
		}

		switch ( $step ) {
			case 1: return $this->step_validate( $src, $dst );
			case 2: return $this->step_export_database( $src, $data );
			case 3: return $this->step_import_database( $src, $dst, $data );
			case 4: return $this->step_copy_uploads( $src, $dst );
			case 5: return $this->step_fix_urls( $src, $dst, $state['target_domains'] );
			case 6: return $this->step_cleanup( $data );
		}
		return new WP_Error( 'invalid_step', __( 'Invalid clone step.', 'grabwp-tenancy' ) );
	}

	/**
	 * Step 1: Validate source and target tenants, create temp staging directory.
	 */
	private function step_validate( $source_tenant_id, $target_tenant_id ) {
		// Verify source exists in tenants.php (or is mainsite).
		if ( ! $this->tenant_exists( $source_tenant_id ) && ! $this->is_mainsite( $source_tenant_id ) ) {
			return new WP_Error( 'source_not_found', __( 'Source tenant not found.', 'grabwp-tenancy' ) );
		}

		// Verify target exists in tenants.php.
		if ( ! $this->tenant_exists( $target_tenant_id ) ) {
			return new WP_Error( 'target_not_found', __( 'Target tenant not found.', 'grabwp-tenancy' ) );
		}

		// Create temp staging directory.
		$ts      = time();
		$tmp_dir = GRABWP_TENANCY_BASE_DIR . '/tmp/clone-' . $source_tenant_id . '-' . $ts;
		if ( ! wp_mkdir_p( $tmp_dir ) ) {
			return new WP_Error( 'mkdir_fail', __( 'Cannot create temp staging directory.', 'grabwp-tenancy' ) );
		}

		// Base plugin always uses shared MySQL.
		$db_type = 'shared';

		return [
			'message' => __( 'Source and target tenants validated.', 'grabwp-tenancy' ),
			'data'    => compact( 'db_type', 'tmp_dir', 'ts' ),
		];
	}

	/**
	 * Step 2: Export source tenant database.
	 */
	private function step_export_database( $source_tenant_id, $data ) {
		$exporter = new GrabWP_Tenancy_Clone_Db_Exporter();
		$result   = $exporter->export( $source_tenant_id, $data['tmp_dir'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return [ 'message' => __( 'Source database exported.', 'grabwp-tenancy' ) ];
	}

	/**
	 * Step 3: Import database into target tenant with prefix replacement.
	 */
	private function step_import_database( $source_tenant_id, $target_tenant_id, $data ) {
		$sql_file  = $data['tmp_dir'] . '/database.sql';
		$meta_file = $data['tmp_dir'] . '/metadata.json';

		if ( ! file_exists( $sql_file ) || ! file_exists( $meta_file ) ) {
			return new WP_Error( 'missing_export', __( 'Export files not found.', 'grabwp-tenancy' ) );
		}

		$meta = json_decode( file_get_contents( $meta_file ), true );
		if ( ! is_array( $meta ) ) {
			return new WP_Error( 'bad_meta', __( 'Export metadata is invalid.', 'grabwp-tenancy' ) );
		}

		$importer = new GrabWP_Tenancy_Clone_Db_Importer();
		$result   = $importer->import( $target_tenant_id, $sql_file, $meta );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return [ 'message' => __( 'Database imported into target tenant.', 'grabwp-tenancy' ) ];
	}

	/**
	 * Step 4: Copy uploads from source to target tenant.
	 */
	private function step_copy_uploads( $source_tenant_id, $target_tenant_id ) {
		// Mainsite uploads live in the default WordPress uploads directory.
		if ( $this->is_mainsite( $source_tenant_id ) ) {
			$upload_dir = wp_upload_dir();
			$src        = $upload_dir['basedir'];
		} else {
			$src = GRABWP_TENANCY_BASE_DIR . '/' . $source_tenant_id . '/uploads';
		}
		$dst = GRABWP_TENANCY_BASE_DIR . '/' . $target_tenant_id . '/uploads';

		if ( is_dir( $src ) ) {
			// Exclude tenancy data directories that may live inside wp-content/uploads.
			$exclude_dirs = self::get_tenancy_exclude_dirs();
			GrabWP_Tenancy_Clone_Fs_Helper::recurse_copy( $src, $dst, $exclude_dirs );
		} else {
			wp_mkdir_p( $dst );
		}
		return [ 'message' => __( 'Uploads copied.', 'grabwp-tenancy' ) ];
	}

	/**
	 * Step 5: Comprehensive URL replacement + strip GrabWP plugins if mainsite source.
	 */
	private function step_fix_urls( $source_tenant_id, $target_tenant_id, $target_domains ) {
		// Determine target site URL from its domains.
		$real_domains = array_filter( $target_domains, function ( $d ) {
			return 'nodomain.local' !== $d;
		} );

		if ( ! empty( $real_domains ) ) {
			$new_url = ( is_ssl() ? 'https://' : 'http://' ) . reset( $real_domains );
		} else {
			$new_url = site_url( '/site/' . $target_tenant_id );
		}

		$replacer = new GrabWP_Tenancy_Clone_Url_Replacer();

		// Read current siteurl from target DB — still contains source URL after import.
		$old_url = $replacer->read_current_siteurl( $target_tenant_id );

		if ( ! empty( $old_url ) && $old_url !== $new_url ) {
			$replacer->replace( $target_tenant_id, $old_url, $new_url );
		} else {
			// Fallback: at minimum ensure siteurl/home are set correctly.
			$this->update_tenant_options( $target_tenant_id, $new_url );
		}

		// When cloning mainsite, fix upload paths: mainsite uses /wp-content/uploads/
		// but tenant files live in /wp-content/uploads/grabwp-tenancy/{id}/uploads/.
		if ( $this->is_mainsite( $source_tenant_id ) ) {
			$upload_dir     = wp_upload_dir();
			$old_upload_url = $upload_dir['baseurl'];
			$new_upload_url = GrabWP_Tenancy_Path_Manager::get_tenant_upload_url( $target_tenant_id );
			if ( ! empty( $new_upload_url ) && $old_upload_url !== $new_upload_url ) {
				$replacer->replace( $target_tenant_id, $old_upload_url, $new_upload_url );
			}

			$this->strip_grabwp_from_active_plugins( $target_tenant_id );
		}

		return [ 'message' => __( 'Site URLs updated.', 'grabwp-tenancy' ) ];
	}

	/**
	 * Step 6: Cleanup temp staging directory.
	 */
	private function step_cleanup( $data ) {
		if ( ! empty( $data['tmp_dir'] ) && is_dir( $data['tmp_dir'] ) ) {
			GrabWP_Tenancy_Clone_Fs_Helper::remove_dir( $data['tmp_dir'] );
		}
		return [ 'message' => __( 'Clone complete.', 'grabwp-tenancy' ) ];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build list of tenancy data directories to exclude when copying uploads.
	 *
	 * Prevents infinite recursion when mainsite uploads dir contains
	 * GRABWP_TENANCY_BASE_DIR, GRABWP_TENANCY_PRO_BASE_DIR, or GRABWP_TENANCY_SQLITE_DIR.
	 *
	 * @return array Resolved absolute paths to exclude.
	 */
	private static function get_tenancy_exclude_dirs() {
		$exclude = [];
		$constants = [ 'GRABWP_TENANCY_BASE_DIR', 'GRABWP_TENANCY_PRO_BASE_DIR', 'GRABWP_TENANCY_SQLITE_DIR' ];
		foreach ( $constants as $const ) {
			if ( defined( $const ) && is_dir( constant( $const ) ) ) {
				$real = realpath( constant( $const ) );
				if ( $real ) {
					$exclude[] = $real;
				}
			}
		}
		return $exclude;
	}

	/**
	 * Check if a tenant ID is the mainsite pseudo-ID.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return bool
	 */
	private function is_mainsite( $tenant_id ) {
		return defined( 'GRABWP_MAINSITE_ID' ) && GRABWP_MAINSITE_ID === $tenant_id;
	}

	/**
	 * Check if a tenant exists in tenants.php mappings.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return bool
	 */
	private function tenant_exists( $tenant_id ) {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		if ( ! file_exists( $mappings_file ) ) {
			return false;
		}
		$tenant_mappings = [];
		ob_start();
		include $mappings_file;
		ob_end_clean();
		return isset( $tenant_mappings[ $tenant_id ] );
	}

	/**
	 * Update siteurl and home options in shared MySQL.
	 *
	 * @param string $tenant_id Target tenant ID.
	 * @param string $new_url   New site URL.
	 */
	private function update_tenant_options( $tenant_id, $new_url ) {
		global $wpdb;
		$options_table = $tenant_id . '_options';
		$wpdb->update( $options_table, [ 'option_value' => $new_url ], [ 'option_name' => 'siteurl' ] );
		$wpdb->update( $options_table, [ 'option_value' => $new_url ], [ 'option_name' => 'home' ] );
	}

	/**
	 * Remove GrabWP tenancy plugins from active_plugins in target tenant DB.
	 *
	 * @param string $tenant_id Target tenant ID.
	 */
	private function strip_grabwp_from_active_plugins( $tenant_id ) {
		global $wpdb;
		$options_table = $tenant_id . '_options';
		$raw = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM `{$options_table}` WHERE option_name = %s",
			'active_plugins'
		) );
		if ( $raw ) {
			$plugins  = maybe_unserialize( $raw );
			$filtered = array_values( array_filter( (array) $plugins, function ( $p ) {
				return false === strpos( $p, 'grabwp-tenancy' );
			} ) );
			$wpdb->update(
				$options_table,
				[ 'option_value' => maybe_serialize( $filtered ) ],
				[ 'option_name' => 'active_plugins' ]
			);
		}
	}
}
