/**
 * Reading Progress bar tracker.
 *
 * For each .reading-progress-bar on the page: find the element named in
 * data-rp-selector, then on scroll set the fill's scale (0..1) to how far the
 * viewport has travelled through that element. Transform-based so it stays
 * smooth; the CSS transition on the fill does the animating.
 */
( function () {
	'use strict';

	function setup( bar ) {
		var fill = bar.querySelector( '.reading-progress-bar__fill' );
		if ( ! fill ) {
			return;
		}

		var selector = bar.getAttribute( 'data-rp-selector' );
		var vertical = bar.getAttribute( 'data-rp-orientation' ) === 'vertical';
		var target = selector ? document.querySelector( selector ) : null;
		if ( ! target ) {
			return; // Nothing to track — leave the bar at 0.
		}

		// Reading starts a little below the top of the viewport — people don't
		// read the very top line, they scroll as they go. Progress begins when
		// the tracked element's top is this far down the viewport.
		var START_OFFSET = 0.3; // 30vh

		var ticking = false;

		function update() {
			ticking = false;

			var rect = target.getBoundingClientRect();
			var viewport = window.innerHeight || document.documentElement.clientHeight;
			var startTop = viewport * START_OFFSET;
			var range = rect.height - ( viewport - startTop );

			var progress;
			if ( range <= 0 ) {
				// Element shorter than the readable range: full once its top
				// passes the start line.
				progress = rect.top <= startTop ? 1 : 0;
			} else {
				progress = ( startTop - rect.top ) / range;
			}

			if ( progress < 0 ) {
				progress = 0;
			} else if ( progress > 1 ) {
				progress = 1;
			}

			fill.style.transform = ( vertical ? 'scaleY(' : 'scaleX(' ) + progress + ')';
		}

		function onScroll() {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		}

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onScroll, { passive: true } );
		update();
	}

	function init() {
		var bars = document.querySelectorAll( '.reading-progress-bar' );
		for ( var i = 0; i < bars.length; i++ ) {
			setup( bars[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
