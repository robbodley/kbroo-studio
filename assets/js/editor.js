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
	var esc = window.KbrooStudio.esc;

	var STRUCTURES = [
		{ label: '100', widths: [ 100 ] },
		{ label: '50 / 50', widths: [ 50, 50 ] },
		{ label: '33 / 33 / 33', widths: [ 33, 33, 34 ] },
		{ label: '66 / 33', widths: [ 66, 34 ] },
		{ label: '33 / 66', widths: [ 34, 66 ] },
		{ label: '25 x 4', widths: [ 25, 25, 25, 25 ] },
	];

	var state = {
		layout: normalizeLayout( config.layout ),
		selected: null, // { id, kind }
		status: config.status,
		permalink: config.permalink,
		dirty: false,
		saving: false,
		revision: 0,
	};

	var drag = null; // { kind: 'new', widgetType } | { kind: 'widget', id } | { kind: 'section', id }
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

	function defaults( controls ) {
		var out = {};
		Object.keys( controls ).forEach( function ( key ) {
			out[ key ] = controls[ key ].default;
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

	function hexOrEmpty( value ) {
		return /^#([0-9a-f]{3}){1,2}$/i.test( value || '' ) ? value : '';
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
		settings.width = width;
		return { id: uid(), elType: 'column', settings: settings, elements: [] };
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

	function evenWidths( section ) {
		var count = section.elements.length;
		section.elements.forEach( function ( column, i ) {
			var base = Math.floor( 100 / count );
			column.settings.width = i === count - 1 ? 100 - base * ( count - 1 ) : base;
		} );
	}

	function changed( options ) {
		state.dirty = true;
		state.revision++;
		renderCanvas();
		if ( ! options || options.panel !== false ) {
			renderPanel();
		}
		renderTopbar();
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
	 * Selection
	 * ------------------------------------------------------------------ */

	function select( id, kind, silent ) {
		state.selected = id ? { id: id, kind: kind } : null;
		if ( ! silent ) {
			renderCanvas();
			renderPanel();
		}
	}

	/* ------------------------------------------------------------------ *
	 * App shell
	 * ------------------------------------------------------------------ */

	function mount() {
		var app = document.getElementById( 'kbroo-app' );
		app.innerHTML = '';

		ui.topbar = el( 'header', { class: 'kb-topbar' } );
		ui.panel = el( 'aside', { class: 'kb-panel', 'aria-label': 'Kbroo Studio panel' } );
		ui.canvas = el( 'div', { class: 'kbroo kbroo-page kb-canvas' } );
		ui.canvasWrap = el( 'main', { class: 'kb-canvas-wrap' }, [ ui.canvas ] );
		ui.toast = el( 'div', { class: 'kb-toast', role: 'status', 'aria-live': 'polite' } );

		app.appendChild( ui.topbar );
		app.appendChild( el( 'div', { class: 'kb-main' }, [ ui.panel, ui.canvasWrap ] ) );
		app.appendChild( ui.toast );

		bindCanvasEvents();
		bindKeyboard();

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( state.dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );

		renderTopbar();
		renderPanel();
		renderCanvas();
	}

	function toast( message, isError ) {
		ui.toast.textContent = message;
		ui.toast.className = 'kb-toast is-visible' + ( isError ? ' is-error' : '' );
		clearTimeout( toast.timer );
		toast.timer = setTimeout( function () {
			ui.toast.className = 'kb-toast';
		}, isError ? 6000 : 2500 );
	}

	/* ------------------------------------------------------------------ *
	 * Top bar
	 * ------------------------------------------------------------------ */

	function renderTopbar() {
		var published = state.status === 'publish';
		var saveLabel = state.saving ? 'Saving…' : published ? 'Update' : 'Save draft';

		ui.topbar.innerHTML = '';
		ui.topbar.appendChild(
			el( 'div', { class: 'kb-topbar-start' }, [
				el( 'a', { class: 'kb-icon-btn', href: config.wpEditUrl, title: 'Back to the WordPress editor' }, [
					icon( 'dashicons-arrow-left-alt2' ),
					el( 'span', { class: 'kb-hide-sm', text: 'WordPress editor' } ),
				] ),
				el( 'span', { class: 'kb-brand', text: 'Kbroo Studio' } ),
			] )
		);
		ui.topbar.appendChild(
			el( 'div', { class: 'kb-topbar-title' }, [
				el( 'span', { class: 'kb-doc-title', text: config.title || '(no title)' } ),
				el( 'span', {
					class: 'kb-badge' + ( published ? ' is-live' : '' ),
					text: published ? 'Published' : 'Draft',
				} ),
				state.dirty ? el( 'span', { class: 'kb-unsaved', text: 'Unsaved changes' } ) : null,
			] )
		);
		ui.topbar.appendChild(
			el( 'div', { class: 'kb-topbar-end' }, [
				el( 'a', {
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
				! published && config.canPublish
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

	/* ------------------------------------------------------------------ *
	 * Side panel
	 * ------------------------------------------------------------------ */

	function renderPanel() {
		ui.panel.innerHTML = '';
		var sel = state.selected && find( state.selected.id );
		if ( sel ) {
			renderSettingsPanel( sel.node );
		} else {
			state.selected = null;
			renderWidgetsPanel();
		}
	}

	function renderWidgetsPanel() {
		var grid = el( 'div', { class: 'kb-widget-grid' } );
		Object.keys( config.widgets ).forEach( function ( type ) {
			var widget = config.widgets[ type ];
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
				grid,
				el( 'p', {
					class: 'kb-hint',
					text: 'Drag a widget onto the page. Drop it into a column, or between sections to start a new section.',
				} ),
			] )
		);
	}

	function elementLabel( node ) {
		if ( node.elType === 'widget' ) {
			return config.widgets[ node.widgetType ] ? config.widgets[ node.widgetType ].title : node.widgetType;
		}
		return node.elType === 'section' ? 'Section' : 'Column';
	}

	function controlsFor( node ) {
		if ( node.elType === 'section' ) {
			return config.sectionControls;
		}
		if ( node.elType === 'column' ) {
			return config.columnControls;
		}
		return config.widgets[ node.widgetType ].controls;
	}

	function renderSettingsPanel( node ) {
		var controls = controlsFor( node );
		node.settings = withDefaults( controls, node.settings );

		var body = el( 'div', { class: 'kb-panel-body' } );
		Object.keys( controls ).forEach( function ( key ) {
			body.appendChild( renderControl( node, key, controls[ key ] ) );
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
						onclick: function () {
							select( null );
						},
					},
					[ icon( 'dashicons-arrow-left-alt2' ) ]
				),
				el( 'h2', { text: 'Edit ' + elementLabel( node ) } ),
			] )
		);
		ui.panel.appendChild( body );
	}

	function renderControl( node, key, control ) {
		var id = 'kb-control-' + key;
		var value = node.settings[ key ];
		var input;

		function update( newValue ) {
			node.settings[ key ] = newValue;
			changed( { panel: false } );
		}

		switch ( control.type ) {
			case 'textarea':
				input = el( 'textarea', { id: id, rows: 6 } );
				input.value = value;
				input.addEventListener( 'input', function () {
					update( input.value );
				} );
				break;

			case 'select':
				input = el( 'select', { id: id } );
				Object.keys( control.options ).forEach( function ( optionValue ) {
					var option = el( 'option', { value: optionValue, text: control.options[ optionValue ] } );
					option.selected = optionValue === value;
					input.appendChild( option );
				} );
				input.addEventListener( 'change', function () {
					update( input.value );
				} );
				break;

			case 'color':
				var picker = el( 'input', { type: 'color', id: id } );
				picker.value = hexOrEmpty( value ) || '#000000';
				var clear = el( 'button', { type: 'button', class: 'kb-link', text: value ? 'Clear' : 'Default' } );
				picker.addEventListener( 'input', function () {
					clear.textContent = 'Clear';
					update( picker.value );
				} );
				clear.addEventListener( 'click', function () {
					clear.textContent = 'Default';
					update( '' );
				} );
				input = el( 'div', { class: 'kb-color' }, [ picker, clear ] );
				break;

			case 'number':
				input = el( 'input', { type: 'number', id: id, min: control.min, max: control.max, step: 1 } );
				input.value = value;
				input.addEventListener( 'input', function () {
					if ( input.value === '' ) {
						return;
					}
					var n = Math.round( Number( input.value ) );
					if ( control.min != null ) {
						n = Math.max( control.min, n );
					}
					if ( control.max != null ) {
						n = Math.min( control.max, n );
					}
					update( n );
				} );
				break;

			default:
				input = el( 'input', { type: 'text', id: id } );
				input.value = value;
				input.addEventListener( 'input', function () {
					update( input.value );
				} );
		}

		return el( 'div', { class: 'kb-control kb-control-' + control.type }, [
			el( 'label', { for: id, text: control.label } ),
			input,
		] );
	}

	/* ------------------------------------------------------------------ *
	 * Canvas
	 * ------------------------------------------------------------------ */

	function isSelected( id ) {
		return state.selected && state.selected.id === id;
	}

	function toolbar( node, buttons ) {
		var bar = el( 'div', { class: 'kb-toolbar kb-toolbar-' + node.elType } );
		bar.appendChild( el( 'span', { class: 'kb-toolbar-label', text: elementLabel( node ) } ) );
		buttons.forEach( function ( b ) {
			bar.appendChild( b );
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

	function renderCanvas() {
		var scroll = ui.canvasWrap.scrollTop;
		ui.canvas.innerHTML = '';

		state.layout.elements.forEach( function ( section ) {
			ui.canvas.appendChild( renderSection( section ) );
		} );

		if ( ! state.layout.elements.length ) {
			ui.canvas.appendChild(
				el( 'div', { class: 'kb-empty' }, [
					el( 'p', { class: 'kb-empty-title', text: 'Your page is empty' } ),
					el( 'p', { text: 'Drag a widget here, or pick a structure below to add a section.' } ),
				] )
			);
		}

		ui.canvas.appendChild( renderAddSection() );
		ui.canvasWrap.scrollTop = scroll;
	}

	function renderSection( section ) {
		var s = withDefaults( config.sectionControls, section.settings );
		var padding = ( parseInt( s.padding, 10 ) || 0 ) + 'px';

		var handle = el(
			'span',
			{
				class: 'kb-tool kb-handle',
				title: 'Drag to move section',
				draggable: 'true',
				// Keep the toolbar (and so this handle) rendered while the
				// pointer leaves the section, or the browser cancels the drag.
				onmousedown: function () {
					sectionEl.classList.add( 'kb-toolbar-pinned' );
					document.addEventListener(
						'mouseup',
						function () {
							sectionEl.classList.remove( 'kb-toolbar-pinned' );
						},
						{ once: true }
					);
				},
				ondragstart: function ( e ) {
					startDrag( e, { kind: 'section', id: section.id }, sectionEl );
				},
				ondragend: endDrag,
			},
			[ icon( 'dashicons-move' ) ]
		);

		var row = el( 'div', { class: 'kbroo-row' } );
		section.elements.forEach( function ( column ) {
			row.appendChild( renderColumn( column, section ) );
		} );

		var sectionEl = el(
			'section',
			{
				class:
					'kbroo-section kbroo-section--' + ( s.content_width === 'full' ? 'full' : 'boxed' ) +
					' kb-el kb-section' + ( isSelected( section.id ) ? ' is-selected' : '' ),
				'data-id': section.id,
				style:
					( hexOrEmpty( s.background_color ) ? 'background-color:' + s.background_color + ';' : '' ) +
					'padding-top:' + padding + ';padding-bottom:' + padding + ';' +
					'--kbroo-gap:' + ( parseInt( s.gap, 10 ) || 0 ) + 'px;',
			},
			[
				toolbar( section, [
					handle,
					section.elements.length < config.maxColumns
						? toolButton( 'dashicons-columns', 'Add column', function () {
								addColumn( section.id );
								changed();
						  } )
						: null,
					toolButton( 'dashicons-admin-page', 'Duplicate section', function () {
						duplicateElement( section.id );
						changed();
					} ),
					toolButton( 'dashicons-trash', 'Delete section', function () {
						removeElement( section.id );
						changed();
					} ),
				].filter( Boolean ) ),
				el( 'div', { class: 'kbroo-container' }, [ row ] ),
			]
		);
		return sectionEl;
	}

	function renderColumn( column, section ) {
		var width = Math.max( 5, Math.min( 100, parseInt( column.settings.width, 10 ) || 100 ) );
		var inner = el( 'div', { class: 'kbroo-column-inner kb-dropzone' } );

		column.elements.forEach( function ( widget ) {
			var w = renderWidget( widget );
			if ( w ) {
				inner.appendChild( w );
			}
		} );
		if ( ! column.elements.length ) {
			inner.appendChild( el( 'div', { class: 'kb-column-empty' }, [ icon( 'dashicons-plus' ), el( 'span', { text: 'Drag widget here' } ) ] ) );
		}

		return el(
			'div',
			{
				class: 'kbroo-column kb-el kb-column' + ( isSelected( column.id ) ? ' is-selected' : '' ),
				'data-id': column.id,
				style: '--kbroo-col-width:' + width + ';',
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
				].filter( Boolean ) ),
				inner,
			]
		);
	}

	function renderWidget( widget ) {
		var render = renderers[ widget.widgetType ];
		var definition = config.widgets[ widget.widgetType ];
		if ( ! render || ! definition ) {
			return null;
		}
		var settings = withDefaults( definition.controls, widget.settings );

		var node = el(
			'div',
			{
				class:
					'kbroo-widget kbroo-widget--' + widget.widgetType +
					' kb-el kb-widget' + ( isSelected( widget.id ) ? ' is-selected' : '' ),
				'data-id': widget.id,
				draggable: 'true',
				ondragstart: function ( e ) {
					e.stopPropagation();
					startDrag( e, { kind: 'widget', id: widget.id }, node );
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
		node.appendChild( el( 'div', { class: 'kb-widget-content', html: render( settings ) } ) );
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

		return el( 'div', { class: 'kb-add-section' }, [ toggle, picker ] );
	}

	function addWidgetByClick( type ) {
		var widget = makeWidget( type );
		var target = null;

		if ( state.selected ) {
			var found = find( state.selected.id );
			if ( found && found.node.elType === 'column' ) {
				target = found.node;
			} else if ( found && found.node.elType === 'widget' ) {
				found.list.splice( found.index + 1, 0, widget );
				select( widget.id, 'widget', true );
				changed();
				return;
			} else if ( found && found.node.elType === 'section' ) {
				target = found.node.elements[ 0 ];
			}
		}

		if ( ! target ) {
			var section = makeSection( [ 100 ] );
			state.layout.elements.push( section );
			target = section.elements[ 0 ];
		}
		target.elements.push( widget );
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

	function startDrag( e, payload, dragImage ) {
		drag = payload;
		e.dataTransfer.effectAllowed = payload.kind === 'new' ? 'copy' : 'move';
		// Firefox will not start a drag without data.
		e.dataTransfer.setData( 'text/plain', 'kbroo:' + payload.kind );
		if ( dragImage && e.dataTransfer.setDragImage ) {
			e.dataTransfer.setDragImage( dragImage, 20, 20 );
		}
		ui.canvas.classList.add( 'is-dragging-' + ( payload.kind === 'section' ? 'section' : 'widget' ) );
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
	function insertIndex( children, y ) {
		for ( var i = 0; i < children.length; i++ ) {
			var rect = children[ i ].getBoundingClientRect();
			if ( y < rect.top + rect.height / 2 ) {
				return i;
			}
		}
		return children.length;
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
		if ( ! drag ) {
			return;
		}
		e.preventDefault();
		e.dataTransfer.dropEffect = drag.kind === 'new' ? 'copy' : 'move';

		var column = drag.kind === 'section' ? null : e.target.closest( '.kb-column' );

		if ( column ) {
			var zone = column.querySelector( '.kb-dropzone' );
			var widgets = childrenOf( zone, '.kb-widget' );
			var index = insertIndex( widgets, e.clientY );
			dropTarget = { type: 'column', columnId: column.getAttribute( 'data-id' ), index: index };
			placePlaceholder( zone, widgets, index, 'widget' );
			return;
		}

		// Between sections: move a section, or wrap a widget in a new section.
		var sections = childrenOf( ui.canvas, '.kb-section' );
		var sectionIndex = insertIndex( sections, e.clientY );
		dropTarget = { type: 'section', index: sectionIndex };
		placePlaceholder( ui.canvas, sections, sectionIndex, 'section' );
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

		var node;
		if ( payload.kind === 'section' ) {
			var from = find( payload.id );
			if ( ! from ) {
				return;
			}
			node = from.list.splice( from.index, 1 )[ 0 ];
			state.layout.elements.splice( target.index, 0, node );
			changed();
			return;
		}

		if ( payload.kind === 'new' ) {
			node = makeWidget( payload.widgetType );
		} else {
			var source = find( payload.id );
			if ( ! source ) {
				return;
			}
			node = source.list.splice( source.index, 1 )[ 0 ];
		}

		if ( target.type === 'column' ) {
			var column = find( target.columnId );
			if ( ! column ) {
				return;
			}
			column.node.elements.splice( target.index, 0, node );
		} else {
			var section = makeSection( [ 100 ] );
			section.elements[ 0 ].elements.push( node );
			state.layout.elements.splice( target.index, 0, section );
		}

		select( node.id, 'widget', true );
		changed();
	}

	/* ------------------------------------------------------------------ *
	 * Keyboard
	 * ------------------------------------------------------------------ */

	function bindKeyboard() {
		document.addEventListener( 'keydown', function ( e ) {
			var typing = e.target.closest && e.target.closest( 'input, textarea, select, [contenteditable]' );

			if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' ) {
				e.preventDefault();
				save();
				return;
			}
			if ( typing ) {
				return;
			}
			if ( e.key === 'Escape' ) {
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

	function save( status ) {
		if ( state.saving ) {
			return;
		}
		state.saving = true;
		renderTopbar();

		var revision = state.revision;
		var body = { layout: state.layout };
		if ( status ) {
			body.status = status;
		}

		window
			.fetch( config.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify( body ),
			} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( ! response.ok ) {
						throw new Error( ( data && data.message ) || 'Saving failed (' + response.status + ').' );
					}
					return data;
				} );
			} )
			.then( function ( data ) {
				state.status = data.status;
				state.permalink = data.permalink;
				config.title = data.title;
				// Adopt the server's sanitized copy unless the user kept editing meanwhile.
				if ( state.revision === revision ) {
					state.layout = normalizeLayout( data.layout );
					state.dirty = false;
					if ( state.selected && ! find( state.selected.id ) ) {
						state.selected = null;
					}
					renderCanvas();
				}
				toast( status === 'publish' ? 'Published' : 'Saved' );
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
		getLayout: function () {
			return clone( state.layout );
		},
		save: save,
	};
} )();
