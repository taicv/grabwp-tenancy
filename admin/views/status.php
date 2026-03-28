<?php
/**
 * GrabWP Tenancy - Status Admin Page Template
 *
 * Displays read-only system information and status details in 3 tabs:
 * 1. Plugin General - Overview and system info
 * 2. Base Plugin Status - File structure, DB config, base plugin details
 * 3. Pro Plugin Status - Pro features and their configuration status
 *
 * @package GrabWP_Tenancy
 * @since 1.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gather data for status display.
$grabwp_tenancy_path_status   = GrabWP_Tenancy_Path_Manager::get_path_status();
$grabwp_tenancy_mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
$grabwp_tenancy_base_path     = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
$grabwp_tenancy_settings_inst = GrabWP_Tenancy_Settings::get_instance();
$grabwp_tenancy_settings_file = $grabwp_tenancy_settings_inst->get_settings_file_path();

// Count tenants from mappings file.
$grabwp_tenancy_tenant_count = 0;
if ( file_exists( $grabwp_tenancy_mappings_file ) && is_readable( $grabwp_tenancy_mappings_file ) ) {
	$grabwp_tenancy_tenant_mappings = array();
	ob_start();
	include $grabwp_tenancy_mappings_file;
	ob_end_clean();
	if ( is_array( $grabwp_tenancy_tenant_mappings ) ) {
		$grabwp_tenancy_tenant_count = count( $grabwp_tenancy_tenant_mappings );
	}
}

// Detect database engine.
if ( defined( 'DB_ENGINE' ) ) {
	$grabwp_tenancy_db_engine = DB_ENGINE;
} elseif ( defined( 'DATABASE_TYPE' ) ) {
	$grabwp_tenancy_db_engine = DATABASE_TYPE;
} else {
	$grabwp_tenancy_db_engine = 'mysql';
}
$grabwp_tenancy_db_engine_label = ucfirst( $grabwp_tenancy_db_engine );

// Get current table prefix.
global $table_prefix;
$grabwp_tenancy_main_prefix = defined( 'GRABWP_TENANCY_ORIGINAL_PREFIX' ) ? GRABWP_TENANCY_ORIGINAL_PREFIX : $table_prefix;

// Pro plugin status.
$grabwp_tenancy_is_pro_active = defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) && GRABWP_TENANCY_PRO_ACTIVE;
$grabwp_tenancy_pro_version   = defined( 'GRABWP_TENANCY_PRO_VERSION' ) ? GRABWP_TENANCY_PRO_VERSION : '';

// Pro default config (if pro is active).
$grabwp_tenancy_pro_default_config = array();
if ( $grabwp_tenancy_is_pro_active && class_exists( 'GrabWP_Tenancy_Pro_Config' ) ) {
	$grabwp_tenancy_pro_config_inst    = GrabWP_Tenancy_Pro_Config::get_instance();
	$grabwp_tenancy_pro_default_config = $grabwp_tenancy_pro_config_inst->get_default_config();
}

// =============================================================================
// ENVIRONMENT CHECKS DATA
// =============================================================================

// wp-config.php loader status.
$grabwp_tenancy_wp_config_path         = ABSPATH . 'wp-config.php';
$grabwp_tenancy_wp_config_readable     = is_readable( $grabwp_tenancy_wp_config_path );
$grabwp_tenancy_wp_config_writable     = wp_is_writable( $grabwp_tenancy_wp_config_path );
$grabwp_tenancy_loader_is_active       = defined( 'GRABWP_TENANCY_LOADED' ) && GRABWP_TENANCY_LOADED;
$grabwp_tenancy_loader_line_present    = false;
$grabwp_tenancy_stop_editing_marker    = false;
if ( $grabwp_tenancy_wp_config_readable ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	$grabwp_tenancy_wp_config_content = file_get_contents( $grabwp_tenancy_wp_config_path );
	if ( false !== $grabwp_tenancy_wp_config_content ) {
		$grabwp_tenancy_loader_line_present = ( strpos( $grabwp_tenancy_wp_config_content, 'grabwp-tenancy/load.php' ) !== false );
		$grabwp_tenancy_stop_editing_marker = (
			strpos( $grabwp_tenancy_wp_config_content, "/* That's all, stop editing! Happy publishing. */" ) !== false
			|| strpos( $grabwp_tenancy_wp_config_content, "/* That's all, stop editing! */" ) !== false
		);
	}
}

