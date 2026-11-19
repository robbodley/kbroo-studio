/**
 * Kbroo Studio: front-end behaviour.
 *
 * - Entrance animations: elements with data-kbroo-animation stay hidden
 *   (see frontend.css) until they scroll into view, then play once.
 * - Carousels (.kbroo-carousel): arrows, dots or a counter, autoplay and
 *   looping on top of a scroll-snap strip that works without this script.
 *
 * The editor calls KbrooStudio.initCarousels( canvas, { autoplay: false } )
 * after drawing the canvas.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* Entrance animations ------------------------------------------------ */

	function initAnimations() {
		if ( ! document.documentElement.classList.contains( 'kbroo-js' ) ) {
			return;
		}
		var elements = document.querySelectorAll( '[data-kbroo-animation]' );

		function play( element ) {
			var delay = parseInt( element.getAttribute( 'data-kbroo-delay' ), 10 ) || 0;
			setTimeout( function () {
				element.classList.add( 'kbroo-animated' );
			}, delay );
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			Array.prototype.forEach.call( elements, play );
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						observer.unobserve( entry.target );
						play( entry.target );
					}
				} );
			},
			{ rootMargin: '0px 0px -10% 0px' }
		);
		Array.prototype.forEach.call( elements, function ( element ) {
			observer.observe( element );
		} );
	}

	/* Carousels ---------------------------------------------------------- */

	function initCarousel( root, overrides ) {
		if ( root.kbrooCarousel ) {
			return;
		}
		var options = {};
		try {
			options = JSON.parse( root.getAttribute( 'data-kbroo-carousel' ) || '{}' );
		} catch ( e ) {}
		Object.assign( options, overrides || {} );

		var track = root.querySelector( '.kbroo-carousel__track' );
		var slides = Array.prototype.slice.call( track.children );
		var prev = root.querySelector( '.kbroo-carousel__arrow--prev' );
		var next = root.querySelector( '.kbroo-carousel__arrow--next' );
		var pagination = root.querySelector( '.kbroo-carousel__pagination' );
		var step = Math.max( 1, options.scroll || 1 );
		var timer = null;
		var paused = false;

		function perView() {
			if ( ! slides.length ) {
				return 1;
			}
			return Math.max( 1, Math.round( track.clientWidth / slides[ 0 ].getBoundingClientRect().width ) );
		}

		/** Index of the first slide in view. */
		function current() {
			var left = track.scrollLeft;
			var best = 0;
			slides.forEach( function ( slide, i ) {
				if ( Math.abs( slide.offsetLeft - track.offsetLeft - left ) < Math.abs( slides[ best ].offsetLeft - track.offsetLeft - left ) ) {
					best = i;
				}
			} );
			return best;
		}

		function lastStart() {
			return Math.max( 0, slides.length - perView() );
		}

		function go( index, instant ) {
			index = Math.max( 0, Math.min( index, lastStart() ) );
			track.scrollTo( {
				left: slides[ index ].offsetLeft - track.offsetLeft,
				behavior: instant || reduceMotion ? 'auto' : 'smooth',
			} );
		}

		function move( direction ) {
			var at = current();
			var target = at + direction * step;
			if ( options.loop ) {
				if ( direction > 0 && at >= lastStart() ) {
					target = 0;
				} else if ( direction < 0 && at <= 0 ) {
					target = lastStart();
				}
			}
			go( target );
		}

		function pages() {
			return lastStart() + 1;
		}

		function update() {
			var at = current();
			var count = pages();
			root.classList.toggle( 'is-static', count <= 1 );
			if ( prev && next && ! options.loop ) {
				prev.disabled = at <= 0;
				next.disabled = at >= lastStart();
			}
			if ( ! pagination ) {
				return;
			}
			if ( pagination.classList.contains( 'kbroo-carousel__pagination--fraction' ) ) {
				pagination.textContent = Math.min( at + 1, count ) + ' / ' + count;
				return;
			}
			if ( pagination.children.length !== count ) {
				pagination.textContent = '';
				for ( var i = 0; i < count; i++ ) {
					var dot = document.createElement( 'button' );
					dot.type = 'button';
					dot.className = 'kbroo-carousel__dot';
					dot.setAttribute( 'aria-label', 'Go to slide ' + ( i + 1 ) );
					dot.addEventListener( 'click', go.bind( null, i, false ) );
					pagination.appendChild( dot );
				}
			}
			Array.prototype.forEach.call( pagination.children, function ( dot, i ) {
				dot.setAttribute( 'aria-current', i === Math.min( at, count - 1 ) ? 'true' : 'false' );
			} );
		}

		function schedule() {
			clearInterval( timer );
			if ( options.autoplay && ! reduceMotion ) {
				timer = setInterval( function () {
					if ( ! paused && ! document.hidden ) {
						if ( ! options.loop && current() >= lastStart() ) {
							clearInterval( timer );
							return;
						}
						move( 1 );
					}
				}, options.autoplay );
			}
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				move( -1 );
				schedule();
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				move( 1 );
				schedule();
			} );
		}
		var scrollTimer;
		track.addEventListener( 'scroll', function () {
			clearTimeout( scrollTimer );
			scrollTimer = setTimeout( update, 60 );
		} );
		track.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
				e.preventDefault();
				move( e.key === 'ArrowRight' ? 1 : -1 );
			}
		} );
		if ( options.pause ) {
			root.addEventListener( 'mouseenter', function () {
				paused = true;
			} );
			root.addEventListener( 'mouseleave', function () {
				paused = false;
			} );
		}
		// Keyboard users: stop while anything inside has focus.
		root.addEventListener( 'focusin', function () {
			paused = true;
		} );
		root.addEventListener( 'focusout', function () {
			paused = false;
		} );
		window.addEventListener( 'resize', update );

		root.kbrooCarousel = { go: go, move: move };
		root.classList.add( 'is-ready' );
		update();
		schedule();
	}

	function initCarousels( container, overrides ) {
		Array.prototype.forEach.call( ( container || document ).querySelectorAll( '.kbroo-carousel' ), function ( root ) {
			initCarousel( root, overrides );
		} );
	}

	window.KbrooStudio = window.KbrooStudio || {};
	window.KbrooStudio.initCarousels = initCarousels;

	function ready() {
		initAnimations();
		if ( ! window.KbrooStudio.editor ) {
			initCarousels( document );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
