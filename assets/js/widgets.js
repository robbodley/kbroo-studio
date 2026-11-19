/**
 * Kbroo Studio: editor-side widget renderers.
 *
 * Each renderer returns the same inner HTML that the matching PHP widget's
 * render() produces, so the canvas preview matches the published page.
 * Third-party widgets can add their own with KbrooStudio.registerWidget().
 */
( function () {
	'use strict';

	var renderers = {};

	function esc( value ) {
		return String( value == null ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/**
	 * Close-enough preview of WordPress's wpautop(): blank lines become
	 * paragraphs and single newlines become line breaks, unless the content
	 * already contains block-level HTML.
	 */
	function autop( html ) {
		html = String( html || '' ).trim();
		if ( ! html ) {
			return '';
		}
		if ( /<(p|div|ul|ol|h[1-6]|blockquote|table|pre|figure)[\s>]/i.test( html ) ) {
			return html;
		}
		return html
			.split( /\n\s*\n/ )
			.map( function ( para ) {
				return '<p>' + para.trim().replace( /\n/g, '<br />' ) + '</p>';
			} )
			.join( '\n' );
	}

	var HEADING_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ];

	// Styling comes from each control's `selectors` (see css.js), so the
	// markup carries no inline styles, exactly like the PHP renderers.

	renderers.heading = function ( s ) {
		var tag = HEADING_TAGS.indexOf( s.header_size ) > -1 ? s.header_size : 'h2';
		return '<' + tag + ' class="kbroo-heading">' + esc( s.title ) + '</' + tag + '>';
	};

	renderers[ 'text-editor' ] = function ( s ) {
		// The author's own HTML, previewed as typed. The server runs it
		// through wp_kses_post() when the layout is saved and rendered.
		return '<div class="kbroo-text">' + autop( s.editor ) + '</div>';
	};

	window.KbrooStudio = window.KbrooStudio || {};
	window.KbrooStudio.widgetRenderers = renderers;
	window.KbrooStudio.esc = esc;
	window.KbrooStudio.registerWidget = function ( name, render ) {
		renderers[ name ] = render;
	};
} )();
