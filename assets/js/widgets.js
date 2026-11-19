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

	/** Twin of Heading::INLINE_HTML: keep these tags (and attributes), escape the rest. */
	var INLINE = { br: [], span: [ 'class' ], strong: [], b: [], em: [], i: [], u: [], small: [], sup: [], sub: [], a: [ 'href', 'target', 'rel' ] };

	function inlineHtml( html ) {
		var doc = new DOMParser().parseFromString( '<body>' + String( html == null ? '' : html ) + '</body>', 'text/html' );
		function walk( node ) {
			var out = '';
			node.childNodes.forEach( function ( child ) {
				if ( child.nodeType === 3 ) {
					out += esc( child.textContent );
				} else if ( child.nodeType === 1 ) {
					var tag = child.tagName.toLowerCase();
					var inner = walk( child );
					if ( ! INLINE[ tag ] ) {
						out += inner;
						return;
					}
					var attrs = INLINE[ tag ]
						.filter( function ( a ) {
							return child.hasAttribute( a ) && ! ( a === 'href' && /^\s*javascript:/i.test( child.getAttribute( a ) ) );
						} )
						.map( function ( a ) {
							return ' ' + a + '="' + esc( child.getAttribute( a ) ) + '"';
						} )
						.join( '' );
					out += tag === 'br' ? '<br />' : '<' + tag + attrs + '>' + inner + '</' + tag + '>';
				}
			} );
			return out;
		}
		return walk( doc.body );
	}

	renderers.heading = function ( s ) {
		var tag = HEADING_TAGS.indexOf( s.header_size ) > -1 ? s.header_size : 'h2';
		return '<' + tag + ' class="kbroo-heading">' + inlineHtml( s.title ) + '</' + tag + '>';
	};

	renderers[ 'text-editor' ] = function ( s ) {
		// The author's own HTML, previewed as typed. The server runs it
		// through wp_kses_post() when the layout is saved and rendered.
		return '<div class="kbroo-text">' + autop( s.editor ) + '</div>';
	};

	/** Twin of Widget::link_attributes(). */
	function linkAttributes( link ) {
		if ( ! link || ! link.url ) {
			return '';
		}
		var out = ' href="' + esc( link.url ) + '"';
		var rel = [];
		if ( link.is_external === 'on' ) {
			out += ' target="_blank"';
			rel.push( 'noopener' );
		}
		if ( link.nofollow === 'on' ) {
			rel.push( 'nofollow' );
		}
		return rel.length ? out + ' rel="' + rel.join( ' ' ) + '"' : out;
	}

	// Empty widgets render nothing on the published page. In the editor they
	// show a hint instead (kb- classes are editor-only).
	function hint( iconName, text ) {
		return '<div class="kb-widget-hint"><span class="dashicons ' + iconName + '"></span>' + esc( text ) + '</div>';
	}

	var PLACEHOLDER_IMAGE =
		'data:image/svg+xml;charset=utf-8,' +
		encodeURIComponent(
			'<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">' +
				'<rect width="800" height="450" fill="#e5e7eb"/>' +
				'<path d="M300 300l70-90 50 60 40-40 80 70z" fill="#9ca3af"/>' +
				'<circle cx="470" cy="170" r="28" fill="#9ca3af"/></svg>'
		);

	/** Attachment captions, filled in by the editor from the Media Library. */
	var captions = {};

	renderers.image = function ( s ) {
		var image = s.image || {};
		var img = '<img src="' + esc( image.url || PLACEHOLDER_IMAGE ) + '" alt="" />';
		var link = '';
		if ( s.link_to === 'file' && image.url ) {
			link = ' href="' + esc( image.url ) + '"';
		} else if ( s.link_to === 'custom' ) {
			link = linkAttributes( s.link );
		}
		if ( link ) {
			img = '<a' + link + '>' + img + '</a>';
		}
		var caption = '';
		if ( s.caption_source === 'attachment' && image.id ) {
			caption = captions[ image.id ] || '';
		} else if ( s.caption_source === 'custom' ) {
			caption = s.caption || '';
		}
		if ( caption ) {
			img += '<figcaption class="kbroo-image__caption">' + esc( caption ) + '</figcaption>';
		}
		return '<figure class="kbroo-image">' + img + '</figure>';
	};

	var BUTTON_SIZES = [ 'xs', 'sm', 'md', 'lg', 'xl' ];

	renderers.button = function ( s ) {
		var size = BUTTON_SIZES.indexOf( s.size ) > -1 ? s.size : 'sm';
		var link = linkAttributes( s.link );
		var tag = link ? 'a' : 'span';
		return (
			'<div class="kbroo-button-wrapper"><' + tag + ' class="kbroo-button kbroo-button--' + size + '"' + link + '>' +
			'<span class="kbroo-button__text">' + esc( s.text ) + '</span></' + tag + '></div>'
		);
	};

	renderers.spacer = function () {
		return '<div class="kbroo-spacer" aria-hidden="true"></div>';
	};

	var DIVIDER_TAGS = [ 'span', 'div', 'p', 'h2', 'h3', 'h4', 'h5', 'h6' ];

	renderers.divider = function ( s ) {
		if ( s.look !== 'line_text' || ! String( s.text || '' ).trim() ) {
			return '<div class="kbroo-divider" role="separator"><span class="kbroo-divider__line"></span></div>';
		}
		var tag = DIVIDER_TAGS.indexOf( s.html_tag ) > -1 ? s.html_tag : 'span';
		var position = [ 'left', 'center', 'right' ].indexOf( s.text_align ) > -1 ? s.text_align : 'center';
		return (
			'<div class="kbroo-divider kbroo-divider--text kbroo-divider--text-' + position + '">' +
			'<span class="kbroo-divider__line"></span><' + tag + ' class="kbroo-divider__text">' + esc( s.text ) + '</' + tag + '>' +
			'<span class="kbroo-divider__line"></span></div>'
		);
	};

	renderers.html = function ( s ) {
		// Scripts added with innerHTML do not run, so the canvas shows the
		// markup without its behaviour. The published page runs it.
		return String( s.html || '' ).trim() ? s.html : hint( 'dashicons-editor-code', 'Add your HTML code in the panel.' );
	};

	renderers[ 'menu-anchor' ] = function ( s ) {
		return s.anchor
			? '<div class="kbroo-menu-anchor" id="' + esc( s.anchor ) + '"></div>' + hint( 'dashicons-admin-links', 'Anchor: #' + s.anchor )
			: hint( 'dashicons-admin-links', 'Name this anchor, then link to it with #name.' );
	};

	window.KbrooStudio = window.KbrooStudio || {};
	window.KbrooStudio.linkAttributes = linkAttributes;
	window.KbrooStudio.hint = hint;
	window.KbrooStudio.captions = captions;
	window.KbrooStudio.widgetRenderers = renderers;
	window.KbrooStudio.esc = esc;
	window.KbrooStudio.registerWidget = function ( name, render ) {
		renderers[ name ] = render;
	};
} )();
