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
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	// Remembered, not ignored: the order two filters run in is what decides
	// whether a colour rule can still single a row out of a column that
	// already has a look, and that is worth an assertion.
	$GLOBALS['lstab_filters'][ $hook ][ $priority ][] = $callback;
}
function add_action() {}
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
function checked( $one, $two = true, $echo = true ) {
	$out = (string) $one === (string) $two ? " checked='checked'" : '';

	if ( $echo ) {
		echo $out;
	}

	return $out;
}
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
lstab_check(
	false === strpos( LSTABP_Rules::css_for( '#7a1f2b', 'row' ), '--lstabp-bar' ),
	'a painted row leaves a bar its own colour: the colour chosen is the colour on every row'
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

echo "\nA pill, and a whole column in a colour\n";

/*
 * The two looks that answer "this column is a set of labels" and "this column
 * belongs in a colour". Neither has a condition to write, which is what makes
 * them a column look rather than a colour rule: every row is dressed the same
 * whatever it says.
 */
$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array(
		'Session'    => array( 'look' => 'tint', 'tint' => '#3d4c55', 'ink' => '', 'label' => '' ),
		'Seats left' => array( 'look' => 'pill', 'tint' => '#5fe3cf', 'ink' => '', 'label' => '' ),
	),
);

$dressy = new LSTABP_Column_Looks();
$dressy->capture( $rows, $headers, $source, array() );

$pilled = $dressy->attributes( array(), '120', 1, 0, $source );

lstab_check(
	isset( $pilled['class'] ) && false !== strpos( $pilled['class'], 'lstabp-pill' ),
	'every value in the column is given the badge',
	isset( $pilled['class'] ) ? $pilled['class'] : '(none)'
);
lstab_check(
	isset( $pilled['style'] )
		&& false !== strpos( $pilled['style'], '--lstabp-pill-line:#5fe3cf' )
		&& false !== strpos( $pilled['style'], '--lstabp-pill-fill:' )
		&& false !== strpos( $pilled['style'], '--lstabp-pill-ink:' ),
	'in the three properties a colour rule\'s pill wears, so the two match',
	isset( $pilled['style'] ) ? $pilled['style'] : '(none)'
);

/*
 * The dashboard draws the same badge in JavaScript, as a colour is picked and
 * before anything is saved. Two copies of the same three shares is two chances
 * to change one and forget the other, and the one that matters is the ink: it
 * is what keeps a badge readable when somebody picks white. So the script is
 * read and held to what the server just produced.
 */
$badge_js  = file_get_contents( dirname( __DIR__ ) . '/live-sheets-table-pro/assets/js/lstabp-admin.js' );
$badge_php = LSTABP_Rules::css_for( '#5fe3cf', 'pill' );
$shares    = array();

foreach ( array( 'fill', 'ink' ) as $part ) {
	preg_match( '/--lstabp-pill-' . $part . ':color-mix\(in srgb,#5fe3cf (\d+)%/', $badge_php, $from_php );
	preg_match( '/--lstabp-pill-' . $part . ":color-mix\\(in srgb,' \\+ hex \\+ ' (\\d+)%/", $badge_js, $from_js );

	$shares[ $part ] = array(
		'php' => isset( $from_php[1] ) ? $from_php[1] : '?',
		'js'  => isset( $from_js[1] ) ? $from_js[1] : '?',
	);
}

lstab_check(
	'?' !== $shares['ink']['php'] && $shares['ink']['php'] === $shares['ink']['js']
		&& $shares['fill']['php'] === $shares['fill']['js'],
	'the badge the preview draws is mixed exactly as the one the server sends',
	json_encode( $shares )
);

lstab_check(
	(int) $shares['ink']['php'] <= 35,
	'and its word keeps at most a third of the chosen colour, so white is still readable',
	$shares['ink']['php'] . '%'
);

$blank_pill = $dressy->attributes( array(), '   ', 1, 3, $source );

