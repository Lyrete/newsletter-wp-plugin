( function () {
	'use strict';

	function initExclusive( container ) {
		var events = Array.prototype.slice.call( container.querySelectorAll( 'details.nlwp-event' ) );

		events.forEach( function ( details ) {
			details.addEventListener( 'toggle', function () {
				if ( ! details.open ) {
					return;
				}

				events.forEach( function ( other ) {
					if ( other !== details && other.open ) {
						other.open = false;
					}
				} );
			} );
		} );

		var open = events.filter( function ( details ) {
			return details.open;
		} );
		open.slice( 1 ).forEach( function ( details ) {
			details.open = false;
		} );
	}

	function init() {
		document.querySelectorAll( '.nlwp-exclusive' ).forEach( initExclusive );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
