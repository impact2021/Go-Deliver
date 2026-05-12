<?php
/**
 * Front-end debug panel for Go Deliver (admin-only).
 *
 * Renders a floating panel in wp_footer that shows:
 *  - AJAX log (request + response intercepted via jQuery global events)
 *  - JavaScript error log
 *  - Server info (plugin options, user, PHP/WP versions, last PHP error)
 *
 * Visibility is controlled by the gd_debug_panel option (on by default).
 * Only rendered for users with manage_options capability.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Debug
 */
class Go_Deliver_Debug {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		if ( ! $this->should_show() ) {
			return;
		}

		// Front-end and admin pages use different footer hooks.
		add_action( 'wp_footer',    array( $this, 'render_panel' ), 9999 );
		add_action( 'admin_footer', array( $this, 'render_panel' ), 9999 );
		add_action( 'wp_ajax_gd_debug_info', array( $this, 'ajax_debug_info' ) );
	}

	/**
	 * Returns true when the panel should be rendered.
	 */
	private function should_show() {
		return current_user_can( 'manage_options' )
			&& (bool) get_option( 'gd_debug_panel', 1 );
	}

	/**
	 * AJAX: return server-side debug information as JSON.
	 */
	public function ajax_debug_info() {
		check_ajax_referer( 'gd_debug_info', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$last_error = error_get_last();

		$options = array(
			'gd_fee_percentage'             => get_option( 'gd_fee_percentage', '—' ),
			'gd_job_expiry_days'            => get_option( 'gd_job_expiry_days', '—' ),
			'gd_quote_expiry_days'          => get_option( 'gd_quote_expiry_days', '—' ),
			'gd_job_form_page_id'           => get_option( 'gd_job_form_page_id', '—' ),
			'gd_customer_dashboard_page_id' => get_option( 'gd_customer_dashboard_page_id', '—' ),
			'gd_mover_dashboard_page_id'    => get_option( 'gd_mover_dashboard_page_id', '—' ),
			'gd_messaging_page_id'          => get_option( 'gd_messaging_page_id', '—' ),
			'gd_wallet_page_id'             => get_option( 'gd_wallet_page_id', '—' ),
			'gd_job_redirect_page_id'       => get_option( 'gd_job_redirect_page_id', '—' ),
			'gd_mover_reg_redirect_page_id' => get_option( 'gd_mover_reg_redirect_page_id', '—' ),
			'gd_mover_terms_page_id'        => get_option( 'gd_mover_terms_page_id', '—' ),
			'gd_customer_terms_page_id'     => get_option( 'gd_customer_terms_page_id', '—' ),
			'gd_stripe_publishable_key'     => get_option( 'gd_stripe_publishable_key' ) ? '(set)' : '(not set)',
			'gd_stripe_secret_key'          => get_option( 'gd_stripe_secret_key' ) ? '(set)' : '(not set)',
			'gd_google_maps_api_key'        => get_option( 'gd_google_maps_api_key' ) ? '(set)' : '(not set)',
			'gd_debug_panel'                => get_option( 'gd_debug_panel', 1 ),
		);

		$user    = wp_get_current_user();
		$uploads = wp_upload_dir();

		wp_send_json_success(
			array(
				'php_version'  => PHP_VERSION,
				'wp_version'   => get_bloginfo( 'version' ),
				'gd_version'   => defined( 'GD_VERSION' ) ? GD_VERSION : '—',
				'site_url'     => get_site_url(),
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'user'         => array(
					'id'    => $user->ID,
					'login' => $user->user_login,
					'roles' => implode( ', ', (array) $user->roles ),
				),
				'upload_dir'   => array(
					'path'     => $uploads['path'],
					'writable' => wp_is_writable( $uploads['path'] ),
					'error'    => $uploads['error'] ?: null,
				),
				'last_php_error' => $last_error,
				'options'      => $options,
			)
		);
	}

	/**
	 * Output the debug panel HTML + CSS + JS in wp_footer.
	 */
	public function render_panel() {
		$nonce    = wp_create_nonce( 'gd_debug_info' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<!-- GD Debug Panel -->
		<style id="gd-debug-style">
		#gd-debug-toggle{position:fixed;bottom:16px;right:16px;z-index:99999;background:#1d2327;color:#fff;border:none;border-radius:50%;width:52px;height:52px;font-size:22px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;line-height:1;touch-action:manipulation}
		#gd-debug-panel{position:fixed;bottom:78px;right:8px;z-index:99998;width:540px;max-width:calc(100vw - 16px);max-height:72vh;background:#1d2327;color:#c3c4c7;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.5);display:flex;flex-direction:column;font-family:monospace;font-size:12px;overflow:hidden}
		#gd-debug-panel.gd-debug--hidden{display:none}
		.gd-debug-header{display:flex;align-items:center;padding:8px 12px;background:#101517;gap:6px;flex-shrink:0;flex-wrap:wrap}
		.gd-debug-header span{font-weight:700;font-size:13px;color:#fff;flex:1;min-width:0}
		.gd-debug-tabs{display:flex;gap:0;background:#101517;padding:0 4px;flex-shrink:0;overflow-x:auto;-webkit-overflow-scrolling:touch}
		.gd-debug-tab{padding:8px 10px;cursor:pointer;border-bottom:2px solid transparent;color:#8c8f94;font-size:11px;font-family:monospace;background:none;border-top:none;border-left:none;border-right:none;white-space:nowrap;touch-action:manipulation;-webkit-tap-highlight-color:transparent}
		.gd-debug-tab.is-active{color:#72aee6;border-bottom-color:#72aee6}
		.gd-debug-body{flex:1;overflow-y:auto;overflow-x:hidden;padding:8px;-webkit-overflow-scrolling:touch}
		.gd-debug-pane{display:none}.gd-debug-pane.is-active{display:block}
		.gd-debug-table{width:100%;border-collapse:collapse}
		.gd-debug-table th,.gd-debug-table td{text-align:left;padding:4px 6px;border-bottom:1px solid #2c3338;vertical-align:top;word-break:break-all;font-size:12px}
		.gd-debug-table th{color:#72aee6;width:40%;font-weight:normal}
		.gd-ajax-row{cursor:pointer;border-bottom:1px solid #2c3338}
		.gd-ajax-row:hover{background:#23282d}
		.gd-ajax-row td{padding:4px 6px}
		.gd-ajax-status--ok{color:#68de7c}.gd-ajax-status--err{color:#f86368}.gd-ajax-detail{display:none;background:#101517;padding:6px;border-radius:4px;white-space:pre-wrap;font-size:11px;color:#c3c4c7;max-height:200px;overflow-y:auto}
		.gd-debug-empty{color:#8c8f94;padding:8px;font-style:italic}
		.gd-debug-clear{background:none;border:1px solid #3c434a;color:#8c8f94;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:11px;font-family:monospace;touch-action:manipulation}
		.gd-debug-clear:hover{color:#fff;border-color:#8c8f94}
		.gd-debug-refresh{background:none;border:1px solid #3c434a;color:#8c8f94;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:11px;font-family:monospace;touch-action:manipulation}
		.gd-debug-refresh:hover{color:#fff;border-color:#8c8f94}
		.gd-debug-copy{background:none;border:1px solid #3c434a;color:#8c8f94;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:11px;font-family:monospace;touch-action:manipulation}
		.gd-debug-copy:hover{color:#fff;border-color:#8c8f94}
		.gd-badge{display:inline-block;min-width:16px;height:16px;line-height:16px;border-radius:8px;text-align:center;font-size:10px;padding:0 4px;background:#f86368;color:#fff;margin-left:4px}
		.gd-badge--hidden{display:none}
		.gd-mobile-warn{color:#f0c33c;font-weight:bold}
		.gd-mobile-ok{color:#68de7c}
		.gd-mobile-section{color:#72aee6;font-size:11px;margin:8px 0 2px;font-weight:bold}
		</style>

		<button id="gd-debug-toggle" title="<?php esc_attr_e( 'GD Debug', 'go-deliver' ); ?>">🐛</button>

		<div id="gd-debug-panel" class="gd-debug--hidden">
			<div class="gd-debug-header">
				<span>🐛 <?php esc_html_e( 'Go Deliver Debug', 'go-deliver' ); ?></span>
				<button class="gd-debug-clear" id="gd-debug-clear-ajax"><?php esc_html_e( 'Clear', 'go-deliver' ); ?></button>
				<button class="gd-debug-refresh" id="gd-debug-refresh-info"><?php esc_html_e( 'Refresh', 'go-deliver' ); ?></button>
				<button class="gd-debug-copy" id="gd-debug-copy-mobile" title="<?php esc_attr_e( 'Copy mobile info to clipboard', 'go-deliver' ); ?>">📋</button>
			</div>
			<div class="gd-debug-tabs">
				<button class="gd-debug-tab is-active" data-pane="ajax">
					<?php esc_html_e( 'AJAX', 'go-deliver' ); ?><span class="gd-badge gd-badge--hidden" id="gd-debug-ajax-badge">0</span>
				</button>
				<button class="gd-debug-tab" data-pane="errors">
					<?php esc_html_e( 'JS Errors', 'go-deliver' ); ?><span class="gd-badge gd-badge--hidden" id="gd-debug-err-badge">0</span>
				</button>
				<button class="gd-debug-tab" data-pane="mobile">
					<?php esc_html_e( '📱 Mobile', 'go-deliver' ); ?>
				</button>
				<button class="gd-debug-tab" data-pane="info">
					<?php esc_html_e( 'Server', 'go-deliver' ); ?>
				</button>
			</div>
			<div class="gd-debug-body">
				<div class="gd-debug-pane is-active" id="gd-debug-pane-ajax">
					<p class="gd-debug-empty"><?php esc_html_e( 'No AJAX requests yet.', 'go-deliver' ); ?></p>
				</div>
				<div class="gd-debug-pane" id="gd-debug-pane-errors">
					<p class="gd-debug-empty"><?php esc_html_e( 'No JS errors captured.', 'go-deliver' ); ?></p>
				</div>
				<div class="gd-debug-pane" id="gd-debug-pane-mobile">
					<p class="gd-debug-empty"><?php esc_html_e( 'Loading mobile info…', 'go-deliver' ); ?></p>
				</div>
				<div class="gd-debug-pane" id="gd-debug-pane-info">
					<p class="gd-debug-empty"><?php esc_html_e( 'Loading…', 'go-deliver' ); ?></p>
				</div>
			</div>
		</div>

		<script>
		( function () {
			'use strict';

			var AJAX_URL    = '<?php echo esc_js( esc_url( $ajax_url ) ); ?>';
			var NONCE       = '<?php echo esc_js( $nonce ); ?>';
			var ajaxLog     = [];
			var errorLog    = [];
			var ajaxCount   = 0;
			var errorCount  = 0;
			var panelOpen   = false;

			var toggle    = document.getElementById( 'gd-debug-toggle' );
			var panel     = document.getElementById( 'gd-debug-panel' );
			var paneAjax  = document.getElementById( 'gd-debug-pane-ajax' );
			var paneErr   = document.getElementById( 'gd-debug-pane-errors' );
			var paneMob   = document.getElementById( 'gd-debug-pane-mobile' );
			var paneInfo  = document.getElementById( 'gd-debug-pane-info' );
			var badgeAjax = document.getElementById( 'gd-debug-ajax-badge' );
			var badgeErr  = document.getElementById( 'gd-debug-err-badge' );

			// ----------------------------------------------------------------
			// Panel toggle
			// ----------------------------------------------------------------
			toggle.addEventListener( 'click', function () {
				panelOpen = ! panelOpen;
				panel.classList.toggle( 'gd-debug--hidden', ! panelOpen );
				if ( panelOpen ) {
					badgeAjax.textContent = '0';
					badgeAjax.classList.add( 'gd-badge--hidden' );
					// Load server info if not yet loaded.
					if ( paneInfo.querySelector( '.gd-debug-empty' ) ) {
						fetchServerInfo();
					}
					renderMobileInfo();
				}
			} );

			// ----------------------------------------------------------------
			// Tab switching
			// ----------------------------------------------------------------
			document.querySelectorAll( '.gd-debug-tab' ).forEach( function ( tab ) {
				tab.addEventListener( 'click', function () {
					document.querySelectorAll( '.gd-debug-tab' ).forEach( function ( t ) {
						t.classList.remove( 'is-active' );
					} );
					document.querySelectorAll( '.gd-debug-pane' ).forEach( function ( p ) {
						p.classList.remove( 'is-active' );
					} );
					tab.classList.add( 'is-active' );
					var pane = document.getElementById( 'gd-debug-pane-' + tab.dataset.pane );
					if ( pane ) { pane.classList.add( 'is-active' ); }

					if ( tab.dataset.pane === 'errors' ) {
						badgeErr.textContent = '0';
						badgeErr.classList.add( 'gd-badge--hidden' );
					}
					if ( tab.dataset.pane === 'mobile' ) {
						renderMobileInfo();
					}
				} );
			} );

			// ----------------------------------------------------------------
			// Clear / Refresh / Copy buttons
			// ----------------------------------------------------------------
			document.getElementById( 'gd-debug-clear-ajax' ).addEventListener( 'click', function () {
				ajaxLog   = [];
				ajaxCount = 0;
				badgeAjax.textContent = '0';
				badgeAjax.classList.add( 'gd-badge--hidden' );
				renderAjaxLog();
			} );

			document.getElementById( 'gd-debug-refresh-info' ).addEventListener( 'click', function () {
				paneInfo.innerHTML = '<p class="gd-debug-empty">Loading\u2026</p>';
				fetchServerInfo();
				renderMobileInfo();
			} );

			document.getElementById( 'gd-debug-copy-mobile' ).addEventListener( 'click', function () {
				var text = buildMobileText();
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( function () {
						showCopyFeedback( '✅ Copied!' );
					} ).catch( function () {
						fallbackCopy( text );
					} );
				} else {
					fallbackCopy( text );
				}
			} );

			function showCopyFeedback( msg ) {
				var btn = document.getElementById( 'gd-debug-copy-mobile' );
				var orig = btn.textContent;
				btn.textContent = msg;
				setTimeout( function () { btn.textContent = orig; }, 2000 );
			}

			function fallbackCopy( text ) {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
				document.body.appendChild( ta );
				ta.focus();
				ta.select();
				try {
					document.execCommand( 'copy' );
					showCopyFeedback( '✅ Copied!' );
				} catch ( e ) {
					showCopyFeedback( '❌ Failed' );
				}
				document.body.removeChild( ta );
			}

			// ----------------------------------------------------------------
			// AJAX interception (jQuery global events)
			// ----------------------------------------------------------------
			if ( window.jQuery ) {
				jQuery( document )
					.on( 'ajaxSend', function ( e, xhr, settings ) {
						var entry = {
							id:      ++ajaxCount,
							action:  extractAction( settings.data ),
							url:     settings.url,
							start:   Date.now(),
							status:  '…',
							ok:      null,
							response: '',
						};
						ajaxLog.unshift( entry );
						xhr._gdDebugId = entry.id;
						if ( ! panelOpen ) {
							badgeAjax.textContent = ajaxLog.filter( function(x){ return x.ok === false; } ).length || ajaxLog.length;
							badgeAjax.classList.remove( 'gd-badge--hidden' );
						}
						renderAjaxLog();
					} )
					.on( 'ajaxComplete', function ( e, xhr, settings ) {
						var entry = ajaxLog.find( function ( x ) { return x.id === xhr._gdDebugId; } );
						if ( ! entry ) { return; }
						entry.status   = xhr.status;
						entry.duration = Date.now() - entry.start;
						entry.ok       = ( xhr.status >= 200 && xhr.status < 300 );
						try {
							var parsed = JSON.parse( xhr.responseText );
							entry.response = JSON.stringify( parsed, null, 2 );
							if ( parsed && parsed.success === false ) { entry.ok = false; }
						} catch ( _ ) {
							entry.response = xhr.responseText || '(empty)';
							entry.ok = false;
						}
						if ( ! panelOpen && ! entry.ok ) {
							badgeAjax.textContent = ajaxLog.filter( function(x){ return x.ok === false; } ).length;
							badgeAjax.classList.remove( 'gd-badge--hidden' );
						}
						renderAjaxLog();
					} );
			}

			function extractAction( data ) {
				if ( ! data ) { return '—'; }
				if ( data instanceof FormData ) {
					return data.get( 'action' ) || '—';
				}
				if ( typeof data === 'string' ) {
					var m = data.match( /(?:^|&)action=([^&]*)/ );
					return m ? decodeURIComponent( m[1] ) : '—';
				}
				if ( typeof data === 'object' && data.action ) {
					return data.action;
				}
				return '—';
			}

			function esc( str ) {
				return String( str )
					.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
			}

			function renderAjaxLog() {
				if ( ! ajaxLog.length ) {
					paneAjax.innerHTML = '<p class="gd-debug-empty">No AJAX requests yet.</p>';
					return;
				}
				var html = '<table class="gd-debug-table"><thead><tr>'
					+ '<th>#</th><th>Action</th><th>Status</th><th>Time</th>'
					+ '</tr></thead><tbody>';
				ajaxLog.forEach( function ( entry ) {
					var statusClass = entry.ok === null ? '' : ( entry.ok ? 'gd-ajax-status--ok' : 'gd-ajax-status--err' );
					var statusText  = entry.ok === null ? '…' : entry.status;
					var duration    = entry.duration ? entry.duration + 'ms' : '…';
					html += '<tr class="gd-ajax-row" data-id="' + entry.id + '">'
						+ '<td>' + entry.id + '</td>'
						+ '<td>' + esc( entry.action ) + '</td>'
						+ '<td class="' + statusClass + '">' + statusText + '</td>'
						+ '<td>' + duration + '</td>'
						+ '</tr>'
						+ '<tr id="gd-ajax-detail-' + entry.id + '" style="display:none"><td colspan="4">'
						+ '<div class="gd-ajax-detail">' + esc( entry.response ) + '</div>'
						+ '</td></tr>';
				} );
				html += '</tbody></table>';
				paneAjax.innerHTML = html;

				paneAjax.querySelectorAll( '.gd-ajax-row' ).forEach( function ( row ) {
					row.addEventListener( 'click', function () {
						var detail = document.getElementById( 'gd-ajax-detail-' + row.dataset.id );
						if ( detail ) {
							detail.style.display = detail.style.display === 'none' ? '' : 'none';
						}
					} );
				} );
			}

			// ----------------------------------------------------------------
			// JS error capture
			// ----------------------------------------------------------------
			window.addEventListener( 'error', function ( e ) {
				errorLog.unshift( {
					message: e.message,
					source:  e.filename + ':' + e.lineno + ':' + e.colno,
					time:    new Date().toLocaleTimeString(),
				} );
				errorCount++;
				badgeErr.textContent = errorCount;
				badgeErr.classList.remove( 'gd-badge--hidden' );
				renderErrorLog();
			} );

			window.addEventListener( 'unhandledrejection', function ( e ) {
				errorLog.unshift( {
					message: 'Unhandled Promise: ' + ( e.reason ? ( e.reason.message || String( e.reason ) ) : 'unknown' ),
					source:  '—',
					time:    new Date().toLocaleTimeString(),
				} );
				errorCount++;
				badgeErr.textContent = errorCount;
				badgeErr.classList.remove( 'gd-badge--hidden' );
				renderErrorLog();
			} );

			function renderErrorLog() {
				if ( ! errorLog.length ) {
					paneErr.innerHTML = '<p class="gd-debug-empty">No JS errors captured.</p>';
					return;
				}
				var html = '<table class="gd-debug-table"><tbody>';
				errorLog.forEach( function ( e ) {
					html += '<tr><td class="gd-ajax-status--err">' + esc( e.time ) + '</td>'
						+ '<td>' + esc( e.message ) + '<br><small>' + esc( e.source ) + '</small></td></tr>';
				} );
				html += '</tbody></table>';
				paneErr.innerHTML = html;
			}

			// ----------------------------------------------------------------
			// Mobile info
			// ----------------------------------------------------------------
			var GD_MAX_OFFENDERS   = 10; // max overflow offenders to report
			var GD_MAX_CLASSES     = 3;  // max CSS classes to include in offender label
			var GD_OVERFLOW_THRESH = 2;  // px tolerance before flagging as overflow

			var GD_BREAKPOINTS = [
				{ label: 'max-width:480px',  query: '(max-width:480px)'  },
				{ label: 'max-width:600px',  query: '(max-width:600px)'  },
				{ label: 'max-width:640px',  query: '(max-width:640px)'  },
				{ label: 'max-width:768px',  query: '(max-width:768px)'  },
				{ label: 'max-width:900px',  query: '(max-width:900px)'  },
				{ label: 'max-width:1040px', query: '(max-width:1040px)' },
				{ label: 'max-width:1100px', query: '(max-width:1100px)' },
				{ label: 'prefers dark',     query: '(prefers-color-scheme:dark)' },
				{ label: 'pointer:coarse',   query: '(pointer:coarse)'   },
				{ label: 'hover:none',       query: '(hover:none)'       },
			];

			var GD_INSPECT_SELECTORS = [
				'body',
				'.gd-wrap',
				'.entry-content',
				'.site-content',
				'.content-area',
				'#content',
				'#page',
				'.wp-site-blocks',
				'main',
				'#main',
				'.site-main',
				'.container',
				'.gd-dashboard-layout',
				'.gd-job-form',
				'.gd-job-list',
			];

			function getMobileData() {
				var vw = window.innerWidth;
				var vh = window.innerHeight;
				var sw = screen.width;
				var sh = screen.height;
				var dpr = window.devicePixelRatio || 1;
				var ua = ( navigator && navigator.userAgent ) ? navigator.userAgent : '(unavailable)';
				var orientation = ( screen.orientation && screen.orientation.type ) || ( vw > vh ? 'landscape' : 'portrait' );
				var scrollW = document.documentElement.scrollWidth;
				var hasHScroll = scrollW > vw;

				// Viewport meta tag
				var vmeta = document.querySelector( 'meta[name="viewport"]' );
				var vmetaContent = vmeta ? vmeta.getAttribute( 'content' ) : '(none – THIS IS BAD)';

				// Active breakpoints
				var activeBP = [];
				GD_BREAKPOINTS.forEach( function ( bp ) {
					if ( window.matchMedia && window.matchMedia( bp.query ).matches ) {
						activeBP.push( bp.label );
					}
				} );

				// Element widths / overflow
				var elemData = [];
				GD_INSPECT_SELECTORS.forEach( function ( sel ) {
					var el = document.querySelector( sel );
					if ( ! el ) { return; }
					var cs = window.getComputedStyle( el );
					var bcrW = Math.round( el.getBoundingClientRect().width );
					var scrollW2 = el.scrollWidth;
					elemData.push( {
						sel:        sel,
						width:      bcrW,
						scrollW:    scrollW2,
						overflow:   cs.overflow + '/' + cs.overflowX + '/' + cs.overflowY,
						maxWidth:   cs.maxWidth,
						marginL:    cs.marginLeft,
						marginR:    cs.marginRight,
						paddingL:   cs.paddingLeft,
						paddingR:   cs.paddingRight,
						display:    cs.display,
						overflows:  scrollW2 > bcrW + GD_OVERFLOW_THRESH,
					} );
				} );

				// Find widest offenders that bleed past viewport
				var offenders = [];
				try {
					var all = document.body.querySelectorAll( '*' );
					for ( var i = 0; i < all.length; i++ ) {
						var el2 = all[ i ];
						var rect = el2.getBoundingClientRect();
						if ( rect.right > vw + GD_OVERFLOW_THRESH ) {
							var tag = el2.tagName.toLowerCase();
							var id  = el2.id ? '#' + el2.id : '';
							var cls = el2.className && typeof el2.className === 'string'
								? '.' + el2.className.trim().split( /\s+/ ).slice( 0, GD_MAX_CLASSES ).join( '.' )
								: '';
							offenders.push( tag + id + cls + ' (right:' + Math.round( rect.right ) + 'px)' );
							if ( offenders.length >= GD_MAX_OFFENDERS ) { break; }
						}
					}
				} catch ( _ ) {}

				return {
					vw: vw, vh: vh, sw: sw, sh: sh, dpr: dpr,
					orientation: orientation, ua: ua,
					vmetaContent: vmetaContent,
					scrollW: scrollW, hasHScroll: hasHScroll,
					activeBP: activeBP, elemData: elemData, offenders: offenders,
				};
			}

			function renderMobileInfo() {
				var d = getMobileData();
				var html = '';

				// ── Viewport & Screen ─────────────────────────────────────
				html += '<p class="gd-mobile-section">Viewport &amp; Screen</p>';
				html += '<table class="gd-debug-table"><tbody>';
				html += row( 'Viewport', d.vw + ' × ' + d.vh + 'px' );
				html += row( 'Screen',   d.sw + ' × ' + d.sh + 'px' );
				html += row( 'DPR',      d.dpr + 'x' );
				html += row( 'Orientation', d.orientation );
				html += row( 'Viewport meta', d.vmetaContent );
				html += row(
					'H-scroll',
					d.hasHScroll
						? '<span class="gd-mobile-warn">⚠ YES – doc is ' + d.scrollW + 'px wide (viewport ' + d.vw + 'px)</span>'
						: '<span class="gd-mobile-ok">✓ none</span>'
				);
				html += '</tbody></table>';

				// ── Active breakpoints ────────────────────────────────────
				html += '<p class="gd-mobile-section">Active CSS Breakpoints</p>';
				html += '<table class="gd-debug-table"><tbody>';
				if ( d.activeBP.length ) {
					d.activeBP.forEach( function ( bp ) {
						html += '<tr><td class="gd-mobile-ok">✓</td><td>' + esc( bp ) + '</td></tr>';
					} );
				} else {
					html += '<tr><td colspan="2" class="gd-debug-empty">none matched</td></tr>';
				}
				html += '</tbody></table>';

				// ── Element widths ────────────────────────────────────────
				html += '<p class="gd-mobile-section">Key Element Widths</p>';
				html += '<table class="gd-debug-table"><thead><tr>'
					+ '<th>Selector</th><th>W</th><th>scrollW</th><th>overflow</th>'
					+ '</tr></thead><tbody>';
				if ( d.elemData.length ) {
					d.elemData.forEach( function ( e ) {
						var cls = e.overflows ? ' class="gd-mobile-warn"' : '';
						html += '<tr' + cls + '>'
							+ '<td>' + esc( e.sel ) + '</td>'
							+ '<td>' + e.width + '</td>'
							+ '<td>' + ( e.overflows ? '<span class="gd-mobile-warn">' + e.scrollW + '⚠</span>' : e.scrollW ) + '</td>'
							+ '<td>' + esc( e.overflow ) + '</td>'
							+ '</tr>';
					} );
				} else {
					html += '<tr><td colspan="4" class="gd-debug-empty">no matching elements</td></tr>';
				}
				html += '</tbody></table>';

				// ── Overflow offenders ────────────────────────────────────
				html += '<p class="gd-mobile-section">Overflow Offenders (right &gt; viewport)</p>';
				html += '<table class="gd-debug-table"><tbody>';
				if ( d.offenders.length ) {
					d.offenders.forEach( function ( o ) {
						html += '<tr><td class="gd-mobile-warn">' + esc( o ) + '</td></tr>';
					} );
				} else {
					html += '<tr><td class="gd-mobile-ok">✓ None found</td></tr>';
				}
				html += '</tbody></table>';

				// ── User agent ────────────────────────────────────────────
				html += '<p class="gd-mobile-section">User Agent</p>';
				html += '<p style="word-break:break-all;font-size:11px;padding:0 4px">' + esc( d.ua ) + '</p>';

				paneMob.innerHTML = html;
			}

			function row( label, value ) {
				return '<tr><th>' + esc( label ) + '</th><td>' + value + '</td></tr>';
			}

			function buildMobileText() {
				var d = getMobileData();
				var lines = [
					'=== Go Deliver Mobile Debug ===',
					'Viewport : ' + d.vw + ' x ' + d.vh,
					'Screen   : ' + d.sw + ' x ' + d.sh,
					'DPR      : ' + d.dpr,
					'Orient   : ' + d.orientation,
					'Meta vp  : ' + d.vmetaContent,
					'H-scroll : ' + ( d.hasHScroll ? 'YES (docW=' + d.scrollW + ')' : 'none' ),
					'',
					'Active breakpoints: ' + ( d.activeBP.join( ', ' ) || 'none' ),
					'',
					'Element widths:',
				];
				d.elemData.forEach( function ( e ) {
					lines.push( '  ' + e.sel + ' → w=' + e.width + ' scrollW=' + e.scrollW + ' overflow=' + e.overflow );
				} );
				lines.push( '' );
				lines.push( 'Offenders:' );
				if ( d.offenders.length ) {
					d.offenders.forEach( function ( o ) { lines.push( '  ' + o ); } );
				} else {
					lines.push( '  none' );
				}
				lines.push( '' );
				lines.push( 'UA: ' + d.ua );
				return lines.join( '\n' );
			}

			// Re-render mobile pane on orientation / resize
			window.addEventListener( 'resize', function () {
				if ( panelOpen && paneMob.classList.contains( 'is-active' ) ) {
					renderMobileInfo();
				}
			} );

			// ----------------------------------------------------------------
			// Server info
			// ----------------------------------------------------------------
			function fetchServerInfo() {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', AJAX_URL, true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function () {
					try {
						var data = JSON.parse( xhr.responseText );
						if ( data && data.success ) {
							renderServerInfo( data.data );
						} else {
							paneInfo.innerHTML = '<p class="gd-debug-empty">Failed to load server info.</p>';
						}
					} catch ( _ ) {
						paneInfo.innerHTML = '<p class="gd-debug-empty">Invalid response.</p>';
					}
				};
				xhr.onerror = function () {
					paneInfo.innerHTML = '<p class="gd-debug-empty">Request failed.</p>';
				};
				xhr.send( 'action=gd_debug_info&nonce=' + encodeURIComponent( NONCE ) );
			}

			function renderServerInfo( d ) {
				var rows = [
					[ 'PHP', d.php_version ],
					[ 'WordPress', d.wp_version ],
					[ 'Go Deliver', d.gd_version ],
					[ 'Site URL', d.site_url ],
					[ 'AJAX URL', d.ajax_url ],
					[ 'User', d.user.login + ' (' + d.user.roles + ')' ],
					[ 'Upload dir writable', d.upload_dir.writable ? '✅ yes' : '❌ no' ],
					[ 'Upload path', d.upload_dir.path ],
				];
				if ( d.upload_dir.error ) {
					rows.push( [ 'Upload error', d.upload_dir.error ] );
				}
				if ( d.last_php_error ) {
					rows.push( [ 'Last PHP error', d.last_php_error.message + ' (' + d.last_php_error.file + ':' + d.last_php_error.line + ')' ] );
				}

				var html = '<table class="gd-debug-table"><tbody>';
				rows.forEach( function ( r ) {
					html += '<tr><th>' + esc( r[0] ) + '</th><td>' + esc( r[1] || '—' ) + '</td></tr>';
				} );
				html += '</tbody></table>';

				html += '<br><strong style="color:#72aee6;font-size:11px">Plugin Options</strong>';
				html += '<table class="gd-debug-table"><tbody>';
				Object.keys( d.options ).forEach( function ( k ) {
					html += '<tr><th>' + esc( k ) + '</th><td>' + esc( d.options[k] ) + '</td></tr>';
				} );
				html += '</tbody></table>';

				paneInfo.innerHTML = html;
			}

		} )();
		</script>
		<?php
	}
}