lstab_check(
	! isset( $blank_pill['class'] ) || false === strpos( $blank_pill['class'], 'lstabp-pill' ),
	'an empty cell gets no badge — a badge round nothing reads as a value the sheet has not got',
	isset( $blank_pill['class'] ) ? $blank_pill['class'] : '(none)'
);

$tinted = $dressy->attributes( array(), 'Opening keynote', 0, 0, $source );

lstab_check(
	isset( $tinted['style'] ) && false !== strpos( $tinted['style'], 'background-color:#3d4c55' ),
	'a whole-column colour paints the cell',
	isset( $tinted['style'] ) ? $tinted['style'] : '(none)'
);
lstab_check(
	isset( $tinted['style'] ) && preg_match( '/(^|;)color:#(f|e)/i', $tinted['style'] ),
	'and works out a readable ink rather than asking for one',
	isset( $tinted['style'] ) ? $tinted['style'] : '(none)'
);
lstab_check(
	isset( $tinted['style'] ) && false !== strpos( $tinted['style'], '--lstab-row-tint:#3d4c55' ),
	'and says what its tint is, so a pinned first column keeps it while the table slides'
);
lstab_check(
	isset( $tinted['class'] ) && false !== strpos( $tinted['class'], 'lstab-ruled' ),
	'a painted column is a painted cell, by the class the table already knows',
	isset( $tinted['class'] ) ? $tinted['class'] : '(none)'
);

$blank_tint = $dressy->attributes( array(), '', 0, 0, $source );

lstab_check(
	isset( $blank_tint['style'] ) && false !== strpos( $blank_tint['style'], 'background-color:#3d4c55' ),
	'an empty cell keeps the paint, or the column is a colour with holes in it'
);

$head_tinted = $dressy->heading_attributes( array(), 'Session', 0, $source );
$head_pilled = $dressy->heading_attributes( array(), 'Seats left', 1, $source );

lstab_check(
	isset( $head_tinted['style'] ) && false !== strpos( $head_tinted['style'], 'background-color:#3d4c55' ),
	'the name at the top of a painted column is painted too',
	isset( $head_tinted['style'] ) ? $head_tinted['style'] : '(none)'
);
lstab_check(
	isset( $head_tinted['style'] ) && false !== strpos( $head_tinted['style'], '--lstab-head-bg:#3d4c55' ),
	'through the token the pinned heading repeats, not only as a background'
);
lstab_check(
	isset( $head_tinted['style'] ) && false !== strpos( $head_tinted['style'], '--lstab-fg:' ),
	'and the hover colour is restated, or the name vanishes under the pointer'
);
lstab_check(
	! isset( $head_pilled['style'] ),
	'a column of pills leaves its heading alone — a heading drawn as a pill reads as one of the values',
	isset( $head_pilled['style'] ) ? $head_pilled['style'] : '(none)'
);

echo "\nA rule still beats a look\n";

/*
 * The one question a screen offering both has to answer: paint this column
 * teal, but paint the closed row grey — which of the two wins? The rule does.
 * It names a condition, so it is the more particular of the two, and a rule
 * the screen offers and the table ignores is worse than no rule at all.
 *
 * This is decided by the order the two filters run in, so it is checked the
 * way WordPress decides it: by priority.
 */
$GLOBALS['lstab_filters'] = array();
( new LSTABP_Column_Looks() )->register();
( new LSTABP_Rules() )->register();

$lstabp_look_at = 0;
$lstabp_rule_at = 0;

foreach ( $GLOBALS['lstab_filters']['lstab_cell_attributes'] as $lstabp_priority => $lstabp_bound ) {
	foreach ( $lstabp_bound as $lstabp_callback ) {
		if ( $lstabp_callback[0] instanceof LSTABP_Column_Looks ) {
			$lstabp_look_at = $lstabp_priority;
		}

		if ( $lstabp_callback[0] instanceof LSTABP_Rules ) {
			$lstabp_rule_at = $lstabp_priority;
		}
	}
}

