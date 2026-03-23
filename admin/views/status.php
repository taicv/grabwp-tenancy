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
$path_status   = GrabWP_Tenancy_Path_Manager::get_path_status();
$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
$base_path     = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
$settings_inst = GrabWP_Tenancy_Settings::get_instance();
$settings_file = $settings_inst->get_settings_file_path();

// Count tenants from mappings file.
$tenant_count = 0;
if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
	$tenant_mappings = array();
	ob_start();
	include $mappings_file;
	ob_end_clean();
	if ( is_array( $tenant_mappings ) ) {
		$tenant_count = count( $tenant_mappings );
	}
}

// Detect database engine.
if ( defined( 'DB_ENGINE' ) ) {
	$db_engine = DB_ENGINE;
} elseif ( defined( 'DATABASE_TYPE' ) ) {
	$db_engine = DATABASE_TYPE;
} else {
	$db_engine = 'mysql';
}
$db_engine_label = ucfirst( $db_engine );

// Get current table prefix.
global $table_prefix;
$main_prefix = defined( 'GRABWP_TENANCY_ORIGINAL_PREFIX' ) ? GRABWP_TENANCY_ORIGINAL_PREFIX : $table_prefix;

// Pro plugin status.
$is_pro_active = defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) && GRABWP_TENANCY_PRO_ACTIVE;
$pro_version   = defined( 'GRABWP_TENANCY_PRO_VERSION' ) ? GRABWP_TENANCY_PRO_VERSION : '';

// Pro default config (if pro is active).
$pro_default_config = array();
if ( $is_pro_active && class_exists( 'GrabWP_Tenancy_Pro_Config' ) ) {
	$pro_config_inst    = GrabWP_Tenancy_Pro_Config::get_instance();
	$pro_default_config = $pro_config_inst->get_default_config();
}

