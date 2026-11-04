// Copies the version of .release-please-manifest.json into the plugin header,
// the PATHLY_VERSION constant, the readme Stable tag, the block files and the
// readme changelog. With --check, only fails when a file is out of date.
//
// Usage: node bin/sync-version.js [--check]
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.join( __dirname, '..' );
const check = process.argv.includes( '--check' );
const version = JSON.parse( fs.readFileSync( path.join( root, '.release-please-manifest.json' ), 'utf8' ) )[ '.' ];
const stale = [];

function update( file, edit ) {
	const full = path.join( root, file );
	const before = fs.readFileSync( full, 'utf8' );
	const after = edit( before );
	if ( after !== before ) {
		stale.push( file );
		if ( ! check ) {
			fs.writeFileSync( full, after );
		}
	}
}

update( 'pathly.php', ( s ) =>
	s
		.replace( /^( \* Version:\s+).+$/m, `$1${ version }` )
		.replace( /define\( 'PATHLY_VERSION', '[^']+' \);/, `define( 'PATHLY_VERSION', '${ version }' );` )
);
update( 'blocks/badge/block.json', ( s ) => s.replace( /"version": "[^"]+"/, `"version": "${ version }"` ) );
update( 'blocks/badge/index.asset.php', ( s ) => s.replace( /'version'(\s+)=> '[^']+'/, `'version'$1=> '${ version }'` ) );
update( 'readme.txt', ( s ) => {
	let out = s.replace( /^Stable tag: .+$/m, `Stable tag: ${ version }` );
	if ( ! out.includes( `= ${ version } =` ) ) {
		// The readme is read by WordPress.org users: its changelog stays in
		// English. Commit messages (French) are never copied into it. Notes
		// are written by hand under "= Unreleased =", renamed at release time.
		if ( out.includes( '\n= Unreleased =\n' ) ) {
			out = out.replace( '\n= Unreleased =\n', `\n= ${ version } =\n` );
		} else {
			out = out.replace( '== Changelog ==\n\n', `== Changelog ==\n\n= ${ version } =\n* Maintenance release.\n\n` );
		}
	}
	return out;
} );

if ( check && stale.length ) {
	console.error( `Version ${ version } not applied to: ${ stale.join( ', ' ) }. Run node bin/sync-version.js.` );
	process.exit( 1 );
}
console.log( check ? `Version ${ version }: up to date.` : `Version ${ version }: ${ stale.join( ', ' ) || 'nothing to change' }.` );
