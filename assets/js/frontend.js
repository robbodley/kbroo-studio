/**
 * Kbroo Studio: front-end behaviour.
 *
 * - Entrance animations: elements with data-kbroo-animation stay hidden
 *   (see frontend.css) until they scroll into view, then play once.
 * - Nav menus (.kbroo-nav): the burger button opens and closes the menu
 *   (a drop-down, side panel or full-screen menu).
 * - Carousels (.kbroo-carousel): arrows, dots or a counter, autoplay and
 *   looping on top of a scroll-snap strip that works without this script.
 * - Forms (.kbroo-form): send without reloading and show field errors.
 * - Lightbox: links with data-kbroo-lightbox="<group>" open their image in
 *   a full-screen dialog, with previous/next through the group.
 * - Videos: an image overlay (.kbroo-video__overlay) is swapped for the
 *   player on click, so nothing loads from YouTube or Vimeo before then.
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

	/* Lightbox ----------------------------------------------------------- */

	var lightbox = null;

	function icon( path ) {
		return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + path + '" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
	}

	function openLightbox( link ) {
		var group = link.getAttribute( 'data-kbroo-lightbox' );
		var links = Array.prototype.filter.call( document.querySelectorAll( 'a[data-kbroo-lightbox]' ), function ( other ) {
			return other.getAttribute( 'data-kbroo-lightbox' ) === group;
		} );
		var index = Math.max( 0, links.indexOf( link ) );

		if ( ! lightbox ) {
			lightbox = document.createElement( 'dialog' );
			lightbox.className = 'kbroo-lightbox';
			lightbox.setAttribute( 'aria-label', 'Image viewer' );
			lightbox.innerHTML =
				'<figure class="kbroo-lightbox__figure"><img class="kbroo-lightbox__image" alt="" /><figcaption class="kbroo-lightbox__caption"></figcaption></figure>' +
				'<span class="kbroo-lightbox__counter" aria-live="polite"></span>' +
				'<button type="button" class="kbroo-lightbox__button kbroo-lightbox__prev" aria-label="Previous image">' + icon( 'M15 5l-7 7 7 7' ) + '</button>' +
				'<button type="button" class="kbroo-lightbox__button kbroo-lightbox__next" aria-label="Next image">' + icon( 'M9 5l7 7-7 7' ) + '</button>' +
				'<button type="button" class="kbroo-lightbox__button kbroo-lightbox__close" aria-label="Close">' + icon( 'M6 6l12 12M18 6L6 18' ) + '</button>';
			document.body.appendChild( lightbox );
			lightbox.querySelector( '.kbroo-lightbox__close' ).addEventListener( 'click', function () {
				lightbox.close();
			} );
			lightbox.addEventListener( 'click', function ( e ) {
				if ( e.target === lightbox || e.target.classList.contains( 'kbroo-lightbox__figure' ) ) {
					lightbox.close();
				}
			} );
			lightbox.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
					e.preventDefault();
					lightbox.kbrooStep( e.key === 'ArrowRight' ? 1 : -1 );
				}
			} );
			lightbox.querySelector( '.kbroo-lightbox__prev' ).addEventListener( 'click', function () {
				lightbox.kbrooStep( -1 );
			} );
			lightbox.querySelector( '.kbroo-lightbox__next' ).addEventListener( 'click', function () {
				lightbox.kbrooStep( 1 );
			} );
			lightbox.addEventListener( 'close', function () {
				if ( lightbox.kbrooOpener ) {
					lightbox.kbrooOpener.focus();
				}
			} );
		}

		function show() {
			var current = links[ index ];
			var image = lightbox.querySelector( '.kbroo-lightbox__image' );
			var inner = current.querySelector( 'img' );
			image.src = current.href;
			image.alt = inner ? inner.alt : '';
			lightbox.querySelector( '.kbroo-lightbox__caption' ).textContent = current.getAttribute( 'data-kbroo-caption' ) || '';
			lightbox.querySelector( '.kbroo-lightbox__counter' ).textContent = links.length > 1 ? index + 1 + ' / ' + links.length : '';
			lightbox.querySelector( '.kbroo-lightbox__prev' ).hidden = links.length < 2;
			lightbox.querySelector( '.kbroo-lightbox__next' ).hidden = links.length < 2;
		}

		lightbox.kbrooStep = function ( direction ) {
			index = ( index + direction + links.length ) % links.length;
			show();
		};
		lightbox.kbrooOpener = link;
		show();
		if ( typeof lightbox.showModal === 'function' ) {
			lightbox.showModal();
		} else {
			lightbox.setAttribute( 'open', '' );
		}
		lightbox.querySelector( '.kbroo-lightbox__close' ).focus();
	}

	/* Videos ------------------------------------------------------------- */

	function playVideo( overlay ) {
		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = overlay.getAttribute( 'data-kbroo-video' );
		var player = wrapper.firstElementChild;
		if ( ! player ) {
			return;
		}
		overlay.parentNode.replaceChild( player, overlay );
		if ( player.tagName === 'VIDEO' ) {
			player.play().catch( function () {} );
		}
		player.focus();
	}

	/** Phones: no autoplay where the widget says so (play_on_mobile off). */
	function stopMobileAutoplay() {
		if ( ! window.matchMedia || ! window.matchMedia( '(max-width: 767px)' ).matches ) {
			return;
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.kbroo-video--no-mobile-autoplay .kbroo-video__player' ), function ( player ) {
			if ( player.tagName === 'VIDEO' ) {
				player.autoplay = false;
				player.pause();
			} else if ( /[?&]autoplay=1/.test( player.src ) ) {
				player.src = player.src.replace( /([?&]autoplay=)1/, '$10' );
			}
		} );
	}

	function initClicks() {
		document.addEventListener( 'click', function ( e ) {
			if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return;
			}
			var link = e.target.closest && e.target.closest( 'a[data-kbroo-lightbox]' );
			if ( link ) {
				e.preventDefault();
				openLightbox( link );
				return;
			}
			var overlay = e.target.closest && e.target.closest( '.kbroo-video__overlay' );
			if ( overlay ) {
				playVideo( overlay );
				return;
			}
			var toggle = e.target.closest && e.target.closest( '.kbroo-nav__toggle' );
			if ( toggle ) {
				toggleNav( toggle.closest( '.kbroo-nav' ) );
				return;
			}
			var close = e.target.closest && e.target.closest( '.kbroo-nav__close' );
			if ( close ) {
				toggleNav( close.closest( '.kbroo-nav' ), false );
				return;
			}
			// A click on the shade beside a side panel lands on the nav itself.
			// On the live site a click on a link (often a #section on the same
			// page) or anywhere else closes an open burger menu too; in the
			// editor it stays open while its settings change.
			Array.prototype.forEach.call( document.querySelectorAll( '.kbroo-nav.is-open' ), function ( nav ) {
				var live = ! nav.closest( '.kb-canvas' );
				if ( e.target === nav || ( live && ( ! nav.contains( e.target ) || e.target.closest( 'a[href]' ) ) ) ) {
					toggleNav( nav, false );
				}
			} );
		} );
		// Grown past the burger size: close it. A full-width drop-down
		// follows the page's edges.
		window.addEventListener( 'resize', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.kbroo-nav.is-open' ), function ( nav ) {
				var toggle = nav.querySelector( '.kbroo-nav__toggle' );
				if ( ! toggle || ! toggle.offsetParent ) {
					toggleNav( nav, false );
				} else {
					stretchNav( nav );
				}
			} );
		} );
		// Esc closes an open menu and returns focus to its button.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' ) {
				return;
			}
			var open = document.querySelector( '.kbroo-nav.is-open' );
			if ( open ) {
				toggleNav( open, false );
				open.querySelector( '.kbroo-nav__toggle' ).focus();
			}
		} );
	}

	/** Open or close a nav menu's burger menu. */
	function toggleNav( nav, force ) {
		if ( ! nav ) {
			return;
		}
		var open = typeof force === 'boolean' ? force : ! nav.classList.contains( 'is-open' );
		var panel = nav.classList.contains( 'kbroo-nav--panel-side' ) || nav.classList.contains( 'kbroo-nav--panel-fullscreen' );
		nav.classList.toggle( 'is-open', open );
		nav.querySelector( '.kbroo-nav__toggle' ).setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			stretchNav( nav );
		}
		if ( panel && ! nav.closest( '.kb-canvas' ) ) {
			// The page stays put behind a side panel or full-screen menu.
			document.documentElement.classList.toggle( 'kbroo-nav-locked', open );
			var close = nav.querySelector( '.kbroo-nav__close' );
			if ( open && close ) {
				close.focus();
			} else if ( ! open && nav.contains( document.activeElement ) ) {
				nav.querySelector( '.kbroo-nav__toggle' ).focus();
			}
		}
	}

	/** A full-width drop-down spans the page, wherever the button is. */
	function stretchNav( nav ) {
		if ( ! nav.classList.contains( 'kbroo-nav--stretch' ) ) {
			return;
		}
		var page = nav.closest( '.kb-canvas' );
		var edge = page ? page.getBoundingClientRect() : { left: 0, width: document.documentElement.clientWidth };
		nav.style.setProperty( '--kbroo-nav-stretch-left', ( edge.left - nav.getBoundingClientRect().left ) + 'px' );
		nav.style.setProperty( '--kbroo-nav-stretch-width', edge.width + 'px' );
	}

	window.KbrooStudio = window.KbrooStudio || {};
	window.KbrooStudio.initCarousels = initCarousels;
	window.KbrooStudio.stretchNav = stretchNav;

	function ready() {
		initAnimations();
		if ( ! window.KbrooStudio.editor ) {
			initCarousels( document );
			initClicks();
			stopMobileAutoplay();
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.kbroo-form' ), initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
