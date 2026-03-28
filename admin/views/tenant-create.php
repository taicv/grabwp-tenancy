<?php
/**
 * GrabWP Tenancy - Create Tenant Admin Page Template
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
	<h1><?php esc_html_e( 'Add New Tenant', 'grabwp-tenancy' ); ?></h1>
	<p><?php esc_html_e( 'Create a new tenant. A path-based URL will be assigned automatically.', 'grabwp-tenancy' ); ?></p>

	<?php
	// Check for error parameter with nonce verification
	$grabwp_tenancy_error_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( isset( $_GET['error'] ) && wp_verify_nonce( $grabwp_tenancy_error_nonce, 'grabwp_tenancy_error' ) ) :
		?>
		<?php
		$grabwp_tenancy_error_message = get_transient( 'grabwp_tenancy_error' );
		if ( $grabwp_tenancy_error_message ) :
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $grabwp_tenancy_error_message ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div>
		<form method="post" class="grabwp-tenancy-form">
			<?php wp_nonce_field( 'grabwp_tenancy_create' ); ?>
			<input type="hidden" name="action" value="create_tenant" />
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Domain Setup', 'grabwp-tenancy' ); ?></th>
					<td>
						<fieldset>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="domain_option" value="has_domain" checked />
								<?php esc_html_e( 'I have a domain', 'grabwp-tenancy' ); ?>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="domain_option" value="map_later" />
								<?php esc_html_e( "I'll set up a domain later", 'grabwp-tenancy' ); ?>
							</label>
						</fieldset>

						<!-- Domain input section (shown when "I have a domain" selected) -->
						<div id="grabwp-domain-section" style="margin-top: 10px;">
							<div class="grabwp-domain-inputs">
								<div class="grabwp-domain-input">
									<input type="text" name="domains[]" placeholder="<?php esc_attr_e( 'Enter domain (e.g. mysite.com)', 'grabwp-tenancy' ); ?>" style="width: 300px;" />
									<button type="button" class="button grabwp-remove-domain" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'grabwp-tenancy' ); ?></button>
								</div>
							</div>
							<button type="button" class="button grabwp-add-domain" style="margin-top: 10px;">
								<?php esc_html_e( 'Add New Domain', 'grabwp-tenancy' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Enter without http:// or www (e.g. mysite.com, blog.mysite.com)', 'grabwp-tenancy' ); ?></p>
						</div>

						<!-- Path-only info (shown when "I'll set up a domain later" selected) -->
						<div id="grabwp-no-domain-section" class="grabwp-path-url-info" style="margin-top: 10px; display: none;">
							<p>
								<strong><?php esc_html_e( 'Your site will be accessible at:', 'grabwp-tenancy' ); ?></strong><br />
								<code><?php echo esc_html( site_url( '/site/{tenant-id}/' ) ); ?></code>
							</p>
							<p class="description"><?php esc_html_e( 'You can add a domain anytime from the tenant edit page.', 'grabwp-tenancy' ); ?></p>
						</div>
					</td>
				</tr>
				<?php
				/**
				 * Add extra fields to tenant creation form
				 *
				 * @since 1.0.4
				 */
				do_action( 'grabwp_tenancy_create_form_fields' );
				?>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Create Tenant', 'grabwp-tenancy' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>" class="button" style="margin-left: 10px;">
					<?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?>
				</a>
			</p>
		</form>
	</div>
</div>
