/**
 * Kbroo Studio: CSS generation for the editor canvas.
 *
 * JavaScript twin of src/Css.php: keep the two in step. The one difference
 * is `forDevice()`. The canvas sits in the editor page, so viewport media
 * queries cannot follow the canvas width; instead the editor previews a
 * device by applying that device's rules (and the larger screens' rules
 * under them) without media queries.
 */
( function () {
	'use strict';

	var DEVICES = [ 'desktop', 'tablet', 'mobile' ];
	var SIDES = [ 'top', 'right', 'bottom', 'left' ];

	function isNumeric( value ) {
		return value !== '' && value !== null && value !== true && value !== false && isFinite( value );
	}

	function deviceKey( key, device ) {
		return device === 'desktop' ? key : key + '_' + device;
	}

	function keys( key, control ) {
		if ( ! control.responsive ) {
			return { desktop: key };
		}
		var out = {};
		DEVICES.forEach( function ( device ) {
			out[ device ] = deviceKey( key, device );
		} );
		return out;
	}

	function units( control ) {
		return control.units && control.units.length ? control.units : [ 'px' ];
	}

	function unit( control, value ) {
		var list = units( control );
		return list.indexOf( value ) > -1 ? value : list[ 0 ];
	}

	function conditionMet( control, settings ) {
		var condition = control.condition || {};
		return Object.keys( condition ).every( function ( rawKey ) {
			var negate = rawKey.slice( -1 ) === '!';
			var key = negate ? rawKey.slice( 0, -1 ) : rawKey;
			var expected = condition[ rawKey ];
			var value = settings[ key ] == null ? '' : settings[ key ];
			var match = Array.isArray( expected ) ? expected.indexOf( value ) > -1 : value === expected;
			return match !== negate;
		} );
	}

	function replacements( control, value ) {
		switch ( control.type ) {
			case 'color':
				return /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test( value || '' ) ? { '{{VALUE}}': value } : null;

			case 'select':
				if ( typeof value !== 'string' || value === '' || ! control.options || ! ( value in control.options ) ) {
					return null;
				}
				// Dictionary values come from code (font stacks hold quotes and commas).
				if ( control.selectors_dictionary && value in control.selectors_dictionary ) {
					return { '{{VALUE}}': String( control.selectors_dictionary[ value ] ) };
				}
				return { '{{VALUE}}': value.replace( /[^A-Za-z0-9_\-.%# ]/g, '' ) };

			case 'number':
				return isNumeric( value ) ? { '{{VALUE}}': String( Number( value ) ) } : null;

			case 'switcher':
				return value === 'yes' ? { '{{VALUE}}': 'yes' } : null;

			case 'slider':
				if ( ! value || ! isNumeric( value.size ) ) {
					return null;
				}
				return { '{{SIZE}}': String( Number( value.size ) ), '{{UNIT}}': unit( control, value.unit ) };

			case 'dimensions':
				if ( ! value ) {
					return null;
				}
				var out = {};
				var set = false;
				SIDES.forEach( function ( side ) {
					var n = value[ side ];
					set = set || isNumeric( n );
					out[ '{{' + side.toUpperCase() + '}}' ] = isNumeric( n ) ? String( Number( n ) ) : '0';
				} );
				if ( ! set ) {
					return null;
				}
				out[ '{{UNIT}}' ] = unit( control, value.unit );
				return out;
		}
		return null;
	}

	function globalId( settings, key ) {
		var ref = settings.__globals__ && settings.__globals__[ key ];
		var match = typeof ref === 'string' && ref.match( /\?id=([a-z0-9_-]{1,40})$/ );
		return match ? match[ 1 ] : '';
	}

	function variable( kind, id, property ) {
		return kind === 'colors' ? '--kbroo-global-color-' + id : '--kbroo-global-typography-' + id + '-' + property;
	}

	// The editor sets these so global links can be checked as in PHP.
	var currentGlobals = null;
	var currentTypographyControls = {};

	function setGlobals( globals, typographyControls ) {
		currentGlobals = globals;
		currentTypographyControls = typographyControls || {};
	}

	/** Twin of Globals::defines(). */
	function defines( kind, id, property ) {
		var item = currentGlobals && ( currentGlobals[ kind ] || [] ).filter( function ( g ) {
			return g._id === id;
		} )[ 0 ];
		if ( ! item ) {
			return false;
		}
		if ( kind === 'colors' ) {
			return !! item.color;
		}
		return Object.keys( currentTypographyControls ).some( function ( key ) {
			var control = currentTypographyControls[ key ];
			if ( control.global_property !== property ) {
				return false;
			}
			var deviceKeys = keys( key, control );
			return Object.keys( deviceKeys ).some( function ( device ) {
				return !! replacements( control, item[ deviceKeys[ device ] ] );
			} );
		} );
	}

	/** Twin of Css::global_replacements(). */
	function globalReplacements( control, settings, settingKey, device ) {
		var id;
		if ( control.type === 'color' ) {
			id = globalId( settings, settingKey );
			return id && defines( 'colors', id ) ? { '{{VALUE}}': 'var(' + variable( 'colors', id ) + ')' } : null;
		}
		if ( ! control.global_group || ! control.global_property || device !== 'desktop' ) {
			return null;
		}
		id = globalId( settings, control.global_group );
		if ( ! id || replacements( control, settings[ settingKey ] ) || ! defines( 'typography', id, control.global_property ) ) {
			return null;
		}
		var v = 'var(' + variable( 'typography', id, control.global_property ) + ')';
		return { '{{VALUE}}': v, '{{SIZE}}': v, '{{UNIT}}': '' };
	}

	/** Twin of Globals::css(), as rules by device. */
	function globalsRules( globals, typographyControls ) {
		var rules = { desktop: [], tablet: [], mobile: [] };
		( globals.colors || [] ).forEach( function ( c ) {
			if ( /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test( c.color || '' ) ) {
				rules.desktop.push( ':root{' + variable( 'colors', c._id ) + ':' + c.color + ';}' );
			}
		} );
		( globals.typography || [] ).forEach( function ( preset ) {
			var controls = {};
			Object.keys( typographyControls ).forEach( function ( key ) {
				var control = Object.assign( {}, typographyControls[ key ] );
				var value = control.type === 'slider' ? '{{SIZE}}{{UNIT}}' : '{{VALUE}}';
				control.selectors = { ':root': variable( 'typography', preset._id, control.global_property ) + ': ' + value + ';' };
				controls[ key ] = control;
			} );
			var r = elementRules( controls, preset, ':root' );
			DEVICES.forEach( function ( d ) {
				rules[ d ] = rules[ d ].concat( r[ d ] );
			} );
		} );
		return rules;
	}

	/** Twin of Fonts::google_url(). */
	function googleUrl( names, fonts ) {
		var families = [];
		names.forEach( function ( name ) {
			var font = fonts[ name ];
			var family = font && font.google ? 'family=' + name.replace( / /g, '+' ) + ':wght@' + font.google : null;
			if ( family && families.indexOf( family ) === -1 ) {
				families.push( family );
			}
		} );
		families.sort();
		return families.length ? 'https://fonts.googleapis.com/css2?' + families.join( '&' ) + '&display=swap' : '';
	}

	function fill( template, map ) {
		return template.replace( /\{\{[A-Z]+\}\}/g, function ( token ) {
			return token in map ? map[ token ] : token;
		} );
	}

	/**
	 * Rules for one element, grouped by device.
	 */
	function elementRules( controls, settings, wrapper ) {
		var rules = { desktop: [], tablet: [], mobile: [] };
		Object.keys( controls ).forEach( function ( key ) {
			var control = controls[ key ];
			if ( ! control.selectors || ! conditionMet( control, settings ) ) {
				return;
			}
			var deviceKeys = keys( key, control );
			Object.keys( deviceKeys ).forEach( function ( device ) {
				var map =
					globalReplacements( control, settings, deviceKeys[ device ], device ) ||
					replacements( control, settings[ deviceKeys[ device ] ] );
				if ( ! map ) {
					return;
				}
				Object.keys( control.selectors ).forEach( function ( selector ) {
					rules[ device ].push(
						selector.split( '{{WRAPPER}}' ).join( wrapper ) +
							'{' + fill( control.selectors[ selector ], Object.assign( { '{{DEVICE}}': device }, map ) ) + '}'
					);
				} );
			} );
		} );
		return rules;
	}

	/**
	 * Front-end stylesheet: tablet and mobile rules in media queries.
	 */
	function stylesheet( rules, breakpoints ) {
		var css = rules.desktop.join( '' );
		[ 'tablet', 'mobile' ].forEach( function ( device ) {
			if ( rules[ device ].length ) {
				css += '@media (max-width:' + breakpoints[ device ] + 'px){' + rules[ device ].join( '' ) + '}';
			}
		} );
		return css;
	}

	/**
	 * Editor stylesheet previewing one device without media queries.
	 */
	function forDevice( rules, device ) {
		var css = rules.desktop.join( '' );
		if ( device === 'tablet' || device === 'mobile' ) {
			css += rules.tablet.join( '' );
		}
		if ( device === 'mobile' ) {
			css += rules.mobile.join( '' );
		}
		return css;
	}

	window.KbrooStudio = window.KbrooStudio || {};
	window.KbrooStudio.css = {
		DEVICES: DEVICES,
		deviceKey: deviceKey,
		keys: keys,
		units: units,
		conditionMet: conditionMet,
		elementRules: elementRules,
		stylesheet: stylesheet,
		forDevice: forDevice,
		globalId: globalId,
		setGlobals: setGlobals,
		globalsRules: globalsRules,
		googleUrl: googleUrl,
		wrapper: function ( id ) {
			return '.kbroo-el-' + id;
		},
	};
} )();
