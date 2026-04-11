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

		add_action( 'wp_footer', array( $this, 'render_panel' ), 9999 );
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
			'gd_fee_percentage'            => get_option( 'gd_fee_percentage', '—' ),
			'gd_job_expiry_days'           => get_option( 'gd_job_expiry_days', '—' ),
			'gd_quote_expiry_days'         => get_option( 'gd_quote_expiry_days', '—' ),
			'gd_job_redirect_page_id'      => get_option( 'gd_job_redirect_page_id', '—' ),
			'gd_mover_reg_redirect_page_id' => get_option( 'gd_mover_reg_redirect_page_id', '—' ),
			'gd_mover_terms_page_id'       => get_option( 'gd_mover_terms_page_id', '—' ),
			'gd_customer_terms_page_id'    => get_option( 'gd_customer_terms_page_id', '—' ),
			'gd_stripe_publishable_key'    => get_option( 'gd_stripe_publishable_key' ) ? '(set)' : '(not set)',
			'gd_stripe_secret_key'         => get_option( 'gd_stripe_secret_key' ) ? '(set)' : '(not set)',
			'gd_google_maps_api_key'       => get_option( 'gd_google_maps_api_key' ) ? '(set)' : '(not set)',
			'gd_debug_panel'               => get_option( 'gd_debug_panel', 1 ),
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
		#gd-debug-toggle{position:fixed;bottom:16px;right:16px;z-index:99999;background:#1d2327;color:#fff;border:none;border-radius:50%;width:44px;height:44px;font-size:20px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;line-height:1}
		#gd-debug-panel{position:fixed;bottom:70px;right:16px;z-index:99998;width:540px;max-width:calc(100vw - 32px);max-height:70vh;background:#1d2327;color:#c3c4c7;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.5);display:flex;flex-direction:column;font-family:monospace;font-size:12px;overflow:hidden}
		#gd-debug-panel.gd-debug--hidden{display:none}
		.gd-debug-header{display:flex;align-items:center;padding:8px 12px;background:#101517;gap:8px;flex-shrink:0}
		.gd-debug-header span{font-weight:700;font-size:13px;color:#fff;flex:1}
		.gd-debug-tabs{display:flex;gap:2px;background:#101517;padding:0 8px;flex-shrink:0}
		.gd-debug-tab{padding:6px 12px;cursor:pointer;border-bottom:2px solid transparent;color:#8c8f94;font-size:11px;font-family:monospace;background:none;border-top:none;border-left:none;border-right:none}
		.gd-debug-tab.is-active{color:#72aee6;border-bottom-color:#72aee6}
		.gd-debug-body{flex:1;overflow-y:auto;padding:8px}
		.gd-debug-pane{display:none}.gd-debug-pane.is-active{display:block}
		.gd-debug-table{width:100%;border-collapse:collapse}
		.gd-debug-table th,.gd-debug-table td{text-align:left;padding:3px 6px;border-bottom:1px solid #2c3338;vertical-align:top;word-break:break-all}
		.gd-debug-table th{color:#72aee6;width:38%;font-weight:normal}
		.gd-ajax-row{cursor:pointer;border-bottom:1px solid #2c3338}
		.gd-ajax-row:hover{background:#23282d}
		.gd-ajax-row td{padding:4px 6px}
		.gd-ajax-status--ok{color:#68de7c}.gd-ajax-status--err{color:#f86368}.gd-ajax-detail{display:none;background:#101517;padding:6px;border-radius:4px;white-space:pre-wrap;font-size:11px;color:#c3c4c7;max-height:200px;overflow-y:auto}
		.gd-debug-empty{color:#8c8f94;padding:8px;font-style:italic}
		.gd-debug-clear{background:none;border:1px solid #3c434a;color:#8c8f94;padding:2px 8px;border-radius:4px;cursor:pointer;font-size:10px;font-family:monospace}
		.gd-debug-clear:hover{color:#fff;border-color:#8c8f94}
		.gd-debug-refresh{background:none;border:1px solid #3c434a;color:#8c8f94;padding:2px 8px;border-radius:4px;cursor:pointer;font-size:10px;font-family:monospace}
		.gd-debug-refresh:hover{color:#fff;border-color:#8c8f94}
		.gd-badge{display:inline-block;min-width:16px;height:16px;line-height:16px;border-radius:8px;text-align:center;font-size:10px;padding:0 4px;background:#f86368;color:#fff;margin-left:4px}
		.gd-badge--hidden{display:none}
		</style>

		<button id="gd-debug-toggle" title="<?php esc_attr_e( 'GD Debug', 'go-deliver' ); ?>">🐛</button>

		<div id="gd-debug-panel" class="gd-debug--hidden">
			<div class="gd-debug-header">
				<span>🐛 <?php esc_html_e( 'Go Deliver Debug', 'go-deliver' ); ?></span>
				<button class="gd-debug-clear" id="gd-debug-clear-ajax"><?php esc_html_e( 'Clear', 'go-deliver' ); ?></button>
				<button class="gd-debug-refresh" id="gd-debug-refresh-info"><?php esc_html_e( 'Refresh', 'go-deliver' ); ?></button>
			</div>
			<div class="gd-debug-tabs">
				<button class="gd-debug-tab is-active" data-pane="ajax">
					<?php esc_html_e( 'AJAX', 'go-deliver' ); ?><span class="gd-badge gd-badge--hidden" id="gd-debug-ajax-badge">0</span>
				</button>
				<button class="gd-debug-tab" data-pane="errors">
					<?php esc_html_e( 'JS Errors', 'go-deliver' ); ?><span class="gd-badge gd-badge--hidden" id="gd-debug-err-badge">0</span>
				</button>
				<button class="gd-debug-tab" data-pane="info">
					<?php esc_html_e( 'Server Info', 'go-deliver' ); ?>
				</button>
			</div>
			<div class="gd-debug-body">
				<div class="gd-debug-pane is-active" id="gd-debug-pane-ajax">
					<p class="gd-debug-empty"><?php esc_html_e( 'No AJAX requests yet.', 'go-deliver' ); ?></p>
				</div>
				<div class="gd-debug-pane" id="gd-debug-pane-errors">
					<p class="gd-debug-empty"><?php esc_html_e( 'No JS errors captured.', 'go-deliver' ); ?></p>
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

			var toggle   = document.getElementById( 'gd-debug-toggle' );
			var panel    = document.getElementById( 'gd-debug-panel' );
			var paneAjax = document.getElementById( 'gd-debug-pane-ajax' );
			var paneErr  = document.getElementById( 'gd-debug-pane-errors' );
			var paneInfo = document.getElementById( 'gd-debug-pane-info' );
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
				} );
			} );

			// ----------------------------------------------------------------
			// Clear / Refresh buttons
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
			} );

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
