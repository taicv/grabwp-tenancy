/**
 * GrabWP Tenancy - Admin JavaScript
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

(function () {
	'use strict';

	var COPY_FEEDBACK_MS = 1500;

	document.addEventListener( 'DOMContentLoaded', function () {
		initDomainManagement();
		initCopyToClipboard();
		initAjaxInstallButton( {
			btnId:        'grabwp-install-mu-btn',
			statusId:     'grabwp-mu-status',
			noticeId:     'grabwp-mu-plugin-notice',
			action:       'grabwp_install_mu_plugin',
			nonceKey:     'muPluginNonce',
			defaultLabel: 'Install MU-Plugin'
		} );
		initAjaxInstallButton( {
			btnId:        'grabwp-install-loader-btn',
			statusId:     'grabwp-loader-status',
			noticeId:     'grabwp-loader-notice',
			action:       'grabwp_install_loader',
			nonceKey:     'loaderNonce',
			defaultLabel: 'Auto Install to wp-config.php'
		} );
		initPathUrlCopy();
		initDomainOptionRadio();
		initStatusPageFix();
		initCodeBlockCopy();
	} );

	/* ---------------------------------------------------------------
	 * Shared utilities
	 * ------------------------------------------------------------- */

	/**
	 * Copy text to the clipboard with a legacy fallback.
	 *
	 * @param {string}   text     Text to copy.
	 * @param {Function} callback Invoked after a successful copy.
	 */
	function copyToClipboard( text, callback ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( callback );
			return;
		}

		var ta       = document.createElement( 'textarea' );
		ta.value     = text;
		ta.style.position = 'fixed';
		ta.style.opacity  = '0';
		document.body.appendChild( ta );
		ta.select();
		document.execCommand( 'copy' );
		document.body.removeChild( ta );
		callback();
	}

	/**
	 * Flash "Copied!" feedback on a button then restore original text.
	 *
	 * @param {HTMLElement} btn The button element.
	 */
	function flashCopyFeedback( btn ) {
		var original     = btn.textContent;
		btn.textContent  = 'Copied!';
		setTimeout( function () {
			btn.textContent = original;
		}, COPY_FEEDBACK_MS );
	}

	/**
	 * Show an inline error / success message next to a button.
	 *
	 * @param {HTMLElement} btn     The trigger button.
	 * @param {string}      text    Message text.
	 * @param {string}      color   CSS color value.
	 */
	function showInlineMessage( btn, text, color ) {
		var msg = btn.parentNode.querySelector( '.grabwp-fix-message' );
		if ( ! msg ) {
			msg           = document.createElement( 'span' );
			msg.className = 'grabwp-fix-message';
			msg.style.marginLeft = '10px';
			msg.style.fontSize   = '12px';
			btn.after( msg );
		}
		msg.style.color = color;
		msg.textContent = text;
	}

	/* ---------------------------------------------------------------
	 * Domain management (create & edit pages)
	 * ------------------------------------------------------------- */

	function initDomainManagement() {
		initDomainSection( {
			containerSelector: '.grabwp-domain-inputs',
			inputClass:        'grabwp-domain-input',
			addBtnClass:       'grabwp-add-domain',
			removeBtnClass:    'grabwp-remove-domain'
		} );

		initDomainSection( {
			containerSelector: '.grabwp-edit-domain-inputs',
			inputClass:        'grabwp-edit-domain-input',
			addBtnClass:       'grabwp-add-edit-domain',
			removeBtnClass:    'grabwp-remove-edit-domain',
			emptyFallback:     true
		} );
	}

	/**
	 * Set up add/remove domain rows for a given container.
	 *
	 * @param {Object} cfg
	 * @param {string} cfg.containerSelector CSS selector for the wrapper.
	 * @param {string} cfg.inputClass        Class applied to each domain row.
	 * @param {string} cfg.addBtnClass       Class of the "Add" button.
	 * @param {string} cfg.removeBtnClass    Class of the "Remove" button.
	 * @param {boolean} [cfg.emptyFallback]  Inject placeholder domain on submit when all inputs empty.
	 */
	function initDomainSection( cfg ) {
		var container = document.querySelector( cfg.containerSelector );
		if ( ! container ) {
			return;
		}

		document.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( cfg.addBtnClass ) ) {
				addDomainInput( cfg.containerSelector, cfg.inputClass, cfg.removeBtnClass );
			} else if ( e.target.classList.contains( cfg.removeBtnClass ) ) {
				e.target.closest( '.' + cfg.inputClass ).remove();
			}
		} );

		if ( cfg.emptyFallback ) {
			var form = container.closest( 'form' );
			if ( form ) {
				form.addEventListener( 'submit', function () {
					var inputs   = container.querySelectorAll( 'input[name="domains[]"]' );
					var hasValue = false;
					for ( var i = 0; i < inputs.length; i++ ) {
						if ( inputs[ i ].value.trim() !== '' ) {
							hasValue = true;
							break;
						}
					}
					if ( ! hasValue ) {
						var hidden   = document.createElement( 'input' );
						hidden.type  = 'hidden';
						hidden.name  = 'domains[]';
						hidden.value = 'nodomain.local';
						this.appendChild( hidden );
					}
				} );
			}
		}
	}

	/**
	 * Append a new domain input row.
	 *
	 * @param {string} containerSelector Wrapper CSS selector.
	 * @param {string} inputClass        Row class.
	 * @param {string} removeBtnClass    Remove-button class.
	 */
	function addDomainInput( containerSelector, inputClass, removeBtnClass ) {
		var html = '<div class="' + inputClass + '">' +
			'<input type="text" name="domains[]" placeholder="' + grabwpTenancyAdmin.enterDomainPlaceholder + '" style="width: 300px;" />' +
			'<button type="button" class="button ' + removeBtnClass + '" style="margin-left: 10px;">' + grabwpTenancyAdmin.removeText + '</button>' +
			'</div>';
		document.querySelector( containerSelector ).insertAdjacentHTML( 'beforeend', html );
	}

	/* ---------------------------------------------------------------
	 * Copy-to-clipboard (admin notice textareas)
	 * ------------------------------------------------------------- */

	function initCopyToClipboard() {
		bindCopyButton( 'grabwp-copy-btn', 'grabwp-load-textarea' );
		bindCopyButton( 'grabwp-copy-mu-btn', 'grabwp-mu-textarea' );
	}

	/**
	 * Bind a copy-to-clipboard button to a hidden textarea.
	 *
	 * @param {string} btnId      Button element ID.
	 * @param {string} textareaId Textarea element ID.
	 */
	function bindCopyButton( btnId, textareaId ) {
		var btn = document.getElementById( btnId );
		var ta  = document.getElementById( textareaId );

		if ( ! btn || ! ta ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			ta.style.display = 'block';
			ta.select();
			copyToClipboard( ta.value, function () {
				flashCopyFeedback( btn );
			} );
			ta.style.display = 'none';
		} );
	}

	/* ---------------------------------------------------------------
	 * AJAX install button (MU-Plugin & Loader share the same pattern)
	 * ------------------------------------------------------------- */

	/**
	 * Wire up an AJAX "install" button.
	 *
	 * @param {Object} cfg
	 * @param {string} cfg.btnId        Button element ID.
	 * @param {string} cfg.statusId     Status span element ID.
	 * @param {string} cfg.noticeId     Notice wrapper element ID.
	 * @param {string} cfg.action       WordPress AJAX action name.
	 * @param {string} cfg.nonceKey     Key in grabwpTenancyAdmin holding the nonce.
	 * @param {string} cfg.defaultLabel Default button text for reset on failure.
	 */
	function initAjaxInstallButton( cfg ) {
		var btn    = document.getElementById( cfg.btnId );
		var status = document.getElementById( cfg.statusId );

		if ( ! btn || typeof grabwpTenancyAdmin === 'undefined' ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled       = true;
			btn.textContent    = 'Installing…';
			status.textContent = '';

			var data = new FormData();
			data.append( 'action', cfg.action );
			data.append( '_ajax_nonce', grabwpTenancyAdmin[ cfg.nonceKey ] );

			fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						status.style.color = 'green';
						status.textContent = '✓ ' + ( res.data || 'Installed successfully.' );
						var notice = document.getElementById( cfg.noticeId );
						if ( notice ) {
							setTimeout( function () { notice.style.display = 'none'; }, 2000 );
						}
					} else {
						status.style.color = 'red';
						status.textContent = '✗ ' + ( res.data || 'Installation failed.' );
						btn.disabled    = false;
						btn.textContent = cfg.defaultLabel;
					}
				} )
				.catch( function () {
					status.style.color = 'red';
					status.textContent = '✗ Network error. Please try again.';
					btn.disabled    = false;
					btn.textContent = cfg.defaultLabel;
				} );
		} );
	}

	/* ---------------------------------------------------------------
	 * Domain option radio toggle (create tenant page)
	 * ------------------------------------------------------------- */

	function initDomainOptionRadio() {
		var radios = document.querySelectorAll( 'input[name="domain_option"]' );
		if ( ! radios.length ) {
			return;
		}

		var domainSection   = document.getElementById( 'grabwp-domain-section' );
		var noDomainSection = document.getElementById( 'grabwp-no-domain-section' );

		function toggle() {
			var checked = document.querySelector( 'input[name="domain_option"]:checked' );
			if ( ! checked ) {
				return;
			}
			var val = checked.value;
			if ( domainSection ) {
				domainSection.style.display = val === 'has_domain' ? '' : 'none';
			}
			if ( noDomainSection ) {
				noDomainSection.style.display = val === 'map_later' ? '' : 'none';
			}
		}

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', toggle );
		}
		toggle();

		var form = document.querySelector( '.grabwp-tenancy-form' );
		if ( form ) {
			form.addEventListener( 'submit', function () {
				var checked = document.querySelector( 'input[name="domain_option"]:checked' );
				if ( checked && checked.value === 'map_later' && domainSection ) {
					var inputs = domainSection.querySelectorAll( 'input[name="domains[]"]' );
					for ( var j = 0; j < inputs.length; j++ ) {
						inputs[ j ].parentNode.removeChild( inputs[ j ] );
					}
				}
			} );
		}
	}

	/* ---------------------------------------------------------------
	 * Path / URL copy buttons
	 * ------------------------------------------------------------- */

	function initPathUrlCopy() {
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'grabwp-copy-path-url' ) ) {
				return;
			}
			var value = e.target.getAttribute( 'data-copy-value' );
			if ( ! value ) {
				return;
			}
			copyToClipboard( value, function () {
				flashCopyFeedback( e.target );
			} );
		} );
	}

	/* ---------------------------------------------------------------
	 * Status page "Fix Now" buttons
	 * ------------------------------------------------------------- */

	/**
	 * @since 1.3.0
	 */
	function initStatusPageFix() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.grabwp-fix-btn' );
			if ( ! btn || typeof grabwpTenancyAdmin === 'undefined' ) {
				return;
			}

			var action = btn.getAttribute( 'data-fix-action' );
			var nonce  = btn.getAttribute( 'data-fix-nonce' );
			if ( ! action || ! nonce ) {
				return;
			}

			var card         = btn.closest( '.grabwp-env-card' );
			var originalText = btn.textContent;
			btn.disabled     = true;
			btn.textContent  = 'Fixing…';

			var data = new FormData();
			data.append( 'action', action );
			data.append( '_ajax_nonce', nonce );

			fetch( ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						var msg = '✓ ' + ( res.data || 'Fixed' );
						btn.textContent        = msg;
						btn.style.backgroundColor = '#46b450';
						btn.style.borderColor     = '#46b450';
						btn.style.color           = '#fff';
						if ( card ) {
							var errorSpan = card.querySelector( '.grabwp-fix-error' );
							if ( errorSpan ) {
								errorSpan.innerHTML = '<span style="color: #46b450;">' + msg + '</span>';
							}
						}
						setTimeout( function () { window.location.reload(); }, COPY_FEEDBACK_MS );
					} else {
						btn.disabled    = false;
						btn.textContent = originalText;
						var failStr     = typeof res.data === 'string' ? res.data : 'Fix failed.';
						showInlineMessage( btn, '✗ ' + failStr, '#dc3232' );
					}
				} )
				.catch( function () {
					btn.disabled    = false;
					btn.textContent = originalText;
					showInlineMessage( btn, '✗ Network error.', '#dc3232' );
				} );
		} );
	}

	/* ---------------------------------------------------------------
	 * Code block copy (status page manual instructions)
	 * ------------------------------------------------------------- */

	/**
	 * @since 1.3.0
	 */
	function initCodeBlockCopy() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.grabwp-copy-code-btn' );
			if ( ! btn ) {
				return;
			}

			var container = btn.closest( '.grabwp-manual-code' );
			if ( ! container ) {
				return;
			}

			var pre = container.querySelector( 'pre' );
			if ( ! pre ) {
				return;
			}

			copyToClipboard( pre.textContent, function () {
				flashCopyFeedback( btn );
			} );
		} );
	}

	/* ---------------------------------------------------------------
	 * Delete confirmation prompt
	 * ------------------------------------------------------------- */

	window.grabwpTenancyConfirmDelete = function ( tenantId ) {
		var userInput = prompt( grabwpTenancyAdmin.confirmMessage + ' ' + tenantId );

		if ( userInput === null ) {
			return false;
		}

		if ( userInput === tenantId ) {
			return true;
		}

		alert( grabwpTenancyAdmin.incorrectIdMessage );
		return false;
	};

})();
