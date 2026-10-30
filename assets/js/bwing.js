/* BWing front-end: live search, chart ranges, tools, newsletter. */
( function () {
	var D = window.bwingData || {};

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', D.nonce );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( D.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } );
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	// Broken thumbnails → letter avatar.
	document.addEventListener( 'error', function ( e ) {
		var img = e.target;
		if ( img.tagName !== 'IMG' || ! img.classList.contains( 'bw-thumb' ) && ! img.classList.contains( 'bw-itemhead__img' ) ) {
			return;
		}
		var span = document.createElement( 'span' );
		span.className = img.className + ' bw-thumb--letter';
		var name = ( img.closest( 'a, li, div' ) || img ).textContent.trim() || '?';
		span.textContent = name.charAt( 0 ).toUpperCase();
		var h = 0;
		for ( var i = 0; i < name.length; i++ ) { h = ( h * 31 + name.charCodeAt( i ) ) % 360; }
		span.style.setProperty( '--h', h );
		img.replaceWith( span );
	}, true );

	// Live search.
	document.querySelectorAll( '[data-bw-live]' ).forEach( function ( form ) {
		var input = form.querySelector( 'input[type=search]' );
		var box = form.querySelector( '.bw-search__results' );
		var timer, active = -1;
		function close() { box.hidden = true; active = -1; }
		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			var q = input.value.trim();
			if ( q.length < 2 ) { close(); return; }
			timer = setTimeout( function () {
				fetch( D.ajax + '?action=bwing_search&q=' + encodeURIComponent( q ) ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
					var items = res && res.data ? res.data : [];
					if ( ! items.length ) {
						box.innerHTML = '<div class="bw-search__none">No matches — press Enter to search the blog.</div>';
					} else {
						box.innerHTML = items.map( function ( it ) {
							var img = it.image ? '<img class="bw-thumb bw-thumb--xs" src="' + esc( it.image ) + '" alt="">' : '<span class="bw-thumb bw-thumb--xs bw-thumb--letter" style="--h:' + ( it.name.length * 37 % 360 ) + '">' + esc( it.name.charAt( 0 ) ) + '</span>';
							return '<a role="option" class="bw-search__item bw-search__item--' + esc( it.type ) + '" href="' + esc( it.url ) + '">' + img + '<span><strong>' + esc( it.name ) + '</strong><small>' + esc( it.meta ) + '</small></span></a>';
						} ).join( '' );
					}
					box.hidden = false;
				} );
			}, 180 );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			var links = box.querySelectorAll( 'a' );
			if ( e.key === 'Escape' ) { close(); return; }
			if ( ! links.length || box.hidden ) { return; }
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				active = ( active + ( e.key === 'ArrowDown' ? 1 : -1 ) + links.length ) % links.length;
				links.forEach( function ( l, i ) { l.classList.toggle( 'is-active', i === active ); } );
			} else if ( e.key === 'Enter' && active > -1 ) {
				e.preventDefault();
				window.location = links[ active ].href;
			}
		} );
		document.addEventListener( 'click', function ( e ) { if ( ! form.contains( e.target ) ) { close(); } } );
	} );

	// "/" focuses the first search box.
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === '/' && ! /input|textarea|select/i.test( document.activeElement.tagName ) ) {
			var s = document.querySelector( '[data-bw-live] input' );
			if ( s ) { e.preventDefault(); s.focus(); }
		}
	} );

	// Chart range toggles.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.bw-ranged .bw-seg__btn' );
		if ( ! btn ) { return; }
		var wrap = btn.closest( '.bw-ranged' );
		var r = btn.getAttribute( 'data-range' );
		wrap.querySelectorAll( '.bw-seg__btn' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b === btn ); } );
		wrap.querySelectorAll( '.bw-chartpane' ).forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-range' ) === r ); } );
	} );

	// Trend explorer: interactive SVG chart with metric tabs, ranges and hover tooltip.
	var NS = 'http://www.w3.org/2000/svg';
	function el( name, attrs, parent ) {
		var n = document.createElementNS( NS, name );
		Object.keys( attrs || {} ).forEach( function ( k ) { n.setAttribute( k, attrs[ k ] ); } );
		if ( parent ) { parent.appendChild( n ); }
		return n;
	}
	function fmt( v, invert ) {
		if ( invert ) { return '#' + Math.round( v ); }
		var a = Math.abs( v );
		if ( a >= 1e9 ) { return ( v / 1e9 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'B'; }
		if ( a >= 1e6 ) { return ( v / 1e6 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'M'; }
		if ( a >= 1e3 ) { return ( v / 1e3 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'K'; }
		return String( Math.round( v ) );
	}
	function fdate( s, long ) {
		var d = new Date( s + 'T00:00:00' );
		return d.toLocaleDateString( undefined, long ? { month: 'short', day: 'numeric', year: 'numeric' } : { month: 'short', day: 'numeric' } );
	}
	function niceTicks( min, max, count ) {
		var span = max - min || 1;
		var step = Math.pow( 10, Math.floor( Math.log10( span / count ) ) );
		var err = span / count / step;
		step *= err >= 7.5 ? 10 : err >= 3.5 ? 5 : err >= 1.5 ? 2 : 1;
		var lo = Math.floor( min / step ) * step, hi = Math.ceil( max / step ) * step, out = [];
		for ( var v = lo; v <= hi + step / 2; v += step ) { out.push( v ); }
		return out;
	}
	function smooth( pts ) {
		if ( pts.length < 3 ) { return 'M' + pts.map( function ( p ) { return p[ 0 ] + ' ' + p[ 1 ]; } ).join( 'L' ); }
		var d = 'M' + pts[ 0 ][ 0 ] + ' ' + pts[ 0 ][ 1 ];
		for ( var i = 0; i < pts.length - 1; i++ ) {
			var p0 = pts[ i - 1 ] || pts[ i ], p1 = pts[ i ], p2 = pts[ i + 1 ], p3 = pts[ i + 2 ] || p2;
			d += 'C' + ( p1[ 0 ] + ( p2[ 0 ] - p0[ 0 ] ) / 6 ) + ' ' + ( p1[ 1 ] + ( p2[ 1 ] - p0[ 1 ] ) / 6 ) + ',' + ( p2[ 0 ] - ( p3[ 0 ] - p1[ 0 ] ) / 6 ) + ' ' + ( p2[ 1 ] - ( p3[ 1 ] - p1[ 1 ] ) / 6 ) + ',' + p2[ 0 ] + ' ' + p2[ 1 ];
		}
		return d;
	}

	document.querySelectorAll( '[data-bw-trends]' ).forEach( function ( box, boxIndex ) {
		var series = JSON.parse( box.getAttribute( 'data-bw-trends' ) );
		var plot = box.querySelector( '.bw-tx__plot' );
		var tip = box.querySelector( '.bw-tx__tip' );
		var state = { metric: Object.keys( series )[ 0 ], range: 90 };
		var svg;

		function data() {
			var pts = series[ state.metric ].points;
			if ( ! state.range ) { return pts; }
			var last = new Date( pts[ pts.length - 1 ][ 0 ] + 'T00:00:00' ).getTime();
			var from = last - state.range * 864e5;
			var out = pts.filter( function ( p ) { return new Date( p[ 0 ] + 'T00:00:00' ).getTime() >= from; } );
			return out.length > 1 ? out : pts.slice( -2 );
		}

		function summary( pts, s ) {
			var first = pts[ 0 ][ 1 ], last = pts[ pts.length - 1 ][ 1 ];
			var diff = s.invert ? first - last : last - first;
			var cls = diff > 0 ? 'up' : diff < 0 ? 'down' : 'flat';
			box.querySelector( '.bw-tx__value' ).textContent = s.kind === 'bar' ? fmt( pts.reduce( function ( a, p ) { return a + p[ 1 ]; }, 0 ) ) : fmt( last, s.invert );
			var delta = box.querySelector( '.bw-tx__delta' );
			delta.className = 'bw-tx__delta bw-trend bw-trend--' + cls;
			if ( s.kind === 'bar' ) {
				delta.className = 'bw-tx__delta bw-trend bw-trend--flat';
				delta.textContent = 'avg ' + fmt( pts.reduce( function ( a, p ) { return a + p[ 1 ]; }, 0 ) / pts.length ) + '/day';
			} else {
				delta.textContent = ( diff > 0 ? '▲ ' : diff < 0 ? '▼ ' : '▬ ' ) + ( s.invert ? Math.abs( diff ) + ' pos' : fmt( Math.abs( diff ) ) );
			}
			box.querySelector( '.bw-tx__span' ).textContent = ( s.kind === 'bar' ? 'total · ' : '' ) + fdate( pts[ 0 ][ 0 ], true ) + ' – ' + fdate( pts[ pts.length - 1 ][ 0 ], true );
		}

		function draw() {
			var s = series[ state.metric ], pts = data();
			var W = Math.max( 280, plot.clientWidth ), H = W < 560 ? 220 : 300;
			var P = { t: 14, r: 14, b: 30, l: W < 560 ? 44 : 56 };
			var iw = W - P.l - P.r, ih = H - P.t - P.b;
			var vals = pts.map( function ( p ) { return p[ 1 ]; } );
			var min = Math.min.apply( null, vals ), max = Math.max.apply( null, vals );
			if ( s.kind === 'bar' ) { min = 0; }
			if ( s.invert ) { min = Math.max( 1, min ); }
			if ( min === max ) { min = s.invert ? Math.max( 1, min - 2 ) : min * 0.9; max = s.invert ? max + 2 : ( max * 1.1 || 1 ); }
			var ticks = niceTicks( min, max, 4 );
			if ( s.invert ) { ticks = ticks.filter( function ( t ) { return t >= 1; } ); if ( ticks[ 0 ] > min ) { ticks.unshift( Math.max( 1, Math.floor( min ) ) ); } }
			var lo = Math.min( ticks[ 0 ], min ), hi = Math.max( ticks[ ticks.length - 1 ], max );
			var x = function ( i ) { return P.l + ( pts.length === 1 ? iw / 2 : iw * i / ( pts.length - 1 ) ); };
			var y = function ( v ) { var r = ( v - lo ) / ( hi - lo || 1 ); return P.t + ih * ( s.invert ? r : 1 - r ); };

			if ( svg ) { svg.remove(); }
			svg = el( 'svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, 'class': 'bw-tx__svg bw-tx__svg--' + state.metric } );
			plot.insertBefore( svg, tip );
			var gid = 'bwtx' + boxIndex;
			var defs = el( 'defs', {}, svg );
			var lg = el( 'linearGradient', { id: gid, x1: 0, x2: 0, y1: 0, y2: 1 }, defs );
			el( 'stop', { offset: 0, 'stop-color': 'currentColor', 'stop-opacity': 0.25 }, lg );
			el( 'stop', { offset: 1, 'stop-color': 'currentColor', 'stop-opacity': 0 }, lg );

			ticks.forEach( function ( t ) {
				var ty = y( t );
				if ( ty < P.t - 1 || ty > P.t + ih + 1 ) { return; }
				el( 'line', { x1: P.l, x2: W - P.r, y1: ty, y2: ty, 'class': 'bw-tx__grid' }, svg );
				el( 'text', { x: P.l - 10, y: ty + 4, 'text-anchor': 'end', 'class': 'bw-tx__axis' }, svg ).textContent = fmt( t, s.invert );
			} );
			var labels = Math.min( pts.length, W < 560 ? 3 : 6 );
			for ( var k = 0; k < labels; k++ ) {
				var idx = Math.round( k * ( pts.length - 1 ) / Math.max( 1, labels - 1 ) );
				el( 'text', { x: x( idx ), y: H - 8, 'text-anchor': k === 0 ? 'start' : k === labels - 1 ? 'end' : 'middle', 'class': 'bw-tx__axis' }, svg ).textContent = fdate( pts[ idx ][ 0 ] );
			}

			if ( s.kind === 'bar' ) {
				var bw = Math.max( 1, iw / pts.length * 0.72 );
				pts.forEach( function ( p, i ) {
					var by = y( p[ 1 ] );
					el( 'rect', { x: x( i ) - bw / 2, y: by, width: bw, height: Math.max( 1, P.t + ih - by ), rx: Math.min( 4, bw / 2 ), 'class': 'bw-tx__col' }, svg );
				} );
			} else {
				var xy = pts.map( function ( p, i ) { return [ +x( i ).toFixed( 1 ), +y( p[ 1 ] ).toFixed( 1 ) ]; } );
				var line = smooth( xy );
				var base = s.invert ? P.t + ih : P.t + ih;
				el( 'path', { d: line + 'L' + xy[ xy.length - 1 ][ 0 ] + ' ' + base + 'L' + xy[ 0 ][ 0 ] + ' ' + base + 'Z', fill: 'url(#' + gid + ')', 'class': 'bw-tx__area' }, svg );
				el( 'path', { d: line, 'class': 'bw-tx__line' }, svg );
				el( 'circle', { cx: xy[ xy.length - 1 ][ 0 ], cy: xy[ xy.length - 1 ][ 1 ], r: 4.5, 'class': 'bw-tx__end' }, svg );
			}

			var guide = el( 'line', { y1: P.t, y2: P.t + ih, 'class': 'bw-tx__guide', visibility: 'hidden' }, svg );
			var dot = el( 'circle', { r: 5, 'class': 'bw-tx__dot', visibility: 'hidden' }, svg );
			var hit = el( 'rect', { x: P.l, y: 0, width: iw, height: H, fill: 'transparent' }, svg );

			function move( clientX ) {
				var rect = svg.getBoundingClientRect();
				var px = ( clientX - rect.left ) * ( W / rect.width );
				var i = Math.max( 0, Math.min( pts.length - 1, Math.round( ( px - P.l ) / iw * ( pts.length - 1 ) ) ) );
				var p = pts[ i ], cx = x( i ), cy = y( p[ 1 ] );
				guide.setAttribute( 'x1', cx ); guide.setAttribute( 'x2', cx ); guide.setAttribute( 'visibility', 'visible' );
				dot.setAttribute( 'cx', cx ); dot.setAttribute( 'cy', cy ); dot.setAttribute( 'visibility', 'visible' );
				var prev = i > 0 ? pts[ i - 1 ][ 1 ] : null, ch = '';
				if ( prev !== null && s.kind !== 'bar' ) {
					var d = s.invert ? prev - p[ 1 ] : p[ 1 ] - prev;
					ch = d ? '<em class="' + ( d > 0 ? 'is-up' : 'is-down' ) + '">' + ( d > 0 ? '▲' : '▼' ) + ' ' + ( s.invert ? Math.abs( d ) : fmt( Math.abs( d ) ) ) + '</em>' : '<em>▬</em>';
				}
				tip.innerHTML = '<small>' + esc( fdate( p[ 0 ], true ) ) + '</small><b>' + esc( s.kind === 'bar' ? Math.round( p[ 1 ] ).toLocaleString() : fmt( p[ 1 ], s.invert ) ) + '</b>' + ch;
				tip.hidden = false;
				var left = cx / W * rect.width, tw = tip.offsetWidth;
				tip.style.left = Math.max( 0, Math.min( rect.width - tw, left - tw / 2 ) ) + 'px';
				tip.style.top = Math.max( 0, cy / H * rect.height - tip.offsetHeight - 14 ) + 'px';
			}
			function leave() { guide.setAttribute( 'visibility', 'hidden' ); dot.setAttribute( 'visibility', 'hidden' ); tip.hidden = true; }
			hit.addEventListener( 'mousemove', function ( e ) { move( e.clientX ); } );
			hit.addEventListener( 'mouseleave', leave );
			hit.addEventListener( 'touchstart', function ( e ) { move( e.touches[ 0 ].clientX ); }, { passive: true } );
			hit.addEventListener( 'touchmove', function ( e ) { move( e.touches[ 0 ].clientX ); }, { passive: true } );
			hit.addEventListener( 'touchend', leave );

			summary( pts, s );
		}

		box.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.bw-seg__btn' );
			if ( ! b ) { return; }
			var group = b.parentNode;
			group.querySelectorAll( '.bw-seg__btn' ).forEach( function ( x ) { x.classList.toggle( 'is-active', x === b ); } );
			if ( b.hasAttribute( 'data-metric' ) ) { state.metric = b.getAttribute( 'data-metric' ); }
			if ( b.hasAttribute( 'data-range' ) ) { state.range = parseInt( b.getAttribute( 'data-range' ), 10 ); }
			draw();
		} );
		if ( window.ResizeObserver ) {
			var lastW = 0;
			new ResizeObserver( function () { if ( plot.clientWidth !== lastW ) { lastW = plot.clientWidth; draw(); } } ).observe( plot );
		} else {
			window.addEventListener( 'resize', draw );
		}
		draw();
	} );

	// Auto-submit filter selects.
	document.querySelectorAll( '[data-bw-autosubmit]' ).forEach( function ( el ) {
		el.addEventListener( 'change', function () { el.form.submit(); } );
	} );

	// Detector + hosting tools.
	function runTool( form, value ) {
		var tool = form.getAttribute( 'data-bw-tool' );
		var out = form.parentNode.querySelector( '.bw-tool__result' );
		var btn = form.querySelector( 'button' );
		var label = btn.textContent;
		btn.disabled = true;
		btn.textContent = ( D.i18n && D.i18n.scanning ) || 'Scanning…';
		out.innerHTML = '<div class="bw-skeleton"><span></span><span></span><span></span></div>';
		post( tool === 'hosting' ? 'bwing_hosting' : 'bwing_detect', { url: value } ).then( function ( res ) {
			out.innerHTML = res.success ? res.data.html : '<div class="bw-alert">' + esc( res.data && res.data.message ? res.data.message : D.i18n.error ) + '</div>';
		} ).catch( function () {
			out.innerHTML = '<div class="bw-alert">' + esc( D.i18n.error ) + '</div>';
		} ).finally( function () {
			btn.disabled = false;
			btn.textContent = label;
		} );
	}
	document.querySelectorAll( '[data-bw-tool]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			runTool( form, form.url.value.trim() );
		} );
		var q = new URLSearchParams( window.location.search ).get( 'url' );
		if ( q ) { form.url.value = q; runTool( form, q ); }
	} );
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-bw-rescan]' );
		var form = document.querySelector( '[data-bw-tool="detect"]' );
		if ( ! b ) { return; }
		if ( ! form ) {
			window.location = ( D.detector || '/theme-detector/' ) + '?url=' + encodeURIComponent( b.getAttribute( 'data-bw-rescan' ) );
			return;
		}
		form.url.value = b.getAttribute( 'data-bw-rescan' );
		form.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		runTool( form, form.url.value );
	} );

	// Newsletter.
	document.querySelectorAll( '[data-bw-newsletter]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var msg = form.parentNode.querySelector( '.bw-newsletter__msg' );
			var btn = form.querySelector( 'button' );
			btn.disabled = true;
			post( 'bwing_subscribe', { email: form.email.value, website: form.website.value } ).then( function ( res ) {
				if ( res.success ) {
					form.reset();
					msg.className = 'bw-newsletter__msg is-ok';
					msg.textContent = form.getAttribute( 'data-success' ) || D.i18n.subscribed;
				} else {
					msg.className = 'bw-newsletter__msg is-err';
					msg.textContent = res.data && res.data.message ? res.data.message : D.i18n.error;
				}
			} ).finally( function () { btn.disabled = false; } );
		} );
	} );
} )();
