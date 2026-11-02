/**
 * Pathly badge block: rendered by PHP, the same markup as [pathly_badge].
 */
( function ( blocks, element, ServerSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'pathly/badge', {
		edit: function () {
			return el( ServerSideRender, {
				block: 'pathly/badge',
				EmptyResponsePlaceholder: function () {
					return el(
						'p',
						{ className: 'pathly-badge-placeholder' },
						__( 'The Pathly badge is off. Turn it on in Settings, Pathly.', 'pathly' )
					);
				},
			} );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n );