// MU-Plugin status.
$grabwp_tenancy_mu_plugin_filename = GrabWP_Tenancy_Installer::MU_PLUGIN_FILE;
$grabwp_tenancy_mu_plugins_dir     = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( ABSPATH . 'wp-content/mu-plugins' );
$grabwp_tenancy_mu_plugin_path     = $grabwp_tenancy_mu_plugins_dir . '/' . $grabwp_tenancy_mu_plugin_filename;
$grabwp_tenancy_mu_dir_exists      = is_dir( $grabwp_tenancy_mu_plugins_dir );
$grabwp_tenancy_mu_dir_writable    = $grabwp_tenancy_mu_dir_exists ? wp_is_writable( $grabwp_tenancy_mu_plugins_dir ) : wp_is_writable( dirname( $grabwp_tenancy_mu_plugins_dir ) );
$grabwp_tenancy_mu_plugin_exists   = file_exists( $grabwp_tenancy_mu_plugin_path );
$grabwp_tenancy_mu_content_valid   = false;
if ( $grabwp_tenancy_mu_plugin_exists && is_readable( $grabwp_tenancy_mu_plugin_path ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	$grabwp_tenancy_mu_content       = file_get_contents( $grabwp_tenancy_mu_plugin_path );
	$grabwp_tenancy_mu_content_valid = ( false !== $grabwp_tenancy_mu_content && strpos( $grabwp_tenancy_mu_content, 'grabwp-tenancy' ) !== false );
}

// Root .htaccess status.
$grabwp_tenancy_root_htaccess_path     = ABSPATH . '.htaccess';
$grabwp_tenancy_root_htaccess_exists   = file_exists( $grabwp_tenancy_root_htaccess_path );
$grabwp_tenancy_root_htaccess_writable = $grabwp_tenancy_root_htaccess_exists ? wp_is_writable( $grabwp_tenancy_root_htaccess_path ) : false;
$grabwp_tenancy_root_dir_writable      = wp_is_writable( ABSPATH );
$grabwp_tenancy_root_htaccess_has_grabwp_block   = false;
$grabwp_tenancy_root_htaccess_block_positioned   = false;
$grabwp_tenancy_root_htaccess_content_valid   = false;
if ( $grabwp_tenancy_root_htaccess_exists && is_readable( $grabwp_tenancy_root_htaccess_path ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	$grabwp_tenancy_root_htaccess_content = file_get_contents( $grabwp_tenancy_root_htaccess_path );
	if ( false !== $grabwp_tenancy_root_htaccess_content ) {
		$grabwp_tenancy_pos = strpos( $grabwp_tenancy_root_htaccess_content, '# BEGIN GrabWP Tenancy' );
		$grabwp_tenancy_wp_pos     = strpos( $grabwp_tenancy_root_htaccess_content, '# BEGIN WordPress' );
		$grabwp_tenancy_root_htaccess_has_grabwp_block = ( false !== $grabwp_tenancy_pos );
		// Block is correctly positioned if it appears before WordPress block (or WP block doesn't exist).
		$grabwp_tenancy_root_htaccess_block_positioned = $grabwp_tenancy_root_htaccess_has_grabwp_block && ( false === $grabwp_tenancy_wp_pos || $grabwp_tenancy_pos < $grabwp_tenancy_wp_pos );
		$grabwp_tenancy_root_htaccess_content_valid    = ( false !== strpos( $grabwp_tenancy_root_htaccess_content, 'RewriteRule ^site/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]' ) );
	}
}

// Data directory .htaccess status.
$grabwp_tenancy_data_htaccess_path   = $grabwp_tenancy_base_path . '/.htaccess';
$grabwp_tenancy_data_htaccess_exists = file_exists( $grabwp_tenancy_data_htaccess_path );
$grabwp_tenancy_data_htaccess_has_php_deny = false;
if ( $grabwp_tenancy_data_htaccess_exists && is_readable( $grabwp_tenancy_data_htaccess_path ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	$grabwp_tenancy_data_htaccess_content = file_get_contents( $grabwp_tenancy_data_htaccess_path );
	if ( false !== $grabwp_tenancy_data_htaccess_content ) {
		$grabwp_tenancy_data_htaccess_has_php_deny = ( strpos( $grabwp_tenancy_data_htaccess_content, 'Deny from all' ) !== false
			|| strpos( $grabwp_tenancy_data_htaccess_content, 'Require all denied' ) !== false );
	}
}

// index.php protection status.
$grabwp_tenancy_index_protection_exists = file_exists( $grabwp_tenancy_base_path . '/index.php' );

// Routing & context.
$grabwp_tenancy_current_host    = defined( 'GRABWP_TENANCY_LOADED' ) && isset( $_SERVER['HTTP_HOST'] )
	? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
	: '';
$grabwp_tenancy_is_tenant_ctx   = defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT;
$grabwp_tenancy_current_tenant  = defined( 'GRABWP_TENANCY_TENANT_ID' ) ? GRABWP_TENANCY_TENANT_ID : '';
$grabwp_tenancy_wp_siteurl      = defined( 'WP_SITEURL' ) ? WP_SITEURL : get_option( 'siteurl' );
$grabwp_tenancy_wp_home         = defined( 'WP_HOME' ) ? WP_HOME : get_option( 'home' );

// Server environment.
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
$grabwp_tenancy_server_software   = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'Unknown', 'grabwp-tenancy' );
$grabwp_tenancy_is_apache         = ( stripos( $grabwp_tenancy_server_software, 'apache' ) !== false || stripos( $grabwp_tenancy_server_software, 'litespeed' ) !== false );
$grabwp_tenancy_is_nginx          = ( stripos( $grabwp_tenancy_server_software, 'nginx' ) !== false );
$grabwp_tenancy_mod_rewrite       = ( $grabwp_tenancy_is_apache && function_exists( 'apache_get_modules' ) ) ? in_array( 'mod_rewrite', apache_get_modules(), true ) : null;
$grabwp_tenancy_is_multisite      = is_multisite();
$grabwp_tenancy_wp_debug          = defined( 'WP_DEBUG' ) && WP_DEBUG;
$grabwp_tenancy_base_dir_writable = is_dir( $grabwp_tenancy_base_path ) ? wp_is_writable( $grabwp_tenancy_base_path ) : false;

// Determine active tab.
// Non-destructive read for tab switching.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$grabwp_tenancy_active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'GrabWP Tenancy Status', 'grabwp-tenancy' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper grabwp-tenancy-tabs">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'general' ) ); ?>"
		   class="nav-tab <?php echo 'general' === $grabwp_tenancy_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Plugin General', 'grabwp-tenancy' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'base' ) ); ?>"
		   class="nav-tab <?php echo 'base' === $grabwp_tenancy_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Base Plugin', 'grabwp-tenancy' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'pro' ) ); ?>"
		   class="nav-tab <?php echo 'pro' === $grabwp_tenancy_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Pro Plugin', 'grabwp-tenancy' ); ?>
		</a>
	</nav>

	<div class="grabwp-tenancy-content">

		<?php
		// Show migration warning on all tabs if using legacy path structure.
		if ( $grabwp_tenancy_path_status['using_old'] ) :
			$grabwp_tenancy_upload_dir   = wp_upload_dir();
			$grabwp_tenancy_new_path     = $grabwp_tenancy_upload_dir['basedir'] . '/grabwp-tenancy';
			$grabwp_tenancy_current_path = $grabwp_tenancy_path_status['current_base'];
			?>
		<div class="notice notice-warning" style="margin: 15px 0;">
			<p><strong><?php esc_html_e( 'GrabWP Tenancy:', 'grabwp-tenancy' ); ?></strong>
			<?php esc_html_e( 'You\'re using a legacy path structure. To comply with WordPress standards:', 'grabwp-tenancy' ); ?></p>
			<p><?php esc_html_e( '1. Deactivate the plugin', 'grabwp-tenancy' ); ?><br>
			<?php
			printf(
				/* translators: %1$s: current folder name, %2$s: new folder path */
				esc_html__( '2. Rename and move the entire %1$s folder to %2$s', 'grabwp-tenancy' ),
				'<code>' . esc_html( basename( $grabwp_tenancy_current_path ) ) . '</code>',
				'<code>' . esc_html( $grabwp_tenancy_new_path ) . '</code>'
			);
			?>
			<br>
			<?php esc_html_e( '3. Reactivate the plugin', 'grabwp-tenancy' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( 'general' === $grabwp_tenancy_active_tab ) : ?>
		<!-- ============================================================ -->
		<!-- TAB: Plugin General                                          -->
		<!-- ============================================================ -->



		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Environment Checks', 'grabwp-tenancy' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Quick health-check of all critical plugin components.', 'grabwp-tenancy' ); ?></p>

		<?php
		// =====================================================================
		// 1. wp-config.php Loader
		// =====================================================================
		?>
		<div class="grabwp-env-card" style="margin-top: 16px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
				<strong><?php esc_html_e( '1. wp-config.php Loader', 'grabwp-tenancy' ); ?></strong>
				<?php if ( $grabwp_tenancy_loader_is_active ) : ?>
					<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Active', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Not loaded', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! $grabwp_tenancy_loader_is_active ) : ?>
			<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
				<?php esc_html_e( 'This line loads the tenant detection script before WordPress boots. Without it, domain/path routing cannot identify which tenant is being accessed.', 'grabwp-tenancy' ); ?>
			</p>
			<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
				<?php
				printf(
					/* translators: %1$s: file path, %2$s: stop-editing marker */
					esc_html__( 'File: %1$s — place before %2$s', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_tenancy_wp_config_path ) . '</code>',
					'<code>/* That\'s all, stop editing! */</code>'
				);
				?>
			</p>
			<div class="grabwp-manual-code">
				<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"><?php echo esc_html( GrabWP_Tenancy_Installer::get_loader_snippet() ); ?></pre>
				<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
					<button type="button" class="button button-small grabwp-copy-code-btn">
						<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
					</button>
					<?php if ( ! $grabwp_tenancy_loader_is_active && $grabwp_tenancy_wp_config_writable && $grabwp_tenancy_stop_editing_marker ) : ?>
						<button type="button" class="button button-small button-primary grabwp-fix-btn"
							data-fix-action="grabwp_install_loader"
							data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_install_loader' ) ); ?>">
							<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
						</button>
					<?php elseif ( ! $grabwp_tenancy_loader_is_active && ! $grabwp_tenancy_wp_config_writable ) : ?>
						<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'wp-config.php is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
					<?php elseif ( ! $grabwp_tenancy_loader_is_active && ! $grabwp_tenancy_stop_editing_marker ) : ?>
						<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Stop-editing marker not found — manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<?php
		// =====================================================================
		// 2. MU-Plugin
		// =====================================================================
		?>
		<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
				<strong><?php esc_html_e( '2. MU-Plugin', 'grabwp-tenancy' ); ?></strong>
				<?php if ( $grabwp_tenancy_mu_plugin_exists && $grabwp_tenancy_mu_content_valid ) : ?>
					<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Installed', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( $grabwp_tenancy_mu_plugin_exists ) : ?>
					<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Outdated', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Missing', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! ( $grabwp_tenancy_mu_plugin_exists && $grabwp_tenancy_mu_content_valid ) ) : ?>
			<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
				<?php esc_html_e( 'WordPress MU-plugins load on every request, even in tenant context. This file ensures GrabWP Tenancy and Pro are available inside tenant admin dashboards for settings sync, plugin/theme hiding, and management features.', 'grabwp-tenancy' ); ?>
			</p>
			<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
				<?php
				printf(
					/* translators: %s: mu-plugin file path */
					esc_html__( 'File: %s', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_tenancy_mu_plugin_path ) . '</code>'
				);
				?>
			</p>
			<div class="grabwp-manual-code">
				<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"><?php echo esc_html( GrabWP_Tenancy_Installer::get_mu_plugin_content() ); ?></pre>
				<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
					<button type="button" class="button button-small grabwp-copy-code-btn">
						<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
					</button>
					<?php if ( ( ! $grabwp_tenancy_mu_plugin_exists || ! $grabwp_tenancy_mu_content_valid ) && ( $grabwp_tenancy_mu_dir_writable || wp_is_writable( dirname( $grabwp_tenancy_mu_plugins_dir ) ) ) ) : ?>
						<button type="button" class="button button-small button-primary grabwp-fix-btn"
							data-fix-action="grabwp_install_mu_plugin"
							data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_install_mu_plugin' ) ); ?>">
							<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
						</button>
					<?php elseif ( ! $grabwp_tenancy_mu_plugin_exists || ! $grabwp_tenancy_mu_content_valid ) : ?>
						<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'mu-plugins directory is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<?php
		// =====================================================================
		// 3. Root .htaccess (Apache/LiteSpeed only)
		// =====================================================================
		if ( $grabwp_tenancy_is_apache ) :
		?>
		<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
				<strong><?php esc_html_e( '3. Root .htaccess Rewrite Rules', 'grabwp-tenancy' ); ?></strong>
				<?php if ( $grabwp_tenancy_root_htaccess_has_grabwp_block && $grabwp_tenancy_root_htaccess_block_positioned && $grabwp_tenancy_root_htaccess_content_valid ) : ?>
					<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Installed', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( $grabwp_tenancy_root_htaccess_has_grabwp_block && ! $grabwp_tenancy_root_htaccess_block_positioned ) : ?>
					<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Wrong position', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( $grabwp_tenancy_root_htaccess_has_grabwp_block && ! $grabwp_tenancy_root_htaccess_content_valid ) : ?>
					<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Invalid content', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( $grabwp_tenancy_root_htaccess_exists ) : ?>
					<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Missing block', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ No .htaccess', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! ( $grabwp_tenancy_root_htaccess_has_grabwp_block && $grabwp_tenancy_root_htaccess_block_positioned && $grabwp_tenancy_root_htaccess_content_valid ) ) : ?>
			<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
				<?php esc_html_e( 'These Apache rewrite rules convert clean URLs like /site/abc123/wp-admin into internal WordPress requests with a ?site=abc123 parameter. This is how path-based tenant routing works.', 'grabwp-tenancy' ); ?>
			</p>
			<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
				<?php
				printf(
					/* translators: %s: .htaccess file path */
					esc_html__( 'File: %s — must appear BEFORE "# BEGIN WordPress"', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_tenancy_root_htaccess_path ) . '</code>'
				);
				?>
			</p>
			<div class="grabwp-manual-code">
				<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"># BEGIN GrabWP Tenancy
