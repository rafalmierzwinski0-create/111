<?php
/**
 * A table's own CSS, which lives in the Pro add-on.
 *
 * The scoping and cleaning are checked rule shape by rule shape, then attacked
 * on purpose, then followed end to end: stored for a table, printed with it,
 * and saved from the table's screen. Needs Pro active.
 *
 * Usage: php tests/pro-css-test.php /absolute/path/to/wp
 *
 * @package TablikoPro\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/pro-css-test.php /path/to/wordpress\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8089';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

$GLOBALS['lstab_passed'] = 0;
$GLOBALS['lstab_failed'] = 0;

function lstab_assert( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['lstab_passed']++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
		return;
	}

	$GLOBALS['lstab_failed']++;
	echo "  \033[31mFAIL\033[0m  {$label}\n";
	if ( '' !== $detail ) {
		echo "        {$detail}\n";
	}
}

function lstab_section( $title ) {
	echo "\n\033[1m{$title}\033[0m\n";
}

function ex_assert( $condition, $label, $detail = '' ) {
	lstab_assert( $condition, $label, $detail );
}

function ex_section( $title ) {
	lstab_section( $title );
}

if ( ! class_exists( 'LSTABP_Custom_Css' ) ) {
	fwrite( STDERR, "Pro is not active on this site.\n" );
	exit( 1 );
}

file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'ok', 'tab' => 'main' ) ) );

wp_set_current_user( 1 );
$server    = rest_get_server();
$source_id = (int) LSTAB_Storage::insert(
	array(
		'title'     => 'Own CSS',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'  => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'gid'       => '0',
	)
);
LSTAB_Sync::run( $source_id );

lstab_section( 'A. The live preview endpoint' );

/*
 * The CSS field's live preview. What it confines the rules to decides whether
 * half the field works: colours are custom properties the table sets on itself,
 * so rules aimed at the frame around the table are inherited into an element
 * that overrides every one of them. Naming the table is the whole fix, and it
 * is one word in one string — worth a test that says so out loud.
 */
$scoped = new WP_REST_Request( 'POST', '/tabliko-pro/v1/scoped-css' );
$scoped->set_param( 'selector', '[data-lstab-preview="stage"]' );
$scoped->set_param( 'css', '& { --lstab-fg: #123456 } td { padding: 2em }' );
$scoped_css = $server->dispatch( $scoped )->get_data()['css'];

lstab_assert(
	false !== strpos( $scoped_css, ':is([data-lstab-preview="stage"] .lstab, #lstab-none){--lstab-fg: #123456}' ),
	'The preview confines the CSS field to the table, not the frame around it',
	$scoped_css
);
lstab_assert(
	false !== strpos( $scoped_css, ':is([data-lstab-preview="stage"] .lstab, #lstab-none) td{' ),
	'And rules naming something inside it still reach it',
	$scoped_css
);

// A selector of somebody else's choosing must not become a way to style the
// dashboard around the preview.
$hostile_scope = new WP_REST_Request( 'POST', '/tabliko-pro/v1/scoped-css' );
$hostile_scope->set_param( 'selector', 'body' );
$hostile_scope->set_param( 'css', 'td { color: red }' );
$hostile_css = $server->dispatch( $hostile_scope )->get_data()['css'];
lstab_assert(
	0 === strpos( $hostile_css, ':is([data-lstab-preview="none"] .lstab, #lstab-none)' ),
	'A selector the plugin does not recognise is refused, not obeyed',
	$hostile_css
);


// ---------------------------------------------------------------------------

lstab_section( '14z. A table with its own CSS' );

// Scoping is the whole safety argument, so it is checked rule shape by rule
// shape rather than only end to end.
$css_selector = LSTABP_Custom_Css::selector( 7 );

// Every rule is given the weight of an id that nothing carries, so a plain
// "td { background: … }" wins over the built-in look, whose rules are classes.
$css_scope = ':is([data-lstab-id="7"], #lstab-none)';

$scoped = LSTABP_Custom_Css::scope( 'td { color: red; }', $css_selector );
lstab_assert(
	$css_scope . ' td{color: red;}' === $scoped,
	'A plain rule is confined to its own table',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'th, td { padding: 0; }', $css_selector );
