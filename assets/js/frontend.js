/**
 * Kbroo Studio: entrance animations on the front end.
 *
 * Elements with data-kbroo-animation stay hidden (see frontend.css) until
 * they scroll into view, then play their animation once, after their delay.
 */
( function () {
	'use strict';

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
} )();
