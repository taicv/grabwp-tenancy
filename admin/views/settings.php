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

    <div class="grabwp-tenancy-content">
        <div class="grabwp-tenancy-form">
            <h3><?php esc_html_e( 'System Information', 'grabwp-tenancy' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Plugin Version', 'grabwp-tenancy' ); ?></th>
                    <td><?php echo esc_html( $this->plugin->version ); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current Tenant', 'grabwp-tenancy' ); ?></th>
                    <td>
                        <?php if ( $this->plugin->is_tenant() ) : ?>
                            <strong><?php echo esc_html( $this->plugin->get_tenant_id() ); ?></strong>
                            (<?php esc_html_e( 'Tenant Site', 'grabwp-tenancy' ); ?>)
                        <?php else : ?>
                            <em><?php esc_html_e( 'Main Site', 'grabwp-tenancy' ); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Pro Plugin Status', 'grabwp-tenancy' ); ?></th>
                    <td>
                        <?php if ( defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) && GRABWP_TENANCY_PRO_ACTIVE ) : ?>
                            <span style="color: #46b450;"><?php esc_html_e( 'Active', 'grabwp-tenancy' ); ?></span>
                        <?php else : ?>
                            <span style="color: #dc3232;"><?php esc_html_e( 'Inactive', 'grabwp-tenancy' ); ?></span>
                            <br>
                            <small><?php esc_html_e( 'Upgrade to GrabWP Tenancy Pro for advanced features.', 'grabwp-tenancy' ); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="grabwp-tenancy-form">
            <h3><?php esc_html_e( 'File Structure', 'grabwp-tenancy' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tenant Mappings File', 'grabwp-tenancy' ); ?></th>
                    <td>
                        <code><?php echo esc_html( WP_CONTENT_DIR . '/grabwp/tenants.php' ); ?></code>
                        <?php if ( file_exists( WP_CONTENT_DIR . '/grabwp/tenants.php' ) ) : ?>
                            <br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists and is readable', 'grabwp-tenancy' ); ?></span>
                        <?php else : ?>
                            <br><span style="color: #dc3232;"><?php esc_html_e( '✗ File does not exist', 'grabwp-tenancy' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tenant Uploads Directory', 'grabwp-tenancy' ); ?></th>
                    <td><code><?php echo esc_html( WP_CONTENT_DIR . '/grabwp/{tenant_id}/uploads' ); ?></code></td>
                </tr>
            </table>
        </div>

        <div class="grabwp-tenancy-form">
            <h3><?php esc_html_e( 'Database Configuration', 'grabwp-tenancy' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Database Type', 'grabwp-tenancy' ); ?></th>
                    <td><?php esc_html_e( 'Shared MySQL with tenant prefixes', 'grabwp-tenancy' ); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Table Prefix Pattern', 'grabwp-tenancy' ); ?></th>
                    <td><code>{tenant_id}_</code></td>
                </tr>
                
                <?php if ( $this->plugin->is_tenant() ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current Table Prefix', 'grabwp-tenancy' ); ?></th>
                    <td><code><?php echo esc_html( $this->plugin->get_tenant_id() . '_' ); ?></code></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="grabwp-tenancy-form">
            <h3><?php esc_html_e( 'Content Isolation', 'grabwp-tenancy' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Upload Isolation', 'grabwp-tenancy' ); ?></th>
                    <td><?php esc_html_e( 'Each tenant has isolated upload directories', 'grabwp-tenancy' ); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Theme & Plugin Isolation', 'grabwp-tenancy' ); ?></th>
                    <td>
                        <?php esc_html_e( 'Shared themes and plugins across all tenants', 'grabwp-tenancy' ); ?>
                        <br><small><?php esc_html_e( 'Upgrade to Pro for complete content isolation', 'grabwp-tenancy' ); ?></small>
                    </td>
                </tr>
            </table>
        </div>

        <div class="grabwp-tenancy-form">
            <h3><?php esc_html_e( 'Domain Routing', 'grabwp-tenancy' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current Domain', 'grabwp-tenancy' ); ?></th>
                    <td><code><?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? 'Unknown' ) ) ); ?></code></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Main Domain', 'grabwp-tenancy' ); ?></th>
                    <td><code><?php echo esc_html( defined( 'WP_SITEURL' ) ? wp_parse_url( WP_SITEURL, PHP_URL_HOST ) : 'Unknown' ); ?></code></td>
                </tr>
            </table>
        </div>

        <div class="grabwp-tenancy-form">
            <h3><?php esc_html_e( 'System Requirements', 'grabwp-tenancy' ); ?></h3>
            
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
                    <th scope="row"><?php esc_html_e( 'Required PHP Version', 'grabwp-tenancy' ); ?></th>
                    <td>7.4+</td>
                </tr>
            </table>
        </div>
    </div>
</div> 