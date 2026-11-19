/**
 * Kbroo Studio: "Edit with Kbroo Studio" button in the block editor.
 *
 * Adds a button to the Summary panel of the document sidebar. Unsaved posts are saved first so the
 * builder opens on a post that exists.
 */
( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! data ) {
		return;
	}

	var h = wp.element.createElement;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	// PluginPostStatusInfo moved from wp.editPost to wp.editor in WordPress 6.6.
	var Slot =
		( wp.editor && wp.editor.PluginPostStatusInfo ) ||
		( wp.editPost && wp.editPost.PluginPostStatusInfo );

	if ( ! Slot ) {
		return;
	}

	function Launcher() {
		var busy = useState( false );
		var needsSave = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return editor.isEditedPostNew() || editor.isEditedPostDirty();
		}, [] );

		function open() {
			if ( ! needsSave ) {
				window.location.href = data.editorUrl;
				return;
			}
			busy[ 1 ]( true );
			Promise.resolve( wp.data.dispatch( 'core/editor' ).savePost() ).then( function () {
				window.location.href = data.editorUrl;
			} );
		}

		return h(
			Slot,
			{ className: 'kbroo-launcher' },
			h(
				'div',
				{ style: { width: '100%' } },
				data.isBuilder
					? h( Notice, { status: 'info', isDismissible: false }, data.notice )
					: null,
				h(
					Button,
					{ variant: 'primary', onClick: open, isBusy: busy[ 0 ], disabled: busy[ 0 ], style: { marginTop: 8, width: '100%', justifyContent: 'center' } },
					data.label
				)
			)
		);
	}

	wp.plugins.registerPlugin( 'kbroo-studio-launcher', { render: Launcher } );
} )( window.wp, window.kbrooLauncher );
