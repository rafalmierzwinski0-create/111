<?php
/**
 * Whether a colour rule shows itself before it has been saved.
 *
 * The question this answers is a fair one to ask of any "live" preview: the
 * mechanism can be wired everywhere and still be broken in one place, and
 * reading the code is not the same as watching it work. So the rule is typed
 * here as the form would hand it over, and the cells that come out the far end
 * are looked at.
 *
 * No WordPress: the handful of functions the classes reach for are stubbed.
 *
 * Usage: php tests/rules-preview-test.php
 *
 * @package LiveSheetsTablePro\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

define( 'ABSPATH', __DIR__ . '/' );
define( 'LSTABP_PATH', __DIR__ . '/../live-sheets-table-pro/' );

$GLOBALS['lstab_options'] = array();

function __( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_html__( $text, $domain = null ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_hex_color( $colour ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $colour ) ? $colour : ''; }
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
function disabled( $one, $two = true, $echo = true ) {
	$out = (bool) $one === (bool) $two ? " disabled='disabled'" : '';

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
function checked( $one, $two = true, $echo = true ) {
	$out = (string) $one === (string) $two ? " checked='checked'" : '';

	if ( $echo ) {
		echo $out;
	}

	return $out;
}

/**
 * Stands in for the free plugin's icon set.
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

require_once __DIR__ . '/../live-sheets-table/includes/class-lstab-renderer.php';
require_once __DIR__ . '/../live-sheets-table-pro/includes/class-lstabp-filters.php';
require_once __DIR__ . '/../live-sheets-table-pro/includes/class-lstabp-rules.php';

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

$headers = array( 'Session', 'Status' );
$source  = array( 'id' => 3, 'columns_config' => array(), 'data' => array( 'headers' => $headers ) );
$rows    = array(
	array( 'Opening keynote', 'Open' ),
	array( 'Workshop',        'Full' ),
);

// What was saved a week ago: nothing at all.
$GLOBALS['lstab_options'][ LSTABP_Rules::OPTION ] = array();

/*
 * The card first, and deliberately: what a preview is handed stands for the
 * whole request, and this file is one long request. A section that hands over
 * a rule set would still be standing in for the stored rules by the time the
 * card was drawn — which is right in the plugin and wrong in a test.
 */
echo "\nThe card hands the browser what it needs\n";

$GLOBALS['lstab_options'][ LSTABP_Rules::OPTION ] = array(
	3 => array( array( 'column' => 'Status', 'operator' => '=', 'value' => 'Full', 'style' => '#fbd5d5', 'scope' => 'pill' ) ),
);

$card = new LSTABP_Rules();

ob_start();
$card->render_pane_card( 'look', $source, true );
$markup = (string) ob_get_clean();

lstab_check( false !== strpos( $markup, 'name="lstabp_rules[0][scope]"' ), 'the card names the fields the collector reads' );
lstab_check( false !== strpos( $markup, 'value="pill"' ), 'every shape is offered, the new ones included' );
lstab_check( false !== strpos( $markup, 'value="dot"' ), 'including the dot' );
lstab_check( false !== strpos( $markup, 'lstabp-pill-face' ), 'and the swatch wears the shape that is chosen' );

file_put_contents( __DIR__ . '/fixtures/rules-card.html', $markup );

echo "\nA rule being typed, with nothing saved\n";

$rules = new LSTABP_Rules();
$rules->preview_request(
	new LSTABP_Fake_Request(
		array(
			'rules' => array(
				array( 'column' => 'Status', 'operator' => '=', 'value' => 'Full', 'style' => '#fbd5d5', 'scope' => 'row' ),
			),
		)
	),
	3
);

$typed = LSTABP_Rules::for_source( 3 );

lstab_check( 1 === count( $typed ), 'the rule reaches the preview before it is saved', count( $typed ) . ' rules' );

$rules->capture( $rows, $headers, $source, array() );

$painted = $rules->attributes( array(), 'Full', 1, 1, $source );
$plain   = $rules->attributes( array(), 'Open', 1, 0, $source );

lstab_check(
	isset( $painted['style'] ) && false !== strpos( $painted['style'], 'background-color:#fbd5d5' ),
	'and the row it names comes out painted',
	isset( $painted['style'] ) ? $painted['style'] : '(nothing)'
);
lstab_check( ! isset( $plain['style'] ), 'while the row it does not name is left alone' );
lstab_check(
	isset( $painted['style'] ) && false !== strpos( $painted['style'], '--lstab-row-tint' ),
	'with the pinned column painted too'
);

echo "\nEvery shape a rule can wear, straight from the form\n";

foreach ( array( 'cell', 'row', 'text', 'pill', 'dot' ) as $scope ) {
	$shape = new LSTABP_Rules();
	$shape->preview_request(
		new LSTABP_Fake_Request(
			array( 'rules' => array( array( 'column' => 'Status', 'operator' => '=', 'value' => 'Open', 'style' => '#5fe3cf', 'scope' => $scope ) ) )
		),
		3
	);

	$shape->capture( $rows, $headers, $source, array() );

	$cell  = $shape->attributes( array(), 'Open', 1, 0, $source );
	$style = isset( $cell['style'] ) ? $cell['style'] : '';
	$class = isset( $cell['class'] ) ? $cell['class'] : '';

	$wanted = array(
		'cell' => 'background-color:#5fe3cf',
		'row'  => '--lstab-row-tint:#5fe3cf',
		'text' => 'color:#5fe3cf',
		'pill' => '--lstabp-pill-line:#5fe3cf',
		'dot'  => '--lstabp-dot:#5fe3cf',
	);

	lstab_check(
		false !== strpos( $style, $wanted[ $scope ] ),
		"\"$scope\" arrives in the preview",
		$style
	);

	if ( 'pill' === $scope || 'dot' === $scope ) {
		lstab_check(
			false !== strpos( $class, 'lstabp-' . $scope ),
			"\"$scope\" also brings the class its shape is drawn with",
			$class
		);
	}
}

echo "\nWhat is typed stands in for what was saved\n";

$GLOBALS['lstab_options'][ LSTABP_Rules::OPTION ] = array(
	3 => array( array( 'column' => 'Status', 'operator' => '=', 'value' => 'Open', 'style' => '#cfebd9', 'scope' => 'cell' ) ),
);

$stored = new LSTABP_Rules();

lstab_check(
	1 === count( LSTABP_Rules::for_source( 3 ) ),
	'the saved rule is used when nothing is being typed'
);

$typing = new LSTABP_Rules();
$typing->preview_request( new LSTABP_Fake_Request( array( 'rules' => array() ) ), 3 );

lstab_check(
	0 === count( LSTABP_Rules::for_source( 3 ) ),
	'and deleting the last rule in the form empties the preview, rather than falling back to the saved one'
);

echo "\n$passed passed, $failed failed\n";

exit( $failed > 0 ? 1 : 0 );
