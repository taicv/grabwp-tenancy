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
			initMuPluginInstall();
			initLoaderInstall();
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
		// Loader copy button
		bindCopyButton( 'grabwp-copy-btn', 'grabwp-load-textarea' );
		// MU-plugin copy button
		bindCopyButton( 'grabwp-copy-mu-btn', 'grabwp-mu-textarea' );
	}

	/**
	 * Bind a copy-to-clipboard button to a hidden textarea
	 *
	 * @param {string} btnId    Button element ID
	 * @param {string} textareaId Textarea element ID
	 */
	function bindCopyButton( btnId, textareaId ) {
		var btn = document.getElementById( btnId );
		var ta  = document.getElementById( textareaId );

		if ( btn && ta ) {
			btn.addEventListener(
				'click',
				function () {
					ta.style.display = 'block';
					ta.select();
					try {
						var successful = document.execCommand( 'copy' );
						if ( successful ) {
							btn.innerText = 'Copied!';
							setTimeout(
								function () {
									btn.innerText = 'Copy to Clipboard';
								},
								1500
							);
						}
					} catch ( e ) {
						// Copy failed, but don't show error to user
					}
					ta.style.display = 'none';
				}
			);
		}
	}

	/**
	 * Initialize MU-Plugin install button handler
	 *
	 * @since 1.2.0
	 */
	function initMuPluginInstall() {
		var btn = document.getElementById( 'grabwp-install-mu-btn' );
		var status = document.getElementById( 'grabwp-mu-status' );

		if ( ! btn || typeof grabwpTenancyAdmin === 'undefined' ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = 'Installing…';
			status.textContent = '';

			var data = new FormData();
			data.append( 'action', 'grabwp_install_mu_plugin' );
			data.append( '_ajax_nonce', grabwpTenancyAdmin.muPluginNonce );

			fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						status.style.color = 'green';
						status.textContent = '✓ ' + ( res.data || 'MU-Plugin installed successfully.' );
						var notice = document.getElementById( 'grabwp-mu-plugin-notice' );
						if ( notice ) {
							setTimeout( function () { notice.style.display = 'none'; }, 2000 );
						}
					} else {
						status.style.color = 'red';
						status.textContent = '✗ ' + ( res.data || 'Installation failed.' );
						btn.disabled = false;
						btn.textContent = 'Install MU-Plugin';
					}
				} )
				.catch( function () {
					status.style.color = 'red';
					status.textContent = '✗ Network error. Please try again.';
					btn.disabled = false;
					btn.textContent = 'Install MU-Plugin';
				} );
		} );
	}

	/**
	 * Initialize wp-config.php loader auto-install button handler
	 *
	 * @since 1.2.0
	 */
	function initLoaderInstall() {
		var btn = document.getElementById( 'grabwp-install-loader-btn' );
		var status = document.getElementById( 'grabwp-loader-status' );

		if ( ! btn || typeof grabwpTenancyAdmin === 'undefined' ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = 'Installing…';
			status.textContent = '';

			var data = new FormData();
			data.append( 'action', 'grabwp_install_loader' );
			data.append( '_ajax_nonce', grabwpTenancyAdmin.loaderNonce );

			fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						status.style.color = 'green';
						status.textContent = '✓ ' + ( res.data || 'Loader installed successfully.' );
						var notice = document.getElementById( 'grabwp-loader-notice' );
						if ( notice ) {
							setTimeout( function () { notice.style.display = 'none'; }, 2000 );
						}
					} else {
						status.style.color = 'red';
						status.textContent = '✗ ' + ( res.data || 'Installation failed.' );
						btn.disabled = false;
						btn.textContent = 'Auto Install to wp-config.php';
					}
				} )
				.catch( function () {
					status.style.color = 'red';
					status.textContent = '✗ Network error. Please try again.';
					btn.disabled = false;
					btn.textContent = 'Auto Install to wp-config.php';
				} );
		} );
	}

	/**
	 * Confirm tenant deletion by requiring tenant ID input
	 *
	 * @param {string} tenantId The tenant ID to confirm
	 * @return {boolean} True if deletion should proceed, false otherwise
	 */
	window.grabwpTenancyConfirmDelete = function(tenantId) {
		var message = grabwpTenancyAdmin.confirmMessage + ' ' + tenantId;
		var userInput = prompt(message);
		
		if (userInput === null) {
			// User clicked Cancel
			return false;
		}
		
		if (userInput === tenantId) {
			// Correct ID entered, proceed with deletion
			return true;
		} else {
			// Wrong ID entered
			alert(grabwpTenancyAdmin.incorrectIdMessage);
			return false;
		}
	};

})();