&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
RewriteRule ^site/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]
RewriteRule ^site/([a-z0-9]{6})/(.+)$ /$2?site=$1 [QSA,L,NE]
&lt;/IfModule&gt;
# END GrabWP Tenancy</pre>
				<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
					<button type="button" class="button button-small grabwp-copy-code-btn">
						<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
					</button>
					<?php if ( ( ! $grabwp_tenancy_root_htaccess_has_grabwp_block || ! $grabwp_tenancy_root_htaccess_block_positioned || ! $grabwp_tenancy_root_htaccess_content_valid ) && ( $grabwp_tenancy_root_htaccess_writable || ( ! $grabwp_tenancy_root_htaccess_exists && $grabwp_tenancy_root_dir_writable ) ) ) : ?>
						<button type="button" class="button button-small button-primary grabwp-fix-btn"
							data-fix-action="grabwp_fix_root_htaccess"
							data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_fix_component' ) ); ?>">
							<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
						</button>
					<?php elseif ( ! $grabwp_tenancy_root_htaccess_has_grabwp_block || ! $grabwp_tenancy_root_htaccess_block_positioned || ! $grabwp_tenancy_root_htaccess_content_valid ) : ?>
						<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Root directory or .htaccess is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php
		// =====================================================================
		// 4. Data Dir .htaccess (security)
		// =====================================================================
		$grabwp_tenancy_step_num = $grabwp_tenancy_is_apache ? '4' : '3';
		?>
		<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
				<strong><?php echo esc_html( $grabwp_tenancy_step_num . '. ' . __( 'Data Directory .htaccess', 'grabwp-tenancy' ) ); ?></strong>
				<?php if ( $grabwp_tenancy_data_htaccess_exists && $grabwp_tenancy_data_htaccess_has_php_deny ) : ?>
					<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Protected', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( $grabwp_tenancy_data_htaccess_exists ) : ?>
					<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Incomplete', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Missing', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! ( $grabwp_tenancy_data_htaccess_exists && $grabwp_tenancy_data_htaccess_has_php_deny ) ) : ?>
			<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
				<?php esc_html_e( 'Prevents direct HTTP access to PHP files and directory listing in the tenant data directory. This is a security measure to protect tenant configuration files from being accessed via URL.', 'grabwp-tenancy' ); ?>
			</p>
			<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
				<?php
				printf(
					/* translators: %s: .htaccess file path */
					esc_html__( 'File: %s', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_tenancy_data_htaccess_path ) . '</code>'
				);
				?>
			</p>
			<div class="grabwp-manual-code">
				<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"># GrabWP Tenancy Security Protection