lstab_assert(
	$css_scope . ' th,' . $css_scope . ' td{padding: 0;}' === $scoped,
	'Every selector in a list is confined, not just the first',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( '&.lstab-paged td { color: red; }', $css_selector );
lstab_assert(
	$css_scope . '.lstab-paged td{color: red;}' === $scoped,
	'"&" means the table itself',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'td[data-label="R&D"], td[data-label="a, b"] { color: red; }', $css_selector );
lstab_assert(
	$css_scope . ' td[data-label="R&D"],' . $css_scope . ' td[data-label="a, b"]{color: red;}' === $scoped,
	'But an "&" or a comma inside a quoted value is only text',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'td[data-label="Old  Town"], td[data-label=Old\\ Town] { color: red; }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, '"Old  Town"' ) && false !== strpos( $scoped, 'Old\\ Town' ),
	'Spaces inside a quoted value and an escaped space are kept as written',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( ':is(th, td) { color: red; }', $css_selector );
lstab_assert(
	$css_scope . ' :is(th, td){color: red;}' === $scoped,
	'A comma inside :is() is not a second selector',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( '@media (max-width: 40em) { td { color: red; } }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, $css_scope . ' td' ) && 0 === strpos( $scoped, '@media' ),
	'Rules inside @media are confined too',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( '@starting-style { td { opacity: 0; } }', $css_selector );
lstab_assert(
	'@starting-style{' . $css_scope . ' td{opacity: 0;}}' === $scoped,
	'And inside @starting-style, rather than being dropped',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( '@keyframes spin { from { opacity: 0 } to { opacity: 1 } }', $css_selector );
lstab_assert(
	false === strpos( $scoped, 'lstab-id="7"] from' ),
	'@keyframes steps are left alone, since prefixing them would break them',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( '@layer base, mine; @layer mine { td { color: red; } }', $css_selector );
lstab_assert(
	0 === strpos( $scoped, '@layer base, mine;@layer mine{' ),
	'A statement putting layers in order is kept, or the order would be lost',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( '@page { margin: 7cm; } @import url(x.css); @namespace svg url(x);', $css_selector );
lstab_assert( '' === $scoped, '@page, which would reach the whole printed page, is dropped with the other statements', $scoped );

$scoped = LSTABP_Custom_Css::scope( '@scope (.lstab-table) to (thead) { td { color: red; } } @scope { th { color: red; } }', $css_selector );
lstab_assert(
	0 === strpos( $scoped, '@scope (' . $css_scope . ' .lstab-table) to (thead){td{color: red;}}' )
		&& false !== strpos( $scoped, '@scope (' . $css_scope . '){th{color: red;}}' ),
	'@scope starts inside the table, and the rules within it are left to it',
	$scoped
);

// A pinned first column paints itself on a layer of its own, which a rule on
// the cell alone would never reach: the scoped CSS repeats any background for
// that layer, and only the background.
$css_pinned = ':is(&,&>td):is(.lstab-sticky-first tr:not(.lstab-detail)>:first-child:not(.lstab-painted))';

$scoped = LSTABP_Custom_Css::scope( 'tbody tr:nth-child(even) > td { background-color: #f5edff; color: red; }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, $css_scope . ' tbody tr:nth-child(even) > td{' . $css_pinned . '{background:none;&::before{width:100%;border-radius:inherit;background-image:none;background-color: #f5edff}' ),
	'A background on a cell or a row reaches the pinned column too',
	$scoped
);

// Reaching all the way to the line under it only where that is exact: at a
// fractional pixel density the pinned layer, placed on its own, would cover
// part of the line, so there it keeps the plugin's half-line gap.
lstab_assert(
	false !== strpos( $scoped, '@media (resolution:1dppx),(resolution:2dppx),(resolution:3dppx),(resolution:4dppx){&:is(td)::before{inset-block:0}}' ),
	'It reaches the row line only at whole pixel densities',
	$scoped
);
lstab_assert(
	1 === substr_count( $scoped, 'color: red' ),
	'But nothing else is copied to that layer',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'tbody tr:hover { background: url("a;b{c}.png") #fff; }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, 'background-image:none;background: url("a;b{c}.png") #fff}' ),
	'A quoted value is copied whole',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'tbody { tr:nth-child(2) > td { background: red; } td { @media (min-width: 1px) { background: blue; } } }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, $css_scope . ' tbody{tr:nth-child(2) > td{' . $css_pinned )
		&& false !== strpos( $scoped, 'td{@media (min-width: 1px){' . $css_pinned ),
	'A background in a nested rule, or a nested @media, reaches it as well',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'td::after { background: red; } td { color: blue; }', $css_selector );
lstab_assert(
	false === strpos( $scoped, 'lstab-sticky-first' ),
	'Rules for a pseudo-element and rules with no background add nothing',
	$scoped
);

$scoped = LSTABP_Custom_Css::scope( 'td::before { content: "🌿 Día"; }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, 'content: "\\1f33f  D\\ed a"' ) && ! preg_match( '/[^\x00-\x7F]/', $scoped ),
	'Letters beyond ASCII are written as CSS escapes, which an emoji script cannot turn into pictures',
	$scoped
);

$deep    = str_repeat( 'a{', 6000 ) . 'background:red' . str_repeat( '}', 6000 );
$started = microtime( true );
LSTABP_Custom_Css::scope( LSTABP_Custom_Css::sanitize( $deep ), $css_selector );
$took = microtime( true ) - $started;
lstab_assert( $took < 0.5, 'Nesting thousands deep cannot make every page view slow', sprintf( '%.2fs', $took ) );

lstab_assert(
	'' === LSTABP_Custom_Css::scope( 'body { display: none; }', '' ) || false === strpos( LSTABP_Custom_Css::scope( 'body { display: none }', $css_selector ), '^body' ),
	'A rule naming the page itself still only reaches inside the table',
	LSTABP_Custom_Css::scope( 'body { display: none }', $css_selector )
);

// Nothing typed here may leave the style block or fetch a stylesheet.
$dirty = LSTABP_Custom_Css::sanitize( "td { color: red }\n</style><script>alert(1)</script>" );
lstab_assert( false === strpos( $dirty, '</' ), 'The one sequence that could end a style block cannot stay', $dirty );

$dirty = LSTABP_Custom_Css::sanitize( 'td { background: url("data:image/svg+xml,<svg></svg>"); }' );
lstab_assert( false !== strpos( $dirty, '<svg><\\/svg>' ), 'It is escaped rather than cut, so an SVG drawn inline still draws', $dirty );

$dirty = LSTABP_Custom_Css::sanitize( '.lstab-scroll { scroll-behavior: smooth; overscroll-behavior-x: contain; behavior: url(x.htc); }' );
lstab_assert(
	false !== strpos( $dirty, 'scroll-behavior: smooth' ) && false !== strpos( $dirty, 'overscroll-behavior-x: contain' ) && false === strpos( $dirty, ' behavior:' ),
	'Old Internet Explorer behaviours are refused without touching scroll-behavior',
	$dirty
);

$dirty = LSTABP_Custom_Css::sanitize( "@import url('https://example.com/x.css');\ntd { color: red }" );
lstab_assert( false === stripos( $dirty, '@import' ), '@import is refused', $dirty );

$dirty = LSTABP_Custom_Css::sanitize( 'td { width: expression(alert(1)); }' );
lstab_assert( false === stripos( $dirty, 'expression(' ), 'Old IE script expressions are refused', $dirty );

lstab_assert(
	strlen( LSTABP_Custom_Css::sanitize( str_repeat( 'a', 40000 ) ) ) <= LSTABP_Custom_Css::MAX_LENGTH,
	'An enormous paste is cut to the stored maximum'
);

// And end to end: stored for the table, printed with it, scoped to it.
LSTABP_Custom_Css::store( $source_id, 'td { border-left: 3px solid #123456; }' );
$with_css = LSTABP_Custom_Css::for_source( $source_id );
lstab_assert(
	'td { border-left: 3px solid #123456; }' === $with_css,
	'The rules survive a round trip through the database',
	$with_css
);

$css_html = do_shortcode( '[tabliko id="' . $source_id . '"]' );
lstab_assert(
	false !== strpos( $css_html, '<style class="lstab-custom-css"' ),
	'The published table carries its own style block'
);
lstab_assert(
	false !== strpos( $css_html, ':is([data-lstab-id="' . $source_id . '"], #lstab-none) td{border-left: 3px solid #123456;}' ),
	'And the rule inside it names this table and nothing else',
	substr( $css_html, (int) strpos( $css_html, '<style' ), 200 )
);

// The same table twice on one page is one set of rules, not two.
LSTABP_Custom_Css::reset_printed();
$twice = do_shortcode( '[tabliko id="' . $source_id . '"]' ) . do_shortcode( '[tabliko id="' . $source_id . '"]' );
lstab_assert(
	1 === substr_count( $twice, 'lstab-custom-css' ),
	'The same table twice on a page prints its rules once',
	(string) substr_count( $twice, 'lstab-custom-css' )
);

LSTABP_Custom_Css::store( $source_id, '' );
LSTABP_Custom_Css::reset_printed();
$without_css = do_shortcode( '[tabliko id="' . $source_id . '"]' );
lstab_assert(
	false === strpos( $without_css, 'lstab-custom-css' ),
	'A table with no CSS of its own carries no style block at all'
);

// ---------------------------------------------------------------------------

ex_section( 'F2. Attacking the CSS field on purpose' );

/*
 * The field's whole safety argument is two sentences: nothing typed here can
 * end the style block, and nothing typed here can reach outside the table. Both
 * are attacked directly rather than demonstrated with a well-behaved example.
 */

$css_sel = LSTABP_Custom_Css::selector( 42 );

/**
 * Whether a stylesheet, once cleaned and scoped, could end its own style block.
 *
 * @param string $css Attempt.
 * @return bool
 */
function ex_escapes_style_block( $css ) {
	$block = LSTABP_Custom_Css::style_tag( 42, $css, false );

	// Everything after the opening tag is what the browser reads as CSS. If
	// "</" appears in it before the tag this code wrote, the block ended early
	// and whatever followed became markup.
	$inside = substr( $block, (int) strpos( $block, '>' ) + 1 );
	$inside = substr( $inside, 0, max( 0, strlen( $inside ) - strlen( '</style>' ) ) );

	return false !== stripos( $inside, '</' );
}

$attacks = array(
	'the plain attempt'                 => 'td{}</style><script>alert(1)</script>',
	'doubling up, so one pass rebuilds it' => 'td{}<</' . '/style><script>alert(1)</script>',
	'three deep'                        => 'td{}<<</' . '//style><img src=x onerror=alert(1)>',
	'hidden in a comment'               => 'td{ /* </' . 'style> */ color: red }',
	'split by a comment'                => 'td{}<' . '/*x*/' . '/style>',
	'hidden in a string'                => 'td{ content: "</' . 'style><script>alert(1)</script>" }',
	'in a selector'                     => '</' . 'style><script>alert(1)</script> td { color: red }',
	'upper case'                        => 'td{}<</' . '/STYLE><SCRIPT>alert(1)</SCRIPT>',
);

foreach ( $attacks as $label => $attempt ) {
	ex_assert( ! ex_escapes_style_block( $attempt ), "Cannot end the style block: {$label}" );
}

// Scope: every rule must carry the table's selector, whatever shape it arrives in.
$escapes = array(
	'a plain rule'            => 'body { display: none }',
	'a closing brace first'   => '} body { display: none }',
	'a brace inside a string' => 'td { content: "}" } body { display: none }',
	'an unknown at-rule'      => '@nonsense { body { display: none } }',
	'a nested media query'    => '@media screen { @media print { body { display: none } } }',
	'a comma-separated list'  => 'td, body { display: none }',
	'a comma inside :not()'   => 'td:not(a, b), body { display: none }',
	'a stray opening brace'   => 'td { color: red } { body { display: none } }',
);

foreach ( $escapes as $label => $attempt ) {
	$scoped = LSTABP_Custom_Css::scope( LSTABP_Custom_Css::sanitize( $attempt ), $css_sel );

	// Every rule in the result has to begin with the table's own selector.
	$loose = false;
	foreach ( explode( '}', $scoped ) as $chunk ) {
		if ( false === strpos( $chunk, '{' ) ) {
			continue;
		}

		$head = trim( substr( $chunk, 0, (int) strpos( $chunk, '{' ) ) );
		$head = trim( $head, '{' );

		if ( '' === $head || '@' === substr( ltrim( $head ), 0, 1 ) ) {
			continue;
		}

		// Split the way the code under test does: a comma inside :not() or
		// :is() belongs to that selector, it does not start a new one.
		$one   = '';
		$paren = 0;
		$list  = array();
		foreach ( str_split( $head ) as $letter ) {
			if ( '(' === $letter ) {
				$paren++;
			} elseif ( ')' === $letter ) {
				$paren = max( 0, $paren - 1 );
			} elseif ( ',' === $letter && 0 === $paren ) {
				$list[] = $one;
				$one    = '';
				continue;
			}
			$one .= $letter;
		}
		$list[] = $one;

		foreach ( $list as $selector_part ) {
			if ( 0 !== strpos( trim( $selector_part ), ':is(' . $css_sel . ', #lstab-none)' ) ) {
				$loose = true;
			}
		}
	}

	ex_assert( ! $loose, "Cannot reach outside the table: {$label}", $scoped );
}

// A rule with a brace inside a quoted value must survive intact, or the safety
// measure would be quietly corrupting ordinary stylesheets.
$quoted = LSTABP_Custom_Css::scope( 'td::after { content: "}"; color: red }', $css_sel );
ex_assert(
	false !== strpos( $quoted, 'content: "}"' ) && false !== strpos( $quoted, 'color: red' ),
	'A brace inside a quoted value is content, not the end of the rule',
	$quoted
);

$commented = LSTABP_Custom_Css::scope( 'td::after { content: "/* not a comment */" }', $css_sel );
ex_assert(
	false !== strpos( $commented, 'not a comment' ),
	'And "/*" inside a quoted value is not a comment either',
	$commented
);

// Fetching a stylesheet from somewhere else on every page view.
$imported = LSTABP_Custom_Css::sanitize( "@import url(https://example.com/x.css);\ntd{color:red}" );
ex_assert( false === stripos( $imported, 'example.com' ), '@import cannot pull in another site\'s stylesheet', $imported );

// A cut paste must not leave the stored value ending in half a character.
$long = LSTABP_Custom_Css::sanitize( str_repeat( 'ą', LSTABP_Custom_Css::MAX_LENGTH ) );
ex_assert(
	$long === wp_check_invalid_utf8( $long, true ) && '' !== $long,
	'An over-long paste is cut on a character boundary, not mid-letter'
);

// The live preview endpoint takes the selector from the caller, so it is the
// one place a scope could be chosen rather than given.
$css_rest = new WP_REST_Request( 'POST', '/tabliko-pro/v1/scoped-css' );
$css_rest->set_param( 'css', 'td { color: red }' );
$css_rest->set_param( 'selector', 'body' );
wp_set_current_user( 1 );
$css_reply = rest_do_request( $css_rest )->get_data();
ex_assert(
	false === strpos( (string) $css_reply['css'], 'body td' ),
	'The preview endpoint refuses a selector of the caller\'s choosing',
	(string) $css_reply['css']
);

$css_rest = new WP_REST_Request( 'POST', '/tabliko-pro/v1/scoped-css' );
$css_rest->set_param( 'css', 'td { color: red }' );
$css_rest->set_param( 'selector', '[data-lstab-preview="stage"]' );
wp_set_current_user( 0 );
$css_denied = rest_do_request( $css_rest );
ex_assert( $css_denied->is_error(), 'And a visitor cannot call it at all' );
wp_set_current_user( 1 );


// ---------------------------------------------------------------------------

lstab_section( 'H. Saved from the table screen, forgotten with the table' );

$_POST = array(
	'_lstabp_custom_css_present' => '1',
	'lstabp_custom_css'          => wp_slash( "th { letter-spacing: 0.04em; }\n</style><b>x</b>" ),
);
do_action( 'lstab_source_saved', $source_id );
$saved = LSTABP_Custom_Css::for_source( $source_id );
lstab_assert( 0 === strpos( $saved, 'th { letter-spacing: 0.04em; }' ), 'The field is saved for the table', $saved );
lstab_assert( false === strpos( $saved, '</' ), 'Cleaned on the way in', $saved );

$_POST = array( 'lstabp_custom_css' => 'td { color: red }' );
do_action( 'lstab_source_saved', $source_id );
lstab_assert( $saved === LSTABP_Custom_Css::for_source( $source_id ), 'A save from a form without the field leaves the rules alone' );

wp_set_current_user( 0 );
$_POST = array(
	'_lstabp_custom_css_present' => '1',
	'lstabp_custom_css'          => 'td { color: red }',
);
do_action( 'lstab_source_saved', $source_id );
lstab_assert( $saved === LSTABP_Custom_Css::for_source( $source_id ), 'Someone who may not write CSS cannot change it' );
wp_set_current_user( 1 );
$_POST = array();

ob_start();
do_action( 'lstab_edit_pane_cards', 'look', LSTAB_Storage::get( $source_id ), true );
$card = (string) ob_get_clean();
lstab_assert( false !== strpos( $card, 'id="lstabp-custom-css"' ), 'The table screen offers the field' );
lstab_assert( false !== strpos( $card, '[data-lstab-id=&quot;' . $source_id . '&quot;]' ), 'And names the selector its rules are given', $card );

ob_start();
do_action( 'lstab_edit_pane_cards', 'general', LSTAB_Storage::get( $source_id ), true );
lstab_assert( false === strpos( (string) ob_get_clean(), 'lstabp-custom-css' ), 'Only on the appearance tab' );

LSTAB_Storage::delete( $source_id );
lstab_assert( '' === LSTABP_Custom_Css::for_source( $source_id ), 'Deleting the table forgets its rules' );

echo "\n  {$GLOBALS['lstab_passed']} passed, {$GLOBALS['lstab_failed']} failed\n";
exit( $GLOBALS['lstab_failed'] ? 1 : 0 );