lstab_check(
	$lstabp_look_at > 0 && $lstabp_rule_at > 0 && $lstabp_look_at < $lstabp_rule_at,
	'a column look is written before a colour rule, so the rule has the last word',
	"look at {$lstabp_look_at}, rule at {$lstabp_rule_at}"
);

echo "\nA scale its rules name sorts in the rules' order\n";

/*
 * Easy, Moderate, Hard sorted as words is Easy, Hard, Moderate, and a table
 * that does that looks as though sorting is broken. Three "is" rules on the
 * column already say which way the scale runs, so the cells carry their place
 * in it and the free plugin sorts by that first.
 */
$GLOBALS['lstab_options'][ LSTABP_Rules::OPTION ] = array(
	9 => array(
		array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Easy', 'style' => '#5fe3cf', 'scope' => 'dot' ),
		array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Moderate', 'style' => '#f2b544', 'scope' => 'dot' ),
		array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Hard', 'style' => '#ff8d8d', 'scope' => 'dot' ),
		array( 'column' => 'Status', 'operator' => '=', 'value' => 'Closed', 'style' => '#5a6b80', 'scope' => 'row' ),
		array( 'column' => 'Status', 'operator' => '=', 'value' => 'Open', 'style' => '#5fe3cf', 'scope' => 'pill' ),
	),
);

$trail_headers = array( 'Trail', 'Difficulty', 'Status' );
$trail_rows    = array(
	array( 'Windgap', 'Hard', 'Closed' ),
	array( 'Blue Lake', 'easy', 'Open' ),
	array( 'Five Tarns', 'Moderate', 'Open' ),
	array( 'Unnamed', 'Unrated', 'Open' ),
);
$trail_source  = array( 'id' => 9 );
$ruled         = new LSTABP_Rules();
$ruled->capture( $trail_rows, $trail_headers, $trail_source, array() );

$places = array();

foreach ( $trail_rows as $lstabp_row_index => $lstabp_row ) {
	$lstabp_cell = $ruled->attributes( array(), $lstabp_row[1], 1, $lstabp_row_index, $trail_source );
	$places[]    = isset( $lstabp_cell['data-lstab-rank'] ) ? $lstabp_cell['data-lstab-rank'] : '-';
}

lstab_check(
	array( '2', '0', '1', '-' ) === $places,
	'each value carries its place in the order the rules are listed, any case, and an unnamed one carries none',
	implode( ' ', $places )
);

$lstabp_status = $ruled->attributes( array(), 'Closed', 2, 0, $trail_source );

lstab_check(
	! isset( $lstabp_status['data-lstab-rank'] ),
	'a rule that paints the whole row is about the row, so with one rule left the column sorts as before'
);

$server = $ruled->sort_ranks( array(), $trail_headers, $trail_source );

lstab_check(
	array( 1 => array( 'easy' => 0, 'moderate' => 1, 'hard' => 2 ) ) === $server,
	'a paged table, sorted on the server, is handed the same order',
	json_encode( $server )
);

$moved = new LSTABP_Rules();
$moved->capture( $trail_rows, $trail_headers, $trail_source, array( 'columns' => array( 0 => array( 'hidden' => true ) ) ) );
$lstabp_moved = $moved->attributes( array(), 'Hard', 0, 0, $trail_source );

