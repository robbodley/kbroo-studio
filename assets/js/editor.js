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
	};

	var history = { stack: [], index: -1, mergeKey: null, mergeTime: 0, savedIndex: 0 };

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
		var previousKind = state.selected && state.selected.kind;
		state.selected = id ? { id: id, kind: kind } : null;
		if ( kind !== previousKind ) {
			state.tab = 'content';
		}
		if ( ! silent ) {
			renderCanvas();
			renderPanel();
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
		ui.canvasWrap = el( 'main', { class: 'kb-canvas-wrap' }, [ ui.canvas ] );
		ui.toast = el( 'div', { class: 'kb-toast', role: 'status', 'aria-live': 'polite' } );

		document.head.appendChild( ui.styles );
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

		resetHistory();
		renderAll();
	}

	function renderAll() {
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
				el( 'a', { class: 'kb-icon-btn', href: config.wpEditUrl, title: 'Back to the WordPress editor' }, [
					icon( 'dashicons-arrow-left-alt2' ),
					el( 'span', { class: 'kb-hide-sm', text: 'WordPress editor' } ),
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
		var scroll = ui.panel.querySelector( '.kb-panel-body' );
		var scrollTop = scroll ? scroll.scrollTop : 0;

		ui.panel.innerHTML = '';
		var sel = state.selected && find( state.selected.id );
		if ( sel ) {
			renderSettingsPanel( sel.node );
		} else {
			state.selected = null;
			renderWidgetsPanel();
		}

		var body = ui.panel.querySelector( '.kb-panel-body' );
		if ( body ) {
			body.scrollTop = scrollTop;
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

		var tabsPresent = Object.keys( TABS ).filter( function ( tab ) {
			return Object.keys( controls ).some( function ( key ) {
				return controls[ key ].tab === tab;
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
		var currentSection = null;
		Object.keys( controls ).forEach( function ( key ) {
			var control = controls[ key ];
			if ( control.tab !== state.tab || ! Css.conditionMet( control, node.settings ) ) {
				return;
			}
			if ( control.section && control.section !== currentSection ) {
				currentSection = control.section;
				body.appendChild( el( 'h3', { class: 'kb-control-section', text: control.section } ) );
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

	function renderControl( node, key, control ) {
		var device = control.responsive ? state.device : 'desktop';
		var settingKey = Css.deviceKey( key, device );
		var id = 'kb-control-' + settingKey;
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
				input = el( 'textarea', { id: id, rows: 6 } );
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
				var picker = el( 'input', { type: 'color', id: id } );
				picker.value = /^#[0-9a-f]{6}$/i.test( value || '' ) ? value : '#000000';
				var clear = el( 'button', { type: 'button', class: 'kb-link', text: value ? 'Clear' : 'Default' } );
				picker.addEventListener( 'input', function () {
					clear.textContent = 'Clear';
					update( picker.value, true );
				} );
				clear.addEventListener( 'click', function () {
					update( '', false );
				} );
				input = el( 'div', { class: 'kb-color' }, [ picker, clear ] );
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

		return el( 'div', { class: 'kb-control kb-control-' + control.type, 'data-control': key }, [ label, input ] );
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
	 * Classes shared with the front end (src/Renderer.php), plus editor state.
	 */
	function elementClasses( node, settings, base, prefix ) {
		var classes = base.concat( [ 'kbroo-el', 'kbroo-el-' + node.id, 'kb-el', 'kb-' + node.elType ] );
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
		ui.styles.textContent = Css.forDevice( cssRules, state.device );
		ui.canvasWrap.scrollTop = scroll;
	}

	function renderSection( section ) {
		var s = withDefaults( config.sectionControls, section.settings );
		collectCss( section, config.sectionControls, s );

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

		var tag = config.sectionControls.html_tag.options[ s.html_tag ] ? s.html_tag : 'section';
		var sectionEl = el(
			tag,
			{
				class: elementClasses( section, s, [ 'kbroo-section', 'kbroo-section--' + ( s.layout === 'full_width' ? 'full' : 'boxed' ) ], '' ),
				'data-id': section.id,
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
				] ),
				el( 'div', { class: 'kbroo-container' }, [ row ] ),
			]
		);
		return sectionEl;
	}

	function renderColumn( column, section ) {
		var s = withDefaults( config.columnControls, column.settings );
		collectCss( column, config.columnControls, s );

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

	function renderWidget( widget ) {
		var render = renderers[ widget.widgetType ];
		var definition = config.widgets[ widget.widgetType ];
		if ( ! render || ! definition ) {
			return null;
		}
		var s = withDefaults( definition.controls, widget.settings );
		collectCss( widget, definition.controls, s );

		var node = el(
			'div',
			{
				class: elementClasses( widget, s, [ 'kbroo-widget', 'kbroo-widget--' + widget.widgetType ], '_' ),
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
		// Insert the preview markup directly so selectors such as
		// "{{WRAPPER}} > .kbroo-heading" match the same tree as the front end.
		var holder = document.createElement( 'div' );
		holder.innerHTML = render( s );
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
		getCss: function () {
			return ui.styles.textContent;
		},
		save: save,
		undo: undo,
		redo: redo,
	};
} )();
