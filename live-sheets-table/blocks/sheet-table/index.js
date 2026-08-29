/**
 * Live Sheets Table — block editor script.
 *
 * Plain ES5 against the wp.* globals, so the plugin ships without a build step
 * and the file in the repository is the file that runs.
 */
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
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;
	var Spinner = components.Spinner;
	var ExternalLink = components.ExternalLink;

	var LAYOUTS = [
		{ label: __( 'Use the source default', 'live-sheets-table' ), value: 'inherit' },
		{ label: __( 'Table with a slider', 'live-sheets-table' ), value: 'table' },
		{ label: __( 'Stack into cards when narrow', 'live-sheets-table' ), value: 'auto' },
		{ label: __( 'Always cards', 'live-sheets-table' ), value: 'cards' }
	];

	var PRESETS = [
		{ label: __( 'Use the source default', 'live-sheets-table' ), value: '' },
		{ label: __( 'Clean', 'live-sheets-table' ), value: 'clean' },
		{ label: __( 'Striped', 'live-sheets-table' ), value: 'striped' },
		{ label: __( 'Bordered', 'live-sheets-table' ), value: 'bordered' }
	];

	/**
	 * Load the saved sources once per editor session.
	 *
	 * @return {Object} { sources, loading }
	 */
	function useSources() {
		var sourcesState = useState( null );
		var sources = sourcesState[ 0 ];
		var setSources = sourcesState[ 1 ];

		useEffect( function () {
			var cancelled = false;

			wp.apiFetch( { path: '/live-sheets-table/v1/sources' } )
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

	registerBlockType( 'live-sheets-table/sheet-table', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var loaded = useSources();

			var options = [ { label: __( 'Select a sheet…', 'live-sheets-table' ), value: 0 } ];

			( loaded.sources || [] ).forEach( function ( source ) {
				options.push( {
					label: source.title + ' (' + source.rowCount + ' ' + __( 'rows', 'live-sheets-table' ) + ')',
					value: source.id
				} );
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Sheet source', 'live-sheets-table' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Saved source', 'live-sheets-table' ),
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
							__( 'Manage sheet sources', 'live-sheets-table' )
						)
					)
				),
				el(
					PanelBody,
					{ title: __( 'Table options', 'live-sheets-table' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Search box', 'live-sheets-table' ),
						checked: !! attributes.showSearch,
						onChange: function ( value ) {
							setAttributes( { showSearch: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( ToggleControl, {
						label: __( 'Sortable columns', 'live-sheets-table' ),
						checked: !! attributes.showSort,
						onChange: function ( value ) {
							setAttributes( { showSort: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( ToggleControl, {
						label: __( 'Show “updated … ago”', 'live-sheets-table' ),
						checked: !! attributes.showUpdated,
						onChange: function ( value ) {
							setAttributes( { showUpdated: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( SelectControl, {
						label: __( 'Layout', 'live-sheets-table' ),
						value: attributes.layout,
						options: LAYOUTS,
						help: __( 'A wide table keeps its shape and gains a draggable slider. Card layouts stack each row instead, which suits tables of long text.', 'live-sheets-table' ),
						onChange: function ( value ) {
							setAttributes( { layout: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( SelectControl, {
						label: __( 'Style preset', 'live-sheets-table' ),
						value: attributes.stylePreset,
						options: PRESETS,
						onChange: function ( value ) {
							setAttributes( { stylePreset: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( TextControl, {
						label: __( 'Caption', 'live-sheets-table' ),
						value: attributes.caption,
						onChange: function ( value ) {
							setAttributes( { caption: value } );
						},
						__nextHasNoMarginBottom: true
					} )
				),
				// Only where an add-on is listening for it. On its own the
				// field would accept text and change nothing.
				lstabBlock.isPro ? el(
					PanelBody,
					{ title: __( 'Which rows', 'live-sheets-table' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Filter', 'live-sheets-table' ),
						help: __( 'Show only matching rows, for example: Kategoria is Rowery. Join conditions with “and”. Operators: is, is not, has, gt, gte, lt, lte.', 'live-sheets-table' ),
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
				body = el( Placeholder, { label: __( 'Google Sheets Table', 'live-sheets-table' ) }, el( Spinner ) );
			} else if ( ! loaded.sources.length ) {
				body = el(
					Placeholder,
					{
						icon: 'editor-table',
						label: __( 'Google Sheets Table', 'live-sheets-table' ),
						instructions: __( 'No sheet sources yet. Add one in the dashboard, then pick it here.', 'live-sheets-table' )
					},
					el( ExternalLink, { href: lstabBlock.addUrl }, __( 'Add a sheet source', 'live-sheets-table' ) )
				);
			} else if ( ! attributes.sourceId ) {
				body = el(
					Placeholder,
					{
						icon: 'editor-table',
						label: __( 'Google Sheets Table', 'live-sheets-table' ),
						instructions: __( 'Choose which saved sheet to show.', 'live-sheets-table' )
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
					block: 'live-sheets-table/sheet-table',
					attributes: attributes
				} );
			}

			return el( 'div', blockProps, inspector, body );
		},

		save: function () {
			// Dynamic block: the server renders it, so nothing is stored in post content.
			return null;
		}
	} );
}( window.wp ) );