# Prevent directory listing
Options -Indexes

# Deny access to PHP files
&lt;FilesMatch "\.php$"&gt;
    &lt;IfModule mod_authz_core.c&gt;
        Require all denied
    &lt;/IfModule&gt;
    &lt;IfModule !mod_authz_core.c&gt;
        Order allow,deny
        Deny from all
    &lt;/IfModule&gt;
&lt;/FilesMatch&gt;</pre>
				<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
					<button type="button" class="button button-small grabwp-copy-code-btn">
						<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
					</button>
					<?php if ( ( ! $grabwp_tenancy_data_htaccess_exists || ! $grabwp_tenancy_data_htaccess_has_php_deny ) && $grabwp_tenancy_base_dir_writable ) : ?>
						<button type="button" class="button button-small button-primary grabwp-fix-btn"
							data-fix-action="grabwp_fix_data_htaccess"
							data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_fix_component' ) ); ?>">
							<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
						</button>
					<?php elseif ( ! $grabwp_tenancy_data_htaccess_exists || ! $grabwp_tenancy_data_htaccess_has_php_deny ) : ?>
						<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Data directory is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<?php
		// =====================================================================
		// 5. index.php Protection
		// =====================================================================
		$grabwp_tenancy_step_num_idx = $grabwp_tenancy_is_apache ? '5' : '4';
		?>
		<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
				<strong><?php echo esc_html( $grabwp_tenancy_step_num_idx . '. ' . __( 'index.php Protection', 'grabwp-tenancy' ) ); ?></strong>
				<?php if ( $grabwp_tenancy_index_protection_exists ) : ?>
					<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Present', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Missing', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! $grabwp_tenancy_index_protection_exists ) : ?>
			<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
				<?php esc_html_e( 'A blank index.php file that prevents web servers from listing tenant directory contents when .htaccess is not supported or misconfigured. Standard WordPress security practice.', 'grabwp-tenancy' ); ?>
			</p>
			<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
				<?php
				printf(
					/* translators: %s: index.php file path */
					esc_html__( 'File: %s', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_tenancy_base_path . '/index.php' ) . '</code>'
				);
				?>
			</p>
			<div class="grabwp-manual-code">
				<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;">&lt;?php
