( function () {
	function init() {
		var form = document.getElementById( 'post' );
		if ( ! form ) {
			return;
		}
		var dirty = false;
		form.addEventListener( 'input', function () {
			dirty = true;
		} );
		form.addEventListener( 'change', function () {
			dirty = true;
		} );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! dirty ) {
				return;
			}
			event.preventDefault();
			event.returnValue = '';
		} );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
