/* global gdPublic */
/**
 * Go Deliver – Public-Facing JavaScript
 * Version: 1.2.33
 */
( function ( $ ) {
	'use strict';

	// =========================================================================
	// Utilities
	// =========================================================================

	/**
	 * Show a toast notification.
	 *
	 * @param {string} message
	 * @param {string} type  'success'|'error'|'warning'|'info'
	 * @param {number} duration  ms
	 */
	function gdToast( message, type, duration ) {
		type     = type     || 'info';
		duration = duration || 4000;

		var $container = $( '#gd-toast-container' );
		if ( ! $container.length ) {
			$container = $( '<div id="gd-toast-container"></div>' ).appendTo( 'body' );
		}

		var $toast = $( '<div class="gd-toast gd-toast--' + type + '">' + gdEscape( message ) + '</div>' );
		$container.append( $toast );

		// Trigger transition.
		setTimeout( function () { $toast.addClass( 'gd-toast--visible' ); }, 10 );

		// Auto-remove.
		setTimeout( function () {
			$toast.removeClass( 'gd-toast--visible' );
			setTimeout( function () { $toast.remove(); }, 300 );
		}, duration );
	}

	/**
	 * Escape HTML to prevent XSS when inserting user-supplied data into DOM.
	 *
	 * @param {string} str
	 * @return {string}
	 */
	function gdEscape( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/**
	 * Show an inline error message beneath a field.
	 *
	 * @param {jQuery} $field
	 * @param {string} message
	 */
	function gdFieldError( $field, message ) {
		$field.addClass( 'gd-field--error' );
		var $err = $field.next( '.gd-field-error' );
		if ( ! $err.length ) {
			$err = $( '<span class="gd-field-error"></span>' ).insertAfter( $field );
		}
		$err.text( message );
	}

	/**
	 * Clear field error state.
	 *
	 * @param {jQuery} $field
	 */
	function gdClearFieldError( $field ) {
		$field.removeClass( 'gd-field--error' );
		$field.next( '.gd-field-error' ).remove();
	}

	/**
	 * Validate that a field is non-empty; show/clear error.
	 *
	 * @param {jQuery} $field
	 * @param {string} label
	 * @return {boolean}
	 */
	function gdRequired( $field, label ) {
		if ( ! $.trim( $field.val() ) ) {
			gdFieldError( $field, label + ' is required.' );
			return false;
		}
		gdClearFieldError( $field );
		return true;
	}

	/**
	 * Validate email format.
	 *
	 * @param {jQuery} $field
	 * @return {boolean}
	 */
	function gdValidateEmail( $field ) {
		var val = $.trim( $field.val() );
		var re  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if ( ! re.test( val ) ) {
			gdFieldError( $field, 'Please enter a valid email address.' );
			return false;
		}
		gdClearFieldError( $field );
		return true;
	}

	/**
	 * Display a loading spinner inside a button and disable it.
	 *
	 * @param {jQuery} $btn
	 */
	function gdBtnLoading( $btn ) {
		$btn.data( 'original-text', $btn.html() )
		    .html( '<span class="gd-loading-spinner"></span>' )
		    .prop( 'disabled', true )
		    .addClass( 'gd-btn--loading' );
	}

	/**
	 * Restore a button to its original state.
	 *
	 * @param {jQuery} $btn
	 */
	function gdBtnReset( $btn ) {
		$btn.html( $btn.data( 'original-text' ) )
		    .prop( 'disabled', false )
		    .removeClass( 'gd-btn--loading' );
	}

	/**
	 * Perform an AJAX request with the global nonce.
	 *
	 * @param {string}   action
	 * @param {Object}   data
	 * @param {Function} success
	 * @param {Function} fail
	 */
	function gdAjax( action, data, success, fail ) {
		data         = data         || {};
		data.action  = action;
		if ( ! data.nonce ) {
			data.nonce = gdPublic.nonce;
		}

		$.ajax( {
			url:      gdPublic.ajaxUrl,
			type:     'POST',
			data:     data,
			dataType: 'json',
			success: function ( response ) {
				if ( response.success ) {
					if ( typeof success === 'function' ) { success( response.data ); }
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : 'An error occurred.';
					if ( typeof fail === 'function' ) { fail( msg ); }
					else { gdToast( msg, 'error' ); }
				}
			},
			error: function () {
				var msg = 'Network error. Please try again.';
				if ( typeof fail === 'function' ) { fail( msg ); }
				else { gdToast( msg, 'error' ); }
			},
		} );
	}

	// =========================================================================
	// Conditional form fields (from form builder)
	// =========================================================================

	function gdInitConditionalFields( $context ) {
		$context = $context || $( document );

		$context.find( '[data-conditional-on]' ).each( function () {
			var $field        = $( this );
			var parentKey     = $field.data( 'conditional-on' );
			var conditionalVal = String( $field.data( 'conditional-value' ) );
			var $parent       = $context.find( '[name="form_data[' + parentKey + ']"], #gd_field_' + parentKey );

			function checkCondition() {
				var current = $parent.val() || '';
				if ( String( current ) === conditionalVal ) {
					$field.addClass( 'gd-visible' ).find( 'input, select, textarea' ).removeAttr( 'disabled' );
				} else {
					$field.removeClass( 'gd-visible' ).find( 'input, select, textarea' ).attr( 'disabled', true ).trigger( 'change' );
				}
			}

			$parent.on( 'change input', checkCondition );
			checkCondition();
		} );
	}

	// =========================================================================
	// Address-field validation (shared by job form and mover registration)
	// =========================================================================

	/**
	 * Validate that every address field in the given section has a geocoded result.
	 * Returns false and shows an inline error if the user typed text but did not
	 * select a suggestion from autocomplete (or geocoding did not complete).
	 *
	 * @param {jQuery} $section
	 * @return {boolean}
	 */
	function validateAddressFields( $section ) {
		var valid = true;
		$section.find( '.gd-location-field' ).each( function () {
			var $wrap    = $( this );
			var $suburb  = $wrap.find( '.gd-suburb-input' );
			var $address = $wrap.find( '.gd-address-input' );
			if ( $suburb.is( ':visible' ) && $.trim( $suburb.val() ) && ! $.trim( $address.val() ) ) {
				gdFieldError( $suburb, 'Please select a valid address from the suggestions.' );
				valid = false;
			}
		} );
		return valid;
	}

	// =========================================================================
	// Multi-step Job Form Wizard
	// =========================================================================

	function gdInitJobForm() {
		var $form = $( '#gd-job-form' );
		if ( ! $form.length ) { return; }

		var currentStep = 1;
		var totalSteps  = $form.find( '.gd-form-section' ).length;
		var $wizardHeader  = $form.closest( '.gd-job-form' ).find( '.gd-job-form__header' );
		var $wizardFooter  = $form.closest( '.gd-job-form' ).find( '.gd-job-form__footer' );
		var $step1Footer   = $form.find( '.gd-job-form__step1-footer' );

		function showStep( step ) {
			$form.find( '.gd-form-section' ).hide().removeClass( 'gd-form-section--active' );
			$form.find( '.gd-form-section[data-step="' + step + '"]' ).show().addClass( 'gd-form-section--active' );

			// Show / hide wizard header and footer for steps 2+.
			if ( step > 1 ) {
				$wizardHeader.show();
				$wizardFooter.show();
			} else {
				$wizardHeader.hide();
				$wizardFooter.hide();
			}

			// Show / hide the step-1 Continue button footer.
			$step1Footer.toggle( step === 1 );

			// Update progress bar (steps 2-6 map to 1-5 out of 5).
			if ( step > 1 ) {
				var modalStep  = step - 1;
				var modalTotal = totalSteps - 1;
				var pct = Math.round( ( modalStep / modalTotal ) * 100 );
				$form.closest( '.gd-job-form' ).find( '.gd-form-progress__fill' ).css( 'width', pct + '%' );
				$form.closest( '.gd-job-form' ).find( '.gd-form-progress' ).attr( 'aria-valuenow', pct );
			} else {
				$form.closest( '.gd-job-form' ).find( '.gd-form-progress__fill' ).css( 'width', '0%' );
				$form.closest( '.gd-job-form' ).find( '.gd-form-progress' ).attr( 'aria-valuenow', 0 );
			}

			// Prev button: shown from step 2 onwards.
			$form.find( '#gd-job-prev' ).toggle( step >= 2 );

			// Next / Submit visibility.
			var $next   = $form.find( '#gd-job-next' );
			var $submit = $form.find( '#gd-job-submit' );
			if ( step === totalSteps ) {
				$next.hide();
				$submit.show();
			} else {
				$next.show();
				$submit.hide();
			}

			// Populate review on last step.
			if ( step === totalSteps ) {
				gdPopulateReview( $form );
			}
		}

		/**
		 * Validate required fields in the current step.
		 *
		 * @return {boolean}
		 */
		function validateStep( step ) {
			var valid = true;
			var $section = $form.find( '.gd-form-section[data-step="' + step + '"]' );

			$section.find( '[required]:visible' ).each( function () {
				var $f = $( this );
				if ( $f.is( 'input[type="checkbox"]' ) ) {
					if ( ! $f.is( ':checked' ) ) {
						gdFieldError( $f, gdEscape( $f.closest( 'label' ).text().trim() ) + ' is required.' );
						valid = false;
					} else {
						gdClearFieldError( $f );
					}
					return;
				}
				if ( ! $.trim( $f.val() ) ) {
					gdFieldError( $f, $f.prev( 'label' ).text().replace( /\*/g, '' ).trim() + ' is required.' );
					valid = false;
				} else {
					gdClearFieldError( $f );
				}
			} );

			// Ensure each address field was confirmed via autocomplete/geocoding.
			if ( ! validateAddressFields( $section ) ) {
				valid = false;
			}

			// Extra validation for the account creation step.
			var $email = $section.find( '#gd_account_email' );
			if ( $email.length && $email.is( ':visible' ) ) {
				if ( ! gdValidateEmail( $email ) ) {
					valid = false;
				}
				var $pass  = $section.find( '#gd_account_password' );
				var $conf  = $section.find( '#gd_account_password_confirm' );
				if ( $pass.length && $.trim( $pass.val() ).length < 8 ) {
					gdFieldError( $pass, 'Password must be at least 8 characters.' );
					valid = false;
				} else if ( $pass.length ) {
					gdClearFieldError( $pass );
				}
				if ( $pass.length && $conf.length && $pass.val() !== $conf.val() ) {
					gdFieldError( $conf, 'Passwords do not match.' );
					valid = false;
				} else if ( $conf.length && $.trim( $pass.val() ) && $pass.val() === $conf.val() ) {
					gdClearFieldError( $conf );
				}
			}

			return valid;
		}

		// Step-1 Continue button → validate step 1, advance to step 2.
		$form.on( 'click', '#gd-job-step1-continue', function () {
			if ( validateStep( 1 ) ) {
				currentStep = 2;
				showStep( 2 );
				$( 'html, body' ).animate( { scrollTop: $form.offset().top - 20 }, 300 );
			}
		} );

		// Next button (steps 2-5).
		$form.on( 'click', '#gd-job-next', function () {
			if ( validateStep( currentStep ) ) {
				currentStep++;
				showStep( currentStep );
				$( 'html, body' ).animate( { scrollTop: $form.offset().top - 20 }, 300 );
			}
		} );

		// Prev button.
		$form.on( 'click', '#gd-job-prev', function () {
			if ( currentStep > 1 ) {
				currentStep--;
				showStep( currentStep );
				$( 'html, body' ).animate( { scrollTop: $form.offset().top - 20 }, 300 );
			}
		} );

		// Submit.
		$form.on( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! validateStep( currentStep ) ) { return; }

			// Ensure suburb hidden fields are populated even when the user
			// typed an address without selecting from the autocomplete dropdown.
			$form.find( '.gd-location-field' ).each( function () {
				var $wrap         = $( this );
				var $suburbHidden = $wrap.find( '.gd-suburb-hidden-input' );
				var $suburb       = $wrap.find( '.gd-suburb-input' );
				if ( ! $.trim( $suburbHidden.val() ) && $.trim( $suburb.val() ) ) {
					$suburbHidden.val( $suburb.val() );
				}
			} );

			var $btn = $form.find( '#gd-job-submit' );
			gdBtnLoading( $btn );

			var formData = new FormData( this );
			formData.append( 'action', 'gd_submit_job' );
			formData.append( 'nonce', gdPublic.nonce );

			$.ajax( {
				url:         gdPublic.ajaxUrl,
				type:        'POST',
				data:        formData,
				contentType: false,
				processData: false,
				dataType:    'json',
				success: function ( response ) {
					gdBtnReset( $btn );
					if ( response.success ) {
						gdToast( 'Your job has been submitted successfully!', 'success', 5000 );
						// Redirect to dashboard after short delay.
						setTimeout( function () {
							if ( gdPublic.dashboardUrl ) {
								window.location.href = gdPublic.dashboardUrl;
							} else {
								$form[ 0 ].reset();
								showStep( 1 );
								currentStep = 1;
							}
						}, 2000 );
					} else {
						var msg = ( response.data && response.data.message ) ? response.data.message : 'Submission failed.';
						gdToast( msg, 'error' );
					}
				},
				error: function () {
					gdBtnReset( $btn );
					gdToast( 'Network error. Please try again.', 'error' );
				},
			} );
		} );

		// Initialize conditional fields.
		gdInitConditionalFields( $form );

		// Initialise location geocoding.
		gdInitLocationFields( $form );

		// Show/hide type-specific fields within step 1 based on job type selection.
		var $jobType = $form.find( '#gd_job_type' );

		function gdToggleTypeFields() {
			var selected = $.trim( $jobType.val() );
			$form.find( '[data-job-type-show]' ).each( function () {
				var types = String( $( this ).data( 'job-type-show' ) ).split( ',' );
				if ( selected && types.indexOf( selected ) !== -1 ) {
					$( this ).slideDown( 150 );
				} else {
					$( this ).slideUp( 150 );
				}
			} );
		}

		$jobType.on( 'change', gdToggleTypeFields );
		gdToggleTypeFields(); // honour any pre-selected value on page load.

		// Show/hide make & model fields based on the vehicle/boat sub-type selection.
		var $vehicleBoatType = $form.find( '#gd_vehicle_boat_type' );

		function gdToggleSubtypeFields() {
			var selected = $.trim( $vehicleBoatType.val() );
			$form.find( '[data-subtype-show]' ).each( function () {
				var subtypes = String( $( this ).data( 'subtype-show' ) ).split( ',' );
				if ( selected && subtypes.indexOf( selected ) !== -1 ) {
					$( this ).slideDown( 150 );
				} else {
					$( this ).slideUp( 150 );
				}
			} );
		}

		$vehicleBoatType.on( 'change', gdToggleSubtypeFields );
		// Reset sub-type fields when the top-level job type changes.
		$jobType.on( 'change', function () {
			$form.find( '[data-subtype-show]' ).hide();
		} );
		gdToggleSubtypeFields(); // honour any pre-selected value on page load.

		showStep( 1 );
	}

	/**
	 * Build the review summary on step 3.
	 *
	 * @param {jQuery} $form
	 */
	function gdPopulateReview( $form ) {
		var $summary = $form.find( '#gd-review-summary' );
		if ( ! $summary.length ) { return; }

		var items = [
			{ label: 'Pickup Location',  value: $form.find( '#gd_pickup_suburb' ).val()  || $form.find( '[name="pickup_suburb"]' ).val() },
			{ label: 'Dropoff Location', value: $form.find( '#gd_dropoff_suburb' ).val() || $form.find( '[name="dropoff_suburb"]' ).val() },
			{ label: 'Date Requested',   value: $form.find( '[name="date_requested"]' ).val() },
		];

		// Include dynamic form fields.
		$form.find( '.gd-form-field:visible input, .gd-form-field:visible select, .gd-form-field:visible textarea' ).each( function () {
			var $input = $( this );
			var label  = $input.closest( '.gd-form-field' ).find( 'label' ).first().text().replace( /\*/g, '' ).trim();
			var value  = $input.val();
			if ( $input.is( ':checkbox' ) ) { value = $input.is( ':checked' ) ? 'Yes' : 'No'; }
			if ( label && value ) {
				items.push( { label: label, value: value } );
			}
		} );

		var html = '';
		$.each( items, function ( i, item ) {
			if ( item.value ) {
				html += '<div class="gd-review-item">' +
				        '<span class="gd-review-item__label">' + gdEscape( item.label ) + '</span>' +
				        '<span class="gd-review-item__value">'  + gdEscape( item.value ) + '</span>' +
				        '</div>';
			}
		} );

		$summary.html( html || '<p class="gd-text-muted">No details entered.</p>' );
	}

	// =========================================================================
	// Location / Geocoding helpers
	// =========================================================================

	function gdInitLocationFields( $context ) {
		$context = $context || $( document );

		$context.find( '.gd-location-field' ).each( function () {
			var $wrap         = $( this );
			var $suburb       = $wrap.find( '.gd-suburb-input' );
			var $suburbHidden = $wrap.find( '.gd-suburb-hidden-input' );
			var $address      = $wrap.find( '.gd-address-input' );
			var $lat          = $wrap.find( '.gd-lat-input' );
			var $lng          = $wrap.find( '.gd-lng-input' );

			// ---------------------------------------------------------------
			// Google Places Autocomplete (preferred)
			// ---------------------------------------------------------------
			if ( gdPublic.hasGooglePlaces && typeof google !== 'undefined' && google.maps && google.maps.places ) {
				var autocomplete = new google.maps.places.Autocomplete(
					$suburb[ 0 ],
					{ types: [ 'geocode' ], componentRestrictions: { country: 'nz' } }
				);

				// Clear stale coordinates whenever the user edits the text.
				$suburb.on( 'input', function () {
					$lat.val( '' );
					$lng.val( '' );
					$address.val( '' );
					$suburbHidden.val( '' );
					gdClearFieldError( $suburb );
				} );

				autocomplete.addListener( 'place_changed', function () {
					var place = autocomplete.getPlace();

					if ( place.geometry && place.geometry.location ) {
						$lat.val( place.geometry.location.lat().toFixed( 6 ) );
						$lng.val( place.geometry.location.lng().toFixed( 6 ) );
					}

					var fullAddress = place.formatted_address || $suburb.val();
					$address.val( fullAddress );

					// Show the full address in the visible field so the customer
					// can confirm their complete address was captured correctly.
					$suburb.val( fullAddress );

					// Extract suburb/locality name from address components so that
					// only a general area (not the full street address) is stored
					// and shown to movers before a quote is accepted.
					var suburbName = '';
					if ( place.address_components ) {
						place.address_components.forEach( function ( comp ) {
							if ( ! suburbName && comp.types && (
								comp.types.indexOf( 'sublocality' ) !== -1 ||
								comp.types.indexOf( 'locality' ) !== -1 ||
								comp.types.indexOf( 'neighborhood' ) !== -1
							) ) {
								suburbName = comp.long_name;
							}
						} );
					}
					$suburbHidden.val( suburbName || fullAddress );
				} );

				return; // Skip Nominatim fallback for this field.
			}

			// ---------------------------------------------------------------
			// Nominatim fallback: auto-geocode on blur
			// ---------------------------------------------------------------

			// Clear stale coordinates whenever the user edits the text.
			$suburb.on( 'input', function () {
				$lat.val( '' );
				$lng.val( '' );
				$address.val( '' );
				$suburbHidden.val( '' );
				gdClearFieldError( $suburb );
			} );

			$suburb.on( 'blur', function () {
				var query = $.trim( $suburb.val() );
				if ( ! query || ( $lat.val() && $lng.val() ) ) { return; }

				$.getJSON(
					'https://nominatim.openstreetmap.org/search',
					{
						q:              query + ', New Zealand',
						format:         'json',
						limit:          1,
						addressdetails: 1,
					},
					function ( results ) {
						if ( results && results.length ) {
							var place = results[ 0 ];
							$lat.val( parseFloat( place.lat ).toFixed( 6 ) );
							$lng.val( parseFloat( place.lon ).toFixed( 6 ) );
							$address.val( place.display_name || query );

							// Extract suburb/locality from Nominatim address details
							// so movers only see a general area, not the full address.
							var addr      = place.address || {};
							var suburbVal = addr.suburb      ||
							                addr.city_district ||
							                addr.city        ||
							                addr.town        ||
							                addr.village     ||
							                query;
							$suburbHidden.val( suburbVal );
						} else {
							// No geocoding result: fall back to the typed value.
							$suburbHidden.val( query );
						}
					}
				);
			} );
		} );
	}

	// =========================================================================
	// Customer Dashboard
	// =========================================================================

	function gdInitCustomerDashboard() {
		var $dashboard = $( '#gd-customer-dashboard' );
		if ( ! $dashboard.length ) { return; }

		/**
		 * Activate a dashboard panel by ID.
		 *
		 * @param {string} panelId
		 */
		function gdActivateCustomerPanel( panelId ) {
			$dashboard.find( '.gd-sidebar-nav__item' ).removeClass( 'gd-sidebar-nav__item--active' );
			$dashboard.find( '.gd-sidebar-nav__item[data-panel="' + panelId + '"]' ).addClass( 'gd-sidebar-nav__item--active' );
			$dashboard.find( '.gd-panel' ).hide().removeClass( 'gd-panel--active' );
			$dashboard.find( '#gd-panel-' + panelId ).show().addClass( 'gd-panel--active' );
		}

		// Sidebar panel switching.
		$dashboard.on( 'click keypress', '.gd-sidebar-nav__item[data-panel]', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 ) { return; }
			gdActivateCustomerPanel( $( this ).data( 'panel' ) );
		} );

		// Overview stat-card links and "View all" links that switch panels.
		$dashboard.on( 'click', '.gd-dashboard-switch-panel', function ( e ) {
			e.preventDefault();
			gdActivateCustomerPanel( $( this ).data( 'panel' ) );
			$( 'html, body' ).animate( { scrollTop: $dashboard.offset().top - 20 }, 300 );
		} );

		// Compact "View Job" button on overview → switch to jobs panel and show inline detail.
		$dashboard.on( 'click', '.gd-compact-view-job-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			gdActivateCustomerPanel( 'jobs' );
			gdLoadJobDetail( jobId );
			$( 'html, body' ).animate( { scrollTop: $dashboard.offset().top - 20 }, 300 );
		} );

		// -----------------------------------------------------------------------
		// Inline messaging: open a conversation from anywhere in the dashboard.
		// -----------------------------------------------------------------------
		$dashboard.on( 'click', '.gd-dashboard-open-convo', function ( e ) {
			e.preventDefault();
			var jobId         = $( this ).data( 'job-id' );
			var otherName     = $( this ).data( 'other-name' ) || 'Mover';
			var participantId = parseInt( $( this ).data( 'participant-id' ), 10 ) || 0;
			gdOpenInlineConversation( jobId, otherName, participantId );
		} );

		/**
		 * Open the inline messaging view inside the Messages panel.
		 *
		 * @param {number} jobId
		 * @param {string} otherName
		 */
		function gdOpenInlineConversation( jobId, otherName, participantId ) {
			// Switch to messages panel.
			gdActivateCustomerPanel( 'messages' );
			$( 'html, body' ).animate( { scrollTop: $dashboard.offset().top - 20 }, 300 );

			var $convList = $dashboard.find( '#gd-dashboard-conversations' );
			var $wrap     = $dashboard.find( '#gd-dashboard-messaging-wrap' );
			var nonce     = $wrap.data( 'nonce' ) || gdPublic.nonce;

			// Build messaging panel HTML.
			$wrap.html(
				'<div class="gd-inline-messaging-header">' +
					'<button type="button" class="gd-inline-messaging-back-btn" id="gd-inline-back-btn">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>' +
						' Back' +
					'</button>' +
					'<span class="gd-inline-messaging-title">' + gdEscape( otherName ) + '</span>' +
				'</div>' +
				'<div id="gd-messaging-panel" class="gd-messaging-panel"' +
					' data-job-id="' + parseInt( jobId, 10 ) + '"' +
					' data-participant-id="' + parseInt( participantId || 0, 10 ) + '"' +
					' data-nonce="' + gdEscape( nonce ) + '"' +
					' data-quote-accepted="0"' +
					'>' +
					'<div id="gd-message-list" class="gd-message-list">' +
						'<p class="gd-message-list__empty">Loading…</p>' +
					'</div>' +
					'<div class="gd-messaging-panel__input">' +
						'<textarea id="gd-message-input" class="gd-message-textarea" rows="1" placeholder="Type a message…"></textarea>' +
						'<button type="button" id="gd-send-message-btn" class="gd-btn gd-btn--primary gd-btn--sm">Send</button>' +
					'</div>' +
				'</div>'
			);

			// Show the messaging wrap, hide conversations list.
			$convList.hide();
			$wrap.show();

			// Stop any existing polling.
			if ( gdMessagingPollTimer ) {
				clearInterval( gdMessagingPollTimer );
				gdMessagingPollTimer = null;
			}
			gdLastMessageId = 0;

			// Check if quote is accepted for this job.
			$.getJSON( gdPublic.ajaxUrl, {
				action:  'gd_get_job_detail',
				job_id:  jobId,
				nonce:   gdPublic.nonce,
			}, function ( response ) {
				if ( response.success && response.data ) {
					var quoteAccepted = response.data.quote_accepted ? 1 : 0;
					$wrap.find( '#gd-messaging-panel' ).attr( 'data-quote-accepted', quoteAccepted );
				}
			} );

			var $panel = $wrap.find( '#gd-messaging-panel' );

			// Initial load.
			gdLoadMessages( jobId, $panel );

			// Poll every 30 seconds.
			gdMessagingPollTimer = setInterval( function () {
				gdLoadMessages( jobId, $panel );
			}, 30000 );

			// Send on button click.
			$wrap.on( 'click', '#gd-send-message-btn', function () {
				gdSendMessage( jobId, $panel );
			} );

			// Send on Ctrl+Enter / Cmd+Enter.
			$wrap.on( 'keydown', '#gd-message-input', function ( ev ) {
				if ( ( ev.ctrlKey || ev.metaKey ) && ev.key === 'Enter' ) {
					ev.preventDefault();
					gdSendMessage( jobId, $panel );
				}
			} );

			// Back button.
			$wrap.on( 'click', '#gd-inline-back-btn', function () {
				if ( gdMessagingPollTimer ) {
					clearInterval( gdMessagingPollTimer );
					gdMessagingPollTimer = null;
				}
				$wrap.hide().empty();
				$convList.show();
			} );
		}

		// Customer profile save.
		$dashboard.on( 'submit', '#gd-customer-profile-form', function ( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn  = $form.find( '#gd-customer-profile-save-btn' );

			gdBtnLoading( $btn );

			gdAjax(
				'gd_update_customer_profile',
				{
					first_name: $.trim( $form.find( '[name="first_name"]' ).val() ),
					last_name:  $.trim( $form.find( '[name="last_name"]' ).val() ),
					email:      $.trim( $form.find( '[name="email"]' ).val() ),
					phone:      $.trim( $form.find( '[name="phone"]' ).val() ),
				},
				function ( data ) {
					gdBtnReset( $btn );
					gdToast( data.message, 'success' );
					// Update the welcome heading immediately.
					if ( data.display_name ) {
						$dashboard.find( '.gd-dashboard-header__title' ).text(
							'Welcome back, ' + data.display_name + '!'
						);
					}
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );


		// Show job detail inline.
		$dashboard.on( 'click', '.gd-job-view-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			gdLoadJobDetail( jobId );
		} );

		// Cancel job.
		$dashboard.on( 'click', '.gd-job-cancel-btn', function () {
			var $btn       = $( this );
			var jobId      = $btn.data( 'job-id' );
			var jobStatus  = $btn.data( 'job-status' );
			var moverId    = $btn.data( 'accepted-mover-id' ) || 0;
			var moverName  = $btn.data( 'accepted-mover-company' ) || '';

			// For accepted jobs, show the reason-selection modal.
			if ( 'accepted' === jobStatus ) {
				var $modal = $( '#gd-cancel-reason-modal' );
				// Reset radio to default.
				$modal.find( '[name="gd_cancel_reason"][value="no_longer_needed"]' ).prop( 'checked', true );
				// Store context for the confirm handler.
				$modal.data( 'job-id', jobId );
				$modal.data( 'mover-id', moverId );
				$modal.data( 'mover-name', moverName );
				$modal.addClass( 'gd-modal-overlay--open' );
				return;
			}

			// For open/locked jobs use a simple confirm dialog.
			if ( ! window.confirm( 'Are you sure you want to cancel this job? This cannot be undone.' ) ) { return; }
			gdBtnLoading( $btn );
			gdAjax(
				'gd_cancel_job',
				{ job_id: jobId },
				function () {
					gdToast( 'Job cancelled successfully.', 'success' );
					location.reload();
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Confirm cancellation from the reason modal.
		$( document ).on( 'click', '#gd-cancel-reason-confirm', function () {
			var $modal    = $( '#gd-cancel-reason-modal' );
			var jobId     = $modal.data( 'job-id' );
			var moverId   = $modal.data( 'mover-id' );
			var moverName = $modal.data( 'mover-name' );
			var reason    = $modal.find( '[name="gd_cancel_reason"]:checked' ).val() || 'no_longer_needed';
			var $btn      = $( this );

			gdBtnLoading( $btn );
			gdAjax(
				'gd_cancel_job',
				{ job_id: jobId, cancel_reason: reason },
				function () {
					$modal.removeClass( 'gd-modal-overlay--open' );
					gdBtnReset( $btn );

					if ( 'mover_didnt_read' === reason ) {
						// Show the re-post modal.
						var $repost = $( '#gd-repost-job-modal' );
						$repost.data( 'job-id', jobId );
						$repost.data( 'mover-id', moverId );

						// Show/update exclude option if we have a mover name.
						var $excludeWrap = $repost.find( '#gd-repost-exclude-wrap' );
						if ( moverId && moverName ) {
							// .text() sets textContent, so no HTML injection is possible.
							$repost.find( '#gd-repost-exclude-text' ).text( 'Exclude ' + moverName + ' from seeing this job' );
							$repost.find( '#gd-repost-exclude-check' ).prop( 'checked', false );
							$excludeWrap.show();
						} else {
							$excludeWrap.hide();
						}
						$repost.addClass( 'gd-modal-overlay--open' );
					} else {
						gdToast( 'Job cancelled successfully.', 'success' );
						location.reload();
					}
				},
				function ( msg ) {
					gdBtnReset( $btn );
					$modal.removeClass( 'gd-modal-overlay--open' );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Re-post job confirm.
		$( document ).on( 'click', '#gd-repost-job-confirm', function () {
			var $repost   = $( '#gd-repost-job-modal' );
			var jobId     = $repost.data( 'job-id' );
			var moverId   = $repost.data( 'mover-id' );
			var excludeId = $repost.find( '#gd-repost-exclude-check' ).is( ':checked' ) ? moverId : 0;
			var $btn      = $( this );

			gdBtnLoading( $btn );
			gdAjax(
				'gd_repost_job',
				{ job_id: jobId, exclude_mover_id: excludeId },
				function () {
					gdBtnReset( $btn );
					$repost.removeClass( 'gd-modal-overlay--open' );
					gdToast( 'Job re-posted successfully!', 'success', 5000 );
					location.reload();
				},
				function ( msg ) {
					gdBtnReset( $btn );
					$repost.removeClass( 'gd-modal-overlay--open' );
					gdToast( msg, 'error' );
				}
			);
		} );

		// "No thanks" on the re-post modal – just reload to reflect the cancellation.
		$( document ).on( 'click', '#gd-repost-job-modal .gd-modal__close', function () {
			$( '#gd-repost-job-modal' ).removeClass( 'gd-modal-overlay--open' );
			gdToast( 'Job cancelled successfully.', 'success' );
			location.reload();
		} );

		// Accept quote.
		$dashboard.on( 'click', '.gd-accept-quote-btn', function () {
			var quoteId = $( this ).data( 'quote-id' );
			var jobId   = $( this ).data( 'job-id' );
			if ( ! window.confirm( 'Accept this quote? The mover will be notified and their fee will be charged.' ) ) { return; }
			var $btn = $( this );
			gdBtnLoading( $btn );
			gdAjax(
				'gd_accept_quote',
				{ quote_id: quoteId, job_id: jobId },
				function () {
					gdToast( 'Quote accepted! The mover has been notified.', 'success', 5000 );
					location.reload();
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Submit review.
		$dashboard.on( 'submit', '#gd-review-form', function ( e ) {
			e.preventDefault();
			var $f   = $( this );
			var $btn = $f.find( '[type="submit"]' );
			var rating = $f.find( '.gd-star-input input[type="radio"]:checked' ).val()
			          || $f.find( '[name="rating"]' ).val();
			if ( ! rating || parseInt( rating, 10 ) < 1 ) {
				gdToast( 'Please select a star rating.', 'warning' );
				return;
			}
			gdBtnLoading( $btn );
			gdAjax(
				'gd_submit_review',
				{
					job_id:   $f.find( '[name="job_id"]' ).val(),
					mover_id: $f.find( '[name="mover_id"]' ).val(),
					rating:   rating,
					comment:  $f.find( '[name="comment"]' ).val(),
					nonce:    $f.find( '[name="nonce"]' ).val(),
				},
				function () {
					gdBtnReset( $btn );
					gdToast( 'Review submitted. Thank you!', 'success' );
					$f.closest( '.gd-review-section' ).html( '<p class="gd-text-success">✓ Review submitted.</p>' );
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Toggle quote visibility.
		$dashboard.on( 'click', '.gd-toggle-quotes-btn', function () {
			var $quotes = $( '#gd-quotes-' + $( this ).data( 'job-id' ) );
			$quotes.slideToggle( 200 );
		} );
	}

	/**
	 * Load and display job details inline via AJAX.
	 *
	 * @param {number} jobId
	 */
	function gdLoadJobDetail( jobId ) {
		var $container = $( '#gd-job-detail-container' );
		if ( ! $container.length ) { return; }

		$container.html( '<div class="gd-inline-spinner"><div class="gd-loading-spinner" style="width:32px;height:32px;border-color:var(--gd-border);border-top-color:var(--gd-primary)"></div></div>' );

		gdAjax(
			'gd_get_job_detail',
			{ job_id: jobId },
			function ( data ) {
				$container.html( data.html );
				gdInitStarInput( $container );
				gdInitConditionalFields( $container );
				$( 'html, body' ).animate( { scrollTop: $container.offset().top - 20 }, 400 );
			},
			function ( msg ) {
				$container.html( '<p class="gd-text-danger">' + gdEscape( msg ) + '</p>' );
			}
		);
	}

	// =========================================================================
	// Mover Dashboard
	// =========================================================================

	/**
	 * Activate a top-level dashboard panel via the sidebar nav.
	 *
	 * @param {jQuery} $dashboard
	 * @param {string} panelId  e.g. 'browse-jobs'
	 */
	function gdActivatePanel( $dashboard, panelId ) {
		$dashboard.find( '.gd-sidebar-nav__item' ).removeClass( 'gd-sidebar-nav__item--active' );
		$dashboard.find( '.gd-sidebar-nav__item[data-panel="' + panelId + '"]' ).addClass( 'gd-sidebar-nav__item--active' );

		$dashboard.find( '.gd-panel' ).hide().removeClass( 'gd-panel--active' );
		$dashboard.find( '#gd-panel-' + panelId ).show().addClass( 'gd-panel--active' );

		if ( panelId === 'browse-jobs' ) {
			gdLoadAvailableJobs( $dashboard );
		}
		if ( panelId === 'profile' ) {
			gdInitLocationFields( $dashboard.find( '#gd-panel-profile' ) );
		}
	}

	function gdInitMoverDashboard() {
		var $dashboard = $( '#gd-mover-dashboard' );
		if ( ! $dashboard.length ) { return; }

		// Sidebar nav panel switching.
		$dashboard.on( 'click keypress', '.gd-sidebar-nav__item[data-panel]', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 ) { return; }
			gdActivatePanel( $dashboard, $( this ).data( 'panel' ) );
		} );

		// Inner tabs within My Jobs panel (My Quotes / Accepted / Dismissed).
		$dashboard.on( 'click', '.gd-tab', function () {
			var target = $( this ).data( 'tab' );
			var $wrap  = $( this ).closest( '.gd-panel' );
			$wrap.find( '.gd-tab' ).removeClass( 'gd-tab--active' );
			$( this ).addClass( 'gd-tab--active' );
			$wrap.find( '.gd-tab-panel' ).removeClass( 'gd-tab-panel--active' ).hide();
			$wrap.find( '#gd-tab-' + target ).addClass( 'gd-tab-panel--active' ).show();
		} );

		// Overview sub-tabs (Reviews / Past Jobs).
		$dashboard.on( 'click', '.gd-sub-tab', function () {
			var target = $( this ).data( 'subtab' );
			var $wrapper = $( this ).closest( '.gd-sub-tabs-wrapper' );
			$wrapper.find( '.gd-sub-tab' ).removeClass( 'gd-sub-tab--active' );
			$( this ).addClass( 'gd-sub-tab--active' );
			$wrapper.find( '.gd-sub-panel' ).hide();
			$wrapper.find( '#gd-subtab-' + target ).show();
		} );

		// "Edit Profile" button on overview → open settings panel.
		$dashboard.on( 'click', '#gd-edit-profile-btn', function () {
			gdActivatePanel( $dashboard, 'profile' );
			$( 'html, body' ).animate( { scrollTop: $dashboard.offset().top - 20 }, 300 );
		} );

		// "View All Reviews" links → open reviews panel.
		$dashboard.on( 'click', '.gd-view-all-reviews-btn', function () {
			gdActivatePanel( $dashboard, 'reviews' );
			$( 'html, body' ).animate( { scrollTop: $dashboard.offset().top - 20 }, 300 );
		} );

		// "View All Jobs" links → open My Jobs panel.
		$dashboard.on( 'click', '.gd-view-my-jobs-btn', function () {
			gdActivatePanel( $dashboard, 'my-jobs' );
			$( 'html, body' ).animate( { scrollTop: $dashboard.offset().top - 20 }, 300 );
		} );

		// -------------------------------------------------------------------------
		// Custom photo gallery upload / delete (Settings > My Photos).
		// -------------------------------------------------------------------------

		/**
		 * Build the HTML for a single photo gallery item.
		 *
		 * @param {number|string} attachmentId
		 * @param {string}        thumbUrl
		 * @return {jQuery}
		 */
		function gdPhotoItemHtml( attachmentId, thumbUrl ) {
			var id = gdEscape( String( attachmentId ) );
			return $(
				'<div class="gd-photo-gallery__item" data-id="' + id + '">' +
				'<img src="' + gdEscape( thumbUrl ) + '" alt="">' +
				'<button type="button" class="gd-photo-delete-btn" data-id="' + id + '" title="Delete photo">\u00d7</button>' +
				'</div>'
			);
		}

		/**
		 * Update the photo count label inside the gallery.
		 *
		 * @param {jQuery} $dashboard
		 * @param {number} count  Current number of uploaded photos.
		 * @param {number} max    Maximum allowed photos.
		 */
		function gdUpdatePhotoCount( $dashboard, count, max ) {
			$dashboard.find( '#gd-photo-count' ).text( count + ' of ' + max + ' photos used' );
		}

		// Handle file selection → upload via AJAX.
		$dashboard.on( 'change', '#gd-photo-file-input', function () {
			var file = this.files && this.files[0];
			if ( ! file ) { return; }

			var $gallery = $dashboard.find( '#gd-photo-gallery' );
			var count    = parseInt( $gallery.data( 'count' ), 10 ) || 0;
			var max      = parseInt( $gallery.data( 'max' ), 10 ) || 10;

			if ( count >= max ) {
				gdToast( 'Maximum ' + max + ' photos allowed. Delete a photo first.', 'error' );
				return;
			}

			var $label = $dashboard.find( '#gd-photo-add-label' );
			$label.addClass( 'gd-btn--loading' ).prop( 'disabled', true );

			var formData = new FormData();
			formData.append( 'action', 'gd_upload_mover_photo' );
			formData.append( 'nonce',  gdPublic.nonce );
			formData.append( 'photo',  file );

			$.ajax( {
				url:         gdPublic.ajaxUrl,
				type:        'POST',
				data:        formData,
				processData: false,
				contentType: false,
				success: function ( res ) {
					if ( res && res.success ) {
						var newCount = res.data.count;
						$gallery.data( 'count', newCount );

						// Add item to grid.
						var $grid = $dashboard.find( '#gd-photo-grid' );
						$grid.append( gdPhotoItemHtml( res.data.attachment_id, res.data.thumb_url ) );

						// Update count label.
						gdUpdatePhotoCount( $dashboard, newCount, max );

						// Hide add button if max reached; swap to max notice.
						if ( newCount >= max ) {
							$label.replaceWith( '<span class="gd-photo-gallery__max-notice">Maximum photos reached. Delete one to add more.</span>' );
						}

						// Refresh avatar display if this is the first photo.
						if ( newCount === 1 ) {
							var imgTag = '<img src="' + gdEscape( res.data.thumb_url ) + '" alt="">';
							$dashboard.find( '.gd-profile-card__avatar' ).find( 'img, .gd-profile-card__avatar-placeholder' ).replaceWith( imgTag );
							$dashboard.find( '.gd-about-block__avatar' ).find( 'img, .gd-about-block__avatar-placeholder' ).replaceWith( imgTag );
						}

						gdToast( 'Photo uploaded.', 'success' );
					} else {
						gdToast( ( res && res.data && res.data.message ) || 'Upload failed.', 'error' );
					}
				},
				error: function () {
					gdToast( 'Upload failed. Please try again.', 'error' );
				},
				complete: function () {
					$label.removeClass( 'gd-btn--loading' ).prop( 'disabled', false );
					// Reset file input so the same file can be re-uploaded if needed.
					$dashboard.find( '#gd-photo-file-input' ).val( '' );
				},
			} );
		} );

		// Delete photo.
		$dashboard.on( 'click', '.gd-photo-delete-btn', function () {
			if ( ! window.confirm( 'Delete this photo? This cannot be undone.' ) ) { return; }

			var $btn          = $( this );
			var attachmentId  = $btn.data( 'id' );
			var $item         = $btn.closest( '.gd-photo-gallery__item' );
			var $gallery      = $dashboard.find( '#gd-photo-gallery' );
			var max           = parseInt( $gallery.data( 'max' ), 10 ) || 10;

			$btn.prop( 'disabled', true );

			gdAjax(
				'gd_delete_mover_photo',
				{ attachment_id: attachmentId },
				function ( data ) {
					var newCount = data.count;
					$gallery.data( 'count', newCount );
					$item.remove();

					// Update count label.
					gdUpdatePhotoCount( $dashboard, newCount, max );

					// Re-show the add button if it was replaced by the max notice.
					if ( newCount < max && ! $dashboard.find( '#gd-photo-add-label' ).length ) {
						$dashboard.find( '.gd-photo-gallery__max-notice' ).replaceWith(
							'<label class="gd-btn gd-btn--outline gd-btn--sm gd-photo-add-btn" id="gd-photo-add-label">' +
							'<input type="file" id="gd-photo-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">' +
							'+ Add Photo</label>'
						);
					}

					gdToast( 'Photo deleted.', 'success' );
				},
				function ( msg ) {
					$btn.prop( 'disabled', false );
					gdToast( msg || 'Delete failed.', 'error' );
				}
			);
		} );

		// Filter chips.
		$dashboard.on( 'click', '.gd-filter-chip', function () {
			$( this ).siblings( '.gd-filter-chip' ).removeClass( 'gd-filter-chip--active' );
			$( this ).addClass( 'gd-filter-chip--active' );
			gdUpdateJobsFilterCount( $dashboard );
			gdLoadAvailableJobs( $dashboard );
		} );

		$dashboard.on( 'click', '.gd-jobs-toolbar__filter', function () {
			var $btn = $( this );
			var $bar = $dashboard.find( '#gd-job-filter-bar' );
			$bar.toggleClass( 'gd-filter-bar--hidden' );
			$btn.attr( 'aria-expanded', $bar.hasClass( 'gd-filter-bar--hidden' ) ? 'false' : 'true' );
		} );

		$dashboard.on( 'input', '#gd-job-search-input', function () {
			gdFilterAvailableJobCards( $dashboard );
		} );

		// View job detail modal.
		$dashboard.on( 'click', '.gd-job-view-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			gdOpenJobModal( jobId );
		} );

		// Submit quote button on available-jobs cards opens the job detail modal.
		$dashboard.on( 'click', '.gd-quote-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			gdOpenJobModal( jobId );
		} );

		// "View quotes" stat on job cards opens the job detail modal.
		$dashboard.on( 'click keydown', '.gd-job-card__stat--link', function ( e ) {
			if ( e.type === 'keydown' && e.which !== 13 && e.which !== 32 ) { return; }
			if ( e.type === 'keydown' ) { e.preventDefault(); }
			var jobId = $( this ).closest( '.gd-job-card' ).data( 'job-id' );
			if ( jobId ) { gdOpenJobModal( jobId ); }
		} );

		// Withdraw quote.
		$dashboard.on( 'click', '.gd-withdraw-quote-btn', function () {
			var quoteId = $( this ).data( 'quote-id' );
			if ( ! window.confirm( 'Withdraw this quote?' ) ) { return; }
			var $btn = $( this );
			gdBtnLoading( $btn );
			gdAjax(
				'gd_withdraw_quote',
				{ quote_id: quoteId },
				function () {
					gdToast( 'Quote withdrawn.', 'success' );
					$btn.closest( '.gd-mover-card' ).fadeOut( 300, function () { $( this ).remove(); } );
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Dismiss job.
		$dashboard.on( 'click', '.gd-dismiss-job-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			var $btn  = $( this );
			gdBtnLoading( $btn );
			gdAjax(
				'gd_dismiss_job',
				{ job_id: jobId },
				function ( data ) {
					$btn.closest( '.gd-job-card' ).fadeOut( 300, function () { $( this ).remove(); } );
					// Update the dismissed-jobs tab badge.
					var $badge = $dashboard.find( '#gd-dismissed-badge' );
					if ( $badge.length ) {
						$badge.text( parseInt( $badge.text(), 10 ) + 1 );
					} else {
						$dashboard.find( '[data-tab="dismissed-jobs"]' ).append( '<span class="gd-badge gd-badge--open" id="gd-dismissed-badge" style="margin-left:6px;">1</span>' );
					}
					// Inject the dismissed card into the tab panel immediately
					// so it appears without requiring a page reload.
					if ( data && data.card_html ) {
						var $panel = $dashboard.find( '#gd-tab-dismissed-jobs' );
						$panel.find( '.gd-empty-state' ).remove();
						$panel.prepend( data.card_html );
					}
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Restore dismissed job.
		$dashboard.on( 'click', '.gd-restore-job-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			var $btn  = $( this );
			gdBtnLoading( $btn );
			gdAjax(
				'gd_restore_job',
				{ job_id: jobId },
				function () {
					gdToast( 'Job restored to Available Jobs.', 'success' );
					$btn.closest( '.gd-dismissed-card' ).fadeOut( 300, function () { $( this ).remove(); } );
					// Update the dismissed-jobs tab badge.
					var $badge = $dashboard.find( '#gd-dismissed-badge' );
					if ( $badge.length ) {
						var newCount = parseInt( $badge.text(), 10 ) - 1;
						if ( newCount <= 0 ) {
							$badge.remove();
						} else {
							$badge.text( newCount );
						}
					}
					// Reload available jobs so the restored job appears.
					gdLoadAvailableJobs( $dashboard );
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Quote submission from modal (modal lives outside $dashboard, so listen on document).
		$( document ).on( 'submit', '#gd-quote-form', function ( e ) {
			e.preventDefault();
			gdSubmitQuote( $( this ) );
		} );

		// Mover profile save.
		$dashboard.on( 'submit', '#gd-mover-profile-form', function ( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn  = $form.find( '#gd-profile-save-btn' );

			var jobTypes = [];
			$form.find( 'input[name="job_types[]"]:checked' ).each( function () {
				jobTypes.push( $( this ).val() );
			} );

			gdBtnLoading( $btn );

			gdAjax(
				'gd_update_mover_profile',
				{
					first_name:             $.trim( $form.find( '[name="first_name"]' ).val() ),
					last_name:              $.trim( $form.find( '[name="last_name"]' ).val() ),
					email:                  $.trim( $form.find( '[name="email"]' ).val() ),
					phone:                  $.trim( $form.find( '[name="phone"]' ).val() ),
					base_suburb:            $.trim( $form.find( '[name="base_suburb"]' ).val() ),
					base_lat:               $.trim( $form.find( '[name="base_lat"]' ).val() ),
					base_lng:               $.trim( $form.find( '[name="base_lng"]' ).val() ),
					radius:                 $.trim( $form.find( '[name="radius"]' ).val() ),
					job_types:              jobTypes,
					company_name:           $.trim( $form.find( '[name="company_name"]' ).val() ),
					bio:                    $.trim( $form.find( '[name="bio"]' ).val() ),
					notification_frequency: $.trim( $form.find( '[name="notification_frequency"]' ).val() ),
				},
				function ( data ) {
					gdBtnReset( $btn );
					gdToast( data.message || 'Profile updated.', 'success' );

					// Reflect changes on the overview panel immediately (no reload needed).
					var name   = data.display_name      || '';
					var bio    = data.bio                || '';
					var suburb = data.suburb             || '';
					var photo  = data.profile_photo_url  || '';

					if ( name ) {
						$dashboard.find( '.gd-profile-card__name' ).text( name );
						$dashboard.find( '.gd-about-block__title' ).text( 'About ' + name );
						$dashboard.find( '.gd-dashboard-header__title' ).text( 'Welcome, ' + name + '!' );
					}

					var $bioEl = $dashboard.find( '.gd-about-block__text' );
					if ( bio ) {
						$bioEl.removeClass( 'gd-text-muted' ).html( bio.replace( /\n/g, '<br>' ) );
					} else {
						$bioEl.addClass( 'gd-text-muted' ).text( 'No bio added yet. Go to Settings to tell customers about yourself.' );
					}

					var $locEl = $dashboard.find( '.gd-profile-card__location' );
					if ( suburb ) {
						if ( $locEl.length ) {
							$locEl.text( ' · Based in ' + suburb );
						} else {
							$dashboard.find( '.gd-profile-card__meta' ).append( '<span class="gd-profile-card__location"> · Based in ' + gdEscape( suburb ) + '</span>' );
						}
					} else {
						$locEl.remove();
					}

					if ( photo ) {
						var $cardAvatar  = $dashboard.find( '.gd-profile-card__avatar' );
						var $aboutAvatar = $dashboard.find( '.gd-about-block__avatar' );
						var imgTag = '<img src="' + gdEscape( photo ) + '" alt="' + gdEscape( name ) + '">';
						$cardAvatar.find( 'img, .gd-profile-card__avatar-placeholder' ).replaceWith( imgTag );
						$aboutAvatar.find( 'img, .gd-about-block__avatar-placeholder' ).replaceWith( imgTag );
					}
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Mark job as complete.
		$dashboard.on( 'click', '.gd-complete-job-btn', function () {
			if ( ! window.confirm( 'Mark this job as completed? The customer will be notified and asked to leave a review.' ) ) {
				return;
			}
			var $btn      = $( this );
			var $card     = $btn.closest( '.gd-mover-card' );
			var jobId     = $btn.data( 'job-id' );
			gdBtnLoading( $btn );
			gdAjax(
				'gd_complete_job',
				{ job_id: jobId },
				function ( data ) {
					gdToast( data.message || 'Job marked as completed.', 'success' );

					// Update the badge on the card and remove the Complete button.
					$card.find( '.gd-badge--accepted' ).text( '✓ Completed' );
					$btn.remove();

					// Move the card from the Accepted Jobs tab to the Completed Jobs tab.
					var $completedPanel = $dashboard.find( '#gd-tab-completed-jobs' );
					var $emptyState     = $completedPanel.find( '.gd-empty-state' );

					// Clone the updated card, strip actions that should not appear in Completed tab.
					var $cloned = $card.clone();
					$cloned.find( '.gd-complete-job-btn, .gd-btn--primary' ).remove();
					if ( ! $cloned.find( '.gd-mover-card__actions .gd-btn' ).length ) {
						$cloned.find( '.gd-mover-card__actions' ).remove();
					}
					$cloned.removeAttr( 'id' );
					$completedPanel.append( $cloned );
					$emptyState.hide();

					// Remove the original card from the accepted tab.
					$card.fadeOut( 300, function () { $card.remove(); } );

					// Update tab badges.
					var $acceptedTab   = $dashboard.find( '.gd-tab[data-tab="accepted-jobs"]' );
					var $completedTab  = $dashboard.find( '.gd-tab[data-tab="completed-jobs"]' );
					var $acceptedBadge = $acceptedTab.find( '.gd-badge' );
					var $compBadge     = $completedTab.find( '.gd-badge' );

					// Decrement accepted badge.
					if ( $acceptedBadge.length ) {
						var acceptedCount = parseInt( $acceptedBadge.text(), 10 ) - 1;
						if ( acceptedCount > 0 ) {
							$acceptedBadge.text( acceptedCount );
						} else {
							$acceptedBadge.remove();
						}
					}

					// Increment (or create) completed badge.
					if ( $compBadge.length ) {
						$compBadge.text( parseInt( $compBadge.text(), 10 ) + 1 );
					} else {
						$completedTab.append( '<span class="gd-badge gd-badge--accepted" style="margin-left:6px;">1</span>' );
					}
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );

		// Add sub-user (team member).
		$dashboard.on( 'submit', '#gd-add-sub-user-form', function ( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn  = $form.find( '#gd-add-sub-user-btn' );
			var $msg  = $form.find( '#gd-add-sub-user-msg' );
			var $pw   = $form.find( '[name="password"]' );

			$msg.hide().removeClass( 'gd-alert--error gd-alert--success' );

			if ( $pw.val().length < 8 ) {
				$msg.addClass( 'gd-alert--error' ).text( 'Password must be at least 8 characters.' ).show();
				return;
			}

			gdBtnLoading( $btn );

			$.ajax( {
				url:    gdPublic.ajaxUrl,
				type:   'POST',
				data:   {
					action:     'gd_add_sub_user',
					nonce:      gdPublic.subUsersNonce,
					first_name: $.trim( $form.find( '[name="first_name"]' ).val() ),
					last_name:  $.trim( $form.find( '[name="last_name"]' ).val() ),
					username:   $.trim( $form.find( '[name="username"]' ).val() ),
					email:      $.trim( $form.find( '[name="email"]' ).val() ),
					password:   $form.find( '[name="password"]' ).val(),
				},
				success: function ( res ) {
					gdBtnReset( $btn );
					if ( res.success ) {
						$msg.addClass( 'gd-alert--success' ).text( 'Team member added successfully. Reload the page to see them listed.' ).show();
						$form[0].reset();
					} else {
						$msg.addClass( 'gd-alert--error' ).text( res.data && res.data.message ? res.data.message : 'An error occurred.' ).show();
					}
				},
				error: function () {
					gdBtnReset( $btn );
					$msg.addClass( 'gd-alert--error' ).text( 'Network error. Please try again.' ).show();
				},
			} );
		} );

		// Remove sub-user (team member).
		$dashboard.on( 'click', '.gd-remove-sub-user-btn', function () {
			if ( ! window.confirm( 'Remove this team member? Their account will be permanently deleted.' ) ) {
				return;
			}
			var $btn      = $( this );
			var subUserId = $btn.data( 'sub-user-id' );
			gdBtnLoading( $btn );

			$.ajax( {
				url:    gdPublic.ajaxUrl,
				type:   'POST',
				data:   {
					action:      'gd_remove_sub_user',
					nonce:       gdPublic.subUsersNonce,
					sub_user_id: subUserId,
				},
				success: function ( res ) {
					if ( res.success ) {
						gdToast( res.data && res.data.message ? res.data.message : 'Team member removed.', 'success' );
						$( '#gd-sub-user-' + subUserId ).fadeOut( 300, function () { $( this ).remove(); } );
					} else {
						gdBtnReset( $btn );
						gdToast( res.data && res.data.message ? res.data.message : 'An error occurred.', 'error' );
					}
				},
				error: function () {
					gdBtnReset( $btn );
					gdToast( 'Network error. Please try again.', 'error' );
				},
			} );
		} );
	}

	/**
	 * Load available jobs list via AJAX.
	 *
	 * @param {jQuery} $dashboard
	 */
	function gdLoadAvailableJobs( $dashboard ) {
		var $container = $dashboard.find( '#gd-available-jobs-list' );
		if ( ! $container.length ) { return; }

		var activeFilter = $dashboard.find( '.gd-filter-chip--active' ).data( 'filter' ) || '';

		$container.html( '<div class="gd-inline-spinner"><div class="gd-loading-spinner" style="width:32px;height:32px;border-color:var(--gd-border);border-top-color:var(--gd-primary)"></div></div>' );

		gdAjax(
			'gd_get_available_jobs',
			{ job_type: activeFilter },
			function ( data ) {
				$container.html( data.html || '<div class="gd-empty-state"><div class="gd-empty-state__icon">📦</div><p class="gd-empty-state__text">No available jobs in your area.</p></div>' );
				gdFilterAvailableJobCards( $dashboard );
				gdUpdateJobsFilterCount( $dashboard );
			},
			function ( msg ) {
				$container.html( '<p class="gd-text-danger">' + gdEscape( msg ) + '</p>' );
			}
		);
	}

	function gdUpdateJobsFilterCount( $dashboard ) {
		var $count = $dashboard.find( '#gd-job-filter-count' );
		if ( ! $count.length ) { return; }

		var activeFilter = $dashboard.find( '.gd-filter-chip--active' ).data( 'filter' ) || '';
		if ( activeFilter ) {
			$count.text( '1' ).removeClass( 'gd-hidden' );
		} else {
			$count.text( '0' ).addClass( 'gd-hidden' );
		}
	}

	function gdFilterAvailableJobCards( $dashboard ) {
		var term = $.trim( ( $dashboard.find( '#gd-job-search-input' ).val() || '' ).toLowerCase() );
		var $cards = $dashboard.find( '#gd-available-jobs-list .gd-job-card' );
		var visibleCount = 0;

		$cards.each( function () {
			var $card = $( this );
			var haystack = ( $card.attr( 'data-job-search' ) || $card.text() || '' ).toLowerCase();
			var isVisible = ! term || haystack.indexOf( term ) !== -1;
			$card.toggleClass( 'gd-job-card--search-hidden', ! isVisible );
			if ( isVisible ) {
				visibleCount += 1;
			}
		} );

		$dashboard.find( '#gd-available-jobs-list .gd-job-search-empty' ).remove();
		if ( term && $cards.length && ! visibleCount ) {
			$dashboard.find( '#gd-available-jobs-list' ).append(
				'<div class="gd-job-search-empty">No jobs match your search.</div>'
			);
		}
	}

	/**
	 * Open a job detail modal.
	 *
	 * @param {number} jobId
	 */
	function gdOpenJobModal( jobId ) {
		var $overlay = $( '#gd-job-modal-overlay' );
		var $body    = $overlay.find( '.gd-modal__body' );

		$body.html( '<div class="gd-inline-spinner"><div class="gd-loading-spinner" style="width:32px;height:32px;border-color:var(--gd-border);border-top-color:var(--gd-primary)"></div></div>' );
		$overlay.addClass( 'gd-modal-overlay--open' );

		gdAjax(
			'gd_get_job_detail',
			{ job_id: jobId },
			function ( data ) {
				$body.html( data.html );
				gdInitStarInput( $body );
				gdInitConditionalFields( $body );
			},
			function ( msg ) {
				$body.html( '<p class="gd-text-danger">' + gdEscape( msg ) + '</p>' );
			}
		);
	}

	/**
	 * Submit a quote from the quote form.
	 *
	 * @param {jQuery} $form
	 */
	function gdSubmitQuote( $form ) {
		var valid  = true;
		var $amount = $form.find( '[name="amount"]' );
		var amount  = parseFloat( $amount.val() );

		if ( isNaN( amount ) || amount < 1 ) {
			gdFieldError( $amount, 'Please enter a valid amount (minimum $1).' );
			valid = false;
		} else {
			gdClearFieldError( $amount );
		}

		if ( ! valid ) { return; }

		var $btn = $form.find( '[type="submit"]' );
		gdBtnLoading( $btn );

		gdAjax(
			'gd_submit_quote',
			{
				job_id:  $form.find( '[name="job_id"]' ).val(),
				amount:  amount,
				message: $form.find( '[name="message"]' ).val(),
				nonce:   $form.find( '[name="gd_submit_quote_nonce"]' ).val() || gdPublic.nonce,
			},
			function () {
				gdBtnReset( $btn );
				gdToast( 'Quote submitted successfully!', 'success', 5000 );
				$form.closest( '.gd-modal-overlay' ).removeClass( 'gd-modal-overlay--open' );
				$form.trigger( 'reset' );
				// Refresh the my-quotes tab after a short delay.
				setTimeout( function () {
					var $myQuotes = $( '#gd-tab-my-quotes' );
					if ( $myQuotes.length ) {
						gdAjax( 'gd_get_my_quotes', {}, function ( data ) {
							$myQuotes.html( data.html );
						} );
					}
				}, 500 );
			},
			function ( msg ) {
				gdBtnReset( $btn );
				gdToast( msg, 'error' );
			}
		);
	}

	// =========================================================================
	// Messaging
	// =========================================================================

	var gdMessagingPollTimer = null;
	var gdLastMessageId      = 0;

	/**
	 * Return true if the text contains contact details
	 * (phone numbers, email addresses, or URLs).
	 *
	 * Mirrors the patterns used by the PHP contact_filter() method.
	 *
	 * @param {string} text
	 * @return {boolean}
	 */
	function gdHasContactDetails( text ) {
		if ( /(?:\+?\d[\d\s\-().]{7,}\d)/.test( text ) ) { return true; }
		if ( text.indexOf( '@' ) !== -1 ) { return true; }
		if ( /(https?:\/\/|www\.)[^\s]+/.test( text ) ) { return true; }
		return false;
	}

	function gdInitMessaging() {
		var $panel = $( '#gd-messaging-panel' );
		if ( ! $panel.length ) { return; }

		var jobId = $panel.data( 'job-id' );
		if ( ! jobId ) { return; }

		// Restore dismissed state of the contact-policy notice.
		var warnKey = 'gd_cwarn_' + jobId;
		if ( sessionStorage.getItem( warnKey ) ) {
			$( '#gd-contact-policy-notice' ).hide();
		}

		// Dismiss button for the contact-policy notice.
		$( '#gd-contact-policy-notice' ).on( 'click', '.gd-alert__close', function () {
			$( '#gd-contact-policy-notice' ).slideUp( 200 );
			try { sessionStorage.setItem( warnKey, '1' ); } catch ( e ) {}
		} );

		// Initial load.
		gdLoadMessages( jobId, $panel );

		// Poll every 30 seconds.
		gdMessagingPollTimer = setInterval( function () {
			gdLoadMessages( jobId, $panel );
		}, 30000 );

		// Send message.
		$panel.on( 'click', '#gd-send-message-btn', function () {
			gdSendMessage( jobId, $panel );
		} );

		// Send on Ctrl+Enter / Cmd+Enter.
		$panel.on( 'keydown', '#gd-message-input', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 'Enter' ) {
				e.preventDefault();
				gdSendMessage( jobId, $panel );
			}
		} );

	}

	/**
	 * Load messages from the server.
	 *
	 * @param {number} jobId
	 * @param {jQuery} $panel
	 */
	function gdLoadMessages( jobId, $panel ) {
		var participantId = parseInt( $panel.data( 'participant-id' ), 10 ) || 0;
		gdAjax(
			'gd_get_messages',
			{ job_id: jobId, participant_id: participantId, nonce: $panel.data( 'nonce' ) || gdPublic.nonce },
			function ( messages ) {
				var $list = $panel.find( '#gd-message-list' );
				if ( ! Array.isArray( messages ) || ! messages.length ) {
					if ( ! $list.find( '.gd-message-bubble' ).length ) {
						$list.html( '<p class="gd-message-list__empty">No messages yet. Start the conversation!</p>' );
					}
					return;
				}

				// Only re-render if new messages arrived.
				if ( messages[ messages.length - 1 ].id <= gdLastMessageId ) { return; }

				gdLastMessageId = parseInt( messages[ messages.length - 1 ].id, 10 );

				var currentUserId = parseInt( gdPublic.userId || 0, 10 );
				var html          = '';
				var canReport     = parseInt( $panel.data( 'can-report' ), 10 ) === 1;

				$.each( messages, function ( i, msg ) {
					var isMine  = parseInt( msg.sender_id, 10 ) === currentUserId;
					var dir     = isMine ? 'from' : 'to';
					var time    = msg.created_at ? new Date( msg.created_at.replace( ' ', 'T' ) ).toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } ) : '';
					var reportButton = '';

					if ( canReport && ! isMine && msg.id ) {
						reportButton = '<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-report-activity-btn" data-report-type="message" data-job-id="' + gdEscape( String( jobId ) ) + '" data-message-id="' + gdEscape( String( msg.id ) ) + '">⚑ Report</button>';
					}

					html += '<div class="gd-message-bubble gd-message-bubble--' + dir + '">' +
					        ( ! isMine ? '<span class="gd-message-bubble__sender">' + gdEscape( msg.sender_name || 'User' ) + '</span>' : '' ) +
					        '<div class="gd-message-bubble__body">' + gdEscape( msg.message ) + '</div>' +
					        '<span class="gd-message-bubble__time">' + gdEscape( time ) + '</span>' +
					        reportButton +
					        '</div>';
				} );

				$list.html( html );
				gdScrollToBottom( $list );
			},
			function () {
				// Silent fail on polling errors.
			}
		);
	}

	/**
	 * Submit a quote/message report.
	 *
	 * @param {jQuery} $btn
	 */
	function gdReportActivity( $btn ) {
		var reportType = String( $btn.data( 'report-type' ) || '' );
		var jobId      = parseInt( $btn.data( 'job-id' ), 10 ) || 0;
		var quoteId    = parseInt( $btn.data( 'quote-id' ), 10 ) || 0;
		var messageId  = parseInt( $btn.data( 'message-id' ), 10 ) || 0;
		var reason     = window.prompt( gdPublic.reportPrompt || 'Why are you reporting this activity? (optional)' );

		if ( reason === null || ! reportType || ! jobId ) {
			return;
		}
		reason = $.trim( reason );
		if ( reason.length > 1000 ) {
			gdToast( 'Please keep report notes under 1000 characters.', 'error' );
			return;
		}

		gdBtnLoading( $btn );

		gdAjax(
			'gd_report_activity',
			{
				job_id:      jobId,
				report_type: reportType,
				quote_id:    quoteId,
				message_id:  messageId,
				reason:      reason,
			},
			function ( data ) {
				gdBtnReset( $btn );
				gdToast( ( data && data.message ) ? data.message : 'Report submitted.', 'success' );
			},
			function ( msg ) {
				gdBtnReset( $btn );
				gdToast( msg || 'Could not submit report.', 'error' );
			}
		);
	}

	function gdInitActivityReporting() {
		$( document ).on( 'click', '.gd-report-activity-btn', function () {
			gdReportActivity( $( this ) );
		} );
	}

	/**
	 * Send a message.
	 *
	 * @param {number} jobId
	 * @param {jQuery} $panel
	 */
	function gdSendMessage( jobId, $panel ) {
		var $input        = $panel.find( '#gd-message-input' );
		var $btn          = $panel.find( '#gd-send-message-btn' );
		var message       = $.trim( $input.val() );
		var participantId = parseInt( $panel.data( 'participant-id' ), 10 ) || 0;
		var quoteAccepted = parseInt( $panel.data( 'quote-accepted' ), 10 ) === 1;

		if ( ! message ) { return; }

		// Client-side guard: block contact details before a quote is accepted.
		if ( ! quoteAccepted && gdHasContactDetails( message ) ) {
			gdToast( 'Contact details cannot be shared until a quote has been accepted. Please remove any phone numbers, email addresses, @ symbols, or links.', 'error' );
			return;
		}

		gdBtnLoading( $btn );

		gdAjax(
			'gd_send_message',
			{
				job_id:         jobId,
				participant_id: participantId,
				message:        message,
				nonce:          $panel.data( 'nonce' ) || gdPublic.nonce,
			},
			function () {
				gdBtnReset( $btn );
				$input.val( '' );
				gdLoadMessages( jobId, $panel );
			},
			function ( msg ) {
				gdBtnReset( $btn );
				gdToast( msg, 'error' );
			}
		);
	}

	/**
	 * Scroll an element to its bottom.
	 *
	 * @param {jQuery} $el
	 */
	function gdScrollToBottom( $el ) {
		$el.scrollTop( $el[ 0 ].scrollHeight );
	}

	// =========================================================================
	// Wallet Top-up
	// =========================================================================

	function gdInitWalletTopup() {
		var $form = $( '#gd-topup-form' );
		if ( ! $form.length ) { return; }

		$( '.gd-amount-preset' ).on( 'click', function () {
			$( '#gd-topup-amount' ).val( $( this ).data( 'amount' ) ).trigger( 'focus' );
		} );

		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			var $btn    = $form.find( '[type="submit"]' );
			var $amount = $form.find( '[name="amount"]' );
			var amount  = parseFloat( $amount.val() );

			if ( isNaN( amount ) || amount < 10 ) {
				gdFieldError( $amount, 'Minimum top-up amount is $10.' );
				return;
			}
			if ( amount > 10000 ) {
				gdFieldError( $amount, 'Maximum top-up amount is $10,000.' );
				return;
			}
			gdClearFieldError( $amount );

			gdBtnLoading( $btn );

			gdAjax(
				'gd_stripe_topup',
				{
					amount: amount,
					nonce:  $form.find( '[name="gd_wallet_topup_nonce"]' ).val() || gdPublic.nonce,
				},
				function ( data ) {
					if ( data.session_url ) {
						window.location.href = data.session_url;
					} else {
						gdBtnReset( $btn );
						gdToast( 'Could not create payment session.', 'error' );
					}
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
		} );
	}

	/**
	 * Handle ?gd_topup_success and ?gd_topup_cancelled query params.
	 */
	function gdHandleTopupQueryParams() {
		var params = new URLSearchParams( window.location.search );
		if ( params.get( 'gd_topup_success' ) === '1' ) {
			gdToast( 'Wallet top-up successful! Your balance has been updated.', 'success', 7000 );
		} else if ( params.get( 'gd_topup_cancelled' ) === '1' ) {
			gdToast( 'Payment was cancelled. No charge was made.', 'info', 5000 );
		}
	}

	// =========================================================================
	// Star Rating Input
	// =========================================================================

	function gdInitStarInput( $context ) {
		$context = $context || $( document );

		$context.find( '.gd-star-input' ).each( function () {
			var $widget = $( this );
			var $hidden = $widget.next( 'input[type="hidden"]' );
			if ( ! $hidden.length ) {
				$hidden = $widget.siblings( 'input[type="hidden"][name="rating"]' );
			}
			if ( ! $hidden.length ) {
				$hidden = $widget.closest( 'form' ).find( 'input[type="hidden"][name="rating"]' );
			}

			$widget.find( 'label' ).on( 'click', function () {
				var val = $( this ).prev( 'input[type="radio"]' ).val();
				$hidden.val( val );
			} );

			$widget.find( 'input[type="radio"]' ).on( 'change', function () {
				$hidden.val( $( this ).val() );
			} );
		} );
	}

	// =========================================================================
	// File Upload Preview
	// =========================================================================

	function gdInitFileUploadPreview() {
		$( document ).on( 'change', '.gd-upload-area input[type="file"]', function () {
			var $input   = $( this );
			var $preview = $input.closest( '.gd-upload-area' ).next( '.gd-upload-preview' );
			if ( ! $preview.length ) {
				$preview = $( '<div class="gd-upload-preview"></div>' ).insertAfter( $input.closest( '.gd-upload-area' ) );
			}
			$preview.empty();

			var files = this.files;
			for ( var i = 0; i < files.length; i++ ) {
				var file = files[ i ];
				var $item = $( '<div class="gd-upload-preview__item"></div>' );

				if ( file.type.match( 'image.*' ) ) {
					var reader = new FileReader();
					( function ( $i ) {
						reader.onload = function ( ev ) {
							$i.html( '<img src="' + ev.target.result + '" alt="">' );
						};
					} )( $item );
					reader.readAsDataURL( file );
				} else {
					$item.html( '<div style="padding:8px;font-size:11px;word-break:break-all;">' + gdEscape( file.name ) + '</div>' );
				}

				$preview.append( $item );
			}
		} );

		// Drag and drop.
		$( document ).on( 'dragover', '.gd-upload-area', function ( e ) {
			e.preventDefault();
			$( this ).addClass( 'gd-upload-area--dragover' );
		} );

		$( document ).on( 'dragleave drop', '.gd-upload-area', function ( e ) {
			e.preventDefault();
			$( this ).removeClass( 'gd-upload-area--dragover' );
		} );

		$( document ).on( 'drop', '.gd-upload-area', function ( e ) {
			e.preventDefault();
			var dt    = e.originalEvent.dataTransfer;
			var $input = $( this ).find( 'input[type="file"]' );
			if ( dt && dt.files && $input.length ) {
				$input[ 0 ].files = dt.files;
				$input.trigger( 'change' );
			}
		} );

		// Click-to-browse.
		$( document ).on( 'click', '.gd-upload-area', function () {
			var input = $( this ).find( 'input[type="file"]' )[ 0 ];
			if ( input ) { input.click(); }
		} );
	}

	// =========================================================================
	// Mover Registration Multi-step
	// =========================================================================

	function gdInitMoverRegistration() {
		var $form = $( '#gd-mover-registration-form' );
		if ( ! $form.length ) { return; }

		// The progress bar lives outside <form> in the wrapper div.
		var $wrap = $form.closest( '#gd-mover-registration-form-wrap' );

		var currentStep = 1;
		var totalSteps  = $form.find( '.gd-reg-section' ).length;

		function showRegStep( step ) {
			$form.find( '.gd-reg-section' ).hide().removeClass( 'gd-reg-section--active' );
			$form.find( '.gd-reg-section[data-step="' + step + '"]' ).show().addClass( 'gd-reg-section--active' );

			// Update progress bar (lives in the wrapper, not inside <form>).
			$wrap.find( '.gd-progress-step' ).each( function () {
				var s = parseInt( $( this ).data( 'step' ), 10 );
				$( this ).removeClass( 'gd-progress-step--active gd-progress-step--done' );
				if ( s === step )    { $( this ).addClass( 'gd-progress-step--active' ); }
				else if ( s < step ) { $( this ).addClass( 'gd-progress-step--done' ); }
			} );

			$wrap.find( '.gd-progress-connector' ).each( function ( idx ) {
				$( this ).toggleClass( 'gd-progress-connector--done', idx < step - 1 );
			} );

			$form.find( '#gd-reg-prev' ).toggle( step > 1 );

			var $next   = $form.find( '#gd-reg-next' );
			var $submit = $form.find( '#gd-reg-submit' );
			if ( step === totalSteps ) {
				$next.hide();
				$submit.show();
			} else {
				$next.show();
				$submit.hide();
			}
		}

		function validateRegStep( step ) {
			var valid = true;
			var $section = $form.find( '.gd-reg-section[data-step="' + step + '"]' );

			$section.find( '[required]:visible' ).each( function () {
				var $f = $( this );
				if ( $f.is( 'input[type="checkbox"]' ) ) {
					if ( ! $f.is( ':checked' ) ) {
						gdFieldError( $f, gdEscape( $f.closest( 'label' ).text().trim() ) + ' is required.' );
						valid = false;
					} else {
						gdClearFieldError( $f );
					}
					return;
				}
				if ( ! $.trim( $f.val() ) ) {
					gdFieldError( $f, $f.closest( '.gd-field-group' ).find( 'label' ).first().text().replace( /\*/g, '' ).trim() + ' is required.' );
					valid = false;
				} else {
					gdClearFieldError( $f );
				}
			} );

			// File inputs are hidden via CSS so :visible skips them — check explicitly.
			$section.find( 'input[type="file"][required]' ).each( function () {
				var $f        = $( this );
				var $area     = $f.closest( '.gd-upload-area' );
				var label     = $f.closest( '.gd-doc-upload' ).find( '.gd-doc-upload__label' ).first().text().replace( /\*/g, '' ).trim();
				var errorMsg  = ( label || 'This document' ) + ' is required.';
				var $existing = $area.next( '.gd-field-error' );
				if ( ! $f.val() ) {
					$area.addClass( 'gd-field--error' );
					if ( ! $existing.length ) {
						$( '<span class="gd-field-error"></span>' ).text( errorMsg ).insertAfter( $area );
					} else {
						$existing.text( errorMsg );
					}
					valid = false;
				} else {
					$area.removeClass( 'gd-field--error' );
					$existing.remove();
				}
			} );

			// Ensure each address field was confirmed via autocomplete/geocoding.
			if ( ! validateAddressFields( $section ) ) {
				valid = false;
			}

			// Password validation.
			if ( step === 1 ) {
				var $pw   = $section.find( '[name="password"]' );
				var $cpw  = $section.find( '[name="confirm_password"]' );
				if ( $pw.length && $pw.val().length < 8 ) {
					gdFieldError( $pw, 'Password must be at least 8 characters.' );
					valid = false;
				}
				if ( $pw.length && $cpw.length && $pw.val() !== $cpw.val() ) {
					gdFieldError( $cpw, 'Passwords do not match.' );
					valid = false;
				}
			}

			return valid;
		}

		$form.on( 'click', '#gd-reg-next', function () {
			if ( validateRegStep( currentStep ) ) {
				currentStep++;
				showRegStep( currentStep );
				$( 'html, body' ).animate( { scrollTop: $form.offset().top - 20 }, 300 );
			}
		} );

		$form.on( 'click', '#gd-reg-prev', function () {
			if ( currentStep > 1 ) {
				currentStep--;
				showRegStep( currentStep );
				$( 'html, body' ).animate( { scrollTop: $form.offset().top - 20 }, 300 );
			}
		} );

		$form.on( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! validateRegStep( currentStep ) ) { return; }

			var $btn = $form.find( '#gd-reg-submit' );
			gdBtnLoading( $btn );

			var formData = new FormData( this );
			formData.append( 'action', 'gd_register_mover' );
			// Note: the form already contains the correct wp_nonce_field
			// for 'gd_mover_registration' - do not overwrite it with gdPublic.nonce.

			$.ajax( {
				url:         gdPublic.ajaxUrl,
				type:        'POST',
				data:        formData,
				contentType: false,
				processData: false,
				dataType:    'json',
				success: function ( response ) {
					gdBtnReset( $btn );
					if ( response.success ) {
						if ( gdPublic.moverRegRedirectUrl ) {
							window.location.href = gdPublic.moverRegRedirectUrl;
						} else {
							$( '#gd-mover-registration-form-wrap' ).hide();
							$( '#gd-registration-success' ).show();
							$( 'html, body' ).animate( { scrollTop: $( '#gd-registration-success' ).offset().top - 20 }, 300 );
						}
					} else {
						var msg = ( response.data && response.data.message ) ? response.data.message : 'Registration failed.';
						gdToast( msg, 'error' );
					}
				},
				error: function () {
					gdBtnReset( $btn );
					gdToast( 'Network error. Please try again.', 'error' );
				},
			} );
		} );

		// "Select all" for job types.
		$form.on( 'change', '#gd-reg-select-all-jobs', function () {
			var checked = $( this ).is( ':checked' );
			$form.find( 'input[name="job_types[]"]' ).prop( 'checked', checked );
		} );
		$form.on( 'change', 'input[name="job_types[]"]', function () {
			var total   = $form.find( 'input[name="job_types[]"]' ).length;
			var checked = $form.find( 'input[name="job_types[]"]:checked' ).length;
			$form.find( '#gd-reg-select-all-jobs' ).prop( 'checked', checked === total ).prop( 'indeterminate', checked > 0 && checked < total );
		} );

		gdInitLocationFields( $form );
		showRegStep( 1 );
	}

	// =========================================================================
	// Modal management
	// =========================================================================

	function gdInitModals() {
		// Move every modal overlay to <body> so it is never trapped inside a
		// Divi (or other theme) stacking context created by CSS transform /
		// will-change / filter on a parent element.  position:fixed elements
		// inside such a context are clipped to it regardless of z-index.
		$( '.gd-modal-overlay' ).appendTo( 'body' );

		// Close on overlay click.
		$( document ).on( 'click', '.gd-modal-overlay', function ( e ) {
			if ( $( e.target ).is( '.gd-modal-overlay' ) ) {
				$( this ).removeClass( 'gd-modal-overlay--open' );
			}
		} );

		// Close button.
		$( document ).on( 'click', '.gd-modal__close', function () {
			$( this ).closest( '.gd-modal-overlay' ).removeClass( 'gd-modal-overlay--open' );
		} );

		// Close on Escape key.
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				$( '.gd-modal-overlay--open' ).removeClass( 'gd-modal-overlay--open' );
				if ( gdMessagingPollTimer ) {
					clearInterval( gdMessagingPollTimer );
					gdMessagingPollTimer = null;
				}
			}
		} );
	}

	// =========================================================================
	// Quote fee preview
	// =========================================================================

	function gdInitQuoteFeePreview() {
		$( document ).on( 'input change', '.gd-quote-amount-input', function () {
			var $input  = $( this );
			var amount  = parseFloat( $input.val() );
			var $feeEl  = $input.closest( 'form' ).find( '.gd-fee-preview' );
			var pct     = parseFloat( $feeEl.data( 'fee-pct' ) || 10 );

			if ( $feeEl.length && ! isNaN( amount ) && amount > 0 ) {
				var fee = ( amount * pct / 100 ).toFixed( 2 );
				$feeEl.text( pct + '% fee ≈ $' + fee );
			} else if ( $feeEl.length ) {
				$feeEl.text( '' );
			}
		} );
	}

	// =========================================================================
	// Read-more toggle for job card additional information
	// =========================================================================

	$( document ).on( 'click', '.gd-read-more-btn', function () {
		var $btn   = $( this );
		var $short = $btn.prevAll( '.gd-read-more-short' ).first();
		var $full  = $btn.prevAll( '.gd-read-more-full' ).first();
		if ( $btn.hasClass( 'gd-read-more-btn--expanded' ) ) {
			$full.addClass( 'gd-hidden' );
			$short.removeClass( 'gd-hidden' );
			$btn.text( gdPublic.readMore || 'Read more' ).removeClass( 'gd-read-more-btn--expanded' );
		} else {
			$short.addClass( 'gd-hidden' );
			$full.removeClass( 'gd-hidden' );
			$btn.text( gdPublic.readLess || 'Read less' ).addClass( 'gd-read-more-btn--expanded' );
		}
	} );

	// =========================================================================
	// Lightbox for job photo galleries
	// =========================================================================

	function gdInitLightbox() {
		if ( ! $( '#gd-lightbox' ).length ) {
			$( 'body' ).append(
				'<div id="gd-lightbox" class="gd-lightbox-overlay" role="dialog" aria-modal="true">' +
				'<button class="gd-lightbox__close" aria-label="Close">&times;</button>' +
				'<button class="gd-lightbox__prev" aria-label="Previous">&#8249;</button>' +
				'<div class="gd-lightbox__img-wrap"><img class="gd-lightbox__img" src="" alt=""></div>' +
				'<button class="gd-lightbox__next" aria-label="Next">&#8250;</button>' +
				'<div class="gd-lightbox__counter"></div>' +
				'</div>'
			);
		}

		var $lb      = $( '#gd-lightbox' );
		var $img     = $lb.find( '.gd-lightbox__img' );
		var $counter = $lb.find( '.gd-lightbox__counter' );
		var $prev    = $lb.find( '.gd-lightbox__prev' );
		var $next    = $lb.find( '.gd-lightbox__next' );
		var images   = [];
		var current  = 0;

		function gdLightboxShow( idx ) {
			current = idx;
			$img.attr( 'src', images[ idx ] );
			$img.attr( 'alt', 'Job photo ' + ( idx + 1 ) + ' of ' + images.length );
			if ( images.length > 1 ) {
				$counter.text( ( idx + 1 ) + ' / ' + images.length );
				$prev.toggle( idx > 0 );
				$next.toggle( idx < images.length - 1 );
			} else {
				$counter.text( '' );
				$prev.hide();
				$next.hide();
			}
			$lb.addClass( 'gd-lightbox-overlay--open' );
		}

		function gdLightboxClose() {
			$lb.removeClass( 'gd-lightbox-overlay--open' );
			$img.attr( 'src', '' );
			images = [];
		}

		$( document ).off( 'click.gdLightbox', '.gd-photo-gallery__link' ).on( 'click.gdLightbox', '.gd-photo-gallery__link', function ( e ) {
			e.preventDefault();
			var $gallery = $( this ).closest( '.gd-photo-gallery' );
			images = [];
			$gallery.find( '.gd-photo-gallery__link' ).each( function () {
				images.push( $( this ).attr( 'href' ) );
			} );
			var idx = $gallery.find( '.gd-photo-gallery__link' ).index( this );
			gdLightboxShow( idx < 0 ? 0 : idx );
		} );

		$lb.on( 'click', '.gd-lightbox__prev', function () {
			if ( current > 0 ) { gdLightboxShow( current - 1 ); }
		} );

		$lb.on( 'click', '.gd-lightbox__next', function () {
			if ( current < images.length - 1 ) { gdLightboxShow( current + 1 ); }
		} );

		$lb.on( 'click', '.gd-lightbox__close', gdLightboxClose );

		$lb.on( 'click', function ( e ) {
			if ( $( e.target ).is( '#gd-lightbox' ) ) { gdLightboxClose(); }
		} );

		$( document ).on( 'keydown.gdLightbox', function ( e ) {
			if ( ! $lb.hasClass( 'gd-lightbox-overlay--open' ) ) { return; }
			if ( e.key === 'ArrowLeft' && current > 0 ) { gdLightboxShow( current - 1 ); }
			if ( e.key === 'ArrowRight' && current < images.length - 1 ) { gdLightboxShow( current + 1 ); }
			if ( e.key === 'Escape' ) { gdLightboxClose(); }
		} );
	}

	// =========================================================================
	// Time-since live updater
	// =========================================================================

	/**
	 * Format a number of seconds into a human-readable "time since" string,
	 * matching the PHP Go_Deliver_Jobs::time_since() output.
	 *
	 * @param {number} diffSecs Elapsed seconds (>= 0).
	 * @return {string}
	 */
	function gdTimeSinceFormat( diffSecs ) {
		if ( diffSecs < 60 ) {
			return 'Just now';
		}
		if ( diffSecs < 3600 ) {
			var mins = Math.floor( diffSecs / 60 );
			return mins === 1 ? '1 minute ago' : mins + ' minutes ago';
		}
		if ( diffSecs < 86400 ) {
			var hours = Math.floor( diffSecs / 3600 );
			return hours === 1 ? '1 hour ago' : hours + ' hours ago';
		}
		var days = Math.floor( diffSecs / 86400 );
		return days === 1 ? '1 day ago' : days + ' days ago';
	}

	/**
	 * Find every `.gd-job-card__time-since[data-gd-posted-utc]` element on the
	 * page (including those injected via AJAX) and refresh the displayed text.
	 * Runs immediately on DOM-ready and then every 30 seconds, stopping
	 * automatically once no matching elements remain.
	 */
	function gdInitTimeSince() {
		function updateAll() {
			var $elements = $( '.gd-job-card__time-since[data-gd-posted-utc]' );
			if ( ! $elements.length ) { return; }
			var nowUtc = Math.floor( Date.now() / 1000 );
			$elements.each( function () {
				var postedUtc = parseInt( $( this ).attr( 'data-gd-posted-utc' ), 10 );
				if ( ! postedUtc ) { return; }
				var diff = Math.max( 0, nowUtc - postedUtc );
				var text = gdTimeSinceFormat( diff );
				// job-list.php wraps in "Posted %s"; customer-dashboard uses raw text.
				var $el = $( this );
				if ( $el.closest( '#gd-job-list, #gd-available-jobs-list' ).length ) {
					$el.text( 'Posted ' + text );
				} else {
					$el.text( text );
				}
			} );
		}

		updateAll();
		setInterval( updateAll, 30000 );
	}

	// =========================================================================
	// Mover dashboard tour
	// =========================================================================

	function gdInitMoverTour() {
		if ( ! $( '#gd-tour-overlay' ).length ) {
			return;
		}

		var steps = [
			{
				target  : null,
				title   : 'Welcome to your dashboard! 👋',
				body    : 'This quick 3-step tour highlights the key sections. Use Next → to move forward, ← Back to revisit a step, or ✕ to close at any time.',
			},
			{
				target  : '#gd-tour-nav-messages',
				title   : 'Messages',
				body    : 'Use Messages to speak with customers. All your conversations with job requesters live here.',
			},
			{
				target  : '#gd-tour-nav-my-jobs',
				title   : 'My Jobs',
				body    : 'Use My Jobs to track quotes and accepted jobs. See everything from pending quotes to active moves at a glance.',
			},
			{
				target  : '#gd-tour-nav-settings',
				title   : 'Settings',
				body    : 'Use Settings to update your profile, photos, service areas, and business details so customers can find you.',
			},
		];

		var currentStep = 0;
		var viewportPadding = 10;

		function centerTooltip() {
			var $tooltip = $( '#gd-tour-tooltip' );
			var ttWidth  = $tooltip.outerWidth();
			var ttHeight = $tooltip.outerHeight();
			var winWidth = $( window ).width();
			var winHeight = $( window ).height();
			var top  = ( winHeight / 2 ) - ( ttHeight / 2 );
			var left = ( winWidth / 2 ) - ( ttWidth / 2 );
			if ( top < viewportPadding ) { top = viewportPadding; }
			if ( top + ttHeight > winHeight - viewportPadding ) { top = winHeight - ttHeight - viewportPadding; }
			if ( left < viewportPadding ) { left = viewportPadding; }
			$tooltip.css( { top: top, left: left } );
		}

		function positionTooltip( $target ) {
			if ( ! $target || ! $target.length ) {
				return;
			}
			var $tooltip   = $( '#gd-tour-tooltip' );
			var rect       = $target[0].getBoundingClientRect();
			var ttWidth    = $tooltip.outerWidth();
			var ttHeight   = $tooltip.outerHeight();
			var winWidth   = $( window ).width();
			var winHeight  = $( window ).height();

			var top  = rect.bottom + 12;
			var left = rect.left + ( rect.width / 2 ) - ( ttWidth / 2 );

			// Keep within viewport.
			if ( left < viewportPadding ) { left = viewportPadding; }
			if ( left + ttWidth > winWidth - viewportPadding ) { left = winWidth - ttWidth - viewportPadding; }
			if ( top + ttHeight > winHeight - viewportPadding ) { top = rect.top - ttHeight - 12; }
			if ( top < viewportPadding ) { top = viewportPadding; }

			$tooltip.css( { top: top, left: left } );
		}

		function highlightTarget( $target ) {
			$( '.gd-tour-highlight' ).removeClass( 'gd-tour-highlight' );
			if ( $target && $target.length ) {
				$target.addClass( 'gd-tour-highlight' );
			}
		}

		function showStep( index ) {
			var step = steps[ index ];
			if ( ! step ) {
				return;
			}

			var $target = $( step.target );

			$( '#gd-tour-step-label' ).text( ( index + 1 ) + ' / ' + steps.length );
			$( '#gd-tour-title' ).text( step.title );
			$( '#gd-tour-body' ).text( step.body );

			// Prev button visibility.
			$( '#gd-tour-prev' ).toggle( index > 0 );

			// Next vs Finish.
			if ( index === steps.length - 1 ) {
				$( '#gd-tour-next' ).hide();
				$( '#gd-tour-finish' ).show();
			} else {
				$( '#gd-tour-next' ).show();
				$( '#gd-tour-finish' ).hide();
			}

			highlightTarget( $target );

			$( '#gd-tour-overlay' ).fadeIn( 200 );

			// Scroll target into view then position tooltip.
			if ( $target && $target.length ) {
				$( 'html, body' ).animate( { scrollTop: $target.offset().top - 120 }, 300, function () {
					positionTooltip( $target );
				} );
			} else {
				centerTooltip();
			}
		}

		function startTour() {
			currentStep = 0;
			showStep( currentStep );
		}

		var tourStorageKey = 'gd_tour_seen_' + ( gdPublic.userId || '0' );

		function markTourSeen() {
			try { localStorage.setItem( tourStorageKey, '1' ); } catch ( e ) {}
			$.post( gdPublic.ajaxUrl, {
				action : 'gd_mover_dismiss_tour',
				nonce  : gdPublic.nonce,
			} );
		}

		function isTourSeen() {
			if ( gdPublic.moverTourSeen ) { return true; }
			try { return !! localStorage.getItem( tourStorageKey ); } catch ( e ) { return false; }
		}

		function endTour( completed ) {
			$( '.gd-tour-highlight' ).removeClass( 'gd-tour-highlight' );
			$( '#gd-tour-overlay' ).fadeOut( 200 );

			if ( completed ) {
				$( '#gd-tour-banner' ).slideUp( 300 );
				markTourSeen();
			}
		}

		// ---- Bind events ----

		$( '#gd-start-tour' ).on( 'click', function () {
			startTour();
		} );

		$( '.gd-replay-tour-btn' ).on( 'click', function () {
			startTour();
		} );

		$( '#gd-dismiss-tour-banner' ).on( 'click', function () {
			$( '#gd-tour-banner' ).slideUp( 300 );
			markTourSeen();
		} );

		$( '#gd-tour-close' ).on( 'click', function () {
			endTour( false );
		} );

		$( '#gd-tour-next' ).on( 'click', function () {
			currentStep++;
			showStep( currentStep );
		} );

		$( '#gd-tour-prev' ).on( 'click', function () {
			currentStep--;
			showStep( currentStep );
		} );

		$( '#gd-tour-finish' ).on( 'click', function () {
			endTour( true );
		} );

		// Reposition on resize.
		$( window ).on( 'resize.gdTour', function () {
			if ( $( '#gd-tour-overlay' ).is( ':visible' ) ) {
				var $target = $( steps[ currentStep ].target );
				if ( $target && $target.length ) {
					positionTooltip( $target );
				} else {
					centerTooltip();
				}
			}
		} );

		// Auto-start for first-time visitors.
		if ( ! isTourSeen() ) {
			setTimeout( startTour, 800 );
		}
	}

	// =========================================================================
	// Document ready
	// =========================================================================

	$( function () {
		gdInitModals();
		gdInitJobForm();
		gdInitCustomerDashboard();
		gdInitMoverDashboard();
		gdInitMessaging();
		gdInitWalletTopup();
		gdHandleTopupQueryParams();
		gdInitStarInput();
		gdInitFileUploadPreview();
		gdInitMoverRegistration();
		gdInitConditionalFields();
		gdInitQuoteFeePreview();
		gdInitLightbox();
		gdInitTimeSince();
		gdInitMoverTour();
		gdInitActivityReporting();
	} );

} )( jQuery );
