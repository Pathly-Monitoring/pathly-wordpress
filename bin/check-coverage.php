<?php
/**
 * Fails unless the text coverage report shows 100 % of lines and branches.
 *
 * Usage: php bin/check-coverage.php build/coverage.txt
 *
 * @package Pathly
 */

$report = isset( $argv[1] ) ? (string) file_get_contents( $argv[1] ) : '';
$failed = false;
foreach ( array( 'Lines', 'Branches' ) as $metric ) {
	if ( ! preg_match( '/^\s*' . $metric . ':\s+([0-9.]+)%\s+\((\d+)\/(\d+)\)/m', $report, $m ) ) {
		fwrite( STDERR, "Coverage: no {$metric} line in the report.\n" );
		$failed = true;
		continue;
	}
	$ok = $m[2] === $m[3];
	fwrite( $ok ? STDOUT : STDERR, sprintf( "Coverage %s: %s%% (%s/%s)%s\n", $metric, $m[1], $m[2], $m[3], $ok ? '' : ' — 100 % required' ) );
	$failed = $failed || ! $ok;
}
exit( $failed ? 1 : 0 );
