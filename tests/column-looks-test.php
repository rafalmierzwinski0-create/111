<?php
/**
 * What a column look does to the cells of a table.
 *
 * Needs no WordPress: the handful of functions the class calls are stubbed
 * below, and everything being checked is arithmetic and string building. It
 * runs anywhere, in a second.
 *
 * Usage: php tests/column-looks-test.php
 *
 * @package LiveSheetsTablePro\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lstab_options'] = array();

function __( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) {
	$url = trim( (string) $url );

	return preg_match( '#^https?://[^\s<>"\']+$#i', $url ) ? htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) : '';
}
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_unslash( $value ) { return $value; }
function get_option( $name, $default = false ) {
	return isset( $GLOBALS['lstab_options'][ $name ] ) ? $GLOBALS['lstab_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['lstab_options'][ $name ] = $value;
	return true;
}
function add_filter() {}
function add_action() {}
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function selected( $one, $two, $echo = true ) {
	$out = (string) $one === (string) $two ? " selected='selected'" : '';

	if ( $echo ) {
		echo $out;
	}

	return $out;
}

/**
 * Stands in for the free plugin's icon set, which needs WordPress to load.
 */
class LSTAB_Icons {
	/**
	 * One icon.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function icon( $name ) {
		return '<svg data-icon="' . esc_attr( $name ) . '"></svg>';
	}
}

define( 'LSTABP_PATH', __DIR__ . '/../live-sheets-table-pro/' );

require_once __DIR__ . '/../live-sheets-table/includes/class-lstab-renderer.php';
require_once __DIR__ . '/../live-sheets-table-pro/includes/class-lstabp-filters.php';
require_once __DIR__ . '/../live-sheets-table-pro/includes/class-lstabp-rules.php';
require_once __DIR__ . '/../live-sheets-table-pro/includes/class-lstabp-column-looks.php';

$passed = 0;
$failed = 0;

/**
 * Report one assertion.
 *
 * @param bool   $ok     Whether it held.
 * @param string $what   What was checked.
 * @param string $detail What was seen.
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

/**
 * The share of a bar in one cell, as a percentage of the cell's width.
 *
 * @param array<string,string> $attributes What the class made of the cell.
 * @return float|null
 */
function lstab_bar_of( $attributes ) {
	if ( ! isset( $attributes['style'] ) || ! preg_match( '/--lstabp-bar:([0-9.]+)%/', $attributes['style'], $found ) ) {
		return null;
	}

	return (float) $found[1];
}

$headers = array( 'Session', 'Seats left', 'Booking' );
$source  = array( 'id' => 7, 'columns_config' => array(), 'data' => array( 'headers' => $headers ) );
$rows    = array(
	array( 'Opening keynote',   '120', 'https://example.com/book/1' ),
	array( 'Readability',       '18',  'https://example.com/book/2' ),
	array( 'Faster websites',   '64',  'ask at the desk' ),
	array( 'Spreadsheets',      '0',   '' ),
);

$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array(
		'Seats left' => array( 'look' => 'bar', 'tint' => '#5fe3cf', 'ink' => '', 'label' => '' ),
		'Booking'    => array( 'look' => 'button', 'tint' => '#5fe3cf', 'ink' => '#06100f', 'label' => 'Book a seat' ),
	),
);

$looks = new LSTABP_Column_Looks();
$looks->capture( $rows, $headers, $source, array() );

echo "\nA bar is as long as its number is large\n";

$bars = array();

foreach ( $rows as $row_index => $row ) {
	$bars[ $row_index ] = lstab_bar_of( $looks->attributes( array(), $row[1], 1, $row_index, $source ) );
}

lstab_check( 100.0 === $bars[0], 'the largest number fills the cell', 'got ' . var_export( $bars[0], true ) );
lstab_check( 2.0 === $bars[3], 'zero is a sliver, not nothing at all', 'got ' . var_export( $bars[3], true ) );
lstab_check(
	null !== $bars[1] && null !== $bars[2] && $bars[1] < $bars[2] && $bars[2] < $bars[0],
	'the rest fall in between, in order',
	'18 → ' . var_export( $bars[1], true ) . ', 64 → ' . var_export( $bars[2], true )
);
lstab_check(
	abs( $bars[2] - ( 2 + 64 / 120 * 98 ) ) < 0.01,
	'the length is the share of the largest, measured from zero',
	'got ' . var_export( $bars[2], true ) . ', wanted ' . ( 2 + 64 / 120 * 98 )
);

$dressed = $looks->attributes( array( 'class' => 'lstab-ruled' ), '120', 1, 0, $source );

