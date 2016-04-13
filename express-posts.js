jQuery( document ).ready( function ( $ ) {
	$( '.express-posts-relationship' ).on( 'change', function () {
		var r = $( this ).val();
		$( this ).parents( 'form' ).find( 'fieldset' ).hide();
		$( this ).parents( 'form' ).find( 'fieldset.' + r ).show();
	} );
} );