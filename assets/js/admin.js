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

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var $temp = $( '<textarea>' ).val( text ).css( {
				position: 'fixed',
				top: '-1000px',
				opacity: '0'
			} ).appendTo( 'body' );

			$temp[ 0 ].select();

			try {
				var ok = document.execCommand( 'copy' );
				$temp.remove();
				ok ? resolve() : reject( new Error( 'copy failed' ) );
			} catch ( error ) {
				$temp.remove();
				reject( error );
			}
		} );
	}

	$( document ).on( 'click', '.nlwp-copy', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var target = $button.data( 'target' );
		var $field = target ? $( target ) : $button.closest( '.nlwp-email-preview' ).find( '.nlwp-preview-text' );

		if ( ! $field.length ) {
			return;
		}

		var original = $button.text();

		copyText( $field.val() ).then( function () {
			$button.text( strings.copied || 'Copied' );
			window.setTimeout( function () {
				$button.text( original );
			}, 1500 );
		} ).catch( function () {
			window.alert( strings.copyFailed || 'Copy failed.' );
		} );
	} );

	$( document ).on( 'click', '.nlwp-download', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var target = $button.data( 'target' );
		var $field = target ? $( target ) : $button.closest( '.nlwp-email-preview' ).find( '.nlwp-preview-text' );

		if ( ! $field.length ) {
			return;
		}

		try {
			var blob = new Blob( [ $field.val() ], {
				type: $button.data( 'mime' ) || 'text/plain;charset=utf-8'
			} );
			var url = URL.createObjectURL( blob );
			var $link = $( '<a>' ).attr( {
				href: url,
				download: $button.data( 'filename' ) || 'newsletter.txt'
			} ).appendTo( 'body' );

			$link[ 0 ].click();
			$link.remove();
			URL.revokeObjectURL( url );
		} catch ( error ) {
			window.alert( strings.downloadFailed || 'Download failed.' );
		}
	} );

	$( document ).on( 'click', '.nlwp-send-email', function ( event ) {
		if ( ! window.confirm( strings.confirmSend || strings.confirm || 'Send this newsletter?' ) ) {
			event.preventDefault();
		}
	} );
}( jQuery ) );
