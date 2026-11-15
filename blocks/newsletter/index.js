( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;
	var data = window.nlwpBlock || { issues: [], languages: [], hasIssues: false, defaults: {} };
	var defaults = data.defaults || {};

	var issueOptions = [ { label: __( 'Latest issue', 'newsletter-wp-plugin' ), value: '0' } ].concat( data.issues || [] );
	var languageOptions = data.languages || [ { label: __( 'Site language', 'newsletter-wp-plugin' ), value: '' } ];
	var modeOptions = [
		{ label: __( 'Use plugin settings', 'newsletter-wp-plugin' ), value: '' },
		{ label: __( 'Site language', 'newsletter-wp-plugin' ), value: 'site' },
		{ label: __( 'All languages, back to back', 'newsletter-wp-plugin' ), value: 'stacked' }
	];

	registerBlockType( 'newsletter-wp-plugin/newsletter', {
		apiVersion: 3,
		title: __( 'Newsletter', 'newsletter-wp-plugin' ),
		description: __( 'Display a published newsletter issue with categories, events and multilingual content.', 'newsletter-wp-plugin' ),
		category: 'widgets',
		icon: 'email-alt',
		keywords: [
			__( 'newsletter', 'newsletter-wp-plugin' ),
			__( 'issues', 'newsletter-wp-plugin' ),
			__( 'events', 'newsletter-wp-plugin' )
		],
		supports: {
			html: false,
			align: [ 'wide', 'full' ]
		},
		attributes: {
			issueId: { type: 'number', default: 0 },
			language: { type: 'string', default: '' },
			displayMode: { type: 'string', default: '' },
			showTitle: { type: 'boolean' },
			showIntro: { type: 'boolean' }
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Newsletter', 'newsletter-wp-plugin' ),
							initialOpen: true
						},
						el( SelectControl, {
							label: __( 'Issue', 'newsletter-wp-plugin' ),
							value: String( attributes.issueId || 0 ),
							options: issueOptions,
							onChange: function ( value ) {
								props.setAttributes( { issueId: parseInt( value, 10 ) || 0 } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Language', 'newsletter-wp-plugin' ),
							value: attributes.language || '',
							options: languageOptions,
							onChange: function ( value ) {
								props.setAttributes( { language: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Display mode', 'newsletter-wp-plugin' ),
							value: attributes.displayMode || '',
							options: modeOptions,
							onChange: function ( value ) {
								props.setAttributes( { displayMode: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show title', 'newsletter-wp-plugin' ),
							help: __( 'Follows the plugin setting until changed.', 'newsletter-wp-plugin' ),
							checked: attributes.showTitle !== undefined ? !! attributes.showTitle : !! defaults.showTitle,
							onChange: function ( value ) {
								props.setAttributes( { showTitle: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show introduction', 'newsletter-wp-plugin' ),
							help: __( 'Follows the plugin setting until changed.', 'newsletter-wp-plugin' ),
							checked: attributes.showIntro !== undefined ? !! attributes.showIntro : !! defaults.showIntro,
							onChange: function ( value ) {
								props.setAttributes( { showIntro: value } );
							}
						} )
					)
				),
				el(
					'div',
					blockProps,
					data.hasIssues
						? el( ServerSideRender, {
							block: 'newsletter-wp-plugin/newsletter',
							attributes: attributes
						} )
						: el(
							'p',
							{ className: 'nlwp-block-empty' },
							__( 'Create a newsletter issue to use this block.', 'newsletter-wp-plugin' )
						)
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
