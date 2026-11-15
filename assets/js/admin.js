( function ( $ ) {
	'use strict';

	var strings = window.nlwpAdmin || {};

	function emptyPreview() {
		return $( '<span class="nlwp-media-empty">' ).text( strings.noFile || 'No file selected' );
	}

	$( document ).on( 'click', '.nlwp-media-select', function ( event ) {
		event.preventDefault();

		if ( ! window.wp || ! wp.media ) {
			return;
		}

		var $field = $( this ).closest( '.nlwp-media-field' );
		var frame = wp.media( {
			title: strings.selectFile || 'Select file',
			button: { text: strings.useFile || 'Use this file' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var previewUrl = attachment.url;

			if ( attachment.sizes && attachment.sizes.thumbnail ) {
				previewUrl = attachment.sizes.thumbnail.url;
			}

			$field.find( '.nlwp-media-id' ).val( attachment.id ).trigger( 'change' );

			var $preview = $field.find( '.nlwp-media-preview' ).empty();

			if ( 'image' === attachment.type ) {
				$( '<img class="nlwp-media-thumb" alt="">' )
					.attr( 'src', previewUrl )
					.attr( 'alt', attachment.alt || attachment.title || '' )
					.appendTo( $preview );
			} else {
				$( '<span class="nlwp-media-filename">' )
					.text( attachment.filename || attachment.url )
					.appendTo( $preview );
			}
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.nlwp-media-remove', function ( event ) {
		event.preventDefault();

		var $field = $( this ).closest( '.nlwp-media-field' );
		$field.find( '.nlwp-media-id' ).val( '' ).trigger( 'change' );
		$field.find( '.nlwp-media-preview' ).empty().append( emptyPreview() );
	} );

	function toggleManualEvents() {
		var value = $( 'input[name="nlwp_selection_mode"]:checked' ).val();
		$( '.nlwp-manual-events' ).toggleClass( 'is-disabled', 'manual' !== value );
	}

	$( toggleManualEvents );
	$( document ).on( 'change', 'input[name="nlwp_selection_mode"]', toggleManualEvents );
}( jQuery ) );