// Determine active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'GrabWP Tenancy Status', 'grabwp-tenancy' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper grabwp-tenancy-tabs">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'general' ) ); ?>"
		   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Plugin General', 'grabwp-tenancy' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'base' ) ); ?>"
		   class="nav-tab <?php echo 'base' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Base Plugin', 'grabwp-tenancy' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'pro' ) ); ?>"
		   class="nav-tab <?php echo 'pro' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Pro Plugin', 'grabwp-tenancy' ); ?>
		</a>
	</nav>

	<div class="grabwp-tenancy-content">

		<?php
		// Show migration warning on all tabs if using legacy path structure.
		if ( $path_status['using_old'] ) :
			$upload_dir   = wp_upload_dir();
			$new_path     = $upload_dir['basedir'] . '/grabwp-tenancy';
			$current_path = $path_status['current_base'];
			?>
		<div class="notice notice-warning" style="margin: 15px 0;">
			<p><strong><?php esc_html_e( 'GrabWP Tenancy:', 'grabwp-tenancy' ); ?></strong>
			<?php esc_html_e( 'You\'re using a legacy path structure. To comply with WordPress standards:', 'grabwp-tenancy' ); ?></p>
			<p><?php esc_html_e( '1. Deactivate the plugin', 'grabwp-tenancy' ); ?><br>
			<?php
			printf(
				/* translators: %1$s: current folder name, %2$s: new folder path */
				esc_html__( '2. Rename and move the entire %1$s folder to %2$s', 'grabwp-tenancy' ),
				'<code>' . esc_html( basename( $current_path ) ) . '</code>',
				'<code>' . esc_html( $new_path ) . '</code>'
			);
			?>
			<br>
			<?php esc_html_e( '3. Reactivate the plugin', 'grabwp-tenancy' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( 'general' === $active_tab ) : ?>
		<!-- ============================================================ -->
		<!-- TAB: Plugin General                                          -->
		<!-- ============================================================ -->

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
						<?php if ( $is_pro_active ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( 'Active', 'grabwp-tenancy' ); ?></span>
							<?php if ( $pro_version ) : ?>
								— <?php echo esc_html( $pro_version ); ?>
							<?php endif; ?>
						<?php else : ?>
							<span style="color: #dc3232;"><?php esc_html_e( 'Inactive', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Drop-in Status', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( defined( 'GRABWP_TENANCY_LOADED' ) && GRABWP_TENANCY_LOADED ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ load.php is active', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3232;"><?php esc_html_e( '✗ load.php is not loaded', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Ensure load.php is included in wp-config.php before WordPress loads.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Registered Tenants', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php echo esc_html( $tenant_count ); ?>
						<?php if ( $tenant_count > 0 ) : ?>
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
					<td><code><?php echo esc_html( $db_engine_label ); ?></code></td>
				</tr>
			</table>
		</div>

		<?php elseif ( 'base' === $active_tab ) : ?>
		<!-- ============================================================ -->
		<!-- TAB: Base Plugin Status                                      -->
		<!-- ============================================================ -->

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'File Structure', 'grabwp-tenancy' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Base Directory', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $base_path ); ?></code>
						<?php if ( is_dir( $base_path ) ) : ?>
							<br><span style="color: #46b450;"><?php esc_html_e( '✓ Directory exists', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<br><span style="color: #dc3232;"><?php esc_html_e( '✗ Directory does not exist', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
						<?php if ( $path_status['using_old'] ) : ?>
							<br><span style="color: #ff8c00;"><?php esc_html_e( '⚠ Using legacy path structure', 'grabwp-tenancy' ); ?></span>
						<?php elseif ( $path_status['is_custom'] ) : ?>
							<br><span style="color: #0073aa;"><?php esc_html_e( 'ℹ Using custom path configuration', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Tenant Mappings File', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $mappings_file ); ?></code>
						<?php if ( file_exists( $mappings_file ) ) : ?>
							<br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists and is readable', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<br><span style="color: #dc3232;"><?php esc_html_e( '✗ File does not exist', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Settings File', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $settings_file ); ?></code>
						<?php if ( file_exists( $settings_file ) ) : ?>
							<br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<br><span style="color: #ff8c00;"><?php esc_html_e( '⚠ Not created yet (using defaults)', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Tenant Uploads Pattern', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $base_path . '/{tenant_id}/uploads' ); ?></code>
					</td>
				</tr>
			</table>
		</div>

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Database Configuration', 'grabwp-tenancy' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Database Engine', 'grabwp-tenancy' ); ?></th>
					<td><code><?php echo esc_html( $db_engine_label ); ?></code></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Main Site Prefix', 'grabwp-tenancy' ); ?></th>
					<td><code><?php echo esc_html( $main_prefix ); ?></code></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Tenant Prefix Pattern', 'grabwp-tenancy' ); ?></th>
					<td><code>{tenant_id}_</code></td>
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
				$capability_settings = $settings_inst->get_all();
				$capability_labels   = array(
					'disallow_file_mods'     => __( 'Disallow File Mods', 'grabwp-tenancy' ),
					'disallow_file_edit'     => __( 'Disallow File Edit', 'grabwp-tenancy' ),
					'hide_plugin_management' => __( 'Hide Plugin Management', 'grabwp-tenancy' ),
					'hide_theme_management'  => __( 'Hide Theme Management', 'grabwp-tenancy' ),
					'hide_grabwp_plugins'    => __( 'Hide GrabWP Plugins', 'grabwp-tenancy' ),
				);
				foreach ( $capability_labels as $key => $label ) :
					$value = isset( $capability_settings[ $key ] ) ? $capability_settings[ $key ] : false;
					?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<?php if ( $value ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Enabled', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #999;"><?php esc_html_e( '— Disabled', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<?php elseif ( 'pro' === $active_tab ) : ?>
		<!-- ============================================================ -->
		<!-- TAB: Pro Plugin Status                                       -->
		<!-- ============================================================ -->

		<?php if ( ! $is_pro_active ) : ?>

		<div class="grabwp-tenancy-form" style="text-align: center; padding: 40px 20px;">
			<h3><?php esc_html_e( 'GrabWP Tenancy Pro', 'grabwp-tenancy' ); ?></h3>
			<p style="font-size: 14px; color: #666; max-width: 500px; margin: 10px auto;">
				<?php esc_html_e( 'Upgrade to GrabWP Tenancy Pro for advanced features including complete content isolation, separate databases per tenant, and enhanced management capabilities.', 'grabwp-tenancy' ); ?>
			</p>
			<p>
				<a href="https://grabwp.com/tenancy-pro" target="_blank" class="button button-primary button-hero">
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
					<td><?php echo esc_html( $pro_version ); ?></td>
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
				$isolation_defaults = isset( $pro_default_config['content_isolation'] ) ? $pro_default_config['content_isolation'] : array();
				$isolation_labels   = array(
					'isolate_content' => __( 'Content Isolation', 'grabwp-tenancy' ),
					'isolate_themes'  => __( 'Theme Isolation', 'grabwp-tenancy' ),
					'isolate_plugins' => __( 'Plugin Isolation', 'grabwp-tenancy' ),
					'isolate_uploads' => __( 'Upload Isolation', 'grabwp-tenancy' ),
				);
				foreach ( $isolation_labels as $key => $label ) :
					$value = isset( $isolation_defaults[ $key ] ) ? $isolation_defaults[ $key ] : false;
					?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<?php if ( $value ) : ?>
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
				$db_defaults = isset( $pro_default_config['database'] ) ? $pro_default_config['database'] : array();
				$db_type     = isset( $db_defaults['database_type'] ) ? $db_defaults['database_type'] : 'shared';
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Database Type', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( 'mysql_isolated' === $db_type ) : ?>
							<code><?php esc_html_e( 'Isolated MySQL Database', 'grabwp-tenancy' ); ?></code>
						<?php elseif ( 'sqlite_isolated' === $db_type ) : ?>
							<code><?php esc_html_e( 'Isolated SQLite Database', 'grabwp-tenancy' ); ?></code>
						<?php else : ?>
							<code><?php esc_html_e( 'Shared Database (with table prefixes)', 'grabwp-tenancy' ); ?></code>
						<?php endif; ?>
					</td>
				</tr>

				<?php if ( 'mysql_isolated' === $db_type ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'MySQL Host', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php
						$mysql_host = isset( $db_defaults['tenant_mysql_host'] ) ? $db_defaults['tenant_mysql_host'] : '';
						echo $mysql_host ? '<code>' . esc_html( $mysql_host ) . '</code>' : '<span style="color: #999;">—</span>';
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MySQL Database', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php
						$mysql_db = isset( $db_defaults['tenant_mysql_database'] ) ? $db_defaults['tenant_mysql_database'] : '';
						echo $mysql_db ? '<code>' . esc_html( $mysql_db ) . '</code>' : '<span style="color: #999;">—</span>';
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MySQL Username', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php
						$mysql_user = isset( $db_defaults['tenant_mysql_username'] ) ? $db_defaults['tenant_mysql_username'] : '';
						echo $mysql_user ? '<code>' . esc_html( $mysql_user ) . '</code>' : '<span style="color: #999;">—</span>';
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
