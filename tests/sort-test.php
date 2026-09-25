<?php
/**
 * How the plugin reads a value before it sorts it.
 *
 * Unlike the other suites this one needs no WordPress: everything it exercises
 * is arithmetic on a string. That is deliberate — it runs anywhere, in a second,
 * so nobody has an excuse not to run it after touching the parser.
 *
 * The cases live in fixtures/sort-cases.json, read from here and from
 * tests/sort-browser.mjs, which puts the same values through the browser's copy
 * of the same rules. A visitor cannot tell which table is paged and which sorts
 * in the browser, so the two must agree; one file of cases is how they are held
 * to it.
 *
 * Usage: php tests/sort-test.php
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../live-sheets-table/includes/class-lstab-renderer.php';
require_once __DIR__ . '/../live-sheets-table/includes/class-lstab-paging.php';

$passed = 0;
$failed = 0;

/**
 * Report one assertion.
 *
 * @param bool   $ok      Whether it held.
 * @param string $what    What was being checked.
 * @param string $detail  What was seen.
 * @return void
 */
function lstab_check( $ok, $what, $detail = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		echo "  ok   $what\n";
		return;
	}

	++$failed;
	echo "  FAIL $what\n";

	if ( '' !== $detail ) {
		echo "       $detail\n";
	}
}

$cases = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/sort-cases.json' ), true );

if ( ! is_array( $cases ) ) {
	fwrite( STDERR, "Could not read fixtures/sort-cases.json\n" );
	exit( 1 );
}

echo "\nReading one value at a time\n";

foreach ( $cases['moments'] as $case ) {
	$read  = LSTAB_Renderer::to_moment( $case['value'] );
	$shown = '"' . $case['value'] . '"';

	if ( null === $case['kind'] ) {
		lstab_check(
			null === $read,
			"$shown is not a date or a time",
			null === $read ? '' : 'read as ' . $read['kind'] . ' ' . $read['value']
		);
		continue;
	}

	if ( null === $read ) {
		lstab_check( false, "$shown reads as a {$case['kind']}", 'not recognised at all' );
		continue;
	}

	lstab_check(
		$read['kind'] === $case['kind'] && abs( $read['value'] - $case['number'] ) < 0.000001,
		"$shown reads as {$case['kind']} {$case['number']}",
		'got ' . $read['kind'] . ' ' . $read['value']
	);
}

echo "\nSorting a whole column\n";

$sort = new ReflectionMethod( 'LSTAB_Paging', 'sort' );
$sort->setAccessible( true );

foreach ( $cases['columns'] as $column ) {
	$rows = array_map(
		function ( $value ) {
			return array( $value );
		},
		$column['values']
	);

	$up = array_column( $sort->invoke( null, $rows, 0, 'asc' ), 0 );

	lstab_check(
		$up === $column['ascending'],
		$column['name'] . ' — rising',
		'got [' . implode( ' | ', $up ) . '] wanted [' . implode( ' | ', $column['ascending'] ) . ']'
	);

	/*
	 * Descending is the rising order backwards, except where the fixture says
	 * otherwise: blanks are missing data rather than the smallest value, so
	 * they stay at the bottom whichever way the column is sorted.
	 */
	$wanted_down = isset( $column['descending'] ) ? $column['descending'] : array_reverse( $column['ascending'] );
	$down        = array_column( $sort->invoke( null, $rows, 0, 'desc' ), 0 );

	lstab_check(
		$down === $wanted_down,
		$column['name'] . ' — falling',
		'got [' . implode( ' | ', $down ) . '] wanted [' . implode( ' | ', $wanted_down ) . ']'
	);
}

echo "\nWhat the browser has to agree with\n";

$parity = array();

foreach ( $cases['moments'] as $case ) {
	$read                       = LSTAB_Renderer::to_moment( $case['value'] );
	$parity[ $case['value'] ] = null === $read
		? null
		: array( 'kind' => $read['kind'], 'value' => round( $read['value'], 6 ) );
}

file_put_contents( __DIR__ . '/fixtures/sort-php-said.json', wp_json( $parity ) );
echo "  wrote fixtures/sort-php-said.json — " . count( $parity ) . " values for the browser suite to match\n";

/**
 * Pretty JSON without WordPress around to provide it.
 *
 * @param mixed $data Anything encodable.
 * @return string
 */
function wp_json( $data ) {
	return (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
}

echo "\n$passed passed, $failed failed\n";

exit( $failed > 0 ? 1 : 0 );
