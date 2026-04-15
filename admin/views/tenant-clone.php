<?php
/**
 * Tenant Clone page view — base plugin (shared MySQL only).
 *
 * Variables available (set by GrabWP_Tenancy_Clone_Admin::clone_page):
 *   $tenant_id        (string) Source tenant ID.
 *   $source_db_type   (string) Always 'shared' for base plugin.
 *   $clone_init_nonce (string) Nonce for clone init AJAX.
 *   $clone_step_nonce (string) Nonce for clone step AJAX.
 *   $targets_nonce    (string) Nonce for eligible targets AJAX.
 *   $is_mainsite      (bool)   Whether source is the main site.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Clone steps (6 steps — no symlink step in base).
$clone_steps = [
	1 => __( 'Validating source & target tenants', 'grabwp-tenancy' ),
	2 => __( 'Exporting source database', 'grabwp-tenancy' ),
	3 => __( 'Importing database into target tenant', 'grabwp-tenancy' ),
	4 => __( 'Copying uploads', 'grabwp-tenancy' ),
	5 => __( 'Updating site URLs', 'grabwp-tenancy' ),
	6 => __( 'Cleaning up', 'grabwp-tenancy' ),
];

// Auto-select target from URL param (after clone-to-new-site redirect).
$auto_target = isset( $_GET['target_tenant_id'] ) ? sanitize_key( wp_unslash( $_GET['target_tenant_id'] ) ) : '';

// URL for "Clone to new site" option.
$create_new_url = add_query_arg( [
	'page'         => 'grabwp-tenancy-create',
	'clone_source' => $tenant_id,
], admin_url( 'admin.php' ) );
?>
<div class="wrap grabwp-clone-page">
	<h1>
		<?php if ( $is_mainsite ) : ?>
			<?php esc_html_e( 'Clone Main Site', 'grabwp-tenancy' ); ?>
		<?php else : ?>
			<?php
			printf( esc_html__( 'Clone Tenant: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $tenant_id ) . '</code>' );
			?>
		<?php endif; ?>
	</h1>

	<p class="description">
		<?php
		printf( esc_html__( 'Source database type: %s', 'grabwp-tenancy' ), '<strong>' . esc_html__( 'Shared MySQL', 'grabwp-tenancy' ) . '</strong>' );
		?>
	</p>

	<!-- Clone target choice (hidden when auto_target is set from redirect) -->
	<div id="grabwp-clone-choice" <?php echo $auto_target ? 'style="display:none;"' : ''; ?>>
		<h3><?php esc_html_e( 'Choose clone target:', 'grabwp-tenancy' ); ?></h3>
		<div style="display:flex; gap:16px; margin:16px 0;">
			<button type="button" id="clone-to-existing-btn" class="button" style="display:flex; flex-direction:column; align-items:center; padding:16px 24px;">
				<span class="dashicons dashicons-admin-page" style="font-size:24px; width:24px; height:24px; margin-bottom:6px;"></span>
				<?php esc_html_e( 'Clone to existing site', 'grabwp-tenancy' ); ?>
			</button>
			<a href="<?php echo esc_url( $create_new_url ); ?>" class="button" style="display:flex; flex-direction:column; align-items:center; padding:16px 24px; text-decoration:none;">
				<span class="dashicons dashicons-plus-alt" style="font-size:24px; width:24px; height:24px; margin-bottom:6px;"></span>
				<?php esc_html_e( 'Clone to new site', 'grabwp-tenancy' ); ?>
			</a>
		</div>
	</div>

	<div id="grabwp-clone-notice" style="display:none;" class="notice notice-error inline"><p></p></div>

	<!-- Clone form (hidden until "Clone to existing" is clicked, or shown directly when auto_target set) -->
	<div id="grabwp-clone-form-section" <?php echo $auto_target ? '' : 'style="display:none;"'; ?>>
		<form id="grabwp-clone-form" method="post">
			<input type="hidden" name="source_tenant_id" value="<?php echo esc_attr( $tenant_id ); ?>" />
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clone-target-tenant"><?php esc_html_e( 'Target Tenant', 'grabwp-tenancy' ); ?></label>
					</th>
					<td>
						<select id="clone-target-tenant" name="target_tenant_id" class="regular-text" required>
							<option value=""><?php esc_html_e( '— Loading tenants…', 'grabwp-tenancy' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Select an existing tenant to clone data into. Create the target tenant first if needed.', 'grabwp-tenancy' ); ?></p>

						<div id="clone-target-info" style="margin-top: 10px; display: none;">
							<table style="max-width: 500px;">
								<tr><th><?php esc_html_e( 'Tenant ID', 'grabwp-tenancy' ); ?></th><td id="target-info-id">—</td></tr>
								<tr><th><?php esc_html_e( 'Domains', 'grabwp-tenancy' ); ?></th><td id="target-info-domains">—</td></tr>
								<tr><th><?php esc_html_e( 'Database Type', 'grabwp-tenancy' ); ?></th><td><?php esc_html_e( 'Shared MySQL', 'grabwp-tenancy' ); ?></td></tr>
							</table>
						</div>

						<div class="notice notice-warning inline" style="margin-top: 10px;">
							<p><strong><?php esc_html_e( 'Warning:', 'grabwp-tenancy' ); ?></strong>
							<?php esc_html_e( 'This will overwrite all data in the target tenant (database, uploads). This action cannot be undone.', 'grabwp-tenancy' ); ?></p>
						</div>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" id="clone-submit-btn" class="button button-primary" disabled>
					<?php esc_html_e( 'Clone Tenant', 'grabwp-tenancy' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>" class="button" style="margin-left: 10px;">
					<?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?>
				</a>
			</p>
		</form>
	</div>

	<!-- Step progress list -->
	<ul id="grabwp-clone-steps" class="grabwp-clone-step-list" style="display:none;">
		<?php foreach ( $clone_steps as $num => $label ) : ?>
			<li data-step="<?php echo esc_attr( $num ); ?>" class="grabwp-clone-step grabwp-clone-step--pending">
				<span class="grabwp-clone-step-icon dashicons dashicons-marker"></span>
				<span class="grabwp-clone-step-label"><?php echo esc_html( $label ); ?></span>
				<span class="grabwp-clone-step-msg"></span>
			</li>
		<?php endforeach; ?>
	</ul>

	<!-- Success section -->
	<div id="grabwp-clone-success" style="display:none;">
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'Clone complete!', 'grabwp-tenancy' ); ?></strong>
				<?php esc_html_e( 'The source tenant data has been cloned into the target tenant.', 'grabwp-tenancy' ); ?>
			</p>
		</div>
	</div>

	<p style="margin-top:20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>">&larr; <?php esc_html_e( 'Back to Tenants', 'grabwp-tenancy' ); ?></a>
	</p>
</div>

<style>
.grabwp-clone-step-list { list-style: none; padding: 0; margin: 20px 0; }
.grabwp-clone-step { padding: 8px 12px; margin: 4px 0; border-left: 3px solid #ccc; display: flex; align-items: center; gap: 8px; }
.grabwp-clone-step--pending { border-left-color: #ccc; color: #999; }
.grabwp-clone-step--active { border-left-color: #2271b1; color: #2271b1; }
.grabwp-clone-step--done { border-left-color: #00a32a; color: #333; }
.grabwp-clone-step--error { border-left-color: #d63638; color: #d63638; }
.grabwp-clone-step-msg { font-size: 12px; color: #666; margin-left: auto; }
</style>

<script>
(function($) {
	'use strict';

	var targets = [];
	var autoTarget = '<?php echo esc_js( $auto_target ); ?>';

	// "Clone to existing site" button — reveal the form.
	$('#clone-to-existing-btn').on('click', function() {
		$('#grabwp-clone-choice').hide();
		$('#grabwp-clone-form-section').show();
	});

	// Load eligible targets on page load.
	$.post(ajaxurl, {
		action: 'grabwp_tenancy_clone_eligible_targets',
		source_tenant_id: '<?php echo esc_js( $tenant_id ); ?>',
		nonce: '<?php echo esc_js( $targets_nonce ); ?>'
	}, function(response) {
		var $select = $('#clone-target-tenant');
		$select.empty();

		if (!response.success || !response.data.length) {
			$select.append('<option value=""><?php echo esc_js( __( '— No eligible tenants found —', 'grabwp-tenancy' ) ); ?></option>');
			return;
		}

		targets = response.data;
		$select.append('<option value=""><?php echo esc_js( __( '— Select target tenant —', 'grabwp-tenancy' ) ); ?></option>');
		$.each(targets, function(i, t) {
			var status = t.has_data ? ' ⚠ HAS DATA' : ' ✓ empty';
			var label = t.id + ' (' + (t.domains.join(', ') || 'no domain') + ') — Shared MySQL' + status;
			$select.append('<option value="' + t.id + '">' + label + '</option>');
		});

		// Auto-select target when redirected from tenant creation.
		if (autoTarget) {
			$select.val(autoTarget).trigger('change');
		}
	});

	// Show target info on selection change.
	$(document).on('change', '#clone-target-tenant', function() {
		var tid = $(this).val();
		var $info = $('#clone-target-info');
		var $btn = $('#clone-submit-btn');

		if (!tid) {
			$info.hide();
			$btn.prop('disabled', true);
			return;
		}

		var target = targets.find(function(t) { return t.id === tid; });
		if (target) {
			$('#target-info-id').text(target.id);
			$('#target-info-domains').text(target.domains.join(', ') || 'nodomain.local');
			$info.show();
		}
		$btn.prop('disabled', false);
	});

	// Show error notice.
	function showError(msg) {
		$('#grabwp-clone-notice').show().find('p').text(msg);
	}

	// Run clone steps sequentially.
	function runNextStep(jobId, stepNonce, totalSteps) {
		$.post(ajaxurl, {
			action: 'grabwp_tenancy_clone_step',
			job_id: jobId,
			nonce: stepNonce
		}, function(response) {
			if (!response.success) {
				var step = response.data && response.data.step ? response.data.step : null;
				if (step) {
					$('.grabwp-clone-step[data-step="' + step + '"]')
						.removeClass('grabwp-clone-step--active')
						.addClass('grabwp-clone-step--error')
						.find('.grabwp-clone-step-icon')
						.removeClass('dashicons-marker')
						.addClass('dashicons-no');
				}
				showError(response.data.message || 'Clone step failed.');
				return;
			}

			var d = response.data;
			// Mark current step done.
			$('.grabwp-clone-step[data-step="' + d.step + '"]')
				.removeClass('grabwp-clone-step--active grabwp-clone-step--pending')
				.addClass('grabwp-clone-step--done')
				.find('.grabwp-clone-step-icon')
				.removeClass('dashicons-marker')
				.addClass('dashicons-yes-alt');

			if (d.done) {
				$('#grabwp-clone-success').show();
				return;
			}

			// Mark next step active.
			var nextStep = d.step + 1;
			$('.grabwp-clone-step[data-step="' + nextStep + '"]')
				.removeClass('grabwp-clone-step--pending')
				.addClass('grabwp-clone-step--active')
				.find('.grabwp-clone-step-icon')
				.removeClass('dashicons-marker')
				.addClass('dashicons-update');

			runNextStep(jobId, stepNonce, totalSteps);
		}).fail(function() {
			showError('Network error. Please try again.');
		});
	}

	// Clone form submit.
	$('#grabwp-clone-form').on('submit', function(e) {
		e.preventDefault();

		if (!confirm('<?php echo esc_js( __( 'Are you sure? All data in the target tenant will be overwritten.', 'grabwp-tenancy' ) ); ?>')) {
			return;
		}

		$('#grabwp-clone-notice').hide();
		$('#grabwp-clone-form-section').hide();
		$('#grabwp-clone-steps').show();

		// Mark step 1 as active.
		$('.grabwp-clone-step[data-step="1"]')
			.removeClass('grabwp-clone-step--pending')
			.addClass('grabwp-clone-step--active')
			.find('.grabwp-clone-step-icon')
			.removeClass('dashicons-marker')
			.addClass('dashicons-update');

		$.post(ajaxurl, {
			action: 'grabwp_tenancy_clone_init',
			nonce: '<?php echo esc_js( $clone_init_nonce ); ?>',
			source_tenant_id: '<?php echo esc_js( $tenant_id ); ?>',
			target_tenant_id: $('#clone-target-tenant').val()
		}, function(response) {
			if (!response.success) {
				showError(response.data.message);
				$('#grabwp-clone-form-section').show();
				$('#grabwp-clone-steps').hide();
				return;
			}
			var jobId     = response.data.job_id;
			var stepNonce = '<?php echo esc_js( $clone_step_nonce ); ?>';
			var total     = response.data.total_steps;
			runNextStep(jobId, stepNonce, total);
		}).fail(function() {
			showError('Could not start clone. Please try again.');
			$('#grabwp-clone-form-section').show();
			$('#grabwp-clone-steps').hide();
		});
	});

})(jQuery);
</script>
