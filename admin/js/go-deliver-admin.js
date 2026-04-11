/**
 * Go Deliver – Admin JavaScript
 *
 * @package Go_Deliver
 */

/* global gdAdmin, jQuery */

( function ( $ ) {
	'use strict';

	var ajaxUrl = gdAdmin.ajaxUrl;
	var nonce   = gdAdmin.nonce;

	// =========================================================================
	// Toast helper
	// =========================================================================

	/**
	 * Show a transient toast notification.
	 *
	 * @param {string} message  Text to display.
	 * @param {string} [type]   'success' | 'error' | 'info'  (default: 'success')
	 * @param {number} [duration] ms before auto-dismiss (default: 3500)
	 */
	function gdToast( message, type, duration ) {
		type     = type     || 'success';
		duration = duration || 3500;

		if ( ! $( '#gd-toast-container' ).length ) {
			$( 'body' ).append( '<div id="gd-toast-container"></div>' );
		}

		var $toast = $( '<div class="gd-toast gd-toast-' + type + '"></div>' )
			.text( message );

		$( '#gd-toast-container' ).append( $toast );

		// Trigger reflow so transition fires.
		$toast[0].offsetHeight; // eslint-disable-line no-unused-expressions
		$toast.addClass( 'gd-toast-show' );

		setTimeout( function () {
			$toast.removeClass( 'gd-toast-show' );
			setTimeout( function () { $toast.remove(); }, 300 );
		}, duration );
	}

	// =========================================================================
	// Tab switching
	// =========================================================================

	$( document ).on( 'click', '.gd-tab-link', function ( e ) {
		e.preventDefault();
		var target = $( this ).data( 'tab' );

		$( '.gd-tab-link' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );

		$( '.gd-tab-panel' ).removeClass( 'is-active' );
		$( '#gd-tab-' + target ).addClass( 'is-active' );

		// Update URL hash without scrolling.
		if ( history.replaceState ) {
			history.replaceState( null, null, '#' + target );
		}
	} );

	// Restore active tab from URL hash on load.
	( function () {
		var hash = window.location.hash.replace( '#', '' );
		if ( hash ) {
			var $link = $( '.gd-tab-link[data-tab="' + hash + '"]' );
			if ( $link.length ) {
				$link.trigger( 'click' );
				return;
			}
		}
		// Default: activate first tab.
		$( '.gd-tab-link:first' ).addClass( 'is-active' );
		$( '.gd-tab-panel:first' ).addClass( 'is-active' );
	}() );

	// =========================================================================
	// Form Builder
	// =========================================================================

	var $fieldList = $( '#gd-field-list' );

	// Init jQuery UI Sortable if present.
	if ( $fieldList.length && $.fn.sortable ) {
		$fieldList.sortable( {
			handle:      '.gd-field-handle',
			placeholder: 'gd-field-row gd-field-placeholder',
			axis:        'y',
			tolerance:   'pointer',
		} );
	}

	// --- Add new field ---
	$( document ).on( 'click', '#gd-add-field-btn', function () {
		var $form  = $( '#gd-new-field-form' );
		var $key   = $form.find( '[name="gd_field_key"]' );
		var $label = $form.find( '[name="gd_field_label"]' );
		var $type  = $form.find( '[name="gd_field_type"]' );

		var key   = $.trim( $key.val() );
		var label = $.trim( $label.val() );
		var type  = $type.val();

		if ( ! key || ! label || ! type ) {
			gdToast( 'Key, Label, and Type are required.', 'error' );
			return;
		}

		var required        = $form.find( '[name="gd_field_required"]' ).is( ':checked' );
		var conditionalOn   = $.trim( $form.find( '[name="gd_field_conditional_on"]' ).val() );
		var conditionalVal  = $.trim( $form.find( '[name="gd_field_conditional_value"]' ).val() );
		var optionsRaw      = $.trim( $form.find( '[name="gd_field_options"]' ).val() );

		var $row = buildFieldRow( {
			key:               key,
			label:             label,
			type:              type,
			required:          required,
			conditional_on:    conditionalOn,
			conditional_value: conditionalVal,
			options:           optionsRaw,
		} );

		$fieldList.append( $row );

		// Reset form.
		$form[0].reset();
		gdToast( 'Field added. Click "Save Order" to persist.' );
	} );

	// Show/hide options textarea based on type.
	$( document ).on( 'change', '[name="gd_field_type"]', function () {
		var needsOptions = [ 'select', 'radio' ].indexOf( $( this ).val() ) !== -1;
		$( this ).closest( 'form, .gd-add-field-form' )
			.find( '.gd-options-row' )
			.toggle( needsOptions );
	} );

	/**
	 * Build a field row DOM element.
	 *
	 * @param {Object} field
	 * @return {jQuery}
	 */
	function buildFieldRow( field ) {
		var requiredBadge = field.required
			? '<span class="gd-required-badge">required</span>'
			: '';

		var condInfo = '';
		if ( field.conditional_on ) {
			condInfo = '<span class="gd-tag">if ' +
				escHtml( field.conditional_on ) + '=' +
				escHtml( field.conditional_value ) + '</span>';
		}

		var dataAttrs = [
			'data-key="'              + escAttr( field.key )               + '"',
			'data-label="'            + escAttr( field.label )             + '"',
			'data-type="'             + escAttr( field.type )              + '"',
			'data-required="'         + ( field.required ? '1' : '0' )    + '"',
			'data-conditional_on="'   + escAttr( field.conditional_on   || '' ) + '"',
			'data-conditional_value="'+ escAttr( field.conditional_value || '' ) + '"',
			'data-options="'          + escAttr( field.options            || '' ) + '"',
		].join( ' ' );

		return $( '<li class="gd-field-row" ' + dataAttrs + '>' +
			'<span class="gd-field-handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<span class="gd-field-info">' +
				'<span class="gd-field-label">' + escHtml( field.label ) + requiredBadge + '</span>' +
				'<span class="gd-field-key">' + escHtml( field.key ) + '</span>' +
			'</span>' +
			'<span class="gd-field-meta">' +
				condInfo +
				'<span class="gd-field-type">' + escHtml( field.type ) + '</span>' +
			'</span>' +
			'<span class="gd-field-actions">' +
				'<button type="button" class="button button-small gd-edit-field-btn">Edit</button>' +
				'<button type="button" class="button button-small gd-delete-field-btn">Delete</button>' +
			'</span>' +
		'</li>' );
	}

	// --- Delete field ---
	$( document ).on( 'click', '.gd-delete-field-btn', function () {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( 'Delete this field? This cannot be undone.' ) ) {
			return;
		}
		$( this ).closest( '.gd-field-row' ).remove();
		gdToast( 'Field removed. Click "Save Order" to persist.' );
	} );

	// --- Edit field inline ---
	$( document ).on( 'click', '.gd-edit-field-btn', function () {
		var $row = $( this ).closest( '.gd-field-row' );

		// Remove any existing edit forms.
		$( '.gd-edit-field-inline' ).remove();

		var key   = $row.data( 'key' );
		var label = $row.data( 'label' );
		var type  = $row.data( 'type' );
		var req   = $row.data( 'required' ) === 1 || $row.data( 'required' ) === '1';
		var condOn  = $row.data( 'conditional_on' )    || '';
		var condVal = $row.data( 'conditional_value' ) || '';
		var opts    = $row.data( 'options' )            || '';

		var typeOptions = [ 'text', 'textarea', 'select', 'checkbox', 'radio', 'number' ]
			.map( function ( t ) {
				return '<option value="' + t + '"' + ( t === type ? ' selected' : '' ) + '>' + t + '</option>';
			} ).join( '' );

		var $editForm = $( '<div class="gd-edit-field-inline">' +
			'<div class="gd-form-row">' +
				'<div class="gd-form-col"><label>Key</label>' +
					'<input type="text" class="regular-text" name="edit_key" value="' + escAttr( key ) + '"></div>' +
				'<div class="gd-form-col"><label>Label</label>' +
					'<input type="text" class="regular-text" name="edit_label" value="' + escAttr( label ) + '"></div>' +
				'<div class="gd-form-col"><label>Type</label>' +
					'<select name="edit_type">' + typeOptions + '</select></div>' +
			'</div>' +
			'<div class="gd-form-row">' +
				'<div class="gd-form-col"><label>Conditional On (field key)</label>' +
					'<input type="text" class="regular-text" name="edit_conditional_on" value="' + escAttr( condOn ) + '"></div>' +
				'<div class="gd-form-col"><label>Conditional Value</label>' +
					'<input type="text" class="regular-text" name="edit_conditional_value" value="' + escAttr( condVal ) + '"></div>' +
				'<div class="gd-form-col gd-options-row"' + ( [ 'select', 'radio' ].indexOf( type ) === -1 ? ' style="display:none"' : '' ) + '><label>Options (comma-separated)</label>' +
					'<textarea name="edit_options">' + escHtml( opts ) + '</textarea></div>' +
			'</div>' +
			'<div class="gd-form-row">' +
				'<div class="gd-form-col"><label><input type="checkbox" name="edit_required"' + ( req ? ' checked' : '' ) + '> Required</label></div>' +
			'</div>' +
			'<button type="button" class="button button-primary gd-save-edit-btn">Save</button> ' +
			'<button type="button" class="button gd-cancel-edit-btn">Cancel</button>' +
		'</div>' );

		$row.after( $editForm );
	} );

	$( document ).on( 'click', '.gd-cancel-edit-btn', function () {
		$( this ).closest( '.gd-edit-field-inline' ).remove();
	} );

	$( document ).on( 'click', '.gd-save-edit-btn', function () {
		var $editForm = $( this ).closest( '.gd-edit-field-inline' );
		var $row      = $editForm.prev( '.gd-field-row' );

		var key   = $.trim( $editForm.find( '[name="edit_key"]' ).val() );
		var label = $.trim( $editForm.find( '[name="edit_label"]' ).val() );
		var type  = $editForm.find( '[name="edit_type"]' ).val();
		var req   = $editForm.find( '[name="edit_required"]' ).is( ':checked' );
		var condOn  = $.trim( $editForm.find( '[name="edit_conditional_on"]' ).val() );
		var condVal = $.trim( $editForm.find( '[name="edit_conditional_value"]' ).val() );
		var opts    = $.trim( $editForm.find( '[name="edit_options"]' ).val() );

		if ( ! key || ! label || ! type ) {
			gdToast( 'Key, Label, and Type are required.', 'error' );
			return;
		}

		$row.data( 'key',               key );
		$row.data( 'label',             label );
		$row.data( 'type',              type );
		$row.data( 'required',          req ? '1' : '0' );
		$row.data( 'conditional_on',    condOn );
		$row.data( 'conditional_value', condVal );
		$row.data( 'options',           opts );

		// Re-render the row contents.
		var requiredBadge = req ? '<span class="gd-required-badge">required</span>' : '';
		var condInfo = condOn
			? '<span class="gd-tag">if ' + escHtml( condOn ) + '=' + escHtml( condVal ) + '</span>'
			: '';

		$row.find( '.gd-field-label' ).html( escHtml( label ) + requiredBadge );
		$row.find( '.gd-field-key' ).text( key );
		$row.find( '.gd-field-type' ).text( type );
		$row.find( '.gd-field-meta' ).html(
			condInfo + '<span class="gd-field-type">' + escHtml( type ) + '</span>'
		);

		$editForm.remove();
		gdToast( 'Field updated. Click "Save Order" to persist.' );
	} );

	// --- Save field order via AJAX ---
	$( document ).on( 'click', '#gd-save-fields-btn', function () {
		var $btn    = $( this );
		var fields  = [];

		$fieldList.find( '.gd-field-row' ).each( function () {
			var $r = $( this );
			var f  = {
				key:               $r.data( 'key' ),
				label:             $r.data( 'label' ),
				type:              $r.data( 'type' ),
				required:          $r.data( 'required' ) === '1' || $r.data( 'required' ) === 1,
				conditional_on:    $r.data( 'conditional_on' )    || '',
				conditional_value: $r.data( 'conditional_value' ) || '',
				options:           $r.data( 'options' )            || '',
			};

			// Convert comma-separated options string to array.
			if ( f.options ) {
				f.options = f.options.split( ',' ).map( function ( s ) { return s.trim(); } );
			} else {
				delete f.options;
			}

			fields.push( f );
		} );

		$btn.prop( 'disabled', true ).append( '<span class="gd-spinner-inline"></span>' );

		$.post( ajaxUrl, {
			action: 'gd_save_form_fields',
			nonce:  nonce,
			fields: JSON.stringify( fields ),
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				gdToast( resp.data.message || 'Fields saved.' );
			} else {
				gdToast( resp.data || 'Failed to save fields.', 'error' );
			}
		} )
		.fail( function () {
			gdToast( 'Network error. Please try again.', 'error' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).find( '.gd-spinner-inline' ).remove();
		} );
	} );

	// =========================================================================
	// Mover profile edit form
	// =========================================================================

	$( document ).on( 'submit', '#gd-mover-edit-form', function ( e ) {
		e.preventDefault();
		var $form   = $( this );
		var userId  = $form.data( 'user-id' );
		var $btn    = $form.find( '#gd-mover-edit-submit' );

		// Collect job_types checkboxes as array.
		var jobTypes = [];
		$form.find( 'input[name="job_types[]"]:checked' ).each( function () {
			jobTypes.push( $( this ).val() );
		} );

		$btn.prop( 'disabled', true ).append( '<span class="gd-spinner-inline"></span>' );

		$.post( ajaxUrl, {
			action:      'gd_admin_update_mover_profile',
			nonce:       nonce,
			user_id:     userId,
			first_name:  $.trim( $form.find( '[name="first_name"]' ).val() ),
			last_name:   $.trim( $form.find( '[name="last_name"]' ).val() ),
			email:       $.trim( $form.find( '[name="email"]' ).val() ),
			phone:       $.trim( $form.find( '[name="phone"]' ).val() ),
			base_suburb: $.trim( $form.find( '[name="base_suburb"]' ).val() ),
			base_lat:    $.trim( $form.find( '[name="base_lat"]' ).val() ),
			base_lng:    $.trim( $form.find( '[name="base_lng"]' ).val() ),
			radius:      $.trim( $form.find( '[name="radius"]' ).val() ),
			job_types:   jobTypes,
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				gdToast( resp.data.message || 'Profile updated.' );
			} else {
				gdToast( ( resp.data && resp.data.message ) || 'Failed to update profile.', 'error' );
			}
		} )
		.fail( function () {
			gdToast( 'Network error.', 'error' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).find( '.gd-spinner-inline' ).remove();
		} );
	} );

	// =========================================================================
	// Mover approval: approve / reject / suspend (AJAX, no reload)
	// =========================================================================

	$( document ).on( 'click', '.gd-action-approve, .gd-action-reject, .gd-action-suspend', function () {
		var $btn   = $( this );
		var userId = $btn.data( 'user-id' );
		var action = $btn.data( 'action' ); // 'gd_approve_mover' etc.
		var msg    = $btn.data( 'confirm' ) || 'Are you sure?';

		// eslint-disable-next-line no-alert
		if ( ! window.confirm( msg ) ) {
			return;
		}

		var reason = '';
		var $reasonWrap = $( '#gd-reason-wrap-' + userId );
		if ( $reasonWrap.length ) {
			reason = $.trim( $reasonWrap.find( 'textarea' ).val() );
		}

		$btn.prop( 'disabled', true ).append( '<span class="gd-spinner-inline"></span>' );

		$.post( ajaxUrl, {
			action:  action,
			nonce:   nonce,
			user_id: userId,
			reason:  reason,
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				gdToast( resp.data.message || 'Action completed.' );

				// Update status badge if present.
				var newStatus = resp.data.status || '';
				if ( newStatus ) {
					var $badge = $( '#gd-mover-status-' + userId );
					$badge.attr( 'class', 'gd-badge gd-badge-' + newStatus ).text( newStatus );
				}

				// Reload row or page after short delay.
				setTimeout( function () { window.location.reload(); }, 1200 );
			} else {
				gdToast( resp.data || 'Action failed.', 'error' );
				$btn.prop( 'disabled', false ).find( '.gd-spinner-inline' ).remove();
			}
		} )
		.fail( function () {
			gdToast( 'Network error.', 'error' );
			$btn.prop( 'disabled', false ).find( '.gd-spinner-inline' ).remove();
		} );
	} );

	// Per-document approve / reject.
	$( document ).on( 'click', '.gd-doc-approve, .gd-doc-reject', function () {
		var $btn   = $( this );
		var docId  = $btn.data( 'doc-id' );
		var status = $btn.data( 'status' ); // 'approved' | 'rejected'

		$btn.prop( 'disabled', true ).append( '<span class="gd-spinner-inline"></span>' );

		$.post( ajaxUrl, {
			action: 'gd_update_document_status',
			nonce:  nonce,
			doc_id: docId,
			status: status,
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				gdToast( 'Document ' + status + '.' );
				var $badge = $btn.closest( 'tr' ).find( '.gd-doc-status' );
				$badge.attr( 'class', 'gd-badge gd-badge-' + status + ' gd-doc-status' ).text( status );
				$btn.siblings( '.gd-doc-approve, .gd-doc-reject' ).addBack().remove();
			} else {
				gdToast( resp.data || 'Failed.', 'error' );
				$btn.prop( 'disabled', false ).find( '.gd-spinner-inline' ).remove();
			}
		} )
		.fail( function () {
			gdToast( 'Network error.', 'error' );
			$btn.prop( 'disabled', false ).find( '.gd-spinner-inline' ).remove();
		} );
	} );

	// =========================================================================
	// Wallet adjustment
	// =========================================================================

	// Toggle wallet adjust panel.
	$( document ).on( 'click', '.gd-wallet-adjust-toggle', function () {
		var userId = $( this ).data( 'user-id' );
		$( '#gd-wallet-adjust-' + userId ).toggleClass( 'is-open' );
	} );

	// Submit wallet adjustment.
	$( document ).on( 'submit', '.gd-wallet-adjust form', function ( e ) {
		e.preventDefault();
		var $form    = $( this );
		var userId   = $form.data( 'user-id' );
		var amount   = $.trim( $form.find( '[name="gd_wa_amount"]' ).val() );
		var desc     = $.trim( $form.find( '[name="gd_wa_description"]' ).val() );
		var $btn     = $form.find( '[type="submit"]' );

		if ( amount === '' ) {
			gdToast( 'Amount is required.', 'error' );
			return;
		}

		$btn.prop( 'disabled', true );

		$.post( ajaxUrl, {
			action:      'gd_adjust_wallet',
			nonce:       nonce,
			user_id:     userId,
			amount:      amount,
			description: desc,
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				gdToast( resp.data.message || 'Wallet updated.' );
				$form[0].reset();
				$form.closest( '.gd-wallet-adjust' ).removeClass( 'is-open' );

				var newBal = resp.data.balance;
				if ( typeof newBal !== 'undefined' ) {
					$( '#gd-wallet-balance-' + userId ).text( '$' + parseFloat( newBal ).toFixed( 2 ) );
				}
			} else {
				gdToast( resp.data || 'Failed.', 'error' );
			}
		} )
		.fail( function () {
			gdToast( 'Network error.', 'error' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	// =========================================================================
	// Transactions: filter form (standard submit – no AJAX needed)
	// =========================================================================

	// Date-range quick validation.
	$( document ).on( 'submit', '#gd-transactions-filter', function () {
		var from = $( '[name="date_from"]' ).val();
		var to   = $( '[name="date_to"]' ).val();
		if ( from && to && from > to ) {
			gdToast( '"From" date must be before "To" date.', 'error' );
			return false;
		}
	} );

	// =========================================================================
	// Job list: status filter
	// =========================================================================

	$( document ).on( 'change', '#gd-job-status-filter', function () {
		var val = $( this ).val();
		var url = new URL( window.location.href );
		if ( val ) {
			url.searchParams.set( 'status', val );
		} else {
			url.searchParams.delete( 'status' );
		}
		url.searchParams.delete( 'paged' );
		window.location.href = url.toString();
	} );

	// =========================================================================
	// Settings: copy webhook URL
	// =========================================================================

	$( document ).on( 'click', '#gd-copy-webhook', function () {
		var $input = $( '#gd-webhook-url' );
		$input[0].select();
		try {
			document.execCommand( 'copy' );
			gdToast( 'Webhook URL copied!' );
		} catch ( err ) {
			gdToast( 'Copy failed – please copy manually.', 'error' );
		}
	} );

	// =========================================================================
	// Utility: HTML escaping helpers
	// =========================================================================

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function escAttr( str ) {
		return escHtml( str );
	}

	// =========================================================================
	// Admin location fields – Google Places autocomplete
	// =========================================================================

	function gdInitAdminLocationFields() {
		if ( ! gdAdmin.hasGooglePlaces ) {
			return;
		}
		if ( typeof google === 'undefined' || ! google.maps || ! google.maps.places ) {
			return;
		}

		$( '.gd-admin-location-field' ).each( function () {
			var $wrap    = $( this );
			var $address = $wrap.find( '.gd-admin-address-input' );
			var $suburb  = $wrap.find( '.gd-admin-suburb-input' );
			var $lat     = $wrap.find( '.gd-admin-lat-input' );
			var $lng     = $wrap.find( '.gd-admin-lng-input' );

			// Attach autocomplete to the Full Address input.
			var ac = new google.maps.places.Autocomplete(
				$address[0],
				{ types: [ 'address' ], componentRestrictions: { country: 'nz' } }
			);

			// Clear stale coordinates when the user edits the address manually.
			$address.on( 'input', function () {
				$lat.val( '' );
				$lng.val( '' );
			} );

			ac.addListener( 'place_changed', function () {
				var place = ac.getPlace();

				if ( place.geometry && place.geometry.location ) {
					$lat.val( place.geometry.location.lat().toFixed( 6 ) );
					$lng.val( place.geometry.location.lng().toFixed( 6 ) );
				}

				var fullAddress = place.formatted_address || $address.val();
				$address.val( fullAddress );

				// Populate the suburb field from the locality component.
				if ( place.address_components ) {
					var suburb = '';
					place.address_components.forEach( function ( comp ) {
						if ( ! suburb && comp.types && (
							comp.types.indexOf( 'sublocality' ) !== -1 ||
							comp.types.indexOf( 'locality' ) !== -1 ||
							comp.types.indexOf( 'neighborhood' ) !== -1
						) ) {
							suburb = comp.long_name;
						}
					} );
					if ( suburb ) {
						$suburb.val( suburb );
					}
				}
			} );
		} );
	}

	// Initialise when Google Maps is available – it may load asynchronously.
	if ( gdAdmin.hasGooglePlaces ) {
		if ( typeof google !== 'undefined' && google.maps && google.maps.places ) {
			gdInitAdminLocationFields();
		} else {
			var gdMapsMaxRetries    = 50; // 50 × 200 ms = 10 s timeout
			var gdMapsRetries       = 0;
			var gdMapsCheckInterval = setInterval( function () {
				gdMapsRetries++;
				if ( typeof google !== 'undefined' && google.maps && google.maps.places ) {
					clearInterval( gdMapsCheckInterval );
					gdInitAdminLocationFields();
				} else if ( gdMapsRetries >= gdMapsMaxRetries ) {
					clearInterval( gdMapsCheckInterval );
				}
			}, 200 );
		}
	}

}( jQuery ) );
