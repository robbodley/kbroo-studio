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

	/* Forms -------------------------------------------------------------- */

	function initForm( form ) {
		if ( form.kbrooForm ) {
			return;
		}
		form.kbrooForm = true;
		var texts = {};
		try {
			texts = JSON.parse( form.getAttribute( 'data-kbroo-form' ) || '{}' );
		} catch ( e ) {}
		var message = form.querySelector( '.kbroo-form__message' );
		var button = form.querySelector( '[type=submit]' );

		function show( text, ok ) {
			message.textContent = text || '';
			message.classList.toggle( 'is-success', !! ok );
			message.classList.toggle( 'is-error', ! ok );
		}

		function clearErrors() {
			Array.prototype.forEach.call( form.querySelectorAll( '.kbroo-form__field.has-error' ), function ( field ) {
				field.classList.remove( 'has-error' );
				var error = field.querySelector( '.kbroo-form__error' );
				error.hidden = true;
				error.textContent = '';
				Array.prototype.forEach.call( field.querySelectorAll( 'input, select, textarea' ), function ( input ) {
					input.removeAttribute( 'aria-invalid' );
				} );
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			if ( window.KbrooStudio.editor ) {
				e.preventDefault(); // Never sent from the editor's preview.
				return;
			}
			if ( ! window.fetch || ! window.FormData ) {
				return; // Ordinary post and redirect.
			}
			e.preventDefault();
			clearErrors();
			var data = new FormData( form );
			data.append( 'kbroo_ajax', '1' );
			button.disabled = true;
			form.classList.add( 'is-sending' );
			// form.action would be the hidden input named "action".
			fetch( form.getAttribute( 'action' ), { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( result.success && result.redirect ) {
						window.location.href = result.redirect;
						return;
					}
					show( result.message, result.success );
					if ( result.success ) {
						form.reset();
						return;
					}
					var first = null;
					Object.keys( result.errors || {} ).forEach( function ( name ) {
						var field = form.querySelector( '.kbroo-form__field[data-field="' + name.replace( /[^A-Za-z0-9_]/g, '' ) + '"]' );
						if ( ! field ) {
							return;
						}
						field.classList.add( 'has-error' );
						var error = field.querySelector( '.kbroo-form__error' );
						error.textContent = result.errors[ name ];
						error.hidden = false;
						var input = field.querySelector( 'input, select, textarea' );
						if ( input ) {
							input.setAttribute( 'aria-invalid', 'true' );
							first = first || input;
						}
					} );
					if ( first ) {
						first.focus();
					}
				} )
				.catch( function () {
					show( texts.error, false );
				} )
				.then( function () {
					button.disabled = false;
					form.classList.remove( 'is-sending' );
				} );
		} );
	}

	window.KbrooStudio = window.KbrooStudio || {};
	window.KbrooStudio.initCarousels = initCarousels;

	function ready() {
		initAnimations();
		if ( ! window.KbrooStudio.editor ) {
			initCarousels( document );
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.kbroo-form' ), initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