lstab_check(
	isset( $dressed['class'] ) && false !== strpos( $dressed['class'], 'lstabp-bar' ) && false !== strpos( $dressed['class'], 'lstab-ruled' ),
	'the class is added without throwing away the one already there',
	isset( $dressed['class'] ) ? $dressed['class'] : '(none)'
);
lstab_check(
	isset( $dressed['style'] ) && false !== strpos( $dressed['style'], '--lstabp-bar-colour:#5fe3cf' ),
	'the chosen colour reaches the cell'
);

echo "\nOnly an address becomes a button\n";

$first = $looks->render_cell( null, $rows[0][2], 2, 0, $source );
$note  = $looks->render_cell( null, $rows[2][2], 2, 2, $source );
$blank = $looks->render_cell( null, $rows[3][2], 2, 3, $source );

lstab_check(
	is_string( $first ) && false !== strpos( $first, 'href="https://example.com/book/1"' ) && false !== strpos( $first, '>Book a seat<' ),
	'an address becomes a button carrying its own words',
	(string) $first
);
lstab_check( null === $note, 'a note in the same column is left alone', var_export( $note, true ) );
lstab_check( null === $blank, 'and so is a blank', var_export( $blank, true ) );

$colours = $looks->attributes( array(), $rows[0][2], 2, 0, $source );

lstab_check(
	isset( $colours['style'] )
		&& false !== strpos( $colours['style'], '--lstabp-cta-bg:#5fe3cf' )
		&& false !== strpos( $colours['style'], '--lstabp-cta-ink:#06100f' ),
	'both chosen colours reach the cell',
	isset( $colours['style'] ) ? $colours['style'] : '(none)'
);

echo "\nWhat it refuses to do\n";

$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array( 'Seats left' => array( 'look' => 'bar', 'tint' => '', 'ink' => '', 'label' => '' ) ),
);

$same = new LSTABP_Column_Looks();
$flat = array( array( 'a', '50' ), array( 'b', '50' ), array( 'c', '50' ) );
$same->capture( $flat, array( 'Session', 'Seats left' ), $source, array() );

lstab_check(
	null === lstab_bar_of( $same->attributes( array(), '50', 1, 0, $source ) ),
	'a column where every value is the same is left alone, not painted end to end'
);

$wordy = new LSTABP_Column_Looks();
$words = array( array( 'a', 'lots' ), array( 'b', 'few' ) );
$wordy->capture( $words, array( 'Session', 'Seats left' ), $source, array() );

lstab_check(
	null === lstab_bar_of( $wordy->attributes( array(), 'lots', 1, 0, $source ) ),
	'a column of words gets no bars'
);

$hidden = new LSTABP_Column_Looks();
$hidden->capture(
	$rows,
	$headers,
	array( 'id' => 7, 'columns_config' => array( 1 => array( 'hidden' => true ) ) ),
	array()
);

lstab_check(
	null === lstab_bar_of( $hidden->attributes( array(), '120', 1, 0, $source ) ),
	'a hidden column has no cell to dress'
);

echo "\nSaving\n";

$_POST = array(
	'_lstabp_looks_present' => '1',
	'lstabp_looks'          => array(
		'Seats left' => array( 'look' => 'bar', 'tint' => '#5FE3CF', 'ink' => 'nonsense', 'label' => '' ),
		'Booking'    => array( 'look' => 'button', 'tint' => '#06100f', 'ink' => '#ffffff', 'label' => str_repeat( 'x', 80 ) ),
		'Nothing'    => array( 'look' => 'sparkles' ),
	),
);

$saver = new LSTABP_Column_Looks();
$saver->save( 7 );

$stored = LSTABP_Column_Looks::for_source( 7 );

lstab_check( ! isset( $stored['Nothing'] ), 'a look nobody offers is not stored' );
lstab_check(
	isset( $stored['Seats left']['tint'] ) && '#5fe3cf' === $stored['Seats left']['tint'],
	'a colour is stored in one case',
	isset( $stored['Seats left']['tint'] ) ? $stored['Seats left']['tint'] : '(none)'
);
lstab_check(
	isset( $stored['Seats left']['ink'] ) && '' === $stored['Seats left']['ink'],
	'a colour that is not a colour is dropped'
);
lstab_check(
	isset( $stored['Booking']['label'] ) && LSTABP_Column_Looks::MAX_LABEL === mb_strlen( $stored['Booking']['label'] ),
	'a button cannot carry an essay',
	isset( $stored['Booking']['label'] ) ? mb_strlen( $stored['Booking']['label'] ) . ' characters' : '(none)'
);

$_POST = array( '_lstabp_looks_present' => '1', 'lstabp_looks' => array() );
$saver->save( 7 );

lstab_check( array() === LSTABP_Column_Looks::for_source( 7 ), 'clearing the last one clears the source' );

/*
 * What the browser suite builds its table from. Written rather than duplicated
 * there, so the markup it checks is the markup this class actually produced.
 */