/**
 * GrabWP_Tenancy - Directory Protection
 *
 * @package GrabWP_Tenancy
 */

// Silence is golden.</pre>
				<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
					<button type="button" class="button button-small grabwp-copy-code-btn">
						<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
					</button>
					<?php if ( ! $grabwp_tenancy_index_protection_exists && $grabwp_tenancy_base_dir_writable ) : ?>
						<button type="button" class="button button-small button-primary grabwp-fix-btn"
							data-fix-action="grabwp_fix_index_protection"
							data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_fix_component' ) ); ?>">
							<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
						</button>
					<?php elseif ( ! $grabwp_tenancy_index_protection_exists ) : ?>
						<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Data directory is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		</div>

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'GrabWP Tenancy Information', 'grabwp-tenancy' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Base Plugin Version', 'grabwp-tenancy' ); ?></th>
					<td><?php echo esc_html( $this->plugin->version ); ?></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Pro Plugin', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_is_pro_active ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( 'Active', 'grabwp-tenancy' ); ?></span>
							<?php if ( $grabwp_tenancy_pro_version ) : ?>
								— <?php echo esc_html( $grabwp_tenancy_pro_version ); ?>
							<?php endif; ?>
						<?php else : ?>
							<span style="color: #dc3232;"><?php esc_html_e( 'Inactive', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>


				<tr>
					<th scope="row"><?php esc_html_e( 'Registered Tenants', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php echo esc_html( $grabwp_tenancy_tenant_count ); ?>
						<?php if ( $grabwp_tenancy_tenant_count > 0 ) : ?>
							— <a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>"><?php esc_html_e( 'View all', 'grabwp-tenancy' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>


		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'System Information', 'grabwp-tenancy' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'WordPress Version', 'grabwp-tenancy' ); ?></th>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'PHP Version', 'grabwp-tenancy' ); ?></th>
					<td><?php echo esc_html( PHP_VERSION ); ?></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Database Engine', 'grabwp-tenancy' ); ?></th>
					<td><code><?php echo esc_html( $grabwp_tenancy_db_engine_label ); ?></code></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Web Server', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $grabwp_tenancy_server_software ); ?></code>
						<?php if ( $grabwp_tenancy_is_nginx ) : ?>
							<br><small><?php esc_html_e( 'ℹ .htaccess files are not used by Nginx. Configure tenant routing in your server block instead.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>

				<?php if ( $grabwp_tenancy_is_apache ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'mod_rewrite', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( true === $grabwp_tenancy_mod_rewrite ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Loaded', 'grabwp-tenancy' ); ?></span>
						<?php elseif ( false === $grabwp_tenancy_mod_rewrite ) : ?>
							<span style="color: #dc3232;"><?php esc_html_e( '✗ Not loaded', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Path routing (/site/id) requires mod_rewrite. Query string routing (?site=id) will be used as fallback.', 'grabwp-tenancy' ); ?></small>
						<?php else : ?>
							<span style="color: #999;"><?php esc_html_e( '— Cannot detect (apache_get_modules unavailable)', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'WordPress Multisite', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_is_multisite ) : ?>
							<span style="color: #ff8c00;"><?php esc_html_e( '⚠ Yes — GrabWP Tenancy is not designed for Multisite', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'No', 'grabwp-tenancy' ); ?>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'WP Debug Mode', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_wp_debug ) : ?>
							<span style="color: #ff8c00;"><?php esc_html_e( 'Enabled', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'Disabled', 'grabwp-tenancy' ); ?>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Data Dir Writable', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_base_dir_writable ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Yes', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3232;"><?php esc_html_e( '✗ No', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Plugin needs write access to create tenant directories and manage configuration files.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'MU-Plugins Dir Writable', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_mu_dir_writable ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Yes', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #ff8c00;"><?php esc_html_e( '⚠ No', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Auto-install of MU-plugin will not work. Manual installation required.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<?php elseif ( 'base' === $grabwp_tenancy_active_tab ) : ?>
		<!-- ============================================================ -->
		<!-- TAB: Base Plugin Status                                      -->
		<!-- ============================================================ -->

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'File Structure', 'grabwp-tenancy' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Base Directory', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $grabwp_tenancy_base_path ); ?></code>
						<?php if ( is_dir( $grabwp_tenancy_base_path ) ) : ?>
							<br><span style="color: #46b450;"><?php esc_html_e( '✓ Directory exists', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<br><span style="color: #dc3232;"><?php esc_html_e( '✗ Directory does not exist', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
						<?php if ( $grabwp_tenancy_path_status['using_old'] ) : ?>
							<br><span style="color: #ff8c00;"><?php esc_html_e( '⚠ Using legacy path structure', 'grabwp-tenancy' ); ?></span>
						<?php elseif ( $grabwp_tenancy_path_status['is_custom'] ) : ?>
							<br><span style="color: #0073aa;"><?php esc_html_e( 'ℹ Using custom path configuration', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Tenant Mappings File', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $grabwp_tenancy_mappings_file ); ?></code>
						<?php if ( file_exists( $grabwp_tenancy_mappings_file ) ) : ?>
							<br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists and is readable', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<br><span style="color: #dc3232;"><?php esc_html_e( '✗ File does not exist', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Settings File', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $grabwp_tenancy_settings_file ); ?></code>
						<?php if ( file_exists( $grabwp_tenancy_settings_file ) ) : ?>
							<br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<br><span style="color: #ff8c00;"><?php esc_html_e( '⚠ Not created yet (using defaults)', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Tenant Uploads Pattern', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $grabwp_tenancy_base_path . '/{tenant_id}/uploads' ); ?></code>
					</td>
				</tr>
			</table>
		</div>


		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Tenant Capabilities (Settings)', 'grabwp-tenancy' ); ?></h3>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to settings page */
					esc_html__( 'These settings are configured on the %s page.', 'grabwp-tenancy' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=grabwp-tenancy-settings' ) ) . '">' . esc_html__( 'Settings', 'grabwp-tenancy' ) . '</a>'
				);
				?>
			</p>

			<table class="form-table">
				<?php
				$grabwp_tenancy_capability_settings = $grabwp_tenancy_settings_inst->get_all();
				$grabwp_tenancy_capability_labels   = array(
					'disallow_file_mods'     => __( 'Disallow File Mods', 'grabwp-tenancy' ),
					'disallow_file_edit'     => __( 'Disallow File Edit', 'grabwp-tenancy' ),
					'hide_plugin_management' => __( 'Hide Plugin Management', 'grabwp-tenancy' ),
					'hide_theme_management'  => __( 'Hide Theme Management', 'grabwp-tenancy' ),
					'hide_grabwp_plugins'    => __( 'Hide GrabWP Plugins', 'grabwp-tenancy' ),
				);
				foreach ( $grabwp_tenancy_capability_labels as $grabwp_tenancy_key => $grabwp_tenancy_label ) :
					$grabwp_tenancy_value = isset( $grabwp_tenancy_capability_settings[ $grabwp_tenancy_key ] ) ? $grabwp_tenancy_capability_settings[ $grabwp_tenancy_key ] : false;
					?>
				<tr>
					<th scope="row"><?php echo esc_html( $grabwp_tenancy_label ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_value ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Enabled', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #999;"><?php esc_html_e( '— Disabled', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<?php elseif ( 'pro' === $grabwp_tenancy_active_tab ) : ?>
		<!-- ============================================================ -->
		<!-- TAB: Pro Plugin Status                                       -->
		<!-- ============================================================ -->

		<?php if ( ! $grabwp_tenancy_is_pro_active ) : ?>

		<div class="grabwp-tenancy-form" style="text-align: center; padding: 40px 20px;">
			<h3><?php esc_html_e( 'GrabWP Tenancy Pro', 'grabwp-tenancy' ); ?></h3>
			<p style="font-size: 14px; color: #666; max-width: 500px; margin: 10px auto;">
				<?php esc_html_e( 'Upgrade to GrabWP Tenancy Pro for advanced features including complete content isolation, separate databases per tenant, and enhanced management capabilities.', 'grabwp-tenancy' ); ?>
			</p>
			<p>
				<a href="https://grabwp.com/pro" target="_blank" class="button button-primary button-hero">
					<?php esc_html_e( 'Upgrade to Pro', 'grabwp-tenancy' ); ?>
				</a>
			</p>
		</div>

		<?php else : ?>

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Pro Plugin Information', 'grabwp-tenancy' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Pro Version', 'grabwp-tenancy' ); ?></th>
					<td><?php echo esc_html( $grabwp_tenancy_pro_version ); ?></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'grabwp-tenancy' ); ?></th>
					<td><span style="color: #46b450;"><?php esc_html_e( '✓ Active', 'grabwp-tenancy' ); ?></span></td>
				</tr>
			</table>
		</div>

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Content Isolation (Default Config)', 'grabwp-tenancy' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Default content isolation settings applied when creating new tenants.', 'grabwp-tenancy' ); ?></p>

			<table class="form-table">
				<?php
				$grabwp_tenancy_isolation_defaults = isset( $grabwp_tenancy_pro_default_config['content_isolation'] ) ? $grabwp_tenancy_pro_default_config['content_isolation'] : array();
				$grabwp_tenancy_isolation_labels   = array(
					'isolate_content' => __( 'Content Isolation', 'grabwp-tenancy' ),
					'isolate_themes'  => __( 'Theme Isolation', 'grabwp-tenancy' ),
					'isolate_plugins' => __( 'Plugin Isolation', 'grabwp-tenancy' ),
					'isolate_uploads' => __( 'Upload Isolation', 'grabwp-tenancy' ),
				);
				foreach ( $grabwp_tenancy_isolation_labels as $grabwp_tenancy_key => $grabwp_tenancy_label ) :
					$grabwp_tenancy_value = isset( $grabwp_tenancy_isolation_defaults[ $grabwp_tenancy_key ] ) ? $grabwp_tenancy_isolation_defaults[ $grabwp_tenancy_key ] : false;
					?>
				<tr>
					<th scope="row"><?php echo esc_html( $grabwp_tenancy_label ); ?></th>
					<td>
						<?php if ( $grabwp_tenancy_value ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Isolated', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #999;"><?php esc_html_e( '— Shared', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Database (Default Config)', 'grabwp-tenancy' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Default database configuration for new tenants.', 'grabwp-tenancy' ); ?></p>

			<table class="form-table">
				<?php
				$grabwp_tenancy_db_defaults = isset( $grabwp_tenancy_pro_default_config['database'] ) ? $grabwp_tenancy_pro_default_config['database'] : array();
				$grabwp_tenancy_db_type     = isset( $grabwp_tenancy_db_defaults['database_type'] ) ? $grabwp_tenancy_db_defaults['database_type'] : 'shared';
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Database Type', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( 'mysql_isolated' === $grabwp_tenancy_db_type ) : ?>
							<code><?php esc_html_e( 'Isolated MySQL Database', 'grabwp-tenancy' ); ?></code>
						<?php elseif ( 'sqlite_isolated' === $grabwp_tenancy_db_type ) : ?>
							<code><?php esc_html_e( 'Isolated SQLite Database', 'grabwp-tenancy' ); ?></code>
						<?php else : ?>
							<code><?php esc_html_e( 'Shared Database (with table prefixes)', 'grabwp-tenancy' ); ?></code>
						<?php endif; ?>
					</td>
				</tr>

				<?php if ( 'mysql_isolated' === $grabwp_tenancy_db_type ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'MySQL Host', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php
						$grabwp_tenancy_mysql_host = isset( $grabwp_tenancy_db_defaults['tenant_mysql_host'] ) ? $grabwp_tenancy_db_defaults['tenant_mysql_host'] : '';
						echo $grabwp_tenancy_mysql_host ? '<code>' . esc_html( $grabwp_tenancy_mysql_host ) . '</code>' : '<span style="color: #999;">—</span>';
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MySQL Database', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php
						$grabwp_tenancy_mysql_db = isset( $grabwp_tenancy_db_defaults['tenant_mysql_database'] ) ? $grabwp_tenancy_db_defaults['tenant_mysql_database'] : '';
						echo $grabwp_tenancy_mysql_db ? '<code>' . esc_html( $grabwp_tenancy_mysql_db ) . '</code>' : '<span style="color: #999;">—</span>';
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MySQL Username', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php
						$grabwp_tenancy_mysql_user = isset( $grabwp_tenancy_db_defaults['tenant_mysql_username'] ) ? $grabwp_tenancy_db_defaults['tenant_mysql_username'] : '';
						echo $grabwp_tenancy_mysql_user ? '<code>' . esc_html( $grabwp_tenancy_mysql_user ) . '</code>' : '<span style="color: #999;">—</span>';
						?>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<?php endif; // End pro active check. ?>

		<?php endif; // End tab check. ?>

	</div>
</div>
