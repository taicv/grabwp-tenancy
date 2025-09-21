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
		<?php
		/**
		 * Before tenant list display
		 *
		 * @since 1.0.4
		 */
		do_action( 'grabwp_tenancy_before_tenant_list' );
		?>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search parameter is read-only operation
		if ( empty( $list_table->items ) && empty( $_REQUEST['s'] ) ) :
		?>
			<div style="text-align: center; padding: 40px 20px; color: #666; background: #fff; border: 1px solid #ccd0d4;">
				<h3><?php esc_html_e( 'No Tenants Found', 'grabwp-tenancy' ); ?></h3>
				<p><?php esc_html_e( 'Create your first tenant to get started with multi-tenancy.', 'grabwp-tenancy' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-create' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Your First Tenant', 'grabwp-tenancy' ); ?>
				</a>
			</div>
		<?php else : ?>
			<?php $list_table->display(); ?>
		<?php endif; ?>
		<?php
		/**
		 * After tenant list display
		 *
		 * @since 1.0.4
		 */
		do_action( 'grabwp_tenancy_after_tenant_list' );
		?>
	</div>
</div> 