$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array(
		'Seats left' => array( 'look' => 'bar', 'tint' => '#5fe3cf', 'ink' => '', 'label' => '' ),
		'Booking'    => array( 'look' => 'button', 'tint' => '#5fe3cf', 'ink' => '#06100f', 'label' => 'Book a seat' ),
	),
);

$shown = new LSTABP_Column_Looks();
$shown->capture( $rows, $headers, $source, array() );

$dump = array( 'headers' => $headers, 'rows' => array() );

foreach ( $rows as $row_index => $row ) {
	$cells = array();

	foreach ( $row as $col_index => $value ) {
		$cells[] = array(
			'value'      => $value,
			'html'       => $shown->render_cell( null, $value, $col_index, $row_index, $source ),
			'attributes' => $shown->attributes(
				array( 'data-label' => $headers[ $col_index ], 'data-lstab-align' => 1 === $col_index ? 'end' : 'start' ),
				$value,
				$col_index,
				$row_index,
				$source
			),
		);
	}

	$dump['rows'][] = $cells;
}

file_put_contents( __DIR__ . '/fixtures/column-looks-php-said.json', json_encode( $dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo "\n  wrote fixtures/column-looks-php-said.json for the browser suite\n";

echo "\nThe card in the dashboard\n";

$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array(
		'Seats left' => array( 'look' => 'bar', 'tint' => '#5fe3cf', 'ink' => '', 'label' => '' ),
		'Booking'    => array( 'look' => 'button', 'tint' => '#123456', 'ink' => '#ffffff', 'label' => 'Book a seat' ),
	),
);

$card = new LSTABP_Column_Looks();

ob_start();
$card->render_pane_card( 'look', array( 'id' => 7, 'data' => array( 'headers' => $headers ) ), true );
$markup = (string) ob_get_clean();

lstab_check( false !== strpos( $markup, 'name="lstabp_looks[Seats left][look]"' ), 'the card names its fields after the headings' );
lstab_check( substr_count( $markup, 'class="lstabp-look' ) >= 3, 'every column gets a row of its own' );
lstab_check( false !== strpos( $markup, 'value="#123456"' ), 'a stored colour comes back into the card' );
lstab_check( false !== strpos( $markup, 'value="Book a seat"' ), 'and so do a button\'s own words' );
lstab_check( false !== strpos( $markup, 'data-lstabp-look="bar"' ), 'the row says which look it wears, for the stylesheet to read' );

ob_start();
$card->render_pane_card( 'columns', array( 'id' => 7, 'data' => array( 'headers' => $headers ) ), true );
lstab_check( '' === (string) ob_get_clean(), 'and it keeps off every pane but Appearance' );

// The browser suite reads this to check what the card hands the preview.
file_put_contents( __DIR__ . '/fixtures/column-looks-card.html', $markup );

echo "\nA look being chosen, before anything is saved\n";

/**
 * The few things a preview request is asked for.
 */
class LSTABP_Fake_Request {
	/**
	 * Parameters.
	 *
	 * @var array<string,mixed>
	 */
	protected $params;

	/**
	 * Build one.
	 *
	 * @param array<string,mixed> $params Parameters.
	 */
	public function __construct( $params ) {
		$this->params = $params;
	}

	/**
	 * One parameter.
	 *
	 * @param string $name Parameter name.
	 * @return mixed
	 */
	public function get_param( $name ) {
		return isset( $this->params[ $name ] ) ? $this->params[ $name ] : null;
	}
}

$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array( 'Seats left' => array( 'look' => 'bar', 'tint' => '#5fe3cf', 'ink' => '', 'label' => '' ) ),
);

$live = new LSTABP_Column_Looks();
$live->preview_request(
	new LSTABP_Fake_Request(
		array(
			'looks' => array(
				'Booking' => array( 'look' => 'button', 'tint' => '#ff0000', 'ink' => '#ffffff', 'label' => 'Join' ),
			),
		)
	),
	7
);

$now = LSTABP_Column_Looks::for_source( 7 );

lstab_check( isset( $now['Booking'] ), 'what is being chosen reaches the preview' );
lstab_check( ! isset( $now['Seats left'] ), 'and stands in for what was saved, rather than joining it' );
lstab_check(
	isset( $now['Booking']['tint'] ) && '#ff0000' === $now['Booking']['tint'],
	'with its colours',
	isset( $now['Booking']['tint'] ) ? $now['Booking']['tint'] : '(none)'
);

$looks_now = new LSTABP_Column_Looks();
$looks_now->capture( $rows, $headers, $source, array() );
$button = $looks_now->render_cell( null, $rows[0][2], 2, 0, $source );

lstab_check(
	is_string( $button ) && false !== strpos( $button, '>Join<' ),
	'and the table is drawn with it',
	(string) $button
);

echo "\n$passed passed, $failed failed\n";

exit( $failed > 0 ? 1 : 0 );