lstab_check(
	isset( $lstabp_moved['data-lstab-rank'] ) && '2' === $lstabp_moved['data-lstab-rank'],
	'with a column hidden in front of it, the place lands on the cell the column moved to',
	json_encode( $lstabp_moved )
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

/*
 * The chooser draws what it offers. A dropdown could only name the looks, and
 * the name is the part nobody can picture — so every chip is the look itself,
 * in the very classes the table uses.
 */
lstab_check(
	substr_count( $markup, 'class="lstabp-look-face' ) >= count( $headers ) * ( count( LSTABP_Column_Looks::looks() ) + 1 ),
	'every look on offer is drawn, for every column',
	substr_count( $markup, 'class="lstabp-look-face' ) . ' chips'
);
lstab_check(
	false !== strpos( $markup, 'lstabp-look-face lstabp-bar' ) && false !== strpos( $markup, '--lstabp-bar:64%' ),
	'the bar chip is a bar'
);
lstab_check(
	false !== strpos( $markup, 'lstabp-pill-face' ) && false !== strpos( $markup, '--lstabp-pill-line:' ),
	'the pill chip is the same badge the table draws'
);
lstab_check(
	false !== strpos( $markup, 'lstabp-look-face is-tinted' ),
	'and the whole-column chip is a painted cell'
);
lstab_check(
	false !== strpos( $markup, 'class="lstabp-cta-link"' ) && false !== strpos( $markup, '>Book a seat</a>' ),
	'the button chip wears the words that column was given',
	false !== strpos( $markup, '>Book a seat</a>' ) ? 'yes' : 'no'
);

/*
 * The chip for a look is drawn in that column's own colours, not in a fixed
 * demonstration colour: a chip showing teal beside a picker set to navy is
 * worse than no chip at all.
 */
lstab_check(
	false !== strpos( $markup, '--lstabp-bar-colour:#5fe3cf' ),
	'in the colours that column is actually set to'
);

lstab_check(
	false !== strpos( $markup, 'type="radio"' ) && false === strpos( $markup, '<select class="lstabp-look-pick"' ),
	'and it is a radio group, so one value still arrives under one name'
);

lstab_check(
	false !== strpos( $markup, 'data-lstabp-fold="10"' ),
	'a long list says where it may be folded, for the script to read'
);

lstab_check(
	false !== strpos( $markup, 'data-lstabp-for="bar pill tint button"' ),
	'the colour field is offered to every look that has a colour',
	false !== strpos( $markup, 'data-lstabp-for="bar pill tint button"' ) ? 'yes' : 'no'
);

ob_start();
$card->render_pane_card( 'columns', array( 'id' => 7, 'data' => array( 'headers' => $headers ) ), true );
lstab_check( '' === (string) ob_get_clean(), 'and it keeps off every pane but Appearance' );

// The browser suite reads this to check what the card hands the preview.
file_put_contents( __DIR__ . '/fixtures/column-looks-card.html', $markup );

/*
 * And a second one, wide enough to be folded. A sheet is allowed fifty
 * columns, and a card that draws a row per column buried everything under it;
 * the browser suite opens this one to watch the folding work.
 *
 * One of the chosen columns is deliberately near the end, because the rule
 * that matters most is the one about what is never folded away: ten rows that
 * hide the setting somebody came back to change are worse than the long list.
 */
$lstabp_many = array();
for ( $lstabp_n = 1; $lstabp_n <= 24; $lstabp_n++ ) {
	$lstabp_many[] = 'Column ' . $lstabp_n;
}

$GLOBALS['lstab_options'][ LSTABP_Column_Looks::OPTION ] = array(
	7 => array( 'Column 20' => array( 'look' => 'pill', 'tint' => '#5fe3cf', 'ink' => '', 'label' => '' ) ),
);

ob_start();
( new LSTABP_Column_Looks() )->render_pane_card( 'look', array( 'id' => 7, 'data' => array( 'headers' => $lstabp_many ) ), true );
file_put_contents( __DIR__ . '/fixtures/column-looks-long.html', (string) ob_get_clean() );

/*
 * What wp_localize_script() puts on the page beside the script. The browser
 * suite writes it into its test page, because the folding button is drawn
 * from these strings and a page without them keeps the whole list — which is
 * right, and would quietly turn the folding test into no test at all.
 */
file_put_contents(
	__DIR__ . '/fixtures/admin-settings.json',
	json_encode(
		array( 'maxRules' => LSTABP_Rules::MAX_RULES ) + LSTABP_Rules::fold_words(),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "\n"
);

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
