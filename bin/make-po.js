// Builds languages/pathly-<locale>.po from languages/pathly.pot and bin/i18n/<locale>.json.
// Usage: node bin/make-po.js fr_FR
const fs = require( 'fs' );
const path = require( 'path' );

const locale = process.argv[ 2 ];
const dir = path.join( __dirname, '..', 'languages' );
const dict = JSON.parse( fs.readFileSync( path.join( __dirname, 'i18n', `${ locale }.json` ), 'utf8' ) );
const pot = fs.readFileSync( path.join( dir, 'pathly.pot' ), 'utf8' );
const quote = ( s ) => JSON.stringify( s );
const missing = [];

const blocks = pot.split( /\n\n/ ).map( ( block ) => {
	const id = block.match( /^msgid (".*")$/m );
	if ( ! id ) {
		return block;
	}
	const msgid = JSON.parse( id[ 1 ] );
	if ( msgid === '' ) {
		return block
			.replace( /"Language: [^"]*"\n/, '' )
			.replace( /"Plural-Forms: [^"]*"\n/, '' )
			.replace(
				/("Content-Transfer-Encoding: [^"]*")/,
				`$1\n"Language: ${ locale }\\n"\n"Plural-Forms: nplurals=2; plural=(n > 1);\\n"`
			);
	}
	const value = dict[ msgid ];
	if ( value === undefined ) {
		missing.push( msgid );
	}
	if ( /^msgid_plural/m.test( block ) ) {
		const [ one, many ] = Array.isArray( value ) ? value : [ '', '' ];
		return block.replace( /msgstr\[0\] ""\nmsgstr\[1\] ""/, `msgstr[0] ${ quote( one ) }\nmsgstr[1] ${ quote( many ) }` );
	}
	return block.replace( /msgstr ""$/m, `msgstr ${ quote( value || '' ) }` );
} );

fs.writeFileSync( path.join( dir, `pathly-${ locale }.po` ), blocks.join( '\n\n' ) );
if ( missing.length ) {
	console.error( `Missing ${ locale } translations:\n` + missing.join( '\n' ) );
	process.exit( 1 );
}
