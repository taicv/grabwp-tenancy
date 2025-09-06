/**
 * GrabWP Tenancy - Admin JavaScript
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

(function () {
	'use strict';

	// Wait for DOM to be ready
	document.addEventListener(
		'DOMContentLoaded',
		function () {
			initDomainManagement();
			initCopyToClipboard();
		}
	);

	/**
	 * Initialize domain management functionality
	 */
	function initDomainManagement() {
		// Handle create tenant page
		if (document.querySelector( '.grabwp-domain-inputs' )) {
			initCreateTenantDomainManagement();
		}

		// Handle edit tenant page
		if (document.querySelector( '.grabwp-edit-domain-inputs' )) {
			initEditTenantDomainManagement();
		}
	}

	/**
	 * Initialize domain management for create tenant page
	 */
	function initCreateTenantDomainManagement() {
		// Simple event delegation for dynamic elements
		document.addEventListener(
			'click',
			function (e) {
				if (e.target.classList.contains( 'grabwp-add-domain' )) {
					addCreateDomainInput();
				} else if (e.target.classList.contains( 'grabwp-remove-domain' )) {
					e.target.closest( '.grabwp-domain-input' ).remove();
				}
			}
		);
	}

	/**
	 * Initialize domain management for edit tenant page
	 */
	function initEditTenantDomainManagement() {
		// Simple event delegation for dynamic elements
		document.addEventListener(
			'click',
			function (e) {
				if (e.target.classList.contains( 'grabwp-add-edit-domain' )) {
					addEditDomainInput();
				} else if (e.target.classList.contains( 'grabwp-remove-edit-domain' )) {
					e.target.closest( '.grabwp-edit-domain-input' ).remove();
				}
			}
		);
	}

	/**
	 * Add new domain input for create tenant page
	 */
	function addCreateDomainInput() {
		var inputHtml = '<div class="grabwp-domain-input">' +
			'<input type="text" name="domains[]" placeholder="' + grabwpTenancyAdmin.enterDomainPlaceholder + '" style="width: 300px;" />' +
			'<button type="button" class="button grabwp-remove-domain" style="margin-left: 10px;">' + grabwpTenancyAdmin.removeText + '</button>' +
			'</div>';
		document.querySelector( '.grabwp-domain-inputs' ).insertAdjacentHTML( 'beforeend', inputHtml );
	}

	/**
	 * Add new domain input for edit tenant page
	 */
	function addEditDomainInput() {
		var inputHtml = '<div class="grabwp-edit-domain-input">' +
			'<input type="text" name="domains[]" placeholder="' + grabwpTenancyAdmin.enterDomainPlaceholder + '" style="width: 300px;" />' +
			'<button type="button" class="button grabwp-remove-edit-domain" style="margin-left: 10px;">' + grabwpTenancyAdmin.removeText + '</button>' +
			'</div>';
		document.querySelector( '.grabwp-edit-domain-inputs' ).insertAdjacentHTML( 'beforeend', inputHtml );
	}

	/**
	 * Initialize copy to clipboard functionality for admin notices
	 */
	function initCopyToClipboard() {
		var btn = document.getElementById( 'grabwp-copy-btn' );
		var ta  = document.getElementById( 'grabwp-load-textarea' );

		if (btn && ta) {
			btn.addEventListener(
				'click',
				function () {
					ta.style.display = 'block';
					ta.select();
					try {
						var successful = document.execCommand( 'copy' );
						if (successful) {
							btn.innerText = 'Copied!';
							setTimeout(
								function () {
									btn.innerText = 'Copy to Clipboard';
								},
								1500
							);
						}
					} catch (e) {
						// Copy failed, but don't show error to user
					}
					ta.style.display = 'none';
				}
			);
		}
	}

})();
