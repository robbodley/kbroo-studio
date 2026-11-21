/**
 * Kbroo Studio: the full-screen editor.
 *
 * Plain JavaScript, no build step. The editor keeps the layout tree in
 * `state.layout`, re-renders the canvas from it after every change, and saves
 * it to the REST route registered in src/Rest.php.
 */
( function () {
	'use strict';

	var config = window.kbrooConfig;
	var renderers = window.KbrooStudio.widgetRenderers;
	var Css = window.KbrooStudio.css;

	var STRUCTURES = [
		{ label: '100', widths: [ 100 ] },
		{ label: '50 / 50', widths: [ 50, 50 ] },
		{ label: '33 / 33 / 33', widths: [ 33.33, 33.33, 33.34 ] },
		{ label: '66 / 33', widths: [ 66.66, 33.34 ] },
		{ label: '33 / 66', widths: [ 33.34, 66.66 ] },
		{ label: '25 x 4', widths: [ 25, 25, 25, 25 ] },
	];

	var DEVICES = {
		desktop: { label: 'Desktop', icon: 'dashicons-desktop', width: null },
		tablet: { label: 'Tablet', icon: 'dashicons-tablet', width: 768 },
		mobile: { label: 'Mobile', icon: 'dashicons-smartphone', width: 375 },
	};

	var TABS = { content: 'Content', style: 'Style', advanced: 'Advanced' };

	var HISTORY_LIMIT = 100;
	var MERGE_WINDOW = 1000; // ms: typing in one field becomes one undo step.

	var state = {
		layout: normalizeLayout( config.layout ),
		selected: null, // { id, kind }
		device: 'desktop',
		tab: 'content',
		status: config.status,
		permalink: config.permalink,
		dirty: false,
		saving: false,
		revision: 0,
		navigator: readPref( 'navigator' ) === '1',
		panelView: 'widgets', // Shown when nothing is selected: widgets | globals | revisions | templates.
		title: config.title || '',
		templateDraft: null, // { elements, label } waiting to be saved as a template.
		display: config.display ? JSON.parse( JSON.stringify( config.display ) ) : null, // Header/footer display settings.
		page: config.page ? JSON.parse( JSON.stringify( config.page ) ) : null, // Page settings (pages and posts only).
		globals: JSON.parse( JSON.stringify( config.globals ) ),
		globalsDirty: false,
		preview: null, // { id, label, layout } while previewing a revision.
	};

	var history = { stack: [], index: -1, mergeKey: null, mergeTime: 0, savedIndex: 0 };

	var drag = null; // { kind: 'new', widgetType } | { kind: 'new', elType } | { kind: 'move', id }
	var dropTarget = null;
	var placeholder = el( 'div', { class: 'kb-placeholder' } );

	var ui = {};

	/* ------------------------------------------------------------------ *
	 * Small helpers
	 * ------------------------------------------------------------------ */

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( value === false || value == null ) {
				return;
			}
			if ( key.indexOf( 'on' ) === 0 && typeof value === 'function' ) {
				node.addEventListener( key.slice( 2 ), value );
			} else if ( key === 'html' ) {
				node.innerHTML = value;
			} else if ( key === 'text' ) {
				node.textContent = value;
			} else {
				node.setAttribute( key, value === true ? '' : value );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	/** Per-viewer UI preferences; storage can be unavailable, so never rely on it. */
	function readPref( key ) {
		try {
			return window.localStorage.getItem( 'kbroo-studio:' + key );
		} catch ( e ) {
			return null;
		}
	}

	function writePref( key, value ) {
		try {
			window.localStorage.setItem( 'kbroo-studio:' + key, value );
		} catch ( e ) {}
	}

	function icon( name ) {
		return el( 'span', { class: 'dashicons ' + name, 'aria-hidden': 'true' } );
	}

	function uid() {
		var bytes = new Uint8Array( 4 );
		window.crypto.getRandomValues( bytes );
		return Array.prototype.map
			.call( bytes, function ( b ) {
				return ( '0' + b.toString( 16 ) ).slice( -2 );
			} )
			.join( '' )
			.slice( 0, 7 );
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	/** Defaults for every setting key, including tablet and mobile variants. */
	function defaults( controls ) {
		var out = {};
		Object.keys( controls ).forEach( function ( key ) {
			var control = controls[ key ];
			var keys = Css.keys( key, control );
			Object.keys( keys ).forEach( function ( device ) {
				var value = device === 'desktop' ? control.default : control[ 'default_' + device ];
				out[ keys[ device ] ] = value === undefined ? '' : clone( value );
			} );
		} );
		return out;
	}

	function withDefaults( controls, settings ) {
		return Object.assign( defaults( controls ), settings || {} );
	}

	function normalizeLayout( layout ) {
		layout = layout && typeof layout === 'object' ? clone( layout ) : {};
		layout.version = 1;
		layout.elements = Array.isArray( layout.elements ) ? layout.elements : [];
		return layout;
	}

	function isSet( control, value ) {
		if ( value == null || value === '' ) {
			return false;
		}
		if ( control.type === 'slider' ) {
			return value.size !== '' && value.size != null;
		}
		if ( control.type === 'dimensions' ) {
			return [ 'top', 'right', 'bottom', 'left' ].some( function ( side ) {
				return value[ side ] !== '' && value[ side ] != null;
			} );
		}
		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Layout tree operations
	 * ------------------------------------------------------------------ */

	function makeSection( widths ) {
		return {
			id: uid(),
			elType: 'section',
			settings: defaults( config.sectionControls ),
			elements: widths.map( makeColumn ),
		};
	}

	function makeColumn( width ) {
		var settings = defaults( config.columnControls );
		settings._inline_size = width;
		return { id: uid(), elType: 'column', settings: settings, elements: [] };
	}

	function emptyDimensions() {
		return { unit: 'px', top: '', right: '', bottom: '', left: '', isLinked: true };
	}

	/** An inner section: two columns, no padding of its own. */
	function makeInnerSection() {
		var section = makeSection( [ 50, 50 ] );
		section.isInner = true;
		section.settings.padding = emptyDimensions();
		return section;
	}

	function makeContainer() {
		return { id: uid(), elType: 'container', isInner: false, settings: defaults( config.containerControls ), elements: [] };
	}

	/** Twin of Layout::allowed_children(). */
	function allowedChildren( parentType, inInner ) {
		switch ( parentType ) {
			case 'page':
				return [ 'section', 'container' ];
			case 'section':
				return [ 'column' ];
			case 'column':
				return inInner ? [ 'widget', 'container' ] : [ 'widget', 'section', 'container' ];
			case 'container':
				return [ 'widget', 'container' ];
		}
		return [];
	}

	/** Nodes from the top of the page down to the element (inclusive). */
	function pathTo( id, list, trail ) {
		list = list || state.layout.elements;
		trail = trail || [];
		for ( var i = 0; i < list.length; i++ ) {
			var here = trail.concat( [ list[ i ] ] );
			if ( list[ i ].id === id ) {
				return here;
			}
			if ( list[ i ].elements ) {
				var found = pathTo( id, list[ i ].elements, here );
				if ( found ) {
					return found;
				}
			}
		}
		return null;
	}

	function insideInnerSection( id ) {
		return ( pathTo( id ) || [] ).some( function ( n ) {
			return n.elType === 'section' && n.isInner;
		} );
	}

	function hasInnerSection( node ) {
		return ( node.elements || [] ).some( function ( child ) {
			return ( child.elType === 'section' && child.isInner ) || hasInnerSection( child );
		} );
	}

	/**
	 * Whether `holder` (a node, or null for the page) may take `node` (or a
	 * new element of type `elType`) as a child.
	 */
	function canHold( holder, elType, node ) {
		var parentType = holder ? holder.elType : 'page';
		var inInner = !! holder && holder.elType === 'column' && insideInnerSection( holder.id );
		if ( allowedChildren( parentType, inInner ).indexOf( elType ) === -1 ) {
			return false;
		}
		if ( holder && node && ( node.id === holder.id || find( holder.id, node.elements || [] ) ) ) {
			return false; // Not into itself.
		}
		if ( holder && elType === 'section' && node && hasInnerSection( node ) ) {
			return false; // Inner sections nest one level only.
		}
		if ( holder && elType === 'column' && holder.elements.length >= config.maxColumns && ! ( node && find( node.id, holder.elements ) ) ) {
			return false;
		}
		return true;
	}

	/** Sections and containers record whether they sit inside something. */
	function setParent( node, holder ) {
		if ( node.elType === 'section' || node.elType === 'container' ) {
			node.isInner = !! holder;
		}
	}

	/**
	 * Put a node next to (or into) the target element: into the target when
	 * it can hold it, otherwise after the nearest element whose parent can.
	 * A widget with nowhere to go starts a new section at the end.
	 */
	function insertNode( node, targetId ) {
		var cur = targetId ? find( targetId ) : null;
		while ( cur ) {
			var holder = cur.node;
			if ( holder.elType === 'section' && node.elType !== 'column' && node.elType !== 'section' && holder.elements.length ) {
				holder = holder.elements[ 0 ]; // A section takes widgets into its first column.
			}
			if ( holder.elType !== 'widget' && canHold( holder, node.elType, node ) ) {
				setParent( node, holder );
				holder.elements.push( node );
				if ( node.elType === 'column' ) {
					evenWidths( holder );
				}
				return;
			}
			if ( canHold( cur.parent, node.elType, node ) ) {
				setParent( node, cur.parent );
				cur.list.splice( cur.index + 1, 0, node );
				if ( node.elType === 'column' ) {
					evenWidths( cur.parent );
				}
				return;
			}
			cur = cur.parent ? find( cur.parent.id ) : null;
		}
		if ( node.elType === 'section' || node.elType === 'container' ) {
			setParent( node, null );
			state.layout.elements.push( node );
			return;
		}
		var wrapper = makeSection( [ 100 ] );
		if ( node.elType === 'column' ) {
			node.settings._inline_size = 100;
			wrapper.elements = [ node ];
		} else {
			wrapper.elements[ 0 ].elements.push( node );
		}
		state.layout.elements.push( wrapper );
	}

	/** A new one-column section just before the given section, in the same place. */
	function addSectionAbove( id ) {
		var found = find( id );
		if ( ! found ) {
			return;
		}
		var section = found.node.isInner ? makeInnerSection() : makeSection( [ 100 ] );
		if ( found.node.isInner ) {
			section.elements = [ makeColumn( 100 ) ];
		}
		setParent( section, found.parent );
		found.list.splice( found.index, 0, section );
		select( section.id, 'section', true );
	}

	function makeWidget( type ) {
		return {
			id: uid(),
			elType: 'widget',
			widgetType: type,
			settings: defaults( config.widgets[ type ].controls ),
		};
	}

	/**
	 * Find an element anywhere in the tree.
	 * Returns { node, list, index, parent } or null.
	 */
	function find( id, list, parent ) {
		list = list || state.layout.elements;
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === id ) {
				return { node: list[ i ], list: list, index: i, parent: parent || null };
			}
			if ( list[ i ].elements ) {
				var found = find( id, list[ i ].elements, list[ i ] );
				if ( found ) {
					return found;
				}
			}
		}
		return null;
	}

	function withNewIds( node ) {
		node = clone( node );
		( function walk( n ) {
			n.id = uid();
			( n.elements || [] ).forEach( walk );
		} )( node );
		return node;
	}

	/** Share the desktop width evenly; tablet widths are reset to inherit. */
	function evenWidths( section ) {
		var count = section.elements.length;
		var base = Math.floor( 10000 / count ) / 100;
		section.elements.forEach( function ( column, i ) {
			column.settings._inline_size = i === count - 1 ? Math.round( ( 100 - base * ( count - 1 ) ) * 100 ) / 100 : base;
			column.settings._inline_size_tablet = '';
		} );
	}

	function removeElement( id ) {
		var found = find( id );
		if ( ! found ) {
			return;
		}
		found.list.splice( found.index, 1 );

		// A section with no columns left goes too; remaining columns share the width.
		if ( found.node.elType === 'column' && found.parent ) {
			if ( found.parent.elements.length === 0 ) {
				removeElement( found.parent.id );
			} else {
				evenWidths( found.parent );
			}
		}
		if ( state.selected && ! find( state.selected.id ) ) {
			state.selected = null;
		}
	}

	function duplicateElement( id ) {
		var found = find( id );
		if ( ! found ) {
			return;
		}
		if ( found.node.elType === 'column' && found.parent.elements.length >= config.maxColumns ) {
			return;
		}
		var copy = withNewIds( found.node );
		found.list.splice( found.index + 1, 0, copy );
		if ( copy.elType === 'column' ) {
			evenWidths( found.parent );
		}
		select( copy.id, copy.elType, true );
	}

	function addColumn( sectionId ) {
		var found = find( sectionId );
		if ( ! found || found.node.elements.length >= config.maxColumns ) {
			return;
		}
		found.node.elements.push( makeColumn( 0 ) );
		evenWidths( found.node );
	}

	/* ------------------------------------------------------------------ *
	 * Copy and paste
	 *
	 * A copied element goes on the system clipboard as JSON (so Ctrl+V works
	 * on another page or another site) and into localStorage (so the Paste
	 * menu item works without asking for clipboard permission).
	 * ------------------------------------------------------------------ */

	var CLIP_TYPE = 'kbroo-studio/element';
	var memoryClipboard = null; // When localStorage is unavailable.
	var pasteStyleNext = false;

	function clipboardText( node ) {
		return JSON.stringify( { type: CLIP_TYPE, version: 1, element: node } );
	}

	/** A copied element rebuilt from untrusted JSON, or null. */
	function cleanElement( node, allowed, inInner, depth ) {
		allowed = allowed || [ 'section', 'column', 'container', 'widget' ];
		depth = depth || 0;
		if ( ! node || typeof node !== 'object' || allowed.indexOf( node.elType ) === -1 || depth > 12 ) {
			return null;
		}
		var settings = node.settings && typeof node.settings === 'object' && ! Array.isArray( node.settings ) ? node.settings : {};
		var out = { id: uid(), elType: node.elType, settings: clone( settings ) };
		if ( node.elType === 'widget' ) {
			if ( ! config.widgets[ node.widgetType ] ) {
				return null;
			}
			out.widgetType = node.widgetType;
			return out;
		}
		if ( node.elType === 'section' || node.elType === 'container' ) {
			out.isInner = !! node.isInner;
		}
		var childInner = inInner || ( node.elType === 'section' && out.isInner );
		var childAllowed = allowedChildren( node.elType, childInner );
		out.elements = ( Array.isArray( node.elements ) ? node.elements : [] )
			.map( function ( c ) {
				return cleanElement( c, childAllowed, childInner, depth + 1 );
			} )
			.filter( Boolean );
		if ( node.elType === 'section' ) {
			out.elements = out.elements.slice( 0, config.maxColumns );
			if ( ! out.elements.length ) {
				return null;
			}
		}
		return out;
	}

	function parseClipboard( text ) {
		try {
			var data = JSON.parse( text );
			return data && data.type === CLIP_TYPE ? cleanElement( data.element ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function storedClipboard() {
		var text = null;
		try {
			text = window.localStorage.getItem( 'kbroo-studio:clipboard' );
		} catch ( e ) {}
		return parseClipboard( text || memoryClipboard || '' );
	}

	function remember( text ) {
		memoryClipboard = text;
		try {
			window.localStorage.setItem( 'kbroo-studio:clipboard', text );
		} catch ( e ) {}
	}

	/** Copy from a button or menu: also try the system clipboard. */
	function copyElement( id ) {
		var found = find( id );
		if ( ! found ) {
			return;
		}
		var text = clipboardText( found.node );
		remember( text );
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).catch( function () {} );
		}
		toast( elementLabel( found.node ) + ' copied. Paste it here or on another page.' );
	}

	/**
	 * Insert a copied element next to (or into) the target element (see
	 * insertNode()). With no target it goes at the end of the page.
	 */
	function pasteElement( element, targetId ) {
		var copy = withNewIds( element );
		insertNode( copy, targetId );
		select( copy.id, copy.elType, true );
		changed();
	}

	/**
	 * Copy the Style and Advanced settings (not content, CSS ID or classes)
	 * of a copied element onto an element of the same kind.
	 */
	function pasteStyle( source, targetId ) {
		var target = find( targetId );
		if ( ! source || ! target ) {
			return;
		}
		if ( source.elType !== target.node.elType || source.widgetType !== target.node.widgetType ) {
			toast( 'Paste style needs a copied ' + elementLabel( target.node ).toLowerCase() + '. You copied a ' + elementLabel( source ).toLowerCase() + '.', true );
			return;
		}
		var controls = controlsFor( target.node );
		var from = source.settings;
		var to = target.node.settings;
		var fromGlobals = from.__globals__ || {};
		var toGlobals = Object.assign( {}, to.__globals__ || {} );

		Object.keys( controls ).forEach( function ( key ) {
			var control = controls[ key ];
			if ( control.tab === 'content' || control.type === 'text' ) {
				return;
			}
			var keys = Css.keys( key, control );
			Object.keys( keys ).forEach( function ( device ) {
				var k = keys[ device ];
				if ( Object.prototype.hasOwnProperty.call( from, k ) ) {
					to[ k ] = clone( from[ k ] );
				}
				delete toGlobals[ k ];
				if ( fromGlobals[ k ] ) {
					toGlobals[ k ] = fromGlobals[ k ];
				}
			} );
			if ( control.global_group ) {
				delete toGlobals[ control.global_group ];
				if ( fromGlobals[ control.global_group ] ) {
					toGlobals[ control.global_group ] = fromGlobals[ control.global_group ];
				}
			}
		} );
		if ( Object.keys( toGlobals ).length ) {
			to.__globals__ = toGlobals;
		} else {
			delete to.__globals__;
		}
		changed();
		toast( 'Style pasted.' );
	}

	/** Right-click menu on the canvas. */
	function openContextMenu( e, node ) {
		closeContextMenu();
		var copied = storedClipboard();
		var items = [];
		if ( node ) {
			items.push(
				[ 'Duplicate', 'dashicons-admin-page', function () {
					duplicateElement( node.id );
					changed();
				} ],
				[ 'Copy', 'dashicons-clipboard', function () {
					copyElement( node.id );
				} ]
			);
			// The element itself if it is a section or container, otherwise
			// the nearest one holding it.
			var holder = ( pathTo( node.id ) || [] ).filter( function ( n ) {
				return n.elType === 'section' || n.elType === 'container';
			} ).pop();
			if ( config.canSaveTemplates && holder ) {
				items.push( [ holder === node ? 'Save as template' : 'Save ' + elementLabel( holder ).toLowerCase() + ' as template', 'dashicons-category', function () {
					startTemplateSave( holder );
				} ] );
			}
		}
		items.push( [ copied ? 'Paste ' + elementLabel( copied ).toLowerCase() : 'Paste', 'dashicons-editor-paste-text', copied ? function () {
			pasteElement( copied, node && node.id );
		} : null ] );
		if ( node ) {
			items.push(
				[ 'Paste style', 'dashicons-art', copied && copied.elType === node.elType && copied.widgetType === node.widgetType ? function () {
					pasteStyle( copied, node.id );
				} : null ],
				[ 'Delete', 'dashicons-trash', function () {
					removeElement( node.id );
					changed();
				}, true ]
			);
		}

		var menu = el(
			'div',
			{ class: 'kb-context-menu', role: 'menu' },
			items.map( function ( item ) {
				return el(
					'button',
					{
						type: 'button',
						role: 'menuitem',
						class: 'kb-context-item' + ( item[ 3 ] ? ' is-danger' : '' ),
						disabled: ! item[ 2 ],
						onclick: function () {
							closeContextMenu();
							item[ 2 ]();
						},
					},
					[ icon( item[ 1 ] ), el( 'span', { text: item[ 0 ] } ) ]
				);
			} )
		);
		document.body.appendChild( menu );
		var x = Math.min( e.clientX, window.innerWidth - menu.offsetWidth - 8 );
		var y = Math.min( e.clientY, window.innerHeight - menu.offsetHeight - 8 );
		menu.style.left = Math.max( 8, x ) + 'px';
		menu.style.top = Math.max( 8, y ) + 'px';
		var first = menu.querySelector( 'button:not([disabled])' );
		if ( first ) {
			first.focus();
		}
		ui.contextMenu = menu;
	}

	function closeContextMenu() {
		if ( ui.contextMenu ) {
			ui.contextMenu.remove();
			ui.contextMenu = null;
		}
	}

	function bindClipboard() {
		function typing( e ) {
			return e.target.closest && e.target.closest( 'input, textarea, select, [contenteditable]' );
		}

		document.addEventListener( 'copy', function ( e ) {
			if ( typing( e ) || ! state.selected || state.preview || String( window.getSelection() ) ) {
				return;
			}
			var found = find( state.selected.id );
			if ( ! found ) {
				return;
			}
			var text = clipboardText( found.node );
			e.clipboardData.setData( 'text/plain', text );
			e.preventDefault();
			remember( text );
			toast( elementLabel( found.node ) + ' copied. Paste it here or on another page.' );
		} );

		document.addEventListener( 'paste', function ( e ) {
			var asStyle = pasteStyleNext;
			pasteStyleNext = false;
			if ( typing( e ) || state.preview ) {
				return;
			}
			var element = parseClipboard( e.clipboardData.getData( 'text/plain' ) );
			if ( ! element ) {
				return;
			}
			e.preventDefault();
			if ( asStyle ) {
				if ( state.selected ) {
					pasteStyle( element, state.selected.id );
				}
				return;
			}
			pasteElement( element, state.selected && state.selected.id );
		} );

		ui.canvas.addEventListener( 'contextmenu', function ( e ) {
			if ( state.preview ) {
				return;
			}
			var target = e.target.closest( '.kb-el' );
			var found = target ? find( target.getAttribute( 'data-id' ) ) : null;
			e.preventDefault();
			if ( found ) {
				select( found.node.id, found.node.elType );
			}
			openContextMenu( e, found && found.node );
		} );

		document.addEventListener( 'mousedown', function ( e ) {
			if ( ui.contextMenu && ! ui.contextMenu.contains( e.target ) ) {
				closeContextMenu();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ui.contextMenu ) {
				e.stopImmediatePropagation();
				closeContextMenu();
			}
			// Ctrl+Shift+V: the paste event that follows pastes style only.
			if ( ( e.ctrlKey || e.metaKey ) && e.shiftKey && e.key.toLowerCase() === 'v' ) {
				pasteStyleNext = true;
			}
		}, true );
		window.addEventListener( 'blur', closeContextMenu );
		ui.canvasWrap.addEventListener( 'scroll', closeContextMenu );
	}

	/* ------------------------------------------------------------------ *
	 * Change tracking and undo / redo
	 * ------------------------------------------------------------------ */

	function snapshot() {
		return JSON.stringify( { layout: state.layout, selected: state.selected } );
	}

	function resetHistory() {
		history.stack = [ snapshot() ];
		history.index = 0;
		history.savedIndex = 0;
		history.mergeKey = null;
	}

	function record( mergeKey ) {
		var now = Date.now();
		if ( mergeKey && mergeKey === history.mergeKey && now - history.mergeTime < MERGE_WINDOW && history.index > history.savedIndex ) {
			history.stack[ history.index ] = snapshot();
		} else {
			history.stack = history.stack.slice( 0, history.index + 1 );
			history.stack.push( snapshot() );
			if ( history.stack.length > HISTORY_LIMIT ) {
				history.stack.shift();
				history.savedIndex--;
			}
			history.index = history.stack.length - 1;
		}
		history.mergeKey = mergeKey || null;
		history.mergeTime = now;
	}

	function restore( index ) {
		var entry = JSON.parse( history.stack[ index ] );
		history.index = index;
		history.mergeKey = null;
		state.layout = entry.layout;
		state.selected = entry.selected && find( entry.selected.id ) ? entry.selected : null;
		state.dirty = history.index !== history.savedIndex;
		state.revision++;
		renderAll();
	}

	function undo() {
		state.preview = null;
		if ( history.index > 0 ) {
			restore( history.index - 1 );
		}
	}

	function redo() {
		if ( history.index < history.stack.length - 1 ) {
			restore( history.index + 1 );
		}
	}

	/**
	 * Call after every change to the layout.
	 *
	 * @param {Object} options panel: false keeps the panel (and input focus);
	 *                         mergeKey: consecutive changes with the same key
	 *                         merge into one undo step.
	 */
	function changed( options ) {
		options = options || {};
		state.dirty = true;
		state.revision++;
		record( options.mergeKey );
		renderCanvas();
		if ( options.panel !== false ) {
			renderPanel();
		}
		renderTopbar();
	}

	/* ------------------------------------------------------------------ *
	 * Selection
	 * ------------------------------------------------------------------ */

	function select( id, kind, silent ) {
		if ( state.preview ) {
			return; // The canvas is read-only while previewing a revision.
		}
		var previousKind = state.selected && state.selected.kind;
		if ( ! id ) {
			state.panelView = 'widgets';
		}
		state.selected = id ? { id: id, kind: kind } : null;
		if ( kind !== previousKind ) {
			state.tab = 'content';
		}
		if ( ! silent ) {
			renderCanvas();
			renderPanel();
			renderTopbar();
		}
	}

	function setDevice( device ) {
		state.device = device;
		renderAll();
	}

	/* ------------------------------------------------------------------ *
	 * App shell
	 * ------------------------------------------------------------------ */

	function mount() {
		var app = document.getElementById( 'kbroo-app' );
		app.innerHTML = '';

		ui.topbar = el( 'header', { class: 'kb-topbar' } );
		ui.panel = el( 'aside', { class: 'kb-panel', 'aria-label': 'Kbroo Studio panel' } );
		ui.styles = el( 'style', { id: 'kb-canvas-css' } );
		ui.canvas = el( 'div', { class: 'kbroo kbroo-page kb-canvas' } );
		// Forms in the canvas are previews: never send them.
		ui.canvas.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );
		// A loop item is designed at about the width of one grid cell.
		ui.canvasWrap = el( 'main', { class: 'kb-canvas-wrap' + ( config.templateType === 'loop-item' ? ' is-loop-item' : '' ) }, [ ui.canvas ] );
		ui.toast = el( 'div', { class: 'kb-toast', role: 'status', 'aria-live': 'polite' } );

		document.head.appendChild( ui.styles );
		app.appendChild( ui.topbar );
		ui.navigator = el( 'aside', { class: 'kb-navigator', 'aria-label': 'Navigator' } );
		app.appendChild( el( 'div', { class: 'kb-main' }, [ ui.panel, ui.canvasWrap, ui.navigator ] ) );
		app.appendChild( ui.toast );

		bindCanvasEvents();
		bindKeyboard();
		bindClipboard();

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( state.dirty || state.globalsDirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );

		resetHistory();
		if ( config.openPanel === 'globals' ) {
			state.panelView = 'globals'; // Opened from the Kbroo Studio admin menu.
		}
		renderAll();

		if ( config.conversion ) {
			// Converted from Elementor on opening: nothing is saved until Save.
			state.dirty = true;
			renderTopbar();
			toast(
				'Converted from Elementor. ' +
					( config.conversion.fallbacks
						? config.conversion.fallbacks + ' part(s) could not be converted yet and show as they were. '
						: 'Everything converted. ' ) +
					'Save to switch this page to Kbroo Studio.',
				false,
				9000
			);
		}
	}

	function renderAll() {
		renderTopbar();
		renderPanel();
		renderCanvas();
	}

	function toast( message, isError, duration ) {
		ui.toast.textContent = message;
		ui.toast.className = 'kb-toast is-visible' + ( isError ? ' is-error' : '' );
		clearTimeout( toast.timer );
		toast.timer = setTimeout( function () {
			ui.toast.className = 'kb-toast';
		}, duration || ( isError ? 6000 : 2500 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Top bar
	 * ------------------------------------------------------------------ */

	function renderTopbar() {
		var published = isPublished( state.status ) || config.isTemplate;
		var target = state.page ? state.page.status : state.status; // Status after saving.
		var saveLabel = state.saving ? 'Saving…' : config.isTemplate ? 'Save' : published ? 'Update' : target === 'draft' ? 'Save draft' : 'Save';

		var devices = el( 'div', { class: 'kb-segmented', role: 'group', 'aria-label': 'Preview device' } );
		Object.keys( DEVICES ).forEach( function ( device ) {
			devices.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'kb-icon-btn' + ( state.device === device ? ' is-active' : '' ),
						title: DEVICES[ device ].label,
						'aria-label': DEVICES[ device ].label,
						'aria-pressed': state.device === device ? 'true' : 'false',
						'data-device': device,
						onclick: function () {
							setDevice( device );
						},
					},
					[ icon( DEVICES[ device ].icon ) ]
				)
			);
		} );

		ui.topbar.innerHTML = '';
		ui.topbar.appendChild(
			el( 'div', { class: 'kb-topbar-start' }, [
				el( 'a', { class: 'kb-icon-btn', href: config.wpEditUrl, title: config.isTemplate ? 'Back to the templates' : 'Back to the WordPress editor' }, [
					icon( 'dashicons-arrow-left-alt2' ),
					el( 'span', { class: 'kb-hide-sm', text: config.isTemplate ? 'Templates' : 'WordPress editor' } ),
				] ),
				el( 'span', { class: 'kb-brand', text: 'Kbroo Studio' } ),
				el( 'div', { class: 'kb-segmented' }, [
					el(
						'button',
						{
							type: 'button',
							class: 'kb-icon-btn',
							title: 'Undo (Ctrl+Z)',
							'aria-label': 'Undo',
							'data-action': 'undo',
							disabled: history.index <= 0,
							onclick: undo,
						},
						[ icon( 'dashicons-undo' ) ]
					),
					el(
						'button',
						{
							type: 'button',
							class: 'kb-icon-btn',
							title: 'Redo (Ctrl+Shift+Z)',
							'aria-label': 'Redo',
							'data-action': 'redo',
							disabled: history.index >= history.stack.length - 1,
							onclick: redo,
						},
						[ icon( 'dashicons-redo' ) ]
					),
				] ),
			] )
		);
		ui.topbar.appendChild(
			el( 'div', { class: 'kb-topbar-title' }, [
				devices,
				docTitle(),
				config.isTemplate
					? el( 'span', { class: 'kb-badge is-template', text: ( config.templateTypes[ config.templateType ] || 'Page' ) + ' template' } )
					: el( 'span', {
							class: 'kb-badge' + ( published ? ' is-live' : '' ),
							text: STATUS_LABELS[ state.status ] || 'Draft',
					  } ),
				state.dirty || state.globalsDirty ? el( 'span', { class: 'kb-unsaved', text: 'Unsaved changes' } ) : null,
			] )
		);
		ui.topbar.appendChild(
			el( 'div', { class: 'kb-topbar-end' }, [
				panelButton( 'globals', 'dashicons-admin-site-alt3', 'Site colours, fonts and CSS' ),
				state.display ? panelButton( 'display', 'dashicons-visibility', 'Where it shows' ) : null,
				state.page ? panelButton( 'page', 'dashicons-admin-generic', ( config.postTypeLabel || 'Page' ) + ' settings' ) : null,
				panelButton( 'templates', 'dashicons-category', 'Templates' ),
				panelButton( 'revisions', 'dashicons-backup', 'Revisions' ),
				el(
					'button',
					{
						type: 'button',
						class: 'kb-icon-btn' + ( state.navigator ? ' is-active' : '' ),
						title: 'Navigator',
						'aria-label': 'Navigator',
						'aria-pressed': state.navigator ? 'true' : 'false',
						'data-action': 'navigator',
						onclick: function () {
							state.navigator = ! state.navigator;
							writePref( 'navigator', state.navigator ? '1' : '0' );
							renderTopbar();
							renderNavigator();
						},
					},
					[ icon( 'dashicons-list-view' ) ]
				),
				config.isTemplate ? null : el( 'a', {
					class: 'kb-btn kb-btn-secondary',
					href: published ? state.permalink : config.previewUrl,
					target: '_blank',
					rel: 'noopener',
					text: published ? 'View page' : 'Preview',
				} ),
				el( 'button', {
					type: 'button',
					class: 'kb-btn ' + ( published || ! config.canPublish ? 'kb-btn-primary' : 'kb-btn-secondary' ),
					disabled: state.saving,
					text: saveLabel,
					'data-action': 'save',
					onclick: function () {
						save();
					},
				} ),
				! published && config.canPublish && ! config.isTemplate
					? el( 'button', {
							type: 'button',
							class: 'kb-btn kb-btn-primary',
							disabled: state.saving,
							text: 'Publish',
							'data-action': 'publish',
							onclick: function () {
								save( 'publish' );
							},
					  } )
					: null,
			] )
		);
	}

	function panelButton( view, iconName, title ) {
		var active = ! state.selected && state.panelView === view;
		return el(
			'button',
			{
				type: 'button',
				class: 'kb-icon-btn' + ( active ? ' is-active' : '' ),
				title: title,
				'aria-label': title,
				'aria-pressed': active ? 'true' : 'false',
				'data-action': view,
				onclick: function () {
					showPanel( active ? 'widgets' : view );
				},
			},
			[ icon( iconName ) ]
		);
	}

	function showPanel( view ) {
		if ( state.preview && view !== 'revisions' ) {
			state.preview = null;
		}
		state.panelView = view;
		state.selected = null;
		renderAll();
	}

	/* ------------------------------------------------------------------ *
	 * Side panel
	 * ------------------------------------------------------------------ */

	function renderPanel() {
		var scroll = ui.panel.querySelector( '.kb-panel-body' );
		var scrollTop = scroll ? scroll.scrollTop : 0;

		ui.panel.innerHTML = '';
		var sel = ! state.preview && state.selected && find( state.selected.id );
		if ( sel ) {
			renderSettingsPanel( sel.node );
		} else {
			state.selected = null;
			if ( state.preview || state.panelView === 'revisions' ) {
				renderRevisionsPanel();
			} else if ( state.panelView === 'globals' ) {
				renderGlobalsPanel();
			} else if ( state.panelView === 'templates' ) {
				renderTemplatesPanel();
			} else if ( state.panelView === 'display' && state.display ) {
				renderDisplayPanel();
			} else if ( state.panelView === 'page' && state.page ) {
				renderPagePanel();
			} else {
				renderWidgetsPanel();
			}
		}

		var body = ui.panel.querySelector( '.kb-panel-body' );
		if ( body ) {
			body.scrollTop = scrollTop;
		}
	}

	var LAYOUT_TILES = [
		{ elType: 'container', title: 'Container', icon: 'dashicons-grid-view' },
		{ elType: 'section', title: 'Inner section', icon: 'dashicons-columns' },
	];

	function renderWidgetsPanel() {
		var layoutGrid = el( 'div', { class: 'kb-widget-grid' } );
		LAYOUT_TILES.forEach( function ( tile ) {
			layoutGrid.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'kb-widget-tile',
						draggable: 'true',
						'data-element': tile.elType,
						title: 'Drag onto the page, or click to add',
						ondragstart: function ( e ) {
							startDrag( e, { kind: 'new', elType: tile.elType } );
						},
						ondragend: endDrag,
						onclick: function () {
							var node = tile.elType === 'container' ? makeContainer() : makeInnerSection();
							insertNode( node, state.selected && state.selected.id );
							select( node.id, node.elType, true );
							changed();
						},
					},
					[ icon( tile.icon ), el( 'span', { text: tile.title } ) ]
				)
			);
		} );
		var grid = el( 'div', { class: 'kb-widget-grid' } );
		Object.keys( config.widgets ).forEach( function ( type ) {
			var widget = config.widgets[ type ];
			if ( widget.panel === false ) {
				return;
			}
			grid.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'kb-widget-tile',
						draggable: 'true',
						'data-widget': type,
						title: 'Drag onto the page, or click to add',
						ondragstart: function ( e ) {
							startDrag( e, { kind: 'new', widgetType: type } );
						},
						ondragend: endDrag,
						onclick: function () {
							addWidgetByClick( type );
						},
					},
					[ icon( widget.icon ), el( 'span', { text: widget.title } ) ]
				)
			);
		} );

		ui.panel.appendChild( el( 'div', { class: 'kb-panel-header' }, [ el( 'h2', { text: 'Widgets' } ) ] ) );
		ui.panel.appendChild(
			el( 'div', { class: 'kb-panel-body' }, [
				el( 'h3', { class: 'kb-control-section', text: 'Layout' } ),
				layoutGrid,
				el( 'h3', { class: 'kb-control-section', text: 'Widgets' } ),
				grid,
				el( 'p', {
					class: 'kb-hint',
					text: 'Drag a widget onto the page. Drop it into a column, or between sections to start a new section.',
				} ),
			] )
		);
	}

	function elementLabel( node ) {
		if ( node.settings && node.settings._title ) {
			return node.settings._title;
		}
		if ( node.elType === 'widget' ) {
			return config.widgets[ node.widgetType ] ? config.widgets[ node.widgetType ].title : node.widgetType;
		}
		if ( node.elType === 'section' ) {
			return node.isInner ? 'Inner section' : 'Section';
		}
		return node.elType === 'container' ? 'Container' : 'Column';
	}

	function controlsFor( node ) {
		if ( node.elType === 'section' ) {
			return config.sectionControls;
		}
		if ( node.elType === 'column' ) {
			return config.columnControls;
		}
		if ( node.elType === 'container' ) {
			return config.containerControls;
		}
		return config.widgets[ node.widgetType ].controls;
	}

	function renderSettingsPanel( node ) {
		var controls = controlsFor( node );
		node.settings = withDefaults( controls, node.settings );

		var tabsPresent = Object.keys( TABS ).filter( function ( tab ) {
			return Object.keys( controls ).some( function ( key ) {
				return controls[ key ].tab === tab && ! controls[ key ].hidden;
			} );
		} );
		if ( tabsPresent.indexOf( state.tab ) === -1 ) {
			state.tab = tabsPresent[ 0 ];
		}

		var tabs = el( 'div', { class: 'kb-tabs', role: 'tablist' } );
		tabsPresent.forEach( function ( tab ) {
			tabs.appendChild(
				el( 'button', {
					type: 'button',
					role: 'tab',
					class: 'kb-tab' + ( state.tab === tab ? ' is-active' : '' ),
					'aria-selected': state.tab === tab ? 'true' : 'false',
					'data-tab': tab,
					text: TABS[ tab ],
					onclick: function () {
						state.tab = tab;
						renderPanel();
					},
				} )
			);
		} );

		var body = el( 'div', { class: 'kb-panel-body' } );
		var notice = node.elType === 'widget' && config.widgets[ node.widgetType ].notice;
		if ( notice && state.tab === tabsPresent[ 0 ] ) {
			body.appendChild( el( 'p', { class: 'kb-panel-notice', text: notice } ) );
		}
		var currentSection = null;
		Object.keys( controls ).forEach( function ( key ) {
			var control = controls[ key ];
			if ( control.hidden || control.tab !== state.tab || ! Css.conditionMet( control, node.settings ) ) {
				return;
			}
			if ( control.section && control.section !== currentSection ) {
				currentSection = control.section;
				body.appendChild( el( 'h3', { class: 'kb-control-section', text: control.section } ) );
				if ( control.global_group ) {
					body.appendChild( globalFontControl( node, control.global_group ) );
				}
			}
			body.appendChild( renderControl( node, key, control ) );
		} );

		body.appendChild(
			el( 'div', { class: 'kb-panel-actions' }, [
				el( 'button', {
					type: 'button',
					class: 'kb-btn kb-btn-secondary',
					text: 'Duplicate',
					onclick: function () {
						duplicateElement( node.id );
						changed();
					},
				} ),
				el( 'button', {
					type: 'button',
					class: 'kb-btn kb-btn-secondary',
					text: 'Copy',
					onclick: function () {
						copyElement( node.id );
					},
				} ),
				el( 'button', {
					type: 'button',
					class: 'kb-btn kb-btn-secondary',
					text: 'Paste style',
					title: 'Use the style of the copied element',
					onclick: function () {
						var copied = storedClipboard();
						if ( ! copied ) {
							toast( 'Copy an element first, then paste its style here.', true );
							return;
						}
						pasteStyle( copied, node.id );
					},
				} ),
				el( 'button', {
					type: 'button',
					class: 'kb-btn kb-btn-danger',
					text: 'Delete',
					onclick: function () {
						removeElement( node.id );
						changed();
					},
				} ),
			] )
		);

		ui.panel.appendChild(
			el( 'div', { class: 'kb-panel-header' }, [
				el(
					'button',
					{
						type: 'button',
						class: 'kb-icon-btn',
						title: 'Back to widgets',
						'data-action': 'back',
						onclick: function () {
							select( null );
						},
					},
					[ icon( 'dashicons-arrow-left-alt2' ) ]
				),
				el( 'h2', { text: 'Edit ' + elementLabel( node ) } ),
			] )
		);
		ui.panel.appendChild( tabs );
		ui.panel.appendChild( body );
	}

	/** "Global font" picker at the top of a typography section. */
	function globalFontControl( node, group ) {
		var id = 'kb-control-' + group;
		var current = Css.globalId( node.settings, group );
		var select = el( 'select', { id: id } );
		select.appendChild( el( 'option', { value: '', text: 'None (set below)' } ) );
		state.globals.typography.forEach( function ( preset ) {
			var option = el( 'option', {
				value: preset._id,
				text: ( preset.title || preset._id ) + ( preset.typography_font_family ? ' · ' + preset.typography_font_family : '' ),
			} );
			option.selected = preset._id === current;
			select.appendChild( option );
		} );
		select.addEventListener( 'change', function () {
			setGlobal( node, group, select.value ? 'globals/typography?id=' + select.value : null );
		} );
		return el( 'div', { class: 'kb-control kb-control-select', 'data-control': group }, [
			el( 'label', { for: id }, [ el( 'span', { text: 'Global font' } ) ] ),
			select,
			current ? el( 'p', { class: 'kb-hint kb-hint-small', text: 'Settings below override the global font.' } ) : null,
		] );
	}

	/**
	 * The value a responsive control falls back to on the current device:
	 * the next larger device's value, or the desktop value.
	 */
	function inheritedValue( settings, key, control ) {
		if ( state.device === 'mobile' && isSet( control, settings[ key + '_tablet' ] ) ) {
			return settings[ key + '_tablet' ];
		}
		return settings[ key ];
	}

	/**
	 * Load the Google Fonts the canvas needs: the layout's own families and
	 * every global font (they are few, and the site settings panel shows them).
	 */
	function loadFonts( layout ) {
		var names = [];
		( function walk( list ) {
			list.forEach( function ( node ) {
				Object.keys( node.settings || {} ).forEach( function ( key ) {
					if ( /_font_family$/.test( key ) && node.settings[ key ] ) {
						names.push( node.settings[ key ] );
					}
				} );
				walk( node.elements || [] );
			} );
		} )( layout.elements );
		state.globals.typography.forEach( function ( preset ) {
			if ( preset.typography_font_family ) {
				names.push( preset.typography_font_family );
			}
		} );
		var url = Css.googleUrl( names, config.fonts );
		if ( ! ui.fontLink ) {
			ui.fontLink = el( 'link', { rel: 'stylesheet' } );
			document.head.appendChild( ui.fontLink );
		}
		if ( url && ui.fontLink.getAttribute( 'href' ) !== url ) {
			ui.fontLink.setAttribute( 'href', url );
		}
	}

	function globalLabel( kind, id ) {
		var item = ( state.globals[ kind ] || [] ).filter( function ( g ) {
			return g._id === id;
		} )[ 0 ];
		return item ? item.title || id : id + ' (missing)';
	}

	function setGlobal( node, key, ref ) {
		node.settings.__globals__ = Object.assign( {}, node.settings.__globals__ || {} );
		if ( ref ) {
			node.settings.__globals__[ key ] = ref;
		} else {
			delete node.settings.__globals__[ key ];
		}
		if ( ! Object.keys( node.settings.__globals__ ).length ) {
			delete node.settings.__globals__;
		}
		changed();
	}

	/** A small menu of global colors; picking one links the setting to it. */
	function globalColorMenu( node, settingKey ) {
		var current = Css.globalId( node.settings, settingKey );
		var menu = el( 'div', { class: 'kb-global-menu', role: 'menu' } );
		state.globals.colors.forEach( function ( color ) {
			menu.appendChild(
				el(
					'button',
					{
						type: 'button',
						role: 'menuitem',
						class: 'kb-global-option' + ( current === color._id ? ' is-active' : '' ),
						'data-global': color._id,
						onclick: function () {
							setGlobal( node, settingKey, 'globals/colors?id=' + color._id );
						},
					},
					[ el( 'span', { class: 'kb-swatch', style: 'background:' + ( color.color || 'transparent' ) } ), el( 'span', { text: color.title || color._id } ) ]
				)
			);
		} );
		return menu;
	}

	function renderControl( node, key, control ) {
		var device = control.responsive ? state.device : 'desktop';
		var settingKey = Css.deviceKey( key, device );
		var id = 'kb-control-' + ( node.idPrefix || '' ) + settingKey;
		var value = node.settings[ settingKey ];
		var inherited = device !== 'desktop' ? inheritedValue( node.settings, key, control ) : null;
		var mergeKey = node.id + ':' + settingKey;
		var input;

		function update( newValue, merge ) {
			node.settings[ settingKey ] = newValue;
			// Conditions may show or hide other controls, so redraw the panel
			// for discrete choices; keep it (and focus) while typing.
			changed( { panel: ! merge, mergeKey: merge ? mergeKey : null } );
		}

		switch ( control.type ) {
			case 'textarea':
				input = el( 'textarea', { id: id, rows: control.rows || 6 } );
				input.value = value;
				input.addEventListener( 'input', function () {
					update( input.value, true );
				} );
				break;

			case 'select':
				input = el( 'select', { id: id } );
				if ( device !== 'desktop' ) {
					var inheritLabel = control.options[ inherited ] || ( control.options[ '' ] ) || 'Default';
					input.appendChild( el( 'option', { value: '', text: 'Inherit (' + inheritLabel + ')' } ) );
				}
				Object.keys( control.options ).forEach( function ( optionValue ) {
					if ( device !== 'desktop' && optionValue === '' ) {
						return;
					}
					var option = el( 'option', { value: optionValue, text: control.options[ optionValue ] } );
					option.selected = optionValue === value;
					input.appendChild( option );
				} );
				input.addEventListener( 'change', function () {
					update( input.value, false );
				} );
				break;

			case 'color':
				var linked = Css.globalId( node.settings, settingKey );
				var globe = el(
					'button',
					{
						type: 'button',
						class: 'kb-icon-btn kb-global-btn' + ( linked ? ' is-active' : '' ),
						title: 'Global colours',
						'aria-label': 'Global colours',
						'aria-expanded': 'false',
						onclick: function () {
							var open = wrap.querySelector( '.kb-global-menu' );
							if ( open ) {
								open.remove();
								globe.setAttribute( 'aria-expanded', 'false' );
							} else {
								wrap.appendChild( globalColorMenu( node, settingKey ) );
								globe.setAttribute( 'aria-expanded', 'true' );
							}
						},
					},
					[ icon( 'dashicons-admin-site-alt3' ) ]
				);
				var wrap;
				if ( linked ) {
					var linkedColor = state.globals.colors.filter( function ( c ) {
						return c._id === linked;
					} )[ 0 ];
					wrap = el( 'div', { class: 'kb-color' }, [
						el( 'span', { class: 'kb-global-chip', id: id }, [
							el( 'span', { class: 'kb-swatch', style: 'background:' + ( linkedColor ? linkedColor.color : 'transparent' ) } ),
							el( 'span', { text: globalLabel( 'colors', linked ) } ),
						] ),
						el( 'button', {
							type: 'button',
							class: 'kb-link',
							text: 'Unlink',
							onclick: function () {
								setGlobal( node, settingKey, null );
							},
						} ),
						globe,
					] );
				} else {
					var picker = el( 'input', { type: 'color', id: id } );
					picker.value = /^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test( value || '' ) ? value.slice( 0, 7 ) : '#000000';
					var clear = el( 'button', { type: 'button', class: 'kb-link', text: value ? 'Clear' : 'Default' } );
					picker.addEventListener( 'input', function () {
						clear.textContent = 'Clear';
						update( picker.value, true );
					} );
					clear.addEventListener( 'click', function () {
						update( '', false );
					} );
					wrap = el( 'div', { class: 'kb-color' }, [ picker, clear, globe ] );
				}
				input = wrap;
				break;

			case 'number':
				input = el( 'input', {
					type: 'number',
					id: id,
					min: control.min,
					max: control.max,
					step: 'any',
					placeholder: inherited != null && inherited !== '' ? String( inherited ) : '',
				} );
				input.value = value;
				input.addEventListener( 'input', function () {
					if ( input.value === '' ) {
						update( device === 'desktop' ? control.default : '', true );
						return;
					}
					var n = Number( input.value );
					if ( control.min != null ) {
						n = Math.max( control.min, n );
					}
					if ( control.max != null ) {
						n = Math.min( control.max, n );
					}
					update( n, true );
				} );
				break;

			case 'switcher':
				var box = el( 'input', { type: 'checkbox', id: id, class: 'kb-switch' } );
				box.checked = value === 'yes';
				box.addEventListener( 'change', function () {
					update( box.checked ? 'yes' : '', false );
				} );
				input = box;
				break;

			case 'code':
				input = el( 'div', { class: 'kb-code-wrap' }, [
					el( 'textarea', { id: id, rows: 10, class: 'kb-code', spellcheck: 'false' } ),
					config.canUnfilteredHtml
						? null
						: el( 'p', {
								class: 'kb-control-note',
								text: 'Scripts and some other tags are removed when you save, because your account cannot add unfiltered HTML.',
						  } ),
				] );
				var code = input.querySelector( 'textarea' );
				code.value = value;
				code.addEventListener( 'input', function () {
					update( code.value, true );
				} );
				break;

			case 'css':
				input = el( 'textarea', { id: id, rows: 8, class: 'kb-code', spellcheck: 'false', placeholder: 'selector {\n\tcolor: #333;\n}' } );
				input.value = value || '';
				input.addEventListener( 'input', function () {
					update( input.value, true );
				} );
				break;

			case 'media':
				input = renderMedia( control, id, value || {}, update );
				break;

			case 'gallery':
				input = renderGallery( id, Array.isArray( value ) ? value : [], update );
				break;

			case 'url':
				input = renderUrl( control, id, value || {}, update );
				break;

			case 'icons':
				input = renderIcons( id, value || {}, update );
				break;

			case 'repeater':
				input = renderRepeater( node, key, control, id );
				break;

			case 'multiselect':
				var picked = Array.isArray( value ) ? value.slice() : [];
				var keys = Object.keys( control.options || {} );
				input = el( 'div', { class: 'kb-multiselect', id: id, role: 'group' } );
				if ( ! keys.length ) {
					input.appendChild( el( 'p', { class: 'kb-control-note', text: 'Nothing to choose from yet.' } ) );
				}
				keys.forEach( function ( optionValue ) {
					var box = el( 'input', { type: 'checkbox', value: optionValue } );
					box.checked = picked.indexOf( optionValue ) > -1;
					box.addEventListener( 'change', function () {
						var next = keys.filter( function ( k ) {
							return k === optionValue ? box.checked : picked.indexOf( k ) > -1;
						} );
						update( next, false );
					} );
					input.appendChild( el( 'label', { class: 'kb-check' }, [ box, el( 'span', { text: control.options[ optionValue ] } ) ] ) );
				} );
				break;

			case 'datetime':
			case 'time':
				input = el( 'input', { type: control.type === 'time' ? 'time' : 'datetime-local', id: id } );
				input.value = value || '';
				input.addEventListener( 'change', function () {
					update( input.value, false );
				} );
				break;

			case 'slider':
				input = renderSlider( control, id, value, inherited, update );
				break;

			case 'dimensions':
				input = renderDimensions( control, id, value, inherited, update );
				break;

			default:
				input = el( 'input', { type: 'text', id: id } );
				input.value = value;
				input.addEventListener( 'input', function () {
					update( input.value, true );
				} );
		}

		var label = el( 'label', { for: id }, [
			el( 'span', { text: control.label } ),
			control.responsive
				? el(
						'span',
						{ class: 'kb-device-badge', title: 'Editing the ' + DEVICES[ state.device ].label.toLowerCase() + ' value. Switch device in the top bar.' },
						[ icon( DEVICES[ state.device ].icon ) ]
				  )
				: null,
		] );

		return el( 'div', { class: 'kb-control kb-control-' + control.type, 'data-control': key }, [
			label,
			input,
			control.description ? el( 'p', { class: 'kb-control-note', text: control.description } ) : null,
		] );
	}

	/** Image from the Media Library, or any image address. */
	function renderMedia( control, id, value, update ) {
		var video = control.media_type === 'video';
		var noun = video ? 'video' : 'image';
		var preview = el( 'button', {
			type: 'button',
			class: 'kb-media-preview' + ( value.url ? '' : ' is-empty' ),
			title: 'Choose ' + noun,
			onclick: openLibrary,
		} );
		if ( value.url && video ) {
			preview.appendChild( el( 'span', { class: 'kb-media-file', text: value.url.split( '/' ).pop() } ) );
		} else if ( value.url ) {
			preview.appendChild( el( 'img', { src: value.url, alt: '' } ) );
		} else {
			preview.appendChild( el( 'span', { text: 'Choose ' + noun } ) );
		}

		var address = el( 'input', { type: 'url', id: id, placeholder: 'or paste ' + ( video ? 'a video' : 'an image' ) + ' address' } );
		address.value = value.id ? '' : value.url || '';
		address.addEventListener( 'change', function () {
			update( { url: address.value.trim(), id: '' }, false );
		} );

		function openLibrary() {
			if ( ! window.wp || ! wp.media ) {
				toast( 'The Media Library is not available here. Paste an image address instead.', true );
				return;
			}
			var frame = wp.media( { title: 'Choose ' + noun, library: { type: noun }, multiple: false, button: { text: 'Use this ' + noun } } );
			frame.on( 'open', function () {
				if ( value.id ) {
					var attachment = wp.media.attachment( value.id );
					attachment.fetch();
					frame.state().get( 'selection' ).reset( [ attachment ] );
				}
			} );
			frame.on( 'select', function () {
				var picked = frame.state().get( 'selection' ).first().toJSON();
				window.KbrooStudio.captions[ picked.id ] = picked.caption || '';
				update( { url: picked.url, id: picked.id }, false );
			} );
			frame.open();
		}

		return el( 'div', { class: 'kb-media' }, [
			preview,
			el( 'div', { class: 'kb-media-actions' }, [
				el( 'button', { type: 'button', class: 'kb-btn kb-btn-secondary', text: value.url ? 'Replace' : 'Choose ' + noun, onclick: openLibrary } ),
				value.url
					? el( 'button', {
							type: 'button',
							class: 'kb-link',
							text: 'Remove',
							onclick: function () {
								update( { url: '', id: '' }, false );
							},
					  } )
					: null,
			] ),
			address,
		] );
	}

	/** Gallery: several Media Library images, in order. */
	function renderGallery( id, value, update ) {
		function openLibrary() {
			if ( ! window.wp || ! wp.media ) {
				toast( 'The Media Library is not available here.', true );
				return;
			}
			var frame = wp.media( {
				title: 'Choose images',
				library: { type: 'image' },
				multiple: 'add',
				button: { text: 'Use these images' },
			} );
			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );
				value.forEach( function ( image ) {
					if ( image.id ) {
						var attachment = wp.media.attachment( image.id );
						attachment.fetch();
						selection.add( attachment );
					}
				} );
			} );
			frame.on( 'select', function () {
				var picked = frame.state().get( 'selection' ).toJSON().map( function ( image ) {
					window.KbrooStudio.captions[ image.id ] = image.caption || '';
					return { id: image.id, url: image.url };
				} );
				update( picked, false );
			} );
			frame.open();
		}

		var thumbs = el( 'div', { class: 'kb-gallery-thumbs' } );
		value.forEach( function ( image, i ) {
			thumbs.appendChild(
				el( 'div', { class: 'kb-gallery-thumb' }, [
					el( 'img', { src: image.url, alt: '' } ),
					el( 'button', {
						type: 'button',
						class: 'kb-gallery-remove',
						title: 'Remove',
						'aria-label': 'Remove image ' + ( i + 1 ),
						text: '×',
						onclick: function () {
							update( value.filter( function ( other, j ) {
								return j !== i;
							} ), false );
						},
					} ),
				] )
			);
		} );

		return el( 'div', { class: 'kb-gallery', id: id }, [
			value.length ? thumbs : el( 'p', { class: 'kb-control-note', text: 'No images yet.' } ),
			el( 'div', { class: 'kb-media-actions' }, [
				el( 'button', { type: 'button', class: 'kb-btn kb-btn-secondary', text: value.length ? 'Add or change images' : 'Choose images', onclick: openLibrary } ),
				value.length
					? el( 'button', {
							type: 'button',
							class: 'kb-link',
							text: 'Clear',
							onclick: function () {
								update( [], false );
							},
					  } )
					: null,
				value.length ? el( 'span', { class: 'kb-control-note', text: value.length + ( value.length === 1 ? ' image' : ' images' ) } ) : null,
			] ),
		] );
	}

	/** Link: address, new tab and nofollow (Elementor's link shape). */
	/* Icons: Font Awesome Free, loaded from the server the first time an
	 * icon control is shown (see Icons::for_editor()). */
	var iconData = null;
	var iconRequest = null;
	var ICON_PREFIX = { 'fa-solid': 'fas', 'fa-regular': 'far', 'fa-brands': 'fab' };
	var ICON_LIBRARY_LABELS = { 'fa-solid': 'Solid', 'fa-regular': 'Regular', 'fa-brands': 'Brands' };

	function loadIcons() {
		if ( ! iconRequest ) {
			iconRequest = fetch( config.iconsUrl, { credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce } } )
				.then( parseResponse )
				.then( function ( data ) {
					iconData = data;
					return data;
				} );
		}
		return iconRequest;
	}

	/** { library, name } for a stored value, if the icon is known. */
	function findIcon( value ) {
		var match = value && typeof value.value === 'string' && value.value.match( /^(fas|far|fab)\s+fa-([a-z0-9-]+)$/ );
		if ( ! match || ! iconData ) {
			return null;
		}
		var library = value.library && iconData.libraries[ value.library ] ? value.library : { fas: 'fa-solid', far: 'fa-regular', fab: 'fa-brands' }[ match[ 1 ] ];
		return iconData.libraries[ library ] && iconData.libraries[ library ][ match[ 2 ] ] ? { library: library, name: match[ 2 ] } : null;
	}

	function iconSvg( library, name ) {
		var data = iconData.libraries[ library ][ name ];
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 ' + data[ 0 ] + ' ' + data[ 1 ] );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'fill', 'currentColor' );
		var path = document.createElementNS( ns, 'path' );
		path.setAttribute( 'd', data[ 2 ] );
		svg.appendChild( path );
		return svg;
	}

	function renderIcons( id, value, update ) {
		var preview = el( 'button', { type: 'button', id: id, class: 'kb-icon-preview', title: 'Choose icon', onclick: openPicker } );
		var name = el( 'span', { class: 'kb-icon-name' } );
		function paint() {
			preview.textContent = '';
			var found = findIcon( value );
			if ( found ) {
				preview.appendChild( iconSvg( found.library, found.name ) );
				name.textContent = found.name.replace( /-/g, ' ' );
			} else if ( value.library === 'svg' && value.value && value.value.url ) {
				preview.appendChild( el( 'img', { src: value.value.url, alt: '' } ) );
				name.textContent = 'Uploaded SVG';
			} else {
				preview.appendChild( icon( 'dashicons-plus-alt2' ) );
				name.textContent = value.value ? 'Loading…' : 'No icon';
			}
		}
		function openPicker() {
			loadIcons().then( function () {
				iconPicker( function ( library, iconName ) {
					update( { value: ICON_PREFIX[ library ] + ' fa-' + iconName, library: library }, false );
				} );
			} );
		}
		if ( iconData ) {
			paint();
		} else {
			paint();
			loadIcons().then( paint ).catch( function () {
				name.textContent = 'Icons could not be loaded';
			} );
		}
		return el( 'div', { class: 'kb-icons' }, [
			preview,
			el( 'div', { class: 'kb-icons-side' }, [
				name,
				el( 'button', { type: 'button', class: 'kb-btn kb-btn-secondary', text: value.value ? 'Change' : 'Choose icon', onclick: openPicker } ),
				value.value
					? el( 'button', {
							type: 'button',
							class: 'kb-link',
							text: 'Remove',
							onclick: function () {
								update( { value: '', library: '' }, false );
							},
					  } )
					: null,
			] ),
		] );
	}

	/** Modal with every icon: search, filter by library, click to choose. */
	function iconPicker( onPick ) {
		var LIMIT = 240;
		var library = '';
		var search = el( 'input', { type: 'search', class: 'kb-picker-search', placeholder: 'Search icons', 'aria-label': 'Search icons' } );
		var grid = el( 'div', { class: 'kb-picker-grid', role: 'listbox' } );
		var note = el( 'p', { class: 'kb-control-note' } );
		var tabs = el( 'div', { class: 'kb-picker-tabs' } );
		var overlay;

		function close() {
			overlay.remove();
			document.removeEventListener( 'keydown', onKey, true );
		}
		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				e.stopPropagation();
				close();
			}
		}
		function draw() {
			grid.textContent = '';
			var words = search.value.toLowerCase().trim().split( /\s+/ ).filter( Boolean );
			var shown = 0;
			var total = 0;
			Object.keys( iconData.libraries ).forEach( function ( lib ) {
				if ( library && lib !== library ) {
					return;
				}
				Object.keys( iconData.libraries[ lib ] ).forEach( function ( name ) {
					var haystack = name + ' ' + ( iconData.search[ name ] || '' );
					if ( ! words.every( function ( w ) { return haystack.indexOf( w ) > -1; } ) ) {
						return;
					}
					total++;
					if ( shown >= LIMIT ) {
						return;
					}
					shown++;
					grid.appendChild(
						el(
							'button',
							{
								type: 'button',
								class: 'kb-picker-icon',
								title: name.replace( /-/g, ' ' ) + ' (' + ICON_LIBRARY_LABELS[ lib ] + ')',
								'data-icon': ICON_PREFIX[ lib ] + ' fa-' + name,
								onclick: function () {
									close();
									onPick( lib, name );
								},
							},
							[ iconSvg( lib, name ), el( 'span', { text: name.replace( /-/g, ' ' ) } ) ]
						)
					);
				} );
			} );
			note.textContent = total > shown ? 'Showing ' + shown + ' of ' + total + ' icons. Search to find more.' : total ? total + ' icons' : 'No icons match.';
		}
		[ [ '', 'All' ] ].concat( Object.keys( ICON_LIBRARY_LABELS ).map( function ( k ) { return [ k, ICON_LIBRARY_LABELS[ k ] ]; } ) ).forEach( function ( pair ) {
			tabs.appendChild(
				el( 'button', {
					type: 'button',
					class: 'kb-picker-tab' + ( pair[ 0 ] === library ? ' is-active' : '' ),
					text: pair[ 1 ],
					onclick: function ( e ) {
						library = pair[ 0 ];
						tabs.querySelectorAll( '.kb-picker-tab' ).forEach( function ( b ) {
							b.classList.toggle( 'is-active', b === e.currentTarget );
						} );
						draw();
					},
				} )
			);
		} );
		search.addEventListener( 'input', draw );
		overlay = el(
			'div',
			{
				class: 'kb-modal-overlay',
				onclick: function ( e ) {
					if ( e.target === overlay ) {
						close();
					}
				},
			},
			[
				el( 'div', { class: 'kb-modal kb-icon-picker', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Icon library' }, [
					el( 'div', { class: 'kb-modal-head' }, [
						el( 'h2', { text: 'Icon library' } ),
						el( 'button', { type: 'button', class: 'kb-icon-btn', 'aria-label': 'Close', onclick: close }, [ icon( 'dashicons-no-alt' ) ] ),
					] ),
					el( 'div', { class: 'kb-picker-bar' }, [ search, tabs ] ),
					grid,
					note,
				] ),
			]
		);
		document.body.appendChild( overlay );
		document.addEventListener( 'keydown', onKey, true );
		draw();
		search.focus();
	}

	/* Repeaters: a list of items, each with its own settings (slides,
	 * form fields, social icons). Items are edited in place. */
	var openItems = {};

	function newItemId() {
		return Math.random().toString( 16 ).slice( 2, 9 );
	}

	function itemTitle( control, item, index ) {
		var value = control.title_field ? item[ control.title_field ] : '';
		var titleField = control.fields && control.fields[ control.title_field ];
		if ( titleField && titleField.options && typeof value === 'string' && titleField.options[ value ] ) {
			value = titleField.options[ value ]; // A select: its label.
		}
		if ( value && typeof value === 'object' ) {
			value = typeof value.value === 'string' ? value.value.replace( /^fa[a-z]? fa-/, '' ).replace( /-/g, ' ' ) : '';
		}
		value = String( value || '' ).replace( /<[^>]*>/g, '' ).trim();
		return value ? value.slice( 0, 40 ) : ( control.item_label || 'Item' ) + ' ' + ( index + 1 );
	}

	function renderRepeater( node, key, control, id ) {
		var items = Array.isArray( node.settings[ key ] ) ? node.settings[ key ] : ( node.settings[ key ] = [] );
		var fields = control.fields || {};
		var openKey = node.id + ':' + key;
		var list = el( 'div', { class: 'kb-repeater', id: id } );

		function move( from, to ) {
			if ( to < 0 || to >= items.length ) {
				return;
			}
			items.splice( to, 0, items.splice( from, 1 )[ 0 ] );
			changed();
		}

		items.forEach( function ( item, index ) {
			// Fill in defaults so conditions and placeholders work.
			var withItemDefaults = withDefaults( fields, item );
			Object.keys( withItemDefaults ).forEach( function ( k ) {
				if ( ! ( k in item ) ) {
					item[ k ] = withItemDefaults[ k ];
				}
			} );
			if ( ! item._id ) {
				item._id = newItemId();
			}
			var isOpen = openItems[ openKey ] === item._id;
			var body = null;
			if ( isOpen ) {
				var pseudo = { id: node.id + ':' + key + ':' + item._id, settings: item, idPrefix: key + '-' + item._id + '-' };
				body = el( 'div', { class: 'kb-repeater-body' } );
				Object.keys( fields ).forEach( function ( fieldKey ) {
					var field = fields[ fieldKey ];
					if ( ! field.hidden && Css.conditionMet( field, item ) ) {
						body.appendChild( renderControl( pseudo, fieldKey, field ) );
					}
				} );
			}
			list.appendChild(
				el( 'div', { class: 'kb-repeater-item' + ( isOpen ? ' is-open' : '' ), 'data-item': item._id }, [
					el( 'div', { class: 'kb-repeater-head' }, [
						el( 'button', {
							type: 'button',
							class: 'kb-repeater-title',
							'aria-expanded': isOpen ? 'true' : 'false',
							text: itemTitle( control, item, index ),
							onclick: function () {
								openItems[ openKey ] = isOpen ? null : item._id;
								renderPanel();
							},
						} ),
						toolButton( 'dashicons-arrow-up-alt2', 'Move up', function () {
							move( index, index - 1 );
						} ),
						toolButton( 'dashicons-arrow-down-alt2', 'Move down', function () {
							move( index, index + 1 );
						} ),
						toolButton( 'dashicons-admin-page', 'Duplicate', function () {
							var copy = clone( item );
							copy._id = newItemId();
							items.splice( index + 1, 0, copy );
							openItems[ openKey ] = copy._id;
							changed();
						} ),
						toolButton( 'dashicons-trash', 'Remove', function () {
							items.splice( index, 1 );
							changed();
						} ),
					] ),
					body,
				] )
			);
		} );

		list.appendChild(
			el( 'button', {
				type: 'button',
				class: 'kb-btn kb-btn-secondary kb-repeater-add',
				text: 'Add ' + ( control.item_label || 'item' ).toLowerCase(),
				onclick: function () {
					var item = withDefaults( fields, {} );
					item._id = newItemId();
					items.push( item );
					openItems[ openKey ] = item._id;
					changed();
				},
			} )
		);
		return list;
	}

	function renderUrl( control, id, value, update ) {
		function current( overrides ) {
			return Object.assign( { url: '', is_external: '', nofollow: '' }, value, overrides );
		}
		var address = el( 'input', { type: 'text', id: id, placeholder: 'https://, /page or #anchor', inputmode: 'url' } );
		address.value = value.url || '';
		address.addEventListener( 'input', function () {
			value = current( { url: address.value.trim() } );
			update( value, true );
		} );

		function option( key, text ) {
			var box = el( 'input', { type: 'checkbox' } );
			box.checked = value[ key ] === 'on';
			box.addEventListener( 'change', function () {
				var patch = {};
				patch[ key ] = box.checked ? 'on' : '';
				value = current( patch );
				update( value, false );
			} );
			return el( 'label', { class: 'kb-check' }, [ box, el( 'span', { text: text } ) ] );
		}

		return el( 'div', { class: 'kb-url' }, [ address, option( 'is_external', 'Open in a new tab' ), option( 'nofollow', 'Add nofollow' ) ] );
	}

	function unitSelect( control, current, onChange ) {
		var units = control.units || [ 'px' ];
		if ( units.length < 2 ) {
			return el( 'span', { class: 'kb-unit', text: units[ 0 ] } );
		}
		var select = el( 'select', { class: 'kb-unit', 'aria-label': 'Unit' } );
		units.forEach( function ( u ) {
			var option = el( 'option', { value: u, text: u } );
			option.selected = u === current;
			select.appendChild( option );
		} );
		select.addEventListener( 'change', function () {
			onChange( select.value );
		} );
		return select;
	}

	function numberOrEmpty( raw ) {
		return raw === '' || ! isFinite( raw ) ? '' : Number( raw );
	}

	function renderSlider( control, id, value, inherited, update ) {
		value = Object.assign( { unit: ( control.units || [ 'px' ] )[ 0 ], size: '' }, value || {} );
		var number = el( 'input', {
			type: 'number',
			id: id,
			step: 'any',
			placeholder: inherited && inherited.size !== '' && inherited.size != null ? String( inherited.size ) : '',
		} );
		number.value = value.size;
		number.addEventListener( 'input', function () {
			update( { unit: value.unit, size: numberOrEmpty( number.value ) }, true );
		} );
		return el( 'div', { class: 'kb-input-unit' }, [
			number,
			unitSelect( control, value.unit, function ( u ) {
				update( { unit: u, size: numberOrEmpty( number.value ) }, false );
			} ),
		] );
	}

	function renderDimensions( control, id, value, inherited, update ) {
		var SIDES = [ 'top', 'right', 'bottom', 'left' ];
		value = Object.assign( { unit: ( control.units || [ 'px' ] )[ 0 ], top: '', right: '', bottom: '', left: '', isLinked: true }, value || {} );
		var inputs = {};

		function current( overrides ) {
			var out = { unit: value.unit, isLinked: value.isLinked };
			SIDES.forEach( function ( side ) {
				out[ side ] = numberOrEmpty( inputs[ side ].value );
			} );
			return Object.assign( out, overrides || {} );
		}

		var row = el( 'div', { class: 'kb-dimensions' } );
		SIDES.forEach( function ( side, i ) {
			var input = el( 'input', {
				type: 'number',
				step: 'any',
				id: i === 0 ? id : id + '-' + side,
				'aria-label': side,
				'data-side': side,
				placeholder: inherited && inherited[ side ] !== '' && inherited[ side ] != null ? String( inherited[ side ] ) : '',
			} );
			input.value = value[ side ];
			input.addEventListener( 'input', function () {
				if ( value.isLinked ) {
					SIDES.forEach( function ( other ) {
						if ( other !== side ) {
							inputs[ other ].value = input.value;
						}
					} );
				}
				update( current(), true );
			} );
			inputs[ side ] = input;
			row.appendChild( el( 'div', { class: 'kb-dimension' }, [ input, el( 'span', { text: side } ) ] ) );
		} );

		var link = el(
			'button',
			{
				type: 'button',
				class: 'kb-icon-btn kb-link-sides' + ( value.isLinked ? ' is-active' : '' ),
				title: value.isLinked ? 'Unlink values' : 'Link values',
				'aria-pressed': value.isLinked ? 'true' : 'false',
				onclick: function () {
					update( current( { isLinked: ! value.isLinked } ), false );
				},
			},
			[ icon( value.isLinked ? 'dashicons-admin-links' : 'dashicons-editor-unlink' ) ]
		);
		row.appendChild( link );

		return el( 'div', { class: 'kb-input-unit kb-input-dimensions' }, [
			row,
			unitSelect( control, value.unit, function ( u ) {
				update( current( { unit: u } ), false );
			} ),
		] );
	}


	/* ------------------------------------------------------------------ *
	 * Site colours and fonts
	 * ------------------------------------------------------------------ */

	function globalsChanged( redrawPanel ) {
		state.globalsDirty = true;
		renderCanvas();
		renderTopbar();
		if ( redrawPanel ) {
			renderPanel();
		}
	}

	function newGlobalId( kind ) {
		var id;
		do {
			id = uid();
		} while ( state.globals[ kind ].some( function ( g ) {
			return g._id === id;
		} ) );
		return id;
	}

	function panelHeader( title ) {
		return el( 'div', { class: 'kb-panel-header' }, [
			el(
				'button',
				{
					type: 'button',
					class: 'kb-icon-btn',
					title: 'Back to widgets',
					'data-action': 'back',
					onclick: function () {
						showPanel( 'widgets' );
					},
				},
				[ icon( 'dashicons-arrow-left-alt2' ) ]
			),
			el( 'h2', { text: title } ),
		] );
	}

	function renderGlobalsPanel() {
		var editable = config.canEditGlobals;
		var body = el( 'div', { class: 'kb-panel-body kb-globals' } );
		body.appendChild(
			el( 'p', {
				class: 'kb-hint',
				text: editable
					? 'These apply across the whole site. Link a colour or font to them from any element, and changing it here restyles every page that uses it.'
					: 'These apply across the whole site. Only administrators can change them.',
			} )
		);

		// Colours.
		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Colours' } ) );
		state.globals.colors.forEach( function ( color, index ) {
			var system = config.systemGlobalIds.indexOf( color._id ) > -1;
			var name = el( 'input', { type: 'text', class: 'kb-global-name', 'aria-label': 'Colour name', disabled: ! editable } );
			name.value = color.title;
			name.addEventListener( 'input', function () {
				color.title = name.value;
				globalsChanged( false );
			} );
			var picker = el( 'input', { type: 'color', 'aria-label': ( color.title || color._id ) + ' colour', disabled: ! editable, 'data-global-color': color._id } );
			picker.value = /^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test( color.color || '' ) ? color.color.slice( 0, 7 ) : '#000000';
			picker.addEventListener( 'input', function () {
				color.color = picker.value;
				globalsChanged( false );
			} );
			body.appendChild(
				el( 'div', { class: 'kb-global-row' }, [
					picker,
					name,
					editable && ! system
						? el(
								'button',
								{
									type: 'button',
									class: 'kb-icon-btn',
									title: 'Delete colour',
									'aria-label': 'Delete colour',
									onclick: function () {
										state.globals.colors.splice( index, 1 );
										globalsChanged( true );
									},
								},
								[ icon( 'dashicons-trash' ) ]
						  )
						: el( 'span', { class: 'kb-global-row-space' } ),
				] )
			);
		} );
		if ( editable ) {
			body.appendChild(
				el( 'button', {
					type: 'button',
					class: 'kb-btn kb-btn-secondary kb-add-global',
					text: 'Add colour',
					'data-action': 'add-global-color',
					onclick: function () {
						state.globals.colors.push( { _id: newGlobalId( 'colors' ), title: 'New colour', color: '#000000' } );
						globalsChanged( true );
					},
				} )
			);
		}

		// Fonts.
		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Fonts' } ) );
		var controls = config.globalTypographyControls;
		state.globals.typography.forEach( function ( preset, index ) {
			var system = config.systemGlobalIds.indexOf( preset._id ) > -1;
			var name = el( 'input', { type: 'text', class: 'kb-global-name', 'aria-label': 'Font name', disabled: ! editable } );
			name.value = preset.title;
			name.addEventListener( 'input', function () {
				preset.title = name.value;
				globalsChanged( false );
			} );
			var sample = el( 'div', { class: 'kb-font-sample', text: 'The quick brown fox' } );
			sample.style.fontFamily = 'var(--kbroo-global-typography-' + preset._id + '-font-family)';
			sample.style.fontWeight = 'var(--kbroo-global-typography-' + preset._id + '-font-weight)';

			var fields = el( 'div', { class: 'kb-global-font-fields' } );
			[ 'typography_font_family', 'typography_font_weight', 'typography_font_size', 'typography_line_height' ].forEach( function ( key ) {
				fields.appendChild( renderPresetControl( preset, key, controls[ key ], editable ) );
			} );

			body.appendChild(
				el( 'div', { class: 'kb-global-font', 'data-global-font': preset._id }, [
					el( 'div', { class: 'kb-global-row' }, [
						name,
						editable && ! system
							? el(
									'button',
									{
										type: 'button',
										class: 'kb-icon-btn',
										title: 'Delete font',
										'aria-label': 'Delete font',
										onclick: function () {
											state.globals.typography.splice( index, 1 );
											globalsChanged( true );
										},
									},
									[ icon( 'dashicons-trash' ) ]
							  )
							: null,
					] ),
					sample,
					fields,
				] )
			);
		} );
		if ( editable ) {
			body.appendChild(
				el( 'button', {
					type: 'button',
					class: 'kb-btn kb-btn-secondary kb-add-global',
					text: 'Add font',
					onclick: function () {
						var preset = defaults( controls );
						preset._id = newGlobalId( 'typography' );
						preset.title = 'New font';
						state.globals.typography.push( preset );
						globalsChanged( true );
					},
				} )
			);
		}

		// Custom CSS for the whole site.
		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Custom CSS' } ) );
		var siteCss = el( 'textarea', {
			id: 'kb-global-custom-css',
			rows: 10,
			class: 'kb-code',
			spellcheck: 'false',
			disabled: ! editable,
			'aria-describedby': 'kb-global-custom-css-note',
		} );
		siteCss.value = state.globals.custom_css || '';
		siteCss.addEventListener( 'input', function () {
			state.globals.custom_css = siteCss.value;
			globalsChanged( false );
		} );
		body.appendChild(
			el( 'div', { class: 'kb-control kb-control-css' }, [
				el( 'label', { for: 'kb-global-custom-css', text: 'CSS for every page built with Kbroo Studio' } ),
				siteCss,
				el( 'p', { class: 'kb-control-note', id: 'kb-global-custom-css-note', text: 'Added after everything else, so it can override any style.' } ),
			] )
		);

		ui.panel.appendChild( panelHeader( 'Site colours, fonts and CSS' ) );
		ui.panel.appendChild( body );
	}

	/** One field of a global font preset (device-aware like element settings). */
	function renderPresetControl( preset, key, control, editable ) {
		var device = control.responsive ? state.device : 'desktop';
		var settingKey = Css.deviceKey( key, device );
		var id = 'kb-global-' + preset._id + '-' + settingKey;
		var input;

		function update( value, merge ) {
			preset[ settingKey ] = value;
			globalsChanged( ! merge );
		}

		if ( control.type === 'select' ) {
			input = el( 'select', { id: id, disabled: ! editable } );
			Object.keys( control.options ).forEach( function ( v ) {
				var option = el( 'option', { value: v, text: control.options[ v ] } );
				option.selected = v === ( preset[ settingKey ] || '' );
				input.appendChild( option );
			} );
			input.addEventListener( 'change', function () {
				update( input.value, false );
			} );
		} else {
			var inherited = device !== 'desktop' ? inheritedValue( preset, key, control ) : null;
			input = renderSlider( control, id, preset[ settingKey ], inherited, update );
			if ( ! editable ) {
				Array.prototype.forEach.call( input.querySelectorAll( 'input, select' ), function ( i ) {
					i.disabled = true;
				} );
			}
		}
		return el( 'div', { class: 'kb-control kb-control-' + control.type }, [
			el( 'label', { for: id }, [
				el( 'span', { text: control.label } ),
				control.responsive ? el( 'span', { class: 'kb-device-badge' }, [ icon( DEVICES[ state.device ].icon ) ] ) : null,
			] ),
			input,
		] );
	}

	function saveGlobals() {
		return window
			.fetch( config.globalsUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify( { globals: state.globals } ),
			} )
			.then( parseResponse )
			.then( function ( data ) {
				state.globals = data;
				state.globalsDirty = false;
			} );
	}

	/* ------------------------------------------------------------------ *
	 * Revisions
	 * ------------------------------------------------------------------ */

	var revisions = { items: null, loading: false, error: '' };

	function loadRevisions() {
		revisions.loading = true;
		revisions.error = '';
		renderPanel();
		window
			.fetch( config.revisionsUrl, { credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce } } )
			.then( parseResponse )
			.then( function ( items ) {
				revisions.items = items;
			} )
			.catch( function ( error ) {
				revisions.error = error.message;
			} )
			.then( function () {
				revisions.loading = false;
				renderPanel();
			} );
	}

	function previewRevision( item, label ) {
		window
			.fetch( config.revisionsUrl + '/' + item.id, { credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce } } )
			.then( parseResponse )
			.then( function ( data ) {
				state.preview = { id: item.id, label: label, layout: normalizeLayout( data.layout ) };
				state.selected = null;
				renderAll();
			} )
			.catch( function ( error ) {
				toast( error.message, true );
			} );
	}

	function restorePreview() {
		var layout = state.preview.layout;
		state.preview = null;
		state.layout = clone( layout );
		state.panelView = 'widgets';
		changed();
		toast( 'Revision restored. Save to keep it, or undo to go back.' );
	}

	function renderRevisionsPanel() {
		var body = el( 'div', { class: 'kb-panel-body kb-revisions' } );

		if ( state.preview ) {
			body.appendChild(
				el( 'div', { class: 'kb-preview-banner' }, [
					el( 'p', {}, [ el( 'strong', { text: 'Previewing ' + state.preview.label } ) ] ),
					el( 'p', { class: 'kb-hint', text: 'The page is read-only while you preview. Restoring replaces the current layout; you can still undo it.' } ),
					el( 'div', { class: 'kb-panel-actions' }, [
						el( 'button', { type: 'button', class: 'kb-btn kb-btn-primary', text: 'Restore this version', 'data-action': 'restore-revision', onclick: restorePreview } ),
						el( 'button', {
							type: 'button',
							class: 'kb-btn kb-btn-secondary',
							text: 'Cancel',
							onclick: function () {
								state.preview = null;
								renderAll();
							},
						} ),
					] ),
				] )
			);
		}

		if ( revisions.items === null && ! revisions.loading && ! revisions.error ) {
			setTimeout( loadRevisions, 0 );
		}
		if ( revisions.loading && ! revisions.items ) {
			body.appendChild( el( 'p', { class: 'kb-hint', text: 'Loading revisions…' } ) );
		} else if ( revisions.error ) {
			body.appendChild( el( 'p', { class: 'kb-hint', text: revisions.error } ) );
		} else if ( revisions.items && ! revisions.items.length ) {
			body.appendChild( el( 'p', { class: 'kb-hint', text: 'No saved versions yet. Each save adds one here.' } ) );
		} else if ( revisions.items ) {
			var list = el( 'ul', { class: 'kb-revision-list' } );
			revisions.items.forEach( function ( item, index ) {
				var when = new Date( item.date );
				var label = when.toLocaleString( undefined, { dateStyle: 'medium', timeStyle: 'short' } );
				var active = state.preview && state.preview.id === item.id;
				list.appendChild(
					el( 'li', {}, [
						el(
							'button',
							{
								type: 'button',
								class: 'kb-revision' + ( active ? ' is-active' : '' ),
								'data-revision': item.id,
								onclick: function () {
									previewRevision( item, label );
								},
							},
							[
								el( 'span', { class: 'kb-revision-date', text: label } ),
								el( 'span', { class: 'kb-revision-meta', text: item.ago + ( item.author ? ' · ' + item.author : '' ) + ( index === 0 ? ' · last saved' : '' ) } ),
							]
						),
					] )
				);
			} );
			body.appendChild( list );
		}

		ui.panel.appendChild( panelHeader( 'Revisions' ) );
		ui.panel.appendChild( body );
	}

	/* ------------------------------------------------------------------ *
	 * Document title
	 * ------------------------------------------------------------------ */

	/** The page or template title; click to rename (saved with the page). */
	function docTitle() {
		var button = el( 'button', {
			type: 'button',
			class: 'kb-doc-title',
			title: 'Rename',
			'data-action': 'rename-document',
			text: state.title || '(no title)',
		} );
		button.addEventListener( 'click', function () {
			var input = el( 'input', { type: 'text', class: 'kb-doc-title-input', 'aria-label': 'Title', maxlength: 200 } );
			input.value = state.title;
			var done = false;
			function commit( keep ) {
				if ( done ) {
					return;
				}
				done = true;
				var value = input.value.trim();
				if ( keep && value && value !== state.title ) {
					state.title = value;
					state.dirty = true;
				}
				renderTopbar();
			}
			input.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) {
					commit( true );
				} else if ( e.key === 'Escape' ) {
					e.stopPropagation();
					commit( false );
				}
			} );
			input.addEventListener( 'blur', function () {
				commit( true );
			} );
			button.replaceWith( input );
			input.focus();
			input.select();
		} );
		return button;
	}

	/* ------------------------------------------------------------------ *
	 * Where a header or footer shows
	 * ------------------------------------------------------------------ */

	function displayChanged() {
		state.dirty = true;
		renderTopbar();
		renderPanel();
	}

	function displayLabel( target ) {
		var found = config.displayPicks.filter( function ( p ) {
			return p.value === target;
		} )[ 0 ];
		if ( found ) {
			if ( target.indexOf( 'singular:' ) === 0 ) {
				return found.label + ( found.group === 'Posts' ? ' (post)' : '' );
			}
			return found.group + ' ' + found.label;
		}
		if ( /^(singular|in_category|in_tag|author):/.test( target ) ) {
			return '#' + target.split( ':' )[ 1 ];
		}
		return config.displayTargets[ target ] || target;
	}

	/** Chosen items with remove buttons, and a select to add one. */
	function displayList( listKey, title, generalTargets, addLabel ) {
		var list = state.display[ listKey ];
		var wrap = el( 'div', { class: 'kb-control kb-display-list', 'data-display': listKey } );
		var selectId = 'kb-display-add-' + listKey;
		wrap.appendChild( el( 'label', { for: selectId, text: title } ) );
		var chips = el( 'ul', { class: 'kb-chips' } );
		list.forEach( function ( target, index ) {
			if ( generalTargets === null && config.displayTargets[ target ] ) {
				return; // Shown as checkboxes instead.
			}
			chips.appendChild(
				el( 'li', { class: 'kb-chip' }, [
					el( 'span', { text: displayLabel( target ) } ),
					el(
						'button',
						{
							type: 'button',
							class: 'kb-chip-remove',
							title: 'Remove',
							'aria-label': 'Remove ' + displayLabel( target ),
							onclick: function () {
								list.splice( index, 1 );
								displayChanged();
							},
						},
						[ icon( 'dashicons-no-alt' ) ]
					),
				] )
			);
		} );
		if ( chips.children.length ) {
			wrap.appendChild( chips );
		}
		var select = el( 'select', { id: selectId } );
		select.appendChild( el( 'option', { value: '', text: addLabel } ) );
		if ( generalTargets ) {
			var general = el( 'optgroup', { label: 'Kinds of page' } );
			generalTargets.forEach( function ( t ) {
				if ( list.indexOf( t ) === -1 ) {
					general.appendChild( el( 'option', { value: t, text: config.displayTargets[ t ] } ) );
				}
			} );
			if ( general.children.length ) {
				select.appendChild( general );
			}
		}
		var groups = {};
		config.displayPicks.forEach( function ( p ) {
			if ( list.indexOf( p.value ) > -1 ) {
				return;
			}
			if ( ! groups[ p.group ] ) {
				groups[ p.group ] = el( 'optgroup', { label: p.group } );
				select.appendChild( groups[ p.group ] );
			}
			groups[ p.group ].appendChild( el( 'option', { value: p.value, text: p.label } ) );
		} );
		select.addEventListener( 'change', function () {
			if ( select.value ) {
				list.push( select.value );
				displayChanged();
			}
		} );
		wrap.appendChild( select );
		return wrap;
	}

	function displaySwitch( id, label, checked, onChange ) {
		var box = el( 'input', { type: 'checkbox', id: id, class: 'kb-switch' } );
		box.checked = checked;
		box.addEventListener( 'change', function () {
			onChange( box.checked );
			displayChanged();
		} );
		return el( 'div', { class: 'kb-control kb-control-switcher' }, [ el( 'label', { for: id, text: label } ), box ] );
	}

	/** How the display panel talks about each kind of theme template. */
	var DISPLAY_WORDS = {
		header: { thing: 'header', off: 'the theme\'s own header shows everywhere', everywhere: 'entire_site' },
		footer: { thing: 'footer', off: 'the theme\'s own footer shows everywhere', everywhere: 'entire_site' },
		archive: { thing: 'archive template', off: 'the theme\'s own archive pages show', everywhere: 'archives' },
		'search-results': { thing: 'search results template', off: 'the theme\'s own search results page shows', everywhere: 'search' },
		'single-post': { thing: 'single post template', off: 'the theme\'s own post pages show', everywhere: 'singular_all' },
		'single-page': { thing: 'single page template', off: 'the theme\'s own pages show', everywhere: 'singular_all' },
		'error-404': { thing: '404 template', off: 'the theme\'s own page not found page shows', everywhere: 'not_found' },
	};

	function renderDisplayPanel() {
		var d = state.display;
		var words = DISPLAY_WORDS[ config.templateType ] || DISPLAY_WORDS.header;
		var area = words.thing;
		var pickPosts = config.displayPicks.length > 0;
		var isSingle = config.templateType === 'single-post' || config.templateType === 'single-page';
		var body = el( 'div', { class: 'kb-panel-body kb-display' } );

		var summary;
		if ( ! d.active ) {
			summary = 'Switched off: ' + words.off + '.';
		} else if ( ! d.include.length ) {
			summary = 'Not shown anywhere yet. Choose where below.';
		} else {
			summary = 'Replaces the theme\'s ' + ( area === 'header' || area === 'footer' ? area : 'template' ) + ' on: ' + d.include.map( displayLabel ).join( ', ' ) + ( d.exclude.length ? '; not on: ' + d.exclude.map( displayLabel ).join( ', ' ) : '' ) + '.';
		}
		body.appendChild( el( 'p', { class: 'kb-hint kb-display-summary', text: summary } ) );

		body.appendChild(
			displaySwitch( 'kb-display-active', 'Show on the site', d.active, function ( on ) {
				d.active = on;
			} )
		);
		if ( config.templateType === 'header' ) {
			body.appendChild(
				displaySwitch( 'kb-display-sticky', 'Stick to the top when scrolling', d.sticky, function ( on ) {
					d.sticky = on;
				} )
			);
		}

		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Show on' } ) );
		Object.keys( config.displayTargets ).forEach( function ( target ) {
			var id = 'kb-display-include-' + target.replace( /[^a-z0-9_-]/gi, '-' );
			var box = el( 'input', { type: 'checkbox', id: id, 'data-target': target } );
			box.checked = d.include.indexOf( target ) > -1;
			box.addEventListener( 'change', function () {
				var i = d.include.indexOf( target );
				if ( box.checked && i === -1 ) {
					d.include.push( target );
				} else if ( ! box.checked && i > -1 ) {
					d.include.splice( i, 1 );
				}
				displayChanged();
			} );
			body.appendChild( el( 'div', { class: 'kb-check' }, [ box, el( 'label', { for: id, text: config.displayTargets[ target ] } ) ] ) );
		} );
		if ( pickPosts ) {
			body.appendChild( displayList( 'include', isSingle ? 'And these posts and pages' : 'And on these pages and posts', null, isSingle ? 'Add a post, page, category, tag or author…' : 'Add a page or post…' ) );
		}

		var exceptions = Object.keys( config.displayTargets ).filter( function ( t ) {
			return t !== words.everywhere;
		} );
		if ( exceptions.length || pickPosts ) {
			body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Except on' } ) );
			body.appendChild( displayList( 'exclude', 'Exceptions', exceptions, 'Add an exception…' ) );
		}
		var note;
		if ( config.templateType === 'archive' ) {
			note = 'If more than one archive template fits a page, the most specific wins: "Category archives" beats "All archives", and a Search results template beats both on search results. Changes apply when you save.';
		} else if ( isSingle ) {
			note = 'If more than one single template fits a post or page, the most specific wins: a chosen post beats a category, tag or author, which beats "All posts", which beats "All posts and pages". Canvas pages never use one. Changes apply when you save.';
		} else if ( config.templateType === 'search-results' || config.templateType === 'error-404' ) {
			note = 'If there is more than one, the most recently changed one shows. Changes apply when you save.';
		} else {
			note = 'If more than one ' + area + ' fits a page, the most specific wins: a chosen page beats a kind of page, which beats the entire site. Changes apply when you save.';
		}
		body.appendChild( el( 'p', { class: 'kb-control-note', text: note } ) );

		ui.panel.appendChild( panelHeader( area === 'header' || area === 'footer' ? 'Where this ' + area + ' shows' : 'Where this template shows' ) );
		ui.panel.appendChild( body );
	}

	/* ------------------------------------------------------------------ *
	 * Page settings
	 * ------------------------------------------------------------------ */

	var STATUS_LABELS = { publish: 'Published', draft: 'Draft', pending: 'Pending review', private: 'Private', future: 'Scheduled' };

	function isPublished( status ) {
		return status === 'publish' || status === 'private' || status === 'future';
	}

	var PAGE_LAYOUTS = {
		'': {
			label: 'Default',
			note: 'The theme\'s own page template.',
		},
		full_width: {
			label: 'Full width',
			note: 'The site header and footer, and the content edge to edge with no theme title.',
		},
		canvas: {
			label: 'Canvas',
			note: 'A blank page: no header, footer or title. Only what you build here.',
		},
	};

	/** A page setting changed. Text fields keep their focus (no panel redraw). */
	function pageChanged( redraw ) {
		state.dirty = true;
		renderTopbar();
		if ( redraw ) {
			renderPanel();
		}
	}

	function pageField( id, label, input, note ) {
		return el( 'div', { class: 'kb-control', 'data-page-setting': id }, [
			el( 'label', { for: 'kb-page-' + id, text: label } ),
			input,
			note ? el( 'p', { class: 'kb-control-note', text: note } ) : null,
		] );
	}

	function pageSwitch( id, label, checked, onChange, note ) {
		var box = el( 'input', { type: 'checkbox', id: 'kb-page-' + id, class: 'kb-switch' } );
		box.checked = !! checked;
		box.addEventListener( 'change', function () {
			onChange( box.checked );
			pageChanged( true );
		} );
		return el( 'div', { class: 'kb-control', 'data-page-setting': id }, [
			el( 'div', { class: 'kb-control-switcher' }, [ el( 'label', { for: 'kb-page-' + id, text: label } ), box ] ),
			note ? el( 'p', { class: 'kb-control-note', text: note } ) : null,
		] );
	}

	function renderPagePanel() {
		var p = state.page;
		var noun = ( config.postTypeLabel || 'page' ).toLowerCase();
		var body = el( 'div', { class: 'kb-panel-body kb-page-settings' } );

		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'General' } ) );

		var title = el( 'input', { type: 'text', id: 'kb-page-title', maxlength: 200 } );
		title.value = state.title;
		title.addEventListener( 'input', function () {
			if ( title.value.trim() ) {
				state.title = title.value.trim();
				pageChanged( false );
			}
		} );
		body.appendChild( pageField( 'title', 'Title', title ) );

		var status = el( 'select', { id: 'kb-page-status' } );
		var choices = p.canPublish ? [ 'draft', 'pending', 'private', 'publish' ] : [ 'draft', 'pending' ];
		if ( choices.indexOf( p.status ) === -1 ) {
			choices.push( p.status ); // Such as a scheduled post.
		}
		choices.forEach( function ( value ) {
			var option = el( 'option', { value: value, text: STATUS_LABELS[ value ] || value } );
			option.selected = value === p.status;
			status.appendChild( option );
		} );
		status.addEventListener( 'change', function () {
			p.status = status.value;
			pageChanged( true );
		} );
		body.appendChild(
			pageField(
				'status',
				'Status',
				status,
				p.status !== state.status ? 'Changes to ' + ( STATUS_LABELS[ p.status ] || p.status ).toLowerCase() + ' when you save.' : ''
			)
		);

		if ( p.supports.excerpt ) {
			var excerpt = el( 'textarea', { id: 'kb-page-excerpt', rows: 4 } );
			excerpt.value = p.excerpt || '';
			excerpt.addEventListener( 'input', function () {
				p.excerpt = excerpt.value;
				pageChanged( false );
			} );
			body.appendChild( pageField( 'excerpt', 'Excerpt', excerpt, 'A short summary, shown in post lists and search results.' ) );
		}

		if ( p.supports.featured ) {
			var media = renderMedia( { media_type: 'image' }, 'kb-page-featured', p.featured, function ( value ) {
				p.featured = { id: value.id || '', url: value.id ? value.url : '' };
				pageChanged( true );
			} );
			// Only Media Library images can be featured images.
			var address = media.querySelector( 'input[type="url"]' );
			if ( address ) {
				address.remove();
			}
			body.appendChild(
				el( 'div', { class: 'kb-control', 'data-page-setting': 'featured' }, [ el( 'label', { text: 'Featured image' } ), media ] )
			);
		}

		if ( p.supports.comments ) {
			body.appendChild(
				pageSwitch( 'comments', 'Allow comments', p.comments, function ( on ) {
					p.comments = on;
				}, p.comments ? 'Visitors can comment. The comments show under the ' + noun + '.' : '' )
			);
		}

		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Layout' } ) );

		var titleNote = '';
		if ( p.layout ) {
			titleNote = PAGE_LAYOUTS[ p.layout ].label + ' never shows the theme\'s title.';
		} else if ( ! p.hide_title ) {
			titleNote = 'The theme shows the ' + noun + ' title above your layout.';
		}
		body.appendChild(
			pageSwitch( 'hide_title', 'Hide title', p.hide_title, function ( on ) {
				p.hide_title = on;
			}, titleNote )
		);

		var layout = el( 'select', { id: 'kb-page-layout' } );
		Object.keys( PAGE_LAYOUTS ).forEach( function ( value ) {
			var option = el( 'option', { value: value, text: PAGE_LAYOUTS[ value ].label } );
			option.selected = value === ( p.layout || '' );
			layout.appendChild( option );
		} );
		layout.addEventListener( 'change', function () {
			p.layout = layout.value;
			pageChanged( true );
		} );
		body.appendChild( pageField( 'layout', 'Page layout', layout, PAGE_LAYOUTS[ p.layout || '' ].note ) );

		if ( config.wpEditUrl ) {
			body.appendChild(
				el( 'p', { class: 'kb-control-note kb-page-more' }, [
					el( 'a', { href: config.wpEditUrl, target: '_blank', rel: 'noopener', text: 'More settings in WordPress' } ),
					document.createTextNode( ' (categories, tags, author, URL…)' ),
				] )
			);
		}

		ui.panel.appendChild( panelHeader( ( config.postTypeLabel || 'Page' ) + ' settings' ) );
		ui.panel.appendChild( body );
	}

	/* ------------------------------------------------------------------ *
	 * Template library
	 * ------------------------------------------------------------------ */

	var templates = { items: null, loading: false, error: '', filter: '', search: '', inserting: 0, saving: false };

	function loadTemplates() {
		templates.loading = true;
		templates.error = '';
		renderPanel();
		window
			.fetch( config.templatesUrl, { credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce } } )
			.then( parseResponse )
			.then( function ( data ) {
				templates.items = Array.isArray( data ) ? data : [];
			} )
			.catch( function ( error ) {
				templates.error = error.message || 'The templates could not be loaded.';
			} )
			.then( function () {
				templates.loading = false;
				if ( state.panelView === 'templates' && ! state.selected ) {
					renderPanel();
				}
			} );
	}

	/** Open the library with a section or container ready to save. */
	function startTemplateSave( node ) {
		state.templateDraft = { elements: [ clone( node ) ], label: elementLabel( node ) };
		showPanel( 'templates' );
		var name = document.getElementById( 'kb-template-name' );
		if ( name ) {
			name.focus();
		}
	}

	function saveTemplate( title, type ) {
		var elements = state.templateDraft ? state.templateDraft.elements : state.layout.elements;
		if ( ! elements.length ) {
			toast( 'There is nothing on the page to save yet.', true );
			return;
		}
		templates.saving = true;
		renderPanel();
		window
			.fetch( config.templatesUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify( { title: title, type: type, layout: { elements: elements } } ),
			} )
			.then( parseResponse )
			.then( function ( data ) {
				state.templateDraft = null;
				templates.items = [ data ].concat( templates.items || [] );
				templates.search = ''; // Show the new one.
				templates.filter = '';
				toast( 'Saved "' + data.title + '" to your templates.' );
			} )
			.catch( function ( error ) {
				toast( error.message || 'The template could not be saved.', true );
			} )
			.then( function () {
				templates.saving = false;
				renderPanel();
			} );
	}

	/** Insert a template's sections at the end of the page. */
	function insertTemplate( item ) {
		templates.inserting = item.id;
		renderPanel();
		window
			.fetch( config.templatesUrl + '/' + item.id, { credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce } } )
			.then( parseResponse )
			.then( function ( data ) {
				var nodes = ( ( data.layout && data.layout.elements ) || [] )
					.map( function ( node ) {
						return cleanElement( node, [ 'section', 'container' ] );
					} )
					.filter( Boolean );
				if ( ! nodes.length ) {
					toast( 'That template is empty.', true );
					return;
				}
				var index = state.layout.elements.length;
				nodes.forEach( function ( node, i ) {
					setParent( node, null );
					state.layout.elements.splice( index + i, 0, node );
				} );
				state.selected = null;
				changed();
				toast( 'Inserted "' + data.title + '".' );
				var first = ui.canvas.querySelector( '[data-id="' + nodes[ 0 ].id + '"]' );
				if ( first && first.scrollIntoView ) {
					first.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} )
			.catch( function ( error ) {
				toast( error.message || 'The template could not be inserted.', true );
			} )
			.then( function () {
				templates.inserting = 0;
				renderPanel();
			} );
	}

	function renderTemplatesPanel() {
		var body = el( 'div', { class: 'kb-panel-body kb-templates' } );

		// Save.
		if ( config.canSaveTemplates ) {
			var draft = state.templateDraft;
			var name = el( 'input', { type: 'text', id: 'kb-template-name', maxlength: 200, placeholder: draft ? 'e.g. Hero with button' : 'e.g. Landing page' } );
			var type = el( 'select', { id: 'kb-template-type' } );
			Object.keys( config.templateTypes ).forEach( function ( key ) {
				type.appendChild( el( 'option', { value: key, text: config.templateTypes[ key ] } ) );
			} );
			type.value = draft ? 'section' : 'page';
			var form = el(
				'form',
				{
					class: 'kb-template-save' + ( draft ? ' is-draft' : '' ),
					onsubmit: function ( e ) {
						e.preventDefault();
						if ( ! name.value.trim() ) {
							name.focus();
							return;
						}
						saveTemplate( name.value.trim(), type.value );
					},
				},
				[
					el( 'h3', { class: 'kb-control-section', text: draft ? 'Save this ' + draft.label.toLowerCase() + ' as a template' : 'Save this page as a template' } ),
					el( 'div', { class: 'kb-control' }, [ el( 'label', { for: 'kb-template-name', text: 'Template name' } ), name ] ),
					el( 'div', { class: 'kb-control' }, [ el( 'label', { for: 'kb-template-type', text: 'Type' } ), type ] ),
					el( 'div', { class: 'kb-panel-actions' }, [
						el( 'button', { type: 'submit', class: 'kb-btn kb-btn-primary', disabled: templates.saving, 'data-action': 'save-template', text: templates.saving ? 'Saving…' : 'Save template' } ),
						draft
							? el( 'button', {
									type: 'button',
									class: 'kb-btn kb-btn-secondary',
									text: 'Cancel',
									onclick: function () {
										state.templateDraft = null;
										renderPanel();
									},
							  } )
							: null,
					] ),
					draft ? null : el( 'p', { class: 'kb-control-note', text: 'To save one section, right-click it and choose Save as template.' } ),
				]
			);
			body.appendChild( form );
		}

		// Library.
		body.appendChild( el( 'h3', { class: 'kb-control-section', text: 'Insert a template' } ) );
		if ( templates.items === null && ! templates.loading && ! templates.error ) {
			setTimeout( loadTemplates, 0 );
		}
		if ( templates.loading && ! templates.items ) {
			body.appendChild( el( 'p', { class: 'kb-hint', text: 'Loading templates…' } ) );
		} else if ( templates.error ) {
			body.appendChild( el( 'p', { class: 'kb-hint', text: templates.error } ) );
		} else if ( templates.items && ! templates.items.length ) {
			body.appendChild( el( 'p', { class: 'kb-hint', text: 'No templates yet. Save a section or a whole page here and it can be inserted into any page.' } ) );
		} else if ( templates.items ) {
			var search = el( 'input', { type: 'search', class: 'kb-template-search', placeholder: 'Search templates', 'aria-label': 'Search templates' } );
			search.value = templates.search;
			var filters = el( 'div', { class: 'kb-segmented kb-template-filter', role: 'group', 'aria-label': 'Template type' } );
			[ [ '', 'All' ], [ 'section', 'Sections' ], [ 'page', 'Pages' ], [ 'theme', 'Theme' ] ].forEach( function ( pair ) {
				filters.appendChild(
					el( 'button', {
						type: 'button',
						class: 'kb-btn kb-btn-small' + ( templates.filter === pair[ 0 ] ? ' is-active' : '' ),
						'aria-pressed': templates.filter === pair[ 0 ] ? 'true' : 'false',
						'data-filter': pair[ 0 ] || 'all',
						text: pair[ 1 ],
						onclick: function () {
							templates.filter = pair[ 0 ];
							renderPanel();
						},
					} )
				);
			} );
			var list = el( 'ul', { class: 'kb-template-list' } );
			function draw() {
				list.innerHTML = '';
				var words = templates.search.toLowerCase().split( /\s+/ ).filter( Boolean );
				var shown = templates.items.filter( function ( item ) {
					var title = ( item.title || '' ).toLowerCase();
					var typeMatches = ! templates.filter || item.type === templates.filter || ( templates.filter === 'theme' && config.themeTypes.indexOf( item.type ) > -1 );
					return typeMatches && words.every( function ( w ) {
						return title.indexOf( w ) > -1;
					} );
				} );
				if ( ! shown.length ) {
					list.appendChild( el( 'li', { class: 'kb-hint', text: 'No templates match.' } ) );
				}
				shown.forEach( function ( item ) {
					var when = new Date( item.modified );
					list.appendChild(
						el( 'li', { class: 'kb-template', 'data-template': item.id }, [
							el( 'div', { class: 'kb-template-text' }, [
								el( 'span', { class: 'kb-template-title', text: item.title || '(no title)' } ),
								el( 'span', {
									class: 'kb-template-meta',
									text: ( config.templateTypes[ item.type ] || '' ) + ' · ' + when.toLocaleDateString( undefined, { dateStyle: 'medium' } ),
								} ),
							] ),
							item.editUrl && item.id !== config.postId
								? el( 'a', { class: 'kb-icon-btn', href: item.editUrl, target: '_blank', rel: 'noopener', title: 'Edit template (opens in a new tab)', 'aria-label': 'Edit ' + item.title }, [ icon( 'dashicons-edit' ) ] )
								: null,
							el( 'button', {
								type: 'button',
								class: 'kb-btn kb-btn-secondary kb-btn-small',
								disabled: !! templates.inserting || !! state.preview,
								'data-action': 'insert-template',
								text: templates.inserting === item.id ? 'Inserting…' : 'Insert',
								onclick: function () {
									insertTemplate( item );
								},
							} ),
						] )
					);
				} );
			}
			search.addEventListener( 'input', function () {
				templates.search = search.value;
				draw();
			} );
			draw();
			body.appendChild( search );
			body.appendChild( filters );
			body.appendChild( list );
			body.appendChild( el( 'p', { class: 'kb-control-note', text: 'Inserted at the end of the page; drag it from there. Changing a template later does not change pages it was inserted into.' } ) );
		}
		body.appendChild(
			el( 'p', { class: 'kb-control-note' }, [ el( 'a', { href: config.templatesAdminUrl, target: '_blank', rel: 'noopener', text: 'Manage templates' } ) ] )
		);

		ui.panel.appendChild( panelHeader( 'Templates' ) );
		ui.panel.appendChild( body );
	}

	/* ------------------------------------------------------------------ *
	 * Navigator (layer tree)
	 * ------------------------------------------------------------------ */

	var collapsed = {};
	var navDrag = null; // { id, elType }
	var navTarget = null; // { id, position: 'before' | 'after' | 'inside' }

	var NAV_ICONS = { section: 'dashicons-editor-table', column: 'dashicons-columns', container: 'dashicons-grid-view' };

	/** A short preview of a widget's text, for its Navigator row. */
	function widgetSnippet( node ) {
		var s = node.settings || {};
		var text = s.title || s.editor || '';
		var div = document.createElement( 'div' );
		div.innerHTML = String( text );
		text = ( div.textContent || '' ).replace( /\s+/g, ' ' ).trim();
		return text.length > 28 ? text.slice( 0, 27 ) + '…' : text;
	}

	function hiddenHere( node ) {
		return node.settings && node.settings[ 'hide_' + state.device ] === 'yes';
	}

	function renderNavigator() {
		if ( ! ui.navigator ) {
			return;
		}
		ui.navigator.hidden = ! state.navigator;
		if ( ! state.navigator ) {
			return;
		}
		var tree = el( 'div', { class: 'kb-nav-tree', role: 'tree' } );
		if ( state.preview ) {
			tree.appendChild( el( 'p', { class: 'kb-hint kb-nav-empty', text: 'Previewing a revision.' } ) );
			ui.navigator.innerHTML = '';
			ui.navigator.appendChild( el( 'div', { class: 'kb-panel-header' }, [ el( 'h2', { text: 'Navigator' } ) ] ) );
			ui.navigator.appendChild( el( 'div', { class: 'kb-panel-body kb-nav-body' }, [ tree ] ) );
			return;
		}

		( function walk( list, depth ) {
			list.forEach( function ( node ) {
				var hasChildren = node.elType !== 'widget' && node.elements && node.elements.length;
				tree.appendChild( navRow( node, depth, hasChildren ) );
				if ( hasChildren && ! collapsed[ node.id ] ) {
					walk( node.elements, depth + 1 );
				}
			} );
		} )( state.layout.elements, 0 );

		if ( ! state.layout.elements.length ) {
			tree.appendChild( el( 'p', { class: 'kb-hint kb-nav-empty', text: 'Nothing on the page yet.' } ) );
		}

		ui.navigator.innerHTML = '';
		ui.navigator.appendChild(
			el( 'div', { class: 'kb-panel-header' }, [
				el( 'h2', { text: 'Navigator' } ),
				el(
					'button',
					{
						type: 'button',
						class: 'kb-icon-btn kb-nav-close',
						title: 'Close navigator',
						'aria-label': 'Close navigator',
						onclick: function () {
							state.navigator = false;
							writePref( 'navigator', '0' );
							renderTopbar();
							renderNavigator();
						},
					},
					[ icon( 'dashicons-no-alt' ) ]
				),
			] )
		);
		ui.navigator.appendChild( el( 'div', { class: 'kb-panel-body kb-nav-body' }, [ tree ] ) );
	}

	function navRow( node, depth, hasChildren ) {
		var definition = node.elType === 'widget' ? config.widgets[ node.widgetType ] : null;
		var snippet = node.elType === 'widget' && ! ( node.settings && node.settings._title ) ? widgetSnippet( node ) : '';

		var label = el( 'span', { class: 'kb-nav-label', title: 'Double-click to rename' }, [
			el( 'span', { text: elementLabel( node ) } ),
			snippet ? el( 'span', { class: 'kb-nav-snippet', text: snippet } ) : null,
		] );
		label.addEventListener( 'dblclick', function ( e ) {
			e.stopPropagation();
			renameInline( node, label );
		} );

		var row = el(
			'div',
			{
				class:
					'kb-nav-row kb-nav-' + node.elType +
					( isSelected( node.id ) ? ' is-selected' : '' ) +
					( hiddenHere( node ) ? ' is-hidden-here' : '' ),
				role: 'treeitem',
				'aria-selected': isSelected( node.id ) ? 'true' : 'false',
				'aria-expanded': hasChildren ? ( collapsed[ node.id ] ? 'false' : 'true' ) : null,
				'data-id': node.id,
				draggable: 'true',
				style: 'padding-left:' + ( 8 + depth * 16 ) + 'px',
				onclick: function () {
					// Re-rendering on a second click would detach the row before
					// its dblclick (rename) arrives.
					if ( ! isSelected( node.id ) ) {
						select( node.id, node.elType );
					}
					var target = ui.canvas.querySelector( '[data-id="' + node.id + '"]' );
					if ( target && target.scrollIntoView ) {
						target.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
					}
				},
				ondragstart: function ( e ) {
					navDrag = { id: node.id, elType: node.elType };
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData( 'text/plain', 'kbroo:nav' );
				},
				ondragover: function ( e ) {
					navDragOver( e, node, row );
				},
				ondrop: function ( e ) {
					e.preventDefault();
					navDrop();
				},
				ondragend: navDragEnd,
			},
			[
				hasChildren
					? el(
							'button',
							{
								type: 'button',
								class: 'kb-nav-caret',
								'aria-label': collapsed[ node.id ] ? 'Expand' : 'Collapse',
								onclick: function ( e ) {
									e.stopPropagation();
									collapsed[ node.id ] = ! collapsed[ node.id ];
									renderNavigator();
								},
							},
							[ icon( collapsed[ node.id ] ? 'dashicons-arrow-right' : 'dashicons-arrow-down' ) ]
					  )
					: el( 'span', { class: 'kb-nav-caret-space' } ),
				icon( definition ? definition.icon : NAV_ICONS[ node.elType ] ),
				label,
				hiddenHere( node ) ? el( 'span', { class: 'kb-nav-hidden', title: 'Hidden on ' + state.device }, [ icon( 'dashicons-hidden' ) ] ) : null,
			]
		);
		return row;
	}

	function renameInline( node, label ) {
		var input = el( 'input', { type: 'text', class: 'kb-nav-rename', 'aria-label': 'Name' } );
		input.value = ( node.settings && node.settings._title ) || '';
		input.placeholder = elementLabel( Object.assign( {}, node, { settings: {} } ) );
		label.replaceWith( input );
		input.focus();
		var done = false;
		function commit( keep ) {
			if ( done ) {
				return;
			}
			done = true;
			if ( keep ) {
				var value = input.value.trim().slice( 0, 100 );
				node.settings = node.settings || {};
				if ( value ) {
					node.settings._title = value;
				} else {
					delete node.settings._title;
				}
				changed();
			} else {
				renderNavigator();
			}
		}
		input.addEventListener( 'keydown', function ( e ) {
			e.stopPropagation();
			if ( e.key === 'Enter' ) {
				commit( true );
			} else if ( e.key === 'Escape' ) {
				commit( false );
			}
		} );
		input.addEventListener( 'blur', function () {
			commit( true );
		} );
		input.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
		} );
	}

	/** Whether `ancestorId` is `id` or contains it. */
	function contains( ancestorId, id ) {
		var found = find( ancestorId );
		if ( ! found ) {
			return false;
		}
		return ancestorId === id || !! find( id, found.node.elements || [] );
	}

	function navDragOver( e, node, row ) {
		if ( ! navDrag || contains( navDrag.id, node.id ) ) {
			return;
		}
		var position = null;
		var rect = row.getBoundingClientRect();
		var after = e.clientY > rect.top + rect.height / 2;

		var dragged = find( navDrag.id );
		var here = find( node.id );
		if ( ! dragged || ! here ) {
			return;
		}
		// Inside a column or container (or a section, for a column), else
		// next to the row when its parent can take the dragged element.
		var into = node.elType !== 'widget' && canHold( node, dragged.node.elType, dragged.node );
		var beside = canHold( here.parent, dragged.node.elType, dragged.node ) || here.parent === dragged.parent;
		var middle = Math.abs( e.clientY - ( rect.top + rect.height / 2 ) ) < rect.height / 4;
		if ( into && ( middle || ! beside ) ) {
			position = 'inside';
		} else if ( beside ) {
			position = after ? 'after' : 'before';
		}
		if ( ! position ) {
			return;
		}
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		navTarget = { id: node.id, position: position };
		Array.prototype.forEach.call( ui.navigator.querySelectorAll( '.kb-nav-drop-before, .kb-nav-drop-after, .kb-nav-drop-inside' ), function ( r ) {
			r.classList.remove( 'kb-nav-drop-before', 'kb-nav-drop-after', 'kb-nav-drop-inside' );
		} );
		row.classList.add( 'kb-nav-drop-' + position );
	}

	function navDragEnd() {
		navDrag = null;
		navTarget = null;
		renderNavigator();
	}

	function navDrop() {
		if ( ! navDrag || ! navTarget ) {
			navDragEnd();
			return;
		}
		var moved = moveElement( navDrag.id, navTarget.id, navTarget.position );
		navDrag = null;
		navTarget = null;
		if ( moved ) {
			changed();
		} else {
			renderNavigator();
		}
	}

	/**
	 * Move an element before, after or inside another. Columns that change
	 * section share the width evenly in both sections.
	 */
	function moveElement( id, targetId, position ) {
		if ( id === targetId || contains( id, targetId ) ) {
			return false;
		}
		var source = find( id );
		var target = find( targetId );
		if ( ! source || ! target ) {
			return false;
		}
		var oldParent = source.parent;
		var newParent = position === 'inside' ? target.node : target.parent;
		if ( source.node.elType === 'column' && newParent !== oldParent && newParent.elements.length >= config.maxColumns ) {
			return false;
		}

		var node = source.list.splice( source.index, 1 )[ 0 ];
		setParent( node, newParent );
		if ( position === 'inside' ) {
			target.node.elements.push( node );
		} else {
			target = find( targetId ); // Indexes may have shifted.
			target.list.splice( target.index + ( position === 'after' ? 1 : 0 ), 0, node );
		}

		if ( node.elType === 'column' && newParent !== oldParent ) {
			evenWidths( newParent );
			if ( oldParent.elements.length ) {
				evenWidths( oldParent );
			} else {
				removeElement( oldParent.id );
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Canvas
	 * ------------------------------------------------------------------ */

	var cssRules;

	function isSelected( id ) {
		return state.selected && state.selected.id === id;
	}

	function toolbar( node, buttons ) {
		var bar = el( 'div', { class: 'kb-toolbar kb-toolbar-' + node.elType } );
		bar.appendChild( el( 'span', { class: 'kb-toolbar-label', text: elementLabel( node ) } ) );
		buttons.forEach( function ( b ) {
			if ( b ) {
				bar.appendChild( b );
			}
		} );
		return bar;
	}

	function toolButton( name, title, onClick ) {
		return el(
			'button',
			{
				type: 'button',
				class: 'kb-tool',
				title: title,
				'aria-label': title,
				onclick: function ( e ) {
					e.stopPropagation();
					onClick();
				},
			},
			[ icon( name ) ]
		);
	}

	/**
	 * Show and hide rules (src/Visibility.php): whether any is filled in, and
	 * whether the element shows now for a logged-out visitor.
	 */
	function hasDisplayRules( s ) {
		if ( s.display_mode !== 'show' && s.display_mode !== 'hide' ) {
			return false;
		}
		return !! ( s.display_from || s.display_until || s.display_time_from || s.display_time_until || s.display_login || ( Array.isArray( s.display_days ) && s.display_days.length ) );
	}

	function visibleNow( s ) {
		var now = new Date( Date.now() + ( config.siteTimeOffset || 0 ) * 1000 ); // Site time, read with the UTC getters.
		var pad = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		var stamp = now.getUTCFullYear() + '-' + pad( now.getUTCMonth() + 1 ) + '-' + pad( now.getUTCDate() ) + 'T' + pad( now.getUTCHours() ) + ':' + pad( now.getUTCMinutes() );
		var time = stamp.slice( 11 );
		var match = true;
		if ( ( s.display_from && stamp < s.display_from ) || ( s.display_until && stamp >= s.display_until ) ) {
			match = false;
		}
		if ( Array.isArray( s.display_days ) && s.display_days.length && s.display_days.indexOf( String( now.getUTCDay() || 7 ) ) < 0 ) {
			match = false;
		}
		if ( s.display_time_from || s.display_time_until ) {
			var start = s.display_time_from || '00:00';
			var end = s.display_time_until || '24:00';
			if ( start <= end ? ! ( time >= start && time < end ) : ! ( time >= start || time < end ) ) {
				match = false;
			}
		}
		if ( s.display_login === 'in' ) {
			match = false; // Previewed as a visitor.
		}
		return s.display_mode === 'show' ? match : ! match;
	}

	/**
	 * Classes shared with the front end (src/Renderer.php), plus editor state.
	 */
	function elementClasses( node, settings, base, prefix ) {
		var classes = base.concat( [ 'kbroo-el', 'kbroo-el-' + node.id, 'kb-el', 'kb-' + node.elType ] );
		if ( hasDisplayRules( settings ) ) {
			classes.push( 'kb-has-rules' );
			if ( ! visibleNow( settings ) ) {
				classes.push( 'kb-hidden-here', 'kb-hidden-now' );
			}
		}
		[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
			if ( settings[ 'hide_' + device ] === 'yes' ) {
				classes.push( 'kbroo-hidden-' + device );
				if ( device === state.device ) {
					classes.push( 'kb-hidden-here' );
				}
			}
		} );
		String( settings[ prefix + 'css_classes' ] || '' )
			.split( /\s+/ )
			.forEach( function ( c ) {
				if ( /^[A-Za-z0-9_-]+$/.test( c ) ) {
					classes.push( c );
				}
			} );
		if ( isSelected( node.id ) ) {
			classes.push( 'is-selected' );
		}
		return classes.join( ' ' );
	}

	function collectCss( node, controls, settings ) {
		var rules = Css.elementRules( controls, settings, Css.wrapper( node.id ) );
		Css.DEVICES.forEach( function ( device ) {
			cssRules[ device ] = cssRules[ device ].concat( rules[ device ] );
		} );
	}

	function renderCanvas() {
		var scroll = ui.canvasWrap.scrollTop;
		cssRules = { desktop: [], tablet: [], mobile: [] };
		ui.canvas.innerHTML = '';

		var width = DEVICES[ state.device ].width;
		ui.canvas.style.maxWidth = width ? width + 'px' : '';
		ui.canvasWrap.setAttribute( 'data-device', state.device );

		var layout = state.preview ? state.preview.layout : state.layout;
		Css.setGlobals( state.globals, config.globalTypographyControls );
		ui.canvasWrap.classList.toggle( 'is-previewing', !! state.preview );

		layout.elements.forEach( function ( node ) {
			var rendered = renderElement( node );
			if ( rendered ) {
				ui.canvas.appendChild( rendered );
			}
		} );

		if ( ! layout.elements.length ) {
			ui.canvas.appendChild(
				el( 'div', { class: 'kb-empty' }, [
					el( 'p', { class: 'kb-empty-title', text: 'Your page is empty' } ),
					el( 'p', { text: 'Drag a widget here, or pick a structure below to add a section.' } ),
				] )
			);
		}

		ui.canvas.appendChild( renderAddSection() );
		ui.styles.textContent =
			Css.forDevice( Css.globalsRules( state.globals, config.globalTypographyControls ), state.device ) +
			Css.forDevice( cssRules, state.device ) +
			Css.globalsCustomCss( state.globals );
		loadFonts( layout );
		if ( window.KbrooStudio.initCarousels ) {
			window.KbrooStudio.initCarousels( ui.canvas, { autoplay: 0 } );
		}
		ui.canvasWrap.scrollTop = scroll;
		renderNavigator();
	}

	function renderElement( node ) {
		switch ( node.elType ) {
			case 'section':
				return renderSection( node );
			case 'container':
				return renderContainer( node );
			case 'widget':
				return renderWidget( node );
		}
		return null;
	}

	/** Children of a column or container, inside its drop zone. */
	function renderChildren( holder, zone, emptyText ) {
		holder.elements.forEach( function ( child ) {
			var rendered = renderElement( child );
			if ( rendered ) {
				zone.appendChild( rendered );
			}
		} );
		if ( ! holder.elements.length ) {
			zone.appendChild( el( 'div', { class: 'kb-column-empty' }, [ icon( 'dashicons-plus' ), el( 'span', { text: emptyText } ) ] ) );
		}
		return zone;
	}

	/** Drag handle for sections and containers. */
	function dragHandle( node, getElement ) {
		return el(
			'span',
			{
				class: 'kb-tool kb-handle',
				title: 'Drag to move',
				draggable: 'true',
				// Keep the toolbar (and so this handle) rendered while the
				// pointer leaves the element, or the browser cancels the drag.
				onmousedown: function () {
					var target = getElement();
					target.classList.add( 'kb-toolbar-pinned' );
					document.addEventListener(
						'mouseup',
						function () {
							target.classList.remove( 'kb-toolbar-pinned' );
						},
						{ once: true }
					);
				},
				ondragstart: function ( e ) {
					e.stopPropagation();
					startDrag( e, { kind: 'move', id: node.id }, getElement() );
				},
				ondragend: endDrag,
			},
			[ icon( 'dashicons-move' ) ]
		);
	}

	/** Twin of Renderer::video_source(). */
	function videoSource( link ) {
		link = String( link || '' ).trim();
		var m = link.match( /(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{6,20})/ );
		if ( m ) {
			return { type: 'youtube', id: m[ 1 ] };
		}
		m = link.match( /vimeo\.com\/(?:video\/)?(\d{3,12})/ );
		if ( m ) {
			return { type: 'vimeo', id: m[ 1 ] };
		}
		if ( /^https?:\/\/[^\s"'<>]+\.(mp4|webm|ogv|mov)(\?[^\s"'<>]*)?$/i.test( link ) ) {
			return { type: 'file', url: link };
		}
		return null;
	}

	/**
	 * Background video in the canvas. The canvas is rebuilt on every change,
	 * which would restart an embedded player, so YouTube shows its still
	 * image and Vimeo a plain layer; video files play.
	 */
	function backgroundVideo( s ) {
		if ( s.background_background !== 'video' ) {
			return null;
		}
		var video = videoSource( s.background_video_link );
		if ( ! video ) {
			return null;
		}
		var layer = el( 'div', { class: 'kbroo-bg-video kbroo-bg-video--mobile kb-bg-video-preview', 'aria-hidden': 'true' } );
		if ( video.type === 'file' ) {
			var player = el( 'video', { src: video.url, autoplay: '', loop: '', playsinline: '' } );
			player.muted = true;
			layer.appendChild( player );
		} else if ( video.type === 'youtube' ) {
			layer.style.backgroundImage = 'url("https://i.ytimg.com/vi/' + encodeURIComponent( video.id ) + '/hqdefault.jpg")';
		}
		return layer;
	}

	/** Shape dividers: the server's SVG for each shape (see ShapeDividers). */
	function shapeDividers( s ) {
		var layers = [];
		[ 'top', 'bottom' ].forEach( function ( side ) {
			var key = 'shape_divider_' + side;
			var shape = ( config.shapes || {} )[ s[ key ] ];
			if ( ! shape ) {
				return;
			}
			var classes = 'kbroo-shape kbroo-shape--' + side;
			if ( s[ key + '_flip' ] === 'yes' ) {
				classes += ' kbroo-shape--flip';
			}
			if ( s[ key + '_above_content' ] === 'yes' ) {
				classes += ' kbroo-shape--above';
			}
			var layer = el( 'div', { class: classes, 'aria-hidden': 'true' } );
			layer.innerHTML = shape[ s[ key + '_negative' ] === 'yes' ? 1 : 0 ];
			layers.push( layer );
		} );
		return layers;
	}

	function renderSection( section ) {
		var s = withDefaults( config.sectionControls, section.settings );
		collectCss( section, config.sectionControls, s );

		var row = el( 'div', { class: 'kbroo-row' } );
		section.elements.forEach( function ( column ) {
			row.appendChild( renderColumn( column, section ) );
		} );

		var tag = config.sectionControls.html_tag.options[ s.html_tag ] ? s.html_tag : 'section';
		var classes = [ 'kbroo-section', 'kbroo-section--' + ( s.layout === 'full_width' ? 'full' : 'boxed' ) ];
		if ( section.isInner ) {
			classes.push( 'kbroo-section--inner' );
		}
		var sectionEl = el(
			tag,
			{
				class: elementClasses( section, s, classes, '' ),
				'data-id': section.id,
			},
			[
				toolbar( section, [
					dragHandle( section, function () {
						return sectionEl;
					} ),
					toolButton( 'dashicons-plus-alt2', 'Add section above', function () {
						addSectionAbove( section.id );
						changed();
					} ),
					section.elements.length < config.maxColumns
						? toolButton( 'dashicons-columns', 'Add column', function () {
								addColumn( section.id );
								changed();
						  } )
						: null,
					toolButton( 'dashicons-admin-page', 'Duplicate', function () {
						duplicateElement( section.id );
						changed();
					} ),
					toolButton( 'dashicons-trash', 'Delete', function () {
						removeElement( section.id );
						changed();
					} ),
				] ),
				backgroundVideo( s ),
			].concat( shapeDividers( s ), [
				el( 'div', { class: 'kbroo-container' }, [ row ] ),
			] )
		);
		return sectionEl;
	}

	function renderColumn( column, section ) {
		var s = withDefaults( config.columnControls, column.settings );
		collectCss( column, config.columnControls, s );

		var inner = renderChildren( column, el( 'div', { class: 'kbroo-column-inner kb-dropzone', 'data-holder': column.id } ), 'Drag widget here' );

		return el(
			'div',
			{
				class: elementClasses( column, s, [ 'kbroo-column' ], '' ),
				'data-id': column.id,
			},
			[
				toolbar( column, [
					section.elements.length < config.maxColumns
						? toolButton( 'dashicons-admin-page', 'Duplicate column', function () {
								duplicateElement( column.id );
								changed();
						  } )
						: null,
					toolButton( 'dashicons-trash', 'Delete column', function () {
						removeElement( column.id );
						changed();
					} ),
				] ),
				inner,
			]
		);
	}

	function renderContainer( container ) {
		var s = withDefaults( config.containerControls, container.settings );
		collectCss( container, config.containerControls, s );

		var classes = [ 'kbroo-con', 'kbroo-con--' + ( s.content_width === 'full' ? 'full' : 'boxed' ) ];
		if ( container.isInner ) {
			classes.push( 'kbroo-con--child' );
		}
		var tag = config.containerControls.html_tag.options[ s.html_tag ] ? s.html_tag : 'div';
		var zone = renderChildren( container, el( 'div', { class: 'kbroo-con-inner kb-dropzone', 'data-holder': container.id } ), 'Drag widget or container here' );
		var node = el(
			tag,
			{
				class: elementClasses( container, s, classes, '' ),
				'data-id': container.id,
			},
			[
				toolbar( container, [
					dragHandle( container, function () {
						return node;
					} ),
					toolButton( 'dashicons-admin-page', 'Duplicate', function () {
						duplicateElement( container.id );
						changed();
					} ),
					toolButton( 'dashicons-trash', 'Delete', function () {
						removeElement( container.id );
						changed();
					} ),
				] ),
				backgroundVideo( s ),
			].concat( shapeDividers( s ), [
				zone,
			] )
		);
		return node;
	}

	/* Widgets only WordPress can render (shortcodes) are previewed with HTML
	 * from the server, fetched shortly after their settings stop changing. */
	var serverCache = {};
	var serverLast = {};
	var serverTimers = {};

	function serverPreview( widget, s ) {
		var definition = config.widgets[ widget.widgetType ];
		var key = widget.widgetType + ':' + JSON.stringify( s );
		var empty = window.KbrooStudio.hint( definition.icon, definition.title + ': fill in the settings in the panel.' );
		if ( Object.prototype.hasOwnProperty.call( serverCache, key ) ) {
			serverLast[ widget.id ] = serverCache[ key ];
			return serverCache[ key ] || empty;
		}
		clearTimeout( serverTimers[ widget.id ] );
		serverTimers[ widget.id ] = setTimeout( function () {
			fetch( config.widgetPreviewUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify( { widgetType: widget.widgetType, settings: s } ),
			} )
				.then( parseResponse )
				.then( function ( data ) {
					addPreviewStyles( data.styles );
					serverCache[ key ] = String( data.html || '' );
				} )
				.catch( function ( error ) {
					serverCache[ key ] = window.KbrooStudio.hint( 'dashicons-warning', 'Preview failed: ' + error.message );
				} )
				.then( renderCanvas );
		}, 400 );
		return serverLast[ widget.id ] != null ? serverLast[ widget.id ] || empty : window.KbrooStudio.hint( definition.icon, 'Loading preview…' );
	}

	/* Stylesheets plugins load on the front end for the previewed output
	 * (a gallery's grid, say), sent with the preview. Each is added to the
	 * editor once, after its own styles, in the order the front end prints
	 * them, so the output looks as it does on the page. */
	function addPreviewStyles( markup ) {
		if ( ! markup ) {
			return;
		}
		var template = document.createElement( 'template' );
		template.innerHTML = String( markup );
		Array.prototype.slice.call( template.content.children ).forEach( function ( node ) {
			var tag = node.tagName;
			if ( ( tag !== 'LINK' && tag !== 'STYLE' ) || ( node.id && document.getElementById( node.id ) ) ) {
				return;
			}
			if ( tag === 'LINK' && node.getAttribute( 'rel' ) !== 'stylesheet' ) {
				return;
			}
			document.head.appendChild( node );
		} );
	}

	/** Captions for images linked to the Media Library, fetched once each. */
	var captionRequests = {};

	function fetchCaption( id ) {
		if ( captionRequests[ id ] || ! window.wp || ! wp.media ) {
			return;
		}
		captionRequests[ id ] = true;
		var attachment = wp.media.attachment( id );
		attachment.fetch().then( function () {
			window.KbrooStudio.captions[ id ] = attachment.get( 'caption' ) || '';
			renderCanvas();
		} );
	}

	function renderWidget( widget ) {
		var render = renderers[ widget.widgetType ];
		var definition = config.widgets[ widget.widgetType ];
		if ( ( ! render && ! ( definition && definition.server ) ) || ! definition ) {
			return null;
		}
		var s = withDefaults( definition.controls, widget.settings );
		collectCss( widget, definition.controls, s );
		if ( widget.widgetType === 'image' && s.caption_source === 'attachment' && s.image && s.image.id ) {
			if ( ! ( s.image.id in window.KbrooStudio.captions ) ) {
				fetchCaption( s.image.id );
			}
		}

		var node = el(
			'div',
			{
				class: elementClasses( widget, s, [ 'kbroo-widget', 'kbroo-widget--' + widget.widgetType ], '_' ),
				'data-id': widget.id,
				draggable: 'true',
				ondragstart: function ( e ) {
					e.stopPropagation();
					startDrag( e, { kind: 'move', id: widget.id }, node );
				},
				ondragend: endDrag,
			},
			[
				toolbar( widget, [
					toolButton( 'dashicons-admin-page', 'Duplicate', function () {
						duplicateElement( widget.id );
						changed();
					} ),
					toolButton( 'dashicons-trash', 'Delete', function () {
						removeElement( widget.id );
						changed();
					} ),
				] ),
			]
		);
		// Insert the preview markup directly so selectors such as
		// "{{WRAPPER}} > .kbroo-heading" match the same tree as the front end.
		var holder = document.createElement( 'div' );
		holder.innerHTML = definition.server ? serverPreview( widget, s ) : render( s );
		while ( holder.firstChild ) {
			node.appendChild( holder.firstChild );
		}
		return node;
	}

	function renderAddSection() {
		var picker = el( 'div', { class: 'kb-structures', hidden: true } );
		STRUCTURES.forEach( function ( structure ) {
			var preview = el( 'span', { class: 'kb-structure-preview' } );
			structure.widths.forEach( function ( w ) {
				preview.appendChild( el( 'span', { style: 'flex:' + w + ' 1 0' } ) );
			} );
			picker.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'kb-structure',
						title: structure.label,
						'aria-label': 'Add section, columns ' + structure.label,
						'data-structure': structure.label,
						onclick: function () {
							var section = makeSection( structure.widths );
							state.layout.elements.push( section );
							select( section.id, 'section', true );
							changed();
						},
					},
					[ preview ]
				)
			);
		} );

		var toggle = el(
			'button',
			{
				type: 'button',
				class: 'kb-add-section-btn',
				'data-action': 'add-section',
				onclick: function () {
					picker.hidden = ! picker.hidden;
				},
			},
			[ icon( 'dashicons-plus-alt2' ), el( 'span', { text: 'Add section' } ) ]
		);
		var library = el(
			'button',
			{
				type: 'button',
				class: 'kb-add-template-btn',
				'data-action': 'add-template',
				title: 'Insert a saved template',
				onclick: function () {
					showPanel( 'templates' );
				},
			},
			[ icon( 'dashicons-category' ), el( 'span', { text: 'Templates' } ) ]
		);

		return el( 'div', { class: 'kb-add-section' }, [ el( 'div', { class: 'kb-add-section-buttons' }, [ toggle, library ] ), picker ] );
	}

	function addWidgetByClick( type ) {
		var widget = makeWidget( type );
		insertNode( widget, state.selected && state.selected.id );
		select( widget.id, 'widget', true );
		changed();
	}

	/* ------------------------------------------------------------------ *
	 * Canvas events: selection and drag and drop
	 * ------------------------------------------------------------------ */

	function bindCanvasEvents() {
		ui.canvas.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.kb-toolbar, .kb-add-section' ) ) {
				return;
			}
			// Links inside the preview should not navigate away from the editor.
			if ( e.target.closest( 'a' ) ) {
				e.preventDefault();
			}
			var target = e.target.closest( '.kb-el' );
			if ( ! target ) {
				select( null );
				return;
			}
			var found = find( target.getAttribute( 'data-id' ) );
			if ( found ) {
				select( found.node.id, found.node.elType );
			}
		} );

		ui.canvasWrap.addEventListener( 'dragover', onDragOver );
		ui.canvasWrap.addEventListener( 'drop', onDrop );
		ui.canvasWrap.addEventListener( 'dragleave', function ( e ) {
			if ( ! e.relatedTarget || ! ui.canvasWrap.contains( e.relatedTarget ) ) {
				clearDropTarget();
			}
		} );
	}

	/** What is being dragged: { elType, node } (node is null for a new element). */
	function dragInfo() {
		if ( ! drag ) {
			return null;
		}
		if ( drag.kind === 'new' ) {
			return { elType: drag.widgetType ? 'widget' : drag.elType, node: null };
		}
		var found = find( drag.id );
		return found ? { elType: found.node.elType, node: found.node } : null;
	}

	function startDrag( e, payload, dragImage ) {
		drag = payload;
		e.dataTransfer.effectAllowed = payload.kind === 'new' ? 'copy' : 'move';
		// Firefox will not start a drag without data.
		e.dataTransfer.setData( 'text/plain', 'kbroo:' + payload.kind );
		if ( dragImage && e.dataTransfer.setDragImage ) {
			e.dataTransfer.setDragImage( dragImage, 20, 20 );
		}
		var info = dragInfo();
		ui.canvas.classList.add( 'is-dragging-' + ( info && info.elType !== 'widget' ? 'section' : 'widget' ) );
		if ( dragImage ) {
			// Defer, so the drag image is captured before it is dimmed.
			setTimeout( function () {
				dragImage.classList.add( 'kb-is-dragging' );
			}, 0 );
		}
	}

	function endDrag() {
		drag = null;
		clearDropTarget();
		ui.canvas.classList.remove( 'is-dragging-section', 'is-dragging-widget' );
		Array.prototype.forEach.call( ui.canvas.querySelectorAll( '.kb-is-dragging' ), function ( node ) {
			node.classList.remove( 'kb-is-dragging' );
		} );
	}

	function clearDropTarget() {
		dropTarget = null;
		if ( placeholder.parentNode ) {
			placeholder.parentNode.removeChild( placeholder );
		}
	}

	/**
	 * Index among `children` (ignoring the placeholder and the element being
	 * dragged) where the pointer at `y` should insert.
	 */
	function insertIndex( children, e, horizontal ) {
		for ( var i = 0; i < children.length; i++ ) {
			var rect = children[ i ].getBoundingClientRect();
			if ( horizontal ? e.clientX < rect.left + rect.width / 2 : e.clientY < rect.top + rect.height / 2 ) {
				return i;
			}
		}
		return children.length;
	}

	function isRow( zone ) {
		return /^row/.test( window.getComputedStyle( zone ).flexDirection ) && zone.classList.contains( 'kbroo-con-inner' );
	}

	function childrenOf( container, selector ) {
		return Array.prototype.filter.call( container.children, function ( child ) {
			return child.matches( selector ) && ! child.classList.contains( 'kb-is-dragging' );
		} );
	}

	function placePlaceholder( container, children, index, kind ) {
		placeholder.className = 'kb-placeholder kb-placeholder-' + kind;
		var before = children[ index ] || null;
		if ( ! before && kind === 'section' ) {
			before = ui.canvas.querySelector( '.kb-empty, .kb-add-section' );
		}
		if ( placeholder.parentNode !== container || placeholder.nextSibling !== before ) {
			container.insertBefore( placeholder, before );
		}
	}

	function onDragOver( e ) {
		var info = dragInfo();
		if ( ! info || state.preview ) {
			return;
		}
		e.preventDefault();
		e.dataTransfer.dropEffect = drag.kind === 'new' ? 'copy' : 'move';

		// The innermost column or container under the pointer that can take
		// the dragged element.
		var zone = e.target.closest ? e.target.closest( '.kb-dropzone' ) : null;
		while ( zone ) {
			var holder = find( zone.getAttribute( 'data-holder' ) );
			if ( holder && canHold( holder.node, info.elType, info.node ) ) {
				break;
			}
			zone = zone.parentElement ? zone.parentElement.closest( '.kb-dropzone' ) : null;
		}

		if ( zone ) {
			var children = childrenOf( zone, '.kb-el' );
			var row = isRow( zone );
			var index = insertIndex( children, e, row );
			dropTarget = { holderId: zone.getAttribute( 'data-holder' ), index: index };
			placePlaceholder( zone, children, index, row ? 'row' : 'widget' );
			return;
		}

		// The page: between top-level sections and containers.
		var top = childrenOf( ui.canvas, '.kb-el' );
		var topIndex = insertIndex( top, e, false );
		dropTarget = { holderId: null, index: topIndex };
		placePlaceholder( ui.canvas, top, topIndex, 'section' );
	}

	function onDrop( e ) {
		if ( ! drag || ! dropTarget ) {
			endDrag();
			return;
		}
		e.preventDefault();

		var payload = drag;
		var target = dropTarget;
		endDrag();

		var holder = target.holderId ? find( target.holderId ) : null;
		if ( target.holderId && ! holder ) {
			return;
		}

		var node;
		if ( payload.kind === 'new' ) {
			if ( payload.widgetType ) {
				node = makeWidget( payload.widgetType );
			} else {
				node = payload.elType === 'container' ? makeContainer() : makeInnerSection();
			}
		} else {
			var source = find( payload.id );
			if ( ! source ) {
				return;
			}
			node = source.list.splice( source.index, 1 )[ 0 ];
		}

		if ( holder ) {
			setParent( node, holder.node );
			holder.node.elements.splice( target.index, 0, node );
		} else {
			var topNode = node;
			if ( node.elType === 'widget' ) {
				topNode = makeSection( [ 100 ] );
				topNode.elements[ 0 ].elements.push( node );
			}
			setParent( topNode, null );
			state.layout.elements.splice( target.index, 0, topNode );
		}

		select( node.id, node.elType, true );
		changed();
	}

	/* ------------------------------------------------------------------ *
	 * Keyboard
	 * ------------------------------------------------------------------ */

	function bindKeyboard() {
		document.addEventListener( 'keydown', function ( e ) {
			var typing = e.target.closest && e.target.closest( 'input, textarea, select, [contenteditable]' );
			var mod = e.ctrlKey || e.metaKey;
			var key = e.key.toLowerCase();

			if ( mod && key === 's' ) {
				e.preventDefault();
				save();
				return;
			}
			if ( typing ) {
				return; // Fields keep their own undo while you type.
			}
			if ( mod && key === 'z' ) {
				e.preventDefault();
				if ( e.shiftKey ) {
					redo();
				} else {
					undo();
				}
			} else if ( mod && key === 'y' ) {
				e.preventDefault();
				redo();
			} else if ( e.key === 'Escape' ) {
				select( null );
			} else if ( ( e.key === 'Delete' || e.key === 'Backspace' ) && state.selected ) {
				e.preventDefault();
				removeElement( state.selected.id );
				changed();
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Saving
	 * ------------------------------------------------------------------ */

	function parseResponse( response ) {
		return response.json().then( function ( data ) {
			if ( ! response.ok ) {
				throw new Error( ( data && data.message ) || 'Request failed (' + response.status + ').' );
			}
			return data;
		} );
	}

	function save( status ) {
		if ( state.saving ) {
			return;
		}
		state.saving = true;
		renderTopbar();

		if ( config.isTemplate && config.canPublish ) {
			status = 'publish'; // Templates have no drafts: saving adds them to the library.
		}
		var revision = state.revision;
		var body = { layout: state.layout, title: state.title };
		if ( state.display ) {
			body.display = state.display;
		}
		if ( state.page ) {
			body.page = {
				excerpt: state.page.excerpt,
				featured: state.page.featured.id || 0,
				comments: state.page.comments,
				hide_title: state.page.hide_title,
				layout: state.page.layout,
			};
			if ( ! status && state.page.status !== state.status ) {
				status = state.page.status; // Chosen in Page settings.
			}
		}
		if ( status ) {
			body.status = status;
		}

		( state.globalsDirty && config.canEditGlobals ? saveGlobals() : Promise.resolve() )
			.then( function () {
				return window.fetch( config.restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
					body: JSON.stringify( body ),
				} );
			} )
			.then( parseResponse )
			.then( function ( data ) {
				revisions.items = null; // A save adds a revision; reload the list when shown.
				state.status = data.status;
				state.permalink = data.permalink;
				if ( state.page && data.page ) {
					state.page.status = data.status;
					state.page.featured = data.page.featured;
				}
				config.title = data.title;
				if ( state.title === body.title ) {
					state.title = data.title;
				}
				// Adopt the server's sanitized copy unless the user kept editing
				// meanwhile. Undo history stays; this point becomes "saved".
				if ( state.revision === revision ) {
					state.layout = normalizeLayout( data.layout );
					history.stack[ history.index ] = snapshot();
					history.savedIndex = history.index;
					history.mergeKey = null;
					state.dirty = false;
					if ( state.selected && ! find( state.selected.id ) ) {
						state.selected = null;
					}
					renderCanvas();
					renderPanel();
				}
				if ( ! state.selected && state.panelView === 'revisions' ) {
					renderPanel();
				}
				toast( status === 'publish' && ! config.isTemplate ? 'Published' : 'Saved' );
			} )
			.catch( function ( error ) {
				toast( error.message || 'Saving failed.', true );
			} )
			.then( function () {
				state.saving = false;
				renderTopbar();
			} );
	}

	/* ------------------------------------------------------------------ */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount );
	} else {
		mount();
	}

	// Exposed for debugging and tests.
	window.KbrooStudio.editor = {
		copy: copyElement,
		paste: function ( targetId ) {
			var copied = storedClipboard();
			if ( copied ) {
				pasteElement( copied, targetId );
			}
		},
		pasteStyle: function ( targetId ) {
			pasteStyle( storedClipboard(), targetId );
		},
		getLayout: function () {
			return clone( state.layout );
		},
		getCss: function () {
			return ui.styles.textContent;
		},
		save: save,
		undo: undo,
		redo: redo,
	};
} )();
