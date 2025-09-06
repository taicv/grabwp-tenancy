<?php
/**
 * GrabWP Tenancy - Tenants Admin Page Template
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
	<h1 class="wp-heading-inline"><?php esc_html_e( 'GrabWP Tenancy', 'grabwp-tenancy' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-create' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'grabwp-tenancy' ); ?>
	</a>
	<hr class="wp-header-end">
	
	<p><?php esc_html_e( 'Manage your multi-tenant WordPress sites.', 'grabwp-tenancy' ); ?></p>

	<!-- Tenants List -->
	<div style="margin-top: 30px;">
		<?php if ( empty( $tenants ) ) : ?>
			<div style="text-align: center; padding: 40px 20px; color: #666; background: #fff; border: 1px solid #ccd0d4;">
				<h3><?php esc_html_e( 'No Tenants Found', 'grabwp-tenancy' ); ?></h3>
				<p><?php esc_html_e( 'Create your first tenant to get started with multi-tenancy.', 'grabwp-tenancy' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-create' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Your First Tenant', 'grabwp-tenancy' ); ?>
				</a>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Tenant ID', 'grabwp-tenancy' ); ?></th>
						<th><?php esc_html_e( 'Status', 'grabwp-tenancy' ); ?></th>
						<th><?php esc_html_e( 'Domains', 'grabwp-tenancy' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'grabwp-tenancy' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tenants as $tenant ) : ?>
						<tr>
							<td>
								<code><?php echo esc_html( $tenant->get_id() ); ?></code>
							</td>
							<td>
								<span class="grabwp-status-<?php echo esc_attr( $tenant->get_status() ); ?>">
									<?php echo esc_html( ucfirst( $tenant->get_status() ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $tenant->get_domains() ) ) : ?>
									<?php foreach ( $tenant->get_domains() as $domain ) : ?>
										<code style="margin: 2px; padding: 2px 4px; background: #f0f0f0;"><?php echo esc_html( $domain ); ?></code>
									<?php endforeach; ?>
								<?php else : ?>
									<em><?php esc_html_e( 'No domains assigned', 'grabwp-tenancy' ); ?></em>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $tenant->get_domains() ) ) : ?>
									<a href="<?php echo esc_url( ( is_ssl() ? 'https://' : 'http://' ) . $tenant->get_domains()[0] ); ?>" target="_blank" class="button button-primary">
										<?php esc_html_e( 'Visit Site', 'grabwp-tenancy' ); ?>
									</a>
									<?php
									$admin_url = null;
									if ( method_exists( $tenant, 'get_admin_access_url' ) ) {
										$admin_url = $tenant->get_admin_access_url();
									}
									if ( $admin_url ) :
										?>
										<a href="<?php echo esc_url( $admin_url ); ?>" target="_blank" class="button">
											<?php esc_html_e( 'Visit Admin', 'grabwp-tenancy' ); ?>
										</a>
									<?php else : ?>
										<a href="<?php echo esc_url( ( is_ssl() ? 'https://' : 'http://' ) . $tenant->get_domains()[0] . '/wp-admin/' ); ?>" target="_blank" class="button">
											<?php esc_html_e( 'Visit Admin', 'grabwp-tenancy' ); ?>
										</a>
									<?php endif; ?>
								<?php endif; ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-edit&tenant_id=' . urlencode( $tenant->get_id() ) . '&_wpnonce=' . urlencode( wp_create_nonce( 'grabwp_tenancy_edit' ) ) ) ); ?>" class="button">
									<?php esc_html_e( 'Edit', 'grabwp-tenancy' ); ?>
								</a>
								<form method="post" style="display: inline;">
									<?php wp_nonce_field( 'grabwp_tenancy_delete' ); ?>
									<input type="hidden" name="action" value="delete_tenant" />
									<input type="hidden" name="tenant_id" value="<?php echo esc_attr( $tenant->get_id() ); ?>" />
									<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this tenant?', 'grabwp-tenancy' ) ); ?>')">
										<?php esc_html_e( 'Delete', 'grabwp-tenancy' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div> 
