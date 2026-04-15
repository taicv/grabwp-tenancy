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
	// Clone source passthrough: if coming from clone page, show info and redirect back after creation.
	$grabwp_clone_source = isset( $_GET['clone_source'] ) ? sanitize_key( wp_unslash( $_GET['clone_source'] ) ) : '';
	?>

	<?php if ( $grabwp_clone_source ) : ?>
		<div class="notice notice-info inline" style="margin-bottom: 15px;">
			<p>
				<?php
				$grabwp_clone_source_label = ( defined( 'GRABWP_MAINSITE_ID' ) && GRABWP_MAINSITE_ID === $grabwp_clone_source )
					? __( 'Main Site', 'grabwp-tenancy' )
					: $grabwp_clone_source;
				printf(
					/* translators: %s: source tenant ID or "Main Site" */
					esc_html__( 'After creating this tenant, you will be redirected to clone %s into it.', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_clone_source_label ) . '</code>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

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
			<?php if ( $grabwp_clone_source ) : ?>
				<input type="hidden" name="clone_source" value="<?php echo esc_attr( $grabwp_clone_source ); ?>" />
			<?php endif; ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Domain Setup', 'grabwp-tenancy' ); ?></th>
					<td>
						<fieldset>
							<div style="margin-bottom: 16px;">
								<label style="display: block; margin-bottom: 8px;">
									<input type="radio" name="domain_option" value="has_domain" checked />
									<?php esc_html_e( 'I have a domain', 'grabwp-tenancy' ); ?>
								</label>
								<!-- Domain input section (shown when "I have a domain" selected) -->
								<div id="grabwp-domain-section" style="margin: 4px 0 0 24px;">
									<div class="grabwp-domain-inputs">
										<div class="grabwp-domain-input">
											<input type="text" name="domains[]" placeholder="<?php esc_attr_e( 'Enter domain (e.g. mysite.com)', 'grabwp-tenancy' ); ?>" style="width: auto; max-width: 500px;" />
											<button type="button" class="button grabwp-clear-domain" style="margin-left: 10px;"><?php esc_html_e( 'Clear', 'grabwp-tenancy' ); ?></button>
											<button type="button" class="button grabwp-remove-domain" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'grabwp-tenancy' ); ?></button>
										</div>
									</div>
									
									<p class="description"><?php esc_html_e( 'Enter without http:// or www (e.g. mysite.com, blog.mysite.com)', 'grabwp-tenancy' ); ?></p>
								</div>
							</div>
							<div>
								<label style="display: block; margin-bottom: 8px;">
									<input type="radio" name="domain_option" value="map_later" />
									<?php esc_html_e( "I'll set up a domain later", 'grabwp-tenancy' ); ?>
								</label>
								<!-- Path-only info (shown when "I'll set up a domain later" selected) -->
								<div id="grabwp-no-domain-section" class="grabwp-path-url-info" style="margin: 4px 0 0 24px; display: none;">
									<p>
										<strong><?php esc_html_e( 'Your site will be accessible at:', 'grabwp-tenancy' ); ?></strong><br />
										<code><?php echo esc_html( site_url( '/site/{tenant-id}/' ) ); ?></code>
									</p>
									<p class="description"><?php esc_html_e( 'You can add a domain anytime from the tenant edit page.', 'grabwp-tenancy' ); ?></p>
								</div>
							</div>
						</fieldset>
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
					<?php
					echo $grabwp_clone_source
						? esc_html__( 'Create Tenant & Clone', 'grabwp-tenancy' )
						: esc_html__( 'Create Tenant', 'grabwp-tenancy' );
					?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>" class="button" style="margin-left: 10px;">
					<?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?>
				</a>
			</p>
		</form>
	</div>
</div>
