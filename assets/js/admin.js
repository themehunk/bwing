/* BWing admin */
( function () {
	document.addEventListener( 'click', function ( e ) {
		var copy = e.target.closest( '[data-bwa-copy]' );
		if ( copy ) {
			e.preventDefault();
			var text = copy.getAttribute( 'data-bwa-copy' );
			var done = function () {
				var old = copy.textContent;
				copy.textContent = '✓';
				setTimeout( function () { copy.textContent = old; }, 1200 );
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( text ).then( done );
			} else {
				var t = document.createElement( 'textarea' );
				t.value = text;
				document.body.appendChild( t );
				t.select();
				document.execCommand( 'copy' );
				t.remove();
				done();
			}
			return;
		}
		var confirmEl = e.target.closest( '[data-bwa-confirm]' );
		if ( confirmEl && ! window.confirm( confirmEl.getAttribute( 'data-bwa-confirm' ) ) ) {
			e.preventDefault();
		}
	} );
} )();

/* Step-by-step WordPress.org sync with progress bar. */
( function () {
	var box = document.querySelector( '[data-bwa-sync]' );
	if ( ! box ) { return; }
	var btn = box.querySelector( '[data-bwa-sync-start]' );
	var bar = box.querySelector( '.bwa-progress' );
	var fill = bar.querySelector( 'span' );
	var status = box.querySelector( '.bwa-sync__status' );
	var running = false;

	function call( mode ) {
		var body = new FormData();
		body.append( 'action', 'bwing_admin_sync' );
		body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
		body.append( 'mode', mode );
		return fetch( window.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) {
			return r.text().then( function ( t ) {
				try {
					return JSON.parse( t );
				} catch ( e ) {
					var err = new Error( 'admin-ajax is blocked or broken (HTTP ' + r.status + '). Use “Run sync without AJAX” in Sync health.' );
					err.fatal = true;
					throw err;
				}
			} );
		} );
	}

	// Health check: is admin-ajax reachable?
	var check = document.querySelector( '[data-bwa-ajax-check]' );
	if ( check ) {
		call( 'ping' ).then( function ( res ) {
			check.textContent = res && res.success ? '✓ OK' : '✗ ' + ( res.data && res.data.message ? res.data.message : 'error' );
			check.closest( 'tr' ).className = res && res.success ? 'is-ok' : 'is-bad';
		} ).catch( function ( e ) {
			check.textContent = '✗ ' + e.message;
			check.closest( 'tr' ).className = 'is-bad';
		} );
	}
	function loop( mode, retries ) {
		call( mode ).then( function ( res ) {
			if ( ! res.success ) { throw new Error( res.data && res.data.message ? res.data.message : 'Error' ); }
			var d = res.data, pct = d.total ? Math.round( d.done / d.total * 100 ) : 100;
			fill.style.width = pct + '%';
			status.textContent = pct + '% — ' + d.themes.toLocaleString() + ' themes, ' + d.plugins.toLocaleString() + ' plugins (page ' + d.done + ' of ' + d.total + ')';
			if ( d.finished ) {
				status.textContent = '✓ ' + ( d.log || [] ).join( ' ' ) + ' Reloading…';
				setTimeout( function () { window.location = window.location.href.replace( /&bwing_autosync=1/, '' ); }, 1500 );
				return;
			}
			loop( 'step', 3 );
		} ).catch( function ( err ) {
			if ( retries > 0 && ! err.fatal ) { setTimeout( function () { loop( 'step', retries - 1 ); }, 2000 ); return; }
			running = false;
			btn.disabled = false;
			status.textContent = '⚠ ' + err.message + ' — click Sync now to resume.';
		} );
	}
	function start() {
		if ( running ) { return; }
		running = true;
		btn.disabled = true;
		bar.hidden = false;
		status.textContent = 'Starting…';
		loop( 'start', 3 );
	}
	btn.addEventListener( 'click', start );
	if ( /[?&]bwing_autosync=1/.test( window.location.search ) ) { start(); }
} )();
