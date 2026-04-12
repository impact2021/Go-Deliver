/* global gdPublic */
/**
 * Go Deliver – Public-Facing JavaScript
 * Version: 1.2.0
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
		data.nonce   = gdPublic.nonce;

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
	// Multi-step Job Form Wizard
	// =========================================================================

	function gdInitJobForm() {
		var $form = $( '#gd-job-form' );
		if ( ! $form.length ) { return; }

		var currentStep = 1;
		var totalSteps  = $form.find( '.gd-form-section' ).length;

		function showStep( step ) {
			$form.find( '.gd-form-section' ).hide().removeClass( 'gd-form-section--active' );
			$form.find( '.gd-form-section[data-step="' + step + '"]' ).show().addClass( 'gd-form-section--active' );

			// Update step indicators.
			$form.find( '.gd-step' ).each( function () {
				var s = parseInt( $( this ).data( 'step' ), 10 );
				$( this ).removeClass( 'gd-step--active gd-step--done' );
				if ( s === step ) { $( this ).addClass( 'gd-step--active' ); }
				else if ( s < step ) { $( this ).addClass( 'gd-step--done' ); }
			} );

			// Prev button.
			$form.find( '#gd-job-prev' ).toggle( step > 1 );

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

		// Next button.
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

		// Tab switching.
		$dashboard.on( 'click', '.gd-tab', function () {
			var target = $( this ).data( 'tab' );
			$dashboard.find( '.gd-tab' ).removeClass( 'gd-tab--active' );
			$( this ).addClass( 'gd-tab--active' );
			$dashboard.find( '.gd-tab-panel' ).removeClass( 'gd-tab-panel--active' ).hide();
			$dashboard.find( '#gd-tab-' + target ).addClass( 'gd-tab-panel--active' ).show();
		} );

		// Show job detail inline.
		$dashboard.on( 'click', '.gd-job-view-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			gdLoadJobDetail( jobId );
		} );

		// Cancel job.
		$dashboard.on( 'click', '.gd-job-cancel-btn', function () {
			var jobId = $( this ).data( 'job-id' );
			if ( ! window.confirm( 'Are you sure you want to cancel this job? This cannot be undone.' ) ) { return; }
			var $btn = $( this );
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
			var rating = $f.find( '[name="rating"]' ).val();
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

	function gdInitMoverDashboard() {
		var $dashboard = $( '#gd-mover-dashboard' );
		if ( ! $dashboard.length ) { return; }

		// Tab switching.
		$dashboard.on( 'click', '.gd-tab', function () {
			var target = $( this ).data( 'tab' );
			$dashboard.find( '.gd-tab' ).removeClass( 'gd-tab--active' );
			$( this ).addClass( 'gd-tab--active' );
			$dashboard.find( '.gd-tab-panel' ).removeClass( 'gd-tab-panel--active' ).hide();
			$dashboard.find( '#gd-tab-' + target ).addClass( 'gd-tab-panel--active' ).show();

			// Lazy-load available jobs when that tab is activated.
			if ( target === 'available-jobs' ) {
				gdLoadAvailableJobs( $dashboard );
			}

			// Init location autocomplete when the profile tab is first opened.
			if ( target === 'profile' ) {
				gdInitLocationFields( $dashboard.find( '#gd-tab-profile' ) );
			}
		} );

		// Load available jobs on page load if that tab is active.
		if ( $dashboard.find( '#gd-tab-available-jobs' ).is( ':visible' ) ) {
			gdLoadAvailableJobs( $dashboard );
		}

		// Filter chips.
		$dashboard.on( 'click', '.gd-filter-chip', function () {
			$( this ).siblings( '.gd-filter-chip' ).removeClass( 'gd-filter-chip--active' );
			$( this ).addClass( 'gd-filter-chip--active' );
			gdLoadAvailableJobs( $dashboard );
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
					first_name:  $.trim( $form.find( '[name="first_name"]' ).val() ),
					last_name:   $.trim( $form.find( '[name="last_name"]' ).val() ),
					email:       $.trim( $form.find( '[name="email"]' ).val() ),
					phone:       $.trim( $form.find( '[name="phone"]' ).val() ),
					base_suburb: $.trim( $form.find( '[name="base_suburb"]' ).val() ),
					base_lat:    $.trim( $form.find( '[name="base_lat"]' ).val() ),
					base_lng:    $.trim( $form.find( '[name="base_lng"]' ).val() ),
					radius:      $.trim( $form.find( '[name="radius"]' ).val() ),
					job_types:   jobTypes,
				},
				function ( data ) {
					gdBtnReset( $btn );
					gdToast( data.message || 'Profile updated.', 'success' );
				},
				function ( msg ) {
					gdBtnReset( $btn );
					gdToast( msg, 'error' );
				}
			);
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
			},
			function ( msg ) {
				$container.html( '<p class="gd-text-danger">' + gdEscape( msg ) + '</p>' );
			}
		);
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

	function gdInitMessaging() {
		var $panel = $( '#gd-messaging-panel' );
		if ( ! $panel.length ) { return; }

		var jobId = $panel.data( 'job-id' );
		if ( ! jobId ) { return; }

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
		gdAjax(
			'gd_get_messages',
			{ job_id: jobId },
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

				$.each( messages, function ( i, msg ) {
					var isMine  = parseInt( msg.sender_id, 10 ) === currentUserId;
					var dir     = isMine ? 'from' : 'to';
					var time    = msg.created_at ? new Date( msg.created_at.replace( ' ', 'T' ) ).toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } ) : '';

					html += '<div class="gd-message-bubble gd-message-bubble--' + dir + '">' +
					        ( ! isMine ? '<span class="gd-message-bubble__sender">' + gdEscape( msg.sender_name || 'User' ) + '</span>' : '' ) +
					        '<div class="gd-message-bubble__body">' + gdEscape( msg.message ) + '</div>' +
					        '<span class="gd-message-bubble__time">' + gdEscape( time ) + '</span>' +
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
	 * Send a message.
	 *
	 * @param {number} jobId
	 * @param {jQuery} $panel
	 */
	function gdSendMessage( jobId, $panel ) {
		var $input   = $panel.find( '#gd-message-input' );
		var $btn     = $panel.find( '#gd-send-message-btn' );
		var message  = $.trim( $input.val() );

		if ( ! message ) { return; }

		gdBtnLoading( $btn );

		gdAjax(
			'gd_send_message',
			{
				job_id:  jobId,
				message: message,
				nonce:   $panel.data( 'nonce' ) || gdPublic.nonce,
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

		var currentStep = 1;
		var totalSteps  = $form.find( '.gd-reg-section' ).length;

		function showRegStep( step ) {
			$form.find( '.gd-reg-section' ).hide().removeClass( 'gd-reg-section--active' );
			$form.find( '.gd-reg-section[data-step="' + step + '"]' ).show().addClass( 'gd-reg-section--active' );

			// Update progress bar.
			$form.find( '.gd-progress-step' ).each( function () {
				var s = parseInt( $( this ).data( 'step' ), 10 );
				$( this ).removeClass( 'gd-progress-step--active gd-progress-step--done' );
				if ( s === step )    { $( this ).addClass( 'gd-progress-step--active' ); }
				else if ( s < step ) { $( this ).addClass( 'gd-progress-step--done' ); }
			} );

			$form.find( '.gd-progress-connector' ).each( function ( idx ) {
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

			// Password confirmation.
			if ( step === 1 ) {
				var $pw   = $section.find( '[name="password"]' );
				var $cpw  = $section.find( '[name="confirm_password"]' );
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

		gdInitLocationFields( $form );
		showRegStep( 1 );
	}

	// =========================================================================
	// Modal management
	// =========================================================================

	function gdInitModals() {
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
	} );

} )( jQuery );
