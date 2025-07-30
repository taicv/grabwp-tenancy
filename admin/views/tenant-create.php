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
    <p><?php esc_html_e( 'Create a new tenant with domain mappings.', 'grabwp-tenancy' ); ?></p>

    <?php 
    // Check for error parameter with nonce verification
    $error_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( isset( $_GET['error'] ) && wp_verify_nonce( $error_nonce, 'grabwp_tenancy_error' ) ) : ?>
        <?php 
        $error_message = get_transient( 'grabwp_tenancy_error' );
        if ( $error_message ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( $error_message ); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
        <form method="post">
            <?php wp_nonce_field( 'grabwp_tenancy_create' ); ?>
            <input type="hidden" name="action" value="create_tenant" />
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Domains', 'grabwp-tenancy' ); ?></th>
                    <td>
                        <div class="grabwp-domain-inputs">
                            <div class="grabwp-domain-input">
                                <input type="text" name="domains[]" placeholder="<?php esc_attr_e( 'Enter domain (e.g., tenant1.grabwp.local)', 'grabwp-tenancy' ); ?>" style="width: 300px;" required />
                                <button type="button" class="button grabwp-remove-domain" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'grabwp-tenancy' ); ?></button>
                            </div>
                        </div>
                        <button type="button" class="button grabwp-add-domain" style="margin-top: 10px;">
                            <?php esc_html_e( 'Add Domain', 'grabwp-tenancy' ); ?>
                        </button>
                        <p class="description"><?php esc_html_e( 'Enter at least one domain for this tenant. You can add multiple domains.', 'grabwp-tenancy' ); ?></p>
                        <p class="description"><?php esc_html_e( 'Valid format: example.com, subdomain.example.com (no http:// or www)', 'grabwp-tenancy' ); ?></p>
                    </td>
                </tr>
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

<script type="text/javascript">
// Simple event delegation for dynamic elements
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('grabwp-add-domain')) {
        var inputHtml = '<div class="grabwp-domain-input">' +
            '<input type="text" name="domains[]" placeholder="<?php echo esc_js( __( 'Enter domain (e.g., tenant1.grabwp.local)', 'grabwp-tenancy' ) ); ?>" style="width: 300px;" />' +
            '<button type="button" class="button grabwp-remove-domain" style="margin-left: 10px;"><?php echo esc_js( __( 'Remove', 'grabwp-tenancy' ) ); ?></button>' +
            '</div>';
        document.querySelector('.grabwp-domain-inputs').insertAdjacentHTML('beforeend', inputHtml);
    } else if (e.target.classList.contains('grabwp-remove-domain')) {
        e.target.closest('.grabwp-domain-input').remove();
    }
});
</script> 