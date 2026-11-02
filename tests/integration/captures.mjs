// Screenshots for WordPress.org (.wordpress-org/screenshot-N.png, English) and for
// the French listing (publication/captures/fr/), taken on the wp-env WordPress with
// the simulated Pathly API. Also renders the icon and the banners.
//
// Usage: npx wp-env start && node tests/integration/captures.mjs
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const base = `http://localhost:${ process.env.WP_ENV_PORT || 8890 }`;
const assets = join( root, '.wordpress-org' );
const fr = join( root, 'publication', 'captures', 'fr' );
mkdirSync( assets, { recursive: true } );
mkdirSync( fr, { recursive: true } );

const win = process.platform === 'win32';
const wp = ( ...args ) =>
	execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ].map( ( a ) => ( win ? `"${ a }"` : a ) ), {
		cwd: root,
		shell: win,
		env: { ...process.env, MSYS_NO_PATHCONV: '1' },
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).toString();

// Same markup as the Pathly badge (packages/shared/src/statusBadge.ts), state "ok".
const badge = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="98" height="20" role="img" aria-label="Pathly: ok">
  <title>Pathly: ok</title>
  <rect width="58" height="20" fill="#555"/>
  <rect x="58" width="40" height="20" fill="#2e7d32"/>
  <rect width="98" height="20" fill="url(#g)"/>
  <linearGradient id="g" x2="0" y2="100%"><stop offset="0" stop-color="#fff" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">
    <text x="29" y="14">pathly</text><text x="78" y="14">ok</text>
  </g>
</svg>`;

function reset( locale ) {
	wp( 'plugin', 'activate', 'pathly' );
	for ( const option of [ 'pathly_api_key', 'pathly_state', 'pathly_mock_calls' ] ) {
		try {
			wp( 'option', 'delete', option );
		} catch {
			// Absent.
		}
	}
	wp( 'transient', 'delete', '--all' );
	wp( 'option', 'update', 'blogname', locale === 'fr_FR' ? 'Atelier Lune' : 'Moon Workshop' );
	wp( 'user', 'meta', 'update', 'admin', 'locale', locale === 'fr_FR' ? 'fr_FR' : 'en_US' );
	wp( 'option', 'update', 'date_format', locale === 'fr_FR' ? 'j F Y' : 'F j, Y' );
	wp( 'option', 'update', 'time_format', locale === 'fr_FR' ? 'H:i' : 'g:i a' );
}

async function shoot( browser, locale, out ) {
	reset( locale );
	const context = await browser.newContext( { viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 } );
	const page = await context.newPage();

	// The failed step screenshot sent by the API, and the badge served by Pathly.
	const front = await browser.newPage( { viewport: { width: 1000, height: 640 } } );
	await front.goto( base );
	const failedStep = await front.screenshot();
	await front.close();
	await context.route( 'https://storage.example/**', ( route ) => route.fulfill( { contentType: 'image/png', body: failedStep } ) );
	await context.route( 'https://api.pathlyhq.com/public/**', ( route ) => route.fulfill( { contentType: 'image/svg+xml', body: badge } ) );

	await page.goto( `${ base }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	const settings = `${ base }/wp-admin/options-general.php?page=pathly`;
	const wrap = page.locator( '.pathly-settings' );

	await page.goto( settings );
	await wrap.screenshot( { path: out( 1 ) } );

	await page.fill( '#pathly_api_key', 'sp_live_0123456789abcdef' );
	await page.locator( 'form:has(input[value="pathly_connect"]) button, form:has(input[value="pathly_connect"]) input[type=submit]' ).click();
	await page.locator( 'form:has(input[value="pathly_flow_start"]) input[type=submit]' ).click();
	await page.waitForSelector( '.pathly-proposal' );
	await page.goto( settings );
	await page.waitForLoadState( 'networkidle' );
	await wrap.screenshot( { path: out( 2 ), animations: 'disabled' } );

	await page.locator( 'form:has(input[value="pathly_flow_save"]) input[type=submit]' ).first().click();
	await page.goto( `${ base }/wp-admin/index.php` );
	const box = page.locator( '#pathly_dashboard' );
	await box.scrollIntoViewIfNeeded();
	await page.waitForFunction( () => [ ...document.querySelectorAll( '.pathly-shot' ) ].every( ( i ) => i.complete ) );
	await box.screenshot( { path: out( 3 ) } );

	await page.goto( settings );
	await page.locator( 'form:has(input[value="pathly_badge"]) input[type=submit]' ).click();
	await page.waitForSelector( '.pathly-badge img' );
	await page.goto( settings );
	await page.waitForLoadState( 'networkidle' );
	await wrap.screenshot( { path: out( 4 ), animations: 'disabled' } );

	await context.close();
}

async function brand( browser ) {
	const logo = readFileSync( join( root, 'publication', 'logo.svg' ), 'utf8' );
	writeFileSync( join( assets, 'icon.svg' ), logo );
	const page = await browser.newPage();
	for ( const size of [ 128, 256 ] ) {
		await page.setViewportSize( { width: size, height: size } );
		await page.setContent( `<style>html,body{margin:0}svg{width:${ size }px;height:${ size }px;display:block}</style>${ logo }` );
		await page.screenshot( { path: join( assets, `icon-${ size }x${ size }.png` ) } );
	}
	for ( const [ w, h ] of [ [ 772, 250 ], [ 1544, 500 ] ] ) {
		const k = w / 772;
		await page.setViewportSize( { width: w, height: h } );
		await page.setContent( `<!doctype html><style>
			html,body{margin:0;width:${ w }px;height:${ h }px;overflow:hidden}
			body{background:radial-gradient(circle at 85% 20%,#12324a 0,#0b1220 60%);font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#fff;display:flex;align-items:center;gap:${ 28 * k }px;padding:0 ${ 48 * k }px;box-sizing:border-box}
			svg{width:${ 120 * k }px;height:${ 120 * k }px;flex:none}
			h1{margin:0;font-size:${ 46 * k }px;letter-spacing:-.5px}
			p{margin:${ 10 * k }px 0 0;font-size:${ 19 * k }px;line-height:1.4;color:#cfe9e5;max-width:${ 520 * k }px}
			b{color:#3ddec9;font-weight:600}
		</style>${ logo }<div><h1>Pathly</h1><p>Pathly watches the path. <b>Ping</b> if it's up. <b>Flow</b> if a user can walk it. <b>Chain</b> if the services behind it hold.</p></div>` );
		await page.screenshot( { path: join( assets, `banner-${ w }x${ h }.png` ) } );
	}
	await page.close();
}

try {
	wp( 'language', 'core', 'install', 'fr_FR' );
} catch {
	// Already installed, or offline: the plugin strings are French anyway.
}

const browser = await chromium.launch();
try {
	await brand( browser );
	await shoot( browser, 'en_US', ( n ) => join( assets, `screenshot-${ n }.png` ) );
	await shoot( browser, 'fr_FR', ( n ) => join( fr, `capture-${ n }.png` ) );
	reset( 'en_US' );
} finally {
	await browser.close();
}
console.log( 'Captures written.' );
