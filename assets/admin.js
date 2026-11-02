/**
 * Settings, Pathly: reloads the page while Lumi explores the site.
 */
( function () {
	var pending = document.querySelector( '[data-pathly-refresh]' );
	if ( ! pending ) {
		return;
	}
	var seconds = parseInt( pending.getAttribute( 'data-pathly-refresh' ), 10 ) || 10;
	window.setTimeout( function () {
		window.location.reload();
	}, seconds * 1000 );
} )();
