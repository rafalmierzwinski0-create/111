( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var components = wp.components;

	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;
	var Spinner = components.Spinner;
	var ExternalLink = components.ExternalLink;

	var LAYOUTS = [
		{ label: __( 'As set for the table', 'tabliko' ), value: 'inherit' },
		{ label: __( 'Table with a slider', 'tabliko' ), value: 'table' },
		{ label: __( 'Cards when it stops fitting', 'tabliko' ), value: 'auto' },
		{ label: __( 'Always cards', 'tabliko' ), value: 'cards' }
	];

	var PRESETS = [
		{ label: __( 'As set for the table', 'tabliko' ), value: '' }
	].concat( lstabBlock.presets || [] );

	function useSources() {
		var sourcesState = useState( null );
		var sources = sourcesState[ 0 ];
		var setSources = sourcesState[ 1 ];

		useEffect( function () {
			var cancelled = false;

			wp.apiFetch( { path: '/tabliko/v1/sources' } )
				.then( function ( response ) {
					if ( ! cancelled ) {
						setSources( response || [] );
					}
				} )
				.catch( function () {
					if ( ! cancelled ) {
						setSources( [] );
					}
				} );

			return function () {
				cancelled = true;
			};
		}, [] );

		return { sources: sources, loading: null === sources };
	}

	var blockIcon = el(
		'svg',
		{ viewBox: '0 0 32 32', width: 24, height: 24, xmlns: 'http://www.w3.org/2000/svg' },
		el( 'rect', { x: 3, y: 4, width: 21, height: 21, rx: 5, fill: 'none', stroke: 'currentColor', strokeWidth: 2.2 } ),
		el( 'path', { d: 'M3.6 11.4h19.8M12 11.4v13.2', fill: 'none', stroke: 'currentColor', strokeWidth: 2.2, strokeLinecap: 'round' } ),
		el( 'rect', { x: 23, y: 23, width: 7.4, height: 7.4, rx: 2.2, fill: 'currentColor' } )
	);

	registerBlockType( 'tabliko/sheet-table', {
		icon: blockIcon,
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var loaded = useSources();

			var options = [ { label: __( 'Choose a table…', 'tabliko' ), value: 0 } ];

			( loaded.sources || [] ).forEach( function ( source ) {
				options.push( {
					label: source.title + ' (' + source.rowCount + ' ' + __( 'rows', 'tabliko' ) + ')',
					value: source.id
				} );
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Table', 'tabliko' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Table to show', 'tabliko' ),
						value: attributes.sourceId,
						options: options,
						onChange: function ( value ) {
							setAttributes( { sourceId: parseInt( value, 10 ) || 0 } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el(
						'p',
						{ className: 'lstab-block-hint' },
						el(
							ExternalLink,
							{ href: lstabBlock.manageUrl },
							__( 'Manage tables', 'tabliko' )
						)
					)
				),
				el(
					PanelBody,
					{ title: __( 'What visitors see', 'tabliko' ), initialOpen: true },
					el(
						'p',
						{ className: 'lstab-block-hint' },
						__( 'The search box, sorting and the “updated … ago” line are chosen once, with the table, and show the same wherever it is used.', 'tabliko' ),
						attributes.sourceId ? ' ' : null,
						attributes.sourceId ? el(
							ExternalLink,
							{ href: lstabBlock.editUrl + attributes.sourceId },
							__( 'Change them', 'tabliko' )
						) : null
					),
					el( SelectControl, {
						label: __( 'Layout', 'tabliko' ),
						value: attributes.layout,
						options: LAYOUTS,
						help: __( 'What a table wider than the screen does. “Table with a slider” keeps the columns and lets visitors scroll sideways. The card layouts turn each row into a card instead, which suits long text.', 'tabliko' ),
						onChange: function ( value ) {
							setAttributes( { layout: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( SelectControl, {
						label: __( 'Table style', 'tabliko' ),
						value: attributes.stylePreset,
						options: PRESETS,
						onChange: function ( value ) {
							setAttributes( { stylePreset: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( TextControl, {
						label: __( 'Caption', 'tabliko' ),
						value: attributes.caption,
						onChange: function ( value ) {
							setAttributes( { caption: value } );
						},
						__nextHasNoMarginBottom: true
					} )
				),
				lstabBlock.canFilter ? el(
					PanelBody,
					{ title: __( 'Which rows', 'tabliko' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Filter', 'tabliko' ),
						help: __( 'Show only the rows that match, for example: Category is Bikes. To use several conditions, join them with “and” or a comma; a row must match all of them. The words you can use are listed on the Tabliko → Pro screen.', 'tabliko' ),
						value: attributes.filter,
						onChange: function ( value ) {
							setAttributes( { filter: value } );
						},
						__nextHasNoMarginBottom: true
					} )
				) : null
			);

			var body;

			if ( loaded.loading ) {
				body = el( Placeholder, { label: __( 'Tabliko Table', 'tabliko' ) }, el( Spinner ) );
			} else if ( ! loaded.sources.length ) {
				body = el(
					Placeholder,
					{
						icon: blockIcon,
						label: __( 'Tabliko Table', 'tabliko' ),
						instructions: __( 'There are no tables yet. Add one under Tabliko in the dashboard, then choose it here.', 'tabliko' )
					},
					el( ExternalLink, { href: lstabBlock.addUrl }, __( 'Add a table', 'tabliko' ) )
				);
			} else if ( ! attributes.sourceId ) {
				body = el(
					Placeholder,
					{
						icon: blockIcon,
						label: __( 'Tabliko Table', 'tabliko' ),
						instructions: __( 'Choose which table to show.', 'tabliko' )
					},
					el( SelectControl, {
						value: attributes.sourceId,
						options: options,
						onChange: function ( value ) {
							setAttributes( { sourceId: parseInt( value, 10 ) || 0 } );
						},
						__nextHasNoMarginBottom: true
					} )
				);
			} else {
				body = el( ServerSideRender, {
					block: 'tabliko/sheet-table',
					attributes: attributes
				} );
			}

			return el( 'div', blockProps, inspector, body );
		},

		save: function () {
			return null;
		}
	} );
}( window.wp ) );
