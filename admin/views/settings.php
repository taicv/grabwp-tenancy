<?php
/**
 * GrabWP Tenancy - Settings Admin Page Template
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'GrabWP Tenancy Settings', 'grabwp-tenancy' ); ?></h1>
	<p><?php esc_html_e( 'Configure your multi-tenant WordPress setup.', 'grabwp-tenancy' ); ?></p>

	<?php
	// Success notice after saving settings.
	if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message']
		&& isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_tenancy_notice' ) ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'grabwp-tenancy' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="grabwp-tenancy-content">

		<div class="grabwp-tenancy-form">
			<h3><?php esc_html_e( 'Tenant Capabilities', 'grabwp-tenancy' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Control what tenant site administrators are allowed to do.', 'grabwp-tenancy' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'grabwp_tenancy_save_settings' ); ?>
				<input type="hidden" name="action" value="save_settings" />

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Install Plugins & Themes', 'grabwp-tenancy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="disallow_file_mods" value="1" <?php checked( ! empty( $settings['disallow_file_mods'] ) ); ?> />
								<?php esc_html_e( 'Disallow tenant admins to install, update, and delete plugins/themes (DISALLOW_FILE_MODS)', 'grabwp-tenancy' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Edit Plugins & Themes', 'grabwp-tenancy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="disallow_file_edit" value="1" <?php checked( ! empty( $settings['disallow_file_edit'] ) ); ?> />
								<?php esc_html_e( 'Disallow tenant admins to use the built-in plugin/theme file editor (DISALLOW_FILE_EDIT)', 'grabwp-tenancy' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hide Plugin Management', 'grabwp-tenancy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hide_plugin_management" value="1" <?php checked( ! empty( $settings['hide_plugin_management'] ) ); ?> />
								<?php esc_html_e( 'Hide the Plugins menu entirely from tenant admin dashboards', 'grabwp-tenancy' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hide Theme Management', 'grabwp-tenancy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hide_theme_management" value="1" <?php checked( ! empty( $settings['hide_theme_management'] ) ); ?> />
								<?php esc_html_e( 'Hide the Appearance menu entirely from tenant admin dashboards', 'grabwp-tenancy' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hide GrabWP Plugins', 'grabwp-tenancy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hide_grabwp_plugins" value="1" <?php checked( ! empty( $settings['hide_grabwp_plugins'] ) ); ?> />
								<?php esc_html_e( 'Hide GrabWP plugins from the plugin list on tenant sites', 'grabwp-tenancy' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'grabwp-tenancy' ) ); ?>
			</form>
		</div>

	</div>
</div>
