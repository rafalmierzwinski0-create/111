<?php
/**
 * End-to-end tests for the Pro add-on.
 *
 * Google is never contacted: the mock mu-plugin answers oauth2.googleapis.com
 * and docs.google.com from fixtures, so the token exchange, refresh and
 * authenticated fetch all run their real code paths.
 *
 * Usage: php tests/pro-test.php /absolute/path/to/wp
 *
 * @package LiveSheetsTablePro\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/pro-test.php /path/to/wordpress\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8089';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

/*
 * The menu functions live in the admin, and this suite fires admin_menu from
 * the command line. WordPress 7.1 happens to have add_submenu_page() loaded by
 * then and 6.7 does not, so leaving this out passed on one version and died
 * with a fatal on the other — a difference in the test, not in the plugin,
 * which is exactly the kind of thing that makes a real difference hard to see.
 */
require_once $wp_root . '/wp-admin/includes/plugin.php';

$GLOBALS['lstab_passed'] = 0;
$GLOBALS['lstab_failed'] = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $label     Test name.
 * @param string $detail    Extra context on failure.
 * @return void
 */
function lstabp_assert( $condition, $label, $detail = '' ) {
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

/**
 * Section header.
 *
 * @param string $title Title.
 * @return void
 */
function lstabp_section( $title ) {
	echo "\n\033[1m{$title}\033[0m\n";
}

/**
 * Point the mock at a scenario.
 *
 * @param string $mode Mode name.
 * @param string $tab  Fixture tab.
 * @param string $oauth OAuth behaviour.
 * @return void
 */
function lstabp_set_mock( $mode = 'ok', $tab = 'main', $oauth = 'ok' ) {
	file_put_contents(
		WP_CONTENT_DIR . '/lstab-mock-state.json',
		wp_json_encode(
			array(
				'mode'  => $mode,
				'tab'   => $tab,
				'oauth' => $oauth,
			)
		)
	);
}

// ---------------------------------------------------------------------------

lstabp_section( '1. The add-on loads and lifts the limits' );

lstabp_assert( function_exists( 'lstabp' ), 'Pro plugin loaded' );
lstabp_assert( LSTAB_Limits::is_pro(), 'The tier is Pro' );
lstabp_assert( LSTAB_Limits::max_sources() > 3, 'The source limit is lifted', (string) LSTAB_Limits::max_sources() );
lstabp_assert( 60 === LSTAB_Limits::min_interval(), 'One minute syncing is unlocked', (string) LSTAB_Limits::min_interval() );
lstabp_assert( 9 === count( LSTAB_Styles::available() ), 'Premium presets are available', (string) count( LSTAB_Styles::available() ) );
lstabp_assert( 'midnight' === LSTAB_Styles::sanitize( 'midnight' ), 'A premium preset renders under Pro' );

// ---------------------------------------------------------------------------

lstabp_section( '2. Google connection' );

LSTABP_Google_Auth::save_client( 'test-client-id.apps.googleusercontent.com', 'test-secret' );
lstabp_assert( LSTABP_Google_Auth::has_client(), 'Client credentials are stored' );

$redirect = LSTABP_Google_Auth::redirect_uri();
lstabp_assert( false !== strpos( $redirect, 'admin-post.php' ), 'The redirect URI points at admin-post', $redirect );
lstabp_assert( 0 === strpos( $redirect, admin_url() ), 'The redirect URI is on this site' );

$consent = LSTABP_Google_Auth::consent_url( 'state-123' );
lstabp_assert( 0 === strpos( $consent, 'https://accounts.google.com/' ), 'Consent goes to Google', substr( $consent, 0, 40 ) );
lstabp_assert( false !== strpos( $consent, 'access_type=offline' ), 'Offline access is requested, or there is no refresh token' );
lstabp_assert( false !== strpos( $consent, 'prompt=consent' ), 'Consent is forced, which is what actually yields the refresh token' );
lstabp_assert( false !== strpos( $consent, 'spreadsheets.readonly' ), 'Only read access is requested' );
lstabp_assert( false === strpos( $consent, 'drive' ), 'No Drive-wide scope is requested' );
lstabp_assert( false !== strpos( $consent, 'state-123' ), 'The anti-forgery state is carried' );
lstabp_assert( false === strpos( $consent, 'test-secret' ), 'The client secret never appears in a browser-visible URL' );

// ---------------------------------------------------------------------------

lstabp_section( '3. Token exchange and refresh' );

lstabp_set_mock( 'ok', 'main', 'ok' );
delete_option( LSTABP_Google_Auth::OPTION_TOKEN );

$token = LSTABP_Google_Auth::exchange_code( 'fake-auth-code' );
lstabp_assert( ! is_wp_error( $token ), 'An authorisation code is exchanged for tokens', is_wp_error( $token ) ? $token->get_error_message() : '' );
lstabp_assert( ! empty( $token['refresh_token'] ), 'A refresh token is stored' );
lstabp_assert( $token['expires_at'] > time(), 'An expiry is recorded' );
lstabp_assert( LSTABP_Google_Auth::is_connected(), 'The account reads as connected' );

$stored = get_option( LSTABP_Google_Auth::OPTION_TOKEN );
lstabp_assert( is_array( $stored ) && ! empty( $stored['access_token'] ), 'The token survives in the database' );

// A fresh token must be reused rather than re-fetched.
$first  = LSTABP_Google_Auth::access_token();
$second = LSTABP_Google_Auth::access_token();
lstabp_assert( ! is_wp_error( $first ) && $first === $second, 'A valid token is reused, not refetched' );

// An expired one must refresh, and must keep the refresh token Google only
// hands out once.
$expired               = get_option( LSTABP_Google_Auth::OPTION_TOKEN );
$original_refresh      = $expired['refresh_token'];
$expired['expires_at'] = time() - 10;
update_option( LSTABP_Google_Auth::OPTION_TOKEN, $expired, false );

$refreshed = LSTABP_Google_Auth::access_token();
lstabp_assert( ! is_wp_error( $refreshed ), 'An expired token refreshes automatically', is_wp_error( $refreshed ) ? $refreshed->get_error_message() : '' );

$after = get_option( LSTABP_Google_Auth::OPTION_TOKEN );
lstabp_assert(
	$original_refresh === $after['refresh_token'],
	'The refresh token is kept when Google omits it from a refresh response',
	$after['refresh_token']
);
lstabp_assert( $after['expires_at'] > time(), 'The new expiry is in the future' );

// Failures must be reported, not swallowed.
lstabp_set_mock( 'ok', 'main', 'reject' );
$rejected = LSTABP_Google_Auth::refresh( 'stale-refresh-token' );
lstabp_assert( is_wp_error( $rejected ), 'A rejected refresh returns an error' );
lstabp_assert(
	false !== strpos( $rejected->get_error_message(), 'expired' ) || false !== strpos( $rejected->get_error_message(), 'Google' ),
	'The error explains what Google said',
	$rejected->get_error_message()
);

lstabp_set_mock( 'ok', 'main', 'down' );
$unreachable = LSTABP_Google_Auth::refresh( 'any' );
lstabp_assert( is_wp_error( $unreachable ), 'An unreachable Google returns an error rather than a fatal' );

lstabp_set_mock( 'ok', 'main', 'ok' );
LSTABP_Google_Auth::exchange_code( 'fake-auth-code' );

// ---------------------------------------------------------------------------

lstabp_section( '4. Private sheets' );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

$source_id = LSTAB_Storage::insert(
	array(
		'title'     => 'Prywatny cennik',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit',
		'sheet_id'  => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
	)
);

lstabp_assert( ! LSTABP_Private_Sheets::is_private( $source_id ), 'Sources are public unless marked otherwise' );

/*
 * Both paths now go to the same export endpoint, so the URL no longer tells
 * them apart. What separates them is the credential: a public sheet must be
 * fetched as the public does, with nothing attached.
 */
LSTABP_Private_Sheets::remember_source( array( 'id' => $source_id ) );
$public_url  = LSTAB_Url::csv_endpoint( 'SHEET', '0' );
$public_args = apply_filters( 'lstab_fetch_args', array( 'timeout' => 20 ), $public_url );
lstabp_assert( ! isset( $public_args['headers']['Authorization'] ), 'A public source is fetched without a token', wp_json_encode( $public_args ) );

LSTABP_Private_Sheets::set_private( $source_id, true );
lstabp_assert( LSTABP_Private_Sheets::is_private( $source_id ), 'A source can be marked private' );

LSTABP_Private_Sheets::remember_source( array( 'id' => $source_id ) );
$private_url = LSTAB_Url::csv_endpoint( 'SHEET', '7', 'doc' );
lstabp_assert( false !== strpos( $private_url, '/export' ), 'A private source switches to the authenticated export', $private_url );
lstabp_assert( false !== strpos( $private_url, 'gid=7' ), 'The tab is carried across', $private_url );

$args = apply_filters( 'lstab_fetch_args', array( 'timeout' => 20 ), $private_url );
lstabp_assert( isset( $args['headers']['Authorization'] ), 'A private request carries a bearer token' );
lstabp_assert( 0 === strpos( $args['headers']['Authorization'], 'Bearer ' ), 'The header is a bearer token', $args['headers']['Authorization'] );

// The whole point: a sheet with no public sharing still syncs.
lstabp_set_mock( 'private_only', 'main', 'ok' );
$synced = LSTAB_Sync::run( $source_id );
lstabp_assert( true === $synced, 'A sheet with link sharing switched off still syncs', is_wp_error( $synced ) ? $synced->get_error_message() : '' );

$source = LSTAB_Storage::get( $source_id );
lstabp_assert( 7 === $source['row_count'], 'The private sheet returned its rows', (string) $source['row_count'] );

// Without a connection it must fail honestly rather than silently.
delete_option( LSTABP_Google_Auth::OPTION_TOKEN );
LSTABP_Private_Sheets::remember_source( array( 'id' => $source_id ) );
$unauth = apply_filters( 'lstab_fetch_args', array( 'timeout' => 20 ), $private_url );
lstabp_assert( ! isset( $unauth['headers']['Authorization'] ), 'No token means no bogus Authorization header' );

$failed = LSTAB_Sync::run( $source_id );
lstabp_assert( is_wp_error( $failed ), 'A private sheet without a connection reports an error' );

$after_failure = LSTAB_Storage::get( $source_id );
lstabp_assert( 7 === $after_failure['row_count'], 'The last good copy survives the failure', (string) $after_failure['row_count'] );

lstabp_set_mock( 'ok', 'main', 'ok' );
LSTABP_Google_Auth::exchange_code( 'fake-auth-code' );
LSTABP_Private_Sheets::set_private( $source_id, false );
LSTAB_Sync::run( $source_id );

// ---------------------------------------------------------------------------

lstabp_section( '5. Filtered views' );

$parsed = LSTABP_Filters::parse( 'Kategoria=Rowery, Cena>=500' );
lstabp_assert( 2 === count( $parsed ), 'Two conditions are parsed', wp_json_encode( $parsed ) );
lstabp_assert( 'Kategoria' === $parsed[0]['column'] && '=' === $parsed[0]['operator'], 'The first condition is read correctly', wp_json_encode( $parsed[0] ) );
lstabp_assert( '>=' === $parsed[1]['operator'], 'A two-character operator is not read as one character', wp_json_encode( $parsed[1] ) );

// WordPress blanks a shortcode attribute containing an unclosed "<" as an XSS
// precaution, so a "<" only survives entity-encoded. Word operators are the
// documented form precisely because they always survive; both are checked.
lstabp_assert(
	'' === shortcode_parse_atts( ' filter="Cena netto<100"' )['filter'],
	'WordPress really does blank a raw "<" in a shortcode attribute',
	'if this ever changes, the word operators are still the safe form'
);

// Row counts below come from tests/fixtures/sheet-main.csv: seven rows, of
// which five are "W magazynie", one "Brak" and one "Na zamówienie".
$cases = array(
	'Dostępność=W magazynie'                    => 5,
	'Dostępność is W magazynie'                 => 5,
	'Dostępność=Brak'                           => 1,
	'Dostępność is Brak'                        => 1,
	'Dostępność!=W magazynie'                   => 2,
	'Dostępność not W magazynie'                => 2,
	'Produkt*=Kask'                             => 1,
	'Produkt has Kask'                          => 1,
	'Cena netto>1000'                           => 2,
	'Cena netto gt 1000'                        => 2,
	'Cena netto lt 100'                         => 3,
	'Cena netto>=349'                           => 3,
	'Cena netto gte 349'                        => 3,
	'Cena netto lte 89'                         => 3,
	'Dostępność is W magazynie, Cena netto lt 100' => 2,
	'dostępność IS w magazynie'                 => 5,
	'Nieistniejąca is cokolwiek'                => 7,
	''                                          => 7,
);

foreach ( $cases as $expression => $expected ) {
	// Both forms matter: editors store an attribute as typed, page builders
	// often store "<" and ">" as entities, and both must behave the same.
	foreach ( array( 'as typed' => $expression, 'entity encoded' => esc_attr( $expression ) ) as $form => $written ) {
		$html  = do_shortcode( '[sheet_table id="' . $source_id . '" filter="' . $written . '"]' );
		$count = substr_count( $html, '<tr role="row" class="lstab-row"' );

		lstabp_assert(
			$count === $expected,
			'Filter ' . ( '' === $expression ? '(none)' : $expression ) . " → {$expected} rows ({$form})",
			"got {$count}"
		);
	}
}

// A filter must not change the table itself, only which rows are in it.
$filtered_html = do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność=Brak"]' );
lstabp_assert( false !== strpos( $filtered_html, 'Produkt' ), 'A filtered table keeps its headings' );
lstabp_assert( false !== strpos( $filtered_html, 'lstab-scrollbar' ), 'A filtered table keeps the slider' );
lstabp_assert( false === strpos( $filtered_html, 'Kask Lazer' ), 'Rows that do not match are gone' );

// The same source, filtered differently, on two pages.
$bikes = substr_count( do_shortcode( '[sheet_table id="' . $source_id . '" filter="Cena netto>1000"]' ), '<tr role="row"' );
$cheap = substr_count( do_shortcode( '[sheet_table id="' . $source_id . '" filter="Cena netto<100"]' ), '<tr role="row"' );
lstabp_assert( $bikes !== $cheap, 'One source can feed two differently filtered pages', "{$bikes} vs {$cheap}" );

// Filters must resolve against a renamed column too, since that is the name
// the site owner sees in the dashboard.
LSTAB_Storage::update(
	$source_id,
	array(
		'columns_config' => array( 2 => array( 'label' => 'Status' ) ),
	)
);
$by_label = substr_count( do_shortcode( '[sheet_table id="' . $source_id . '" filter="Status=Brak"]' ), '<tr role="row" class="lstab-row"' );
lstabp_assert( 1 === $by_label, 'A filter matches a renamed column', (string) $by_label );

$by_source = substr_count( do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność=Brak"]' ), '<tr role="row" class="lstab-row"' );
lstabp_assert( 1 === $by_source, 'And still matches the original sheet heading', (string) $by_source );

// Filtering on a hidden column must still work: it is hidden from visitors,
// not from the site owner writing the shortcode.
LSTAB_Storage::update(
	$source_id,
	array(
		'columns_config' => array( 2 => array( 'hidden' => true ) ),
	)
);
$hidden_filter = do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność=Brak"]' );
lstabp_assert( false === strpos( $hidden_filter, 'Dostępność' ), 'The hidden column is not shown' );

// Asserting only that the column is absent is what let this break: filtering
// ran after the column had been removed, so the condition matched nothing and
// every row came through. The row count is the assertion that matters.
$hidden_rows = substr_count( $hidden_filter, '<tr role="row" class="lstab-row"' );
$all_rows    = substr_count( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), '<tr role="row" class="lstab-row"' );
lstabp_assert( 1 === $hidden_rows, 'A filter still selects rows by a column the table hides', (string) $hidden_rows );
lstabp_assert( $hidden_rows < $all_rows, 'Filtering on a hidden column is not a no-op', "{$hidden_rows} of {$all_rows}" );

LSTAB_Storage::update( $source_id, array( 'columns_config' => array() ) );

// ---------------------------------------------------------------------------

// The free plugin refuses to render a filtered table when nothing can honour
// the filter, rather than falling back to every row. This add-on is what says
// the ask can be met.
lstabp_assert( apply_filters( 'lstab_filter_supported', false ), 'The add-on announces that filtering is available' );
$honoured = do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność=Brak"]' );
lstabp_assert( 1 === substr_count( $honoured, '<tr role="row" class="lstab-row"' ), 'And the table renders its matching rows', (string) substr_count( $honoured, '<tr role="row" class="lstab-row"' ) );
lstabp_assert( false === strpos( $honoured, 'not active' ), 'With the add-on there is nothing to warn about' );

// ---------------------------------------------------------------------------

lstabp_section( '5b. Separating conditions' );

// Both "and" and a comma separate conditions, but either can just as easily be
// part of a value. A separator only separates when every piece it produces
// reads as a condition on its own.
$worded = LSTABP_Filters::parse( 'Kategoria is Rowery and Cena gt 2000' );
lstabp_assert( 2 === count( $worded ), '"and" separates two conditions', (string) count( $worded ) );
lstabp_assert( '>' === $worded[1]['operator'], 'The second condition keeps its operator', $worded[1]['operator'] );

$comma = LSTABP_Filters::parse( 'Kategoria is Rowery, Cena gt 2000' );
lstabp_assert( 2 === count( $comma ), 'A comma still separates where both halves read as conditions', (string) count( $comma ) );

$in_value = LSTABP_Filters::parse( 'Opis is Rama, widelec 120 mm' );
lstabp_assert( 1 === count( $in_value ), 'A comma inside a value does not split it', (string) count( $in_value ) );
lstabp_assert( 'Rama, widelec 120 mm' === $in_value[0]['value'], 'The comma is kept in the value', $in_value[0]['value'] );

$and_in_value = LSTABP_Filters::parse( 'Produkt is Rower and Kask' );
lstabp_assert( 1 === count( $and_in_value ), '"and" inside a value does not split it', (string) count( $and_in_value ) );
lstabp_assert( 'Rower and Kask' === $and_in_value[0]['value'], 'The word is kept in the value', $and_in_value[0]['value'] );

$negated = LSTABP_Filters::parse( 'Dostępność is not Brak' );
lstabp_assert( '!=' === $negated[0]['operator'], '"is not" reads as a negation', $negated[0]['operator'] );
lstabp_assert( 'Brak' === $negated[0]['value'], 'The negation does not swallow "not" into the value', $negated[0]['value'] );

// The block has the same reach as the shortcode; whoever builds pages with
// blocks should not have to drop to a raw shortcode to filter.
$block       = new LSTAB_Block();
$block_all   = substr_count( $block->render( array( 'sourceId' => $source_id ) ), '<tr role="row" class="lstab-row"' );
$block_some  = substr_count( $block->render( array( 'sourceId' => $source_id, 'filter' => 'Dostępność is Brak' ) ), '<tr role="row" class="lstab-row"' );
lstabp_assert( $block_some < $block_all, 'The block filters rows too', "{$block_some} of {$block_all}" );
lstabp_assert( 1 === $block_some, 'The block filter selects the same rows as the shortcode', (string) $block_some );

// ---------------------------------------------------------------------------

lstabp_section( '5c. Colour rules' );

// A rule set is stored per source, outside the free plugin's table.
update_option(
	LSTABP_Rules::OPTION,
	array(
		$source_id => array(
			array(
				'column'   => 'Dostępność',
				'operator' => '=',
				'value'    => 'Brak',
				'style'    => 'red',
				'scope'    => 'cell',
			),
			array(
				'column'   => 'Cena netto',
				'operator' => '>',
				'value'    => '1000',
				'style'    => 'bold',
				'scope'    => 'row',
			),
		),
	),
	false
);

$ruled = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false !== strpos( $ruled, 'background-color:#fbd5d5' ), 'A cell rule colours its cell' );
lstabp_assert( false !== strpos( $ruled, 'lstab-ruled' ), 'Styled cells are marked with a class as well' );

// The one "Brak" row, and only it.
lstabp_assert( 1 === substr_count( $ruled, 'background-color:#fbd5d5' ), 'Only matching cells are coloured', (string) substr_count( $ruled, 'background-color:#fbd5d5' ) );

// Two rows are over 1000, five columns each.
lstabp_assert( 10 === substr_count( $ruled, 'font-weight:700' ), 'A row rule reaches every cell in the row', (string) substr_count( $ruled, 'font-weight:700' ) );

// Colours belong in the HTML the visitor receives, not in a script that runs
// afterwards — the same promise the rest of the plugin makes.
lstabp_assert( false === strpos( $ruled, 'lstabp-rules.js' ), 'No script is needed to colour a table' );

/*
 * The same colour, worn as words instead of a fill. A column where most rows
 * are marked becomes a wall of colour when every one of them is filled in, and
 * then nothing stands out; colouring the words says the same thing quietly and
 * leaves the row's own background alone.
 */
update_option(
	LSTABP_Rules::OPTION,
	array(
		$source_id => array(
			array(
				'column'   => 'Dostępność',
				'operator' => '=',
				'value'    => 'Brak',
				'style'    => '#d9a441',
				'scope'    => 'text',
			),
		),
	),
	false
);

$worded = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false !== strpos( $worded, 'color:#d9a441' ), 'A words-only rule colours the words' );
lstabp_assert( false === strpos( $worded, 'background-color:#d9a441' ), 'And leaves the cell its own background' );
lstabp_assert( 1 === substr_count( $worded, 'color:#d9a441' ), 'On the matching cell alone', (string) substr_count( $worded, 'color:#d9a441' ) );
lstabp_assert( false !== strpos( $worded, 'lstab-ruled' ), 'Marked with the same class as any other rule' );

// The choice has to survive being stored, or it is a setting that forgets.
$kept = LSTABP_Rules::sanitize(
	array( array( 'column' => 'Dostępność', 'operator' => '=', 'value' => 'Brak', 'style' => '#d9a441', 'scope' => 'text' ) )
);
lstabp_assert( 'text' === $kept[0]['scope'], 'Words-only is a scope the plugin keeps', $kept[0]['scope'] );

// Bold has no background to leave out, so it reads the same either way.
lstabp_assert(
	LSTABP_Rules::css_for( 'bold', 'text' ) === LSTABP_Rules::css_for( 'bold', 'cell' ),
	'An effect means the same thing whichever way it is worn',
	LSTABP_Rules::css_for( 'bold', 'text' )
);

// And the swatch in the dashboard has to show the difference, or the setting is
// one nobody can check without saving and going to look.
lstabp_assert(
	false === strpos( LSTABP_Rules::css_for( '#d9a441', 'text' ), 'background-color' ),
	'The swatch for a words-only rule carries no fill',
	LSTABP_Rules::css_for( '#d9a441', 'text' )
);

delete_option( LSTABP_Rules::OPTION );
update_option(
	LSTABP_Rules::OPTION,
	array(
		$source_id => array(
			array( 'column' => 'Dostępność', 'operator' => '=', 'value' => 'Brak', 'style' => 'red', 'scope' => 'cell' ),
			array( 'column' => 'Cena netto', 'operator' => '>', 'value' => '1000', 'style' => 'bold', 'scope' => 'row' ),
		),
	),
	false
);

// A rule reads the sheet, so hiding a column changes what is on screen but not
// what the rule can see.
LSTAB_Storage::update( $source_id, array( 'columns_config' => array( 2 => array( 'hidden' => true ) ) ) );
$ruled_hidden = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false === strpos( $ruled_hidden, 'Dostępność' ), 'The column really is hidden' );
lstabp_assert( 8 === substr_count( $ruled_hidden, 'font-weight:700' ), 'A row rule still fires with a column hidden', (string) substr_count( $ruled_hidden, 'font-weight:700' ) );
LSTAB_Storage::update( $source_id, array( 'columns_config' => array() ) );

// Sanitising.
$dirty = LSTABP_Rules::sanitize(
	array(
		array( 'column' => '', 'operator' => '=', 'value' => 'x', 'style' => 'red' ),
		array( 'column' => 'Produkt', 'operator' => 'DROP TABLE', 'value' => 'x', 'style' => 'rainbow', 'scope' => 'planet' ),
	)
);
lstabp_assert( 1 === count( $dirty ), 'A row with no column chosen is not a rule', (string) count( $dirty ) );
lstabp_assert( '=' === $dirty[0]['operator'], 'An unknown comparison falls back to equality', $dirty[0]['operator'] );
lstabp_assert( LSTABP_Rules::DEFAULT_STYLE === $dirty[0]['style'], 'An unknown look falls back to a known one', $dirty[0]['style'] );
lstabp_assert( 'cell' === $dirty[0]['scope'], 'An unknown scope falls back to the cell', $dirty[0]['scope'] );

// The colours themselves: a palette, a colour of somebody's own, the two looks
// that are not a colour, and the five names the first version stored.
$lstabp_picked = LSTABP_Rules::sanitize(
	array(
		array( 'column' => 'Produkt', 'value' => 'a', 'style' => 'custom', 'custom' => '#7C3AED' ),
		array( 'column' => 'Produkt', 'value' => 'b', 'style' => 'custom', 'custom' => 'javascript:alert(1)' ),
		array( 'column' => 'Produkt', 'value' => 'c', 'style' => '#cfebd9' ),
		array( 'column' => 'Produkt', 'value' => 'd', 'style' => 'strike' ),
		array( 'column' => 'Produkt', 'value' => 'e', 'style' => 'amber' ),
		array( 'column' => 'Produkt', 'value' => 'f', 'style' => '#fff' ),
		array( 'column' => 'Produkt', 'value' => 'g', 'style' => '#e9f7ee' ),
	)
);
lstabp_assert( '#7c3aed' === $lstabp_picked[0]['style'], 'A colour of your own is kept, in lower case', $lstabp_picked[0]['style'] );
lstabp_assert( LSTABP_Rules::DEFAULT_STYLE === $lstabp_picked[1]['style'], 'Anything that is not a colour is not stored as one', $lstabp_picked[1]['style'] );
lstabp_assert( '#cfebd9' === $lstabp_picked[2]['style'], 'A palette colour is kept as itself', $lstabp_picked[2]['style'] );
lstabp_assert( 'strike' === $lstabp_picked[3]['style'], 'A look that is not a colour survives', $lstabp_picked[3]['style'] );
lstabp_assert( '#fbdcbc' === $lstabp_picked[4]['style'], 'A colour stored by its old name still means that colour', $lstabp_picked[4]['style'] );
lstabp_assert( '#ffffff' === $lstabp_picked[5]['style'], 'A three-digit colour is written out in full', $lstabp_picked[5]['style'] );
// A rule saved against the palette's own first, paler set moves to the swatch
// that replaced it rather than coming back as a colour off the palette.
lstabp_assert( '#cfebd9' === $lstabp_picked[6]['style'], 'A colour from the first palette moves to the one that replaced it', $lstabp_picked[6]['style'] );

/*
 * Text has to be readable on whatever background somebody picks, including the
 * ones nobody would pick on purpose. 4.5 to 1 is the guidelines' bar for body
 * text — but a mid-tone background is beyond help, since neither black nor
 * white clears the bar on it, so what is asked of those is only that the choice
 * is the best one available.
 */
$lstabp_luminance = static function ( $hex ) {
	$total   = 0.0;
	$weights = array( 0.2126, 0.7152, 0.0722 );

	foreach ( array( 1, 3, 5 ) as $lstabp_at => $lstabp_offset ) {
		$lstabp_channel = hexdec( substr( $hex, $lstabp_offset, 2 ) ) / 255;
		$lstabp_channel = $lstabp_channel <= 0.03928
			? $lstabp_channel / 12.92
			: pow( ( $lstabp_channel + 0.055 ) / 1.055, 2.4 );
		$total         += $weights[ $lstabp_at ] * $lstabp_channel;
	}

	return $total;
};

$lstabp_ratio = static function ( $one, $two ) use ( $lstabp_luminance ) {
	$first  = $lstabp_luminance( $one );
	$second = $lstabp_luminance( $two );

	return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
};

$lstabp_worst = 21.0;
$lstabp_thin  = 0;

mt_srand( 20260101 );
$lstabp_trials = array_keys( LSTABP_Rules::palette() );

for ( $lstabp_i = 0; $lstabp_i < 400; $lstabp_i++ ) {
	$lstabp_trials[] = sprintf( '#%06x', mt_rand( 0, 0xffffff ) );
}

foreach ( $lstabp_trials as $lstabp_bg ) {
	$lstabp_seen = $lstabp_ratio( $lstabp_bg, LSTABP_Rules::ink( $lstabp_bg ) );

	// The best any text colour could manage on this background.
	$lstabp_best = max( $lstabp_ratio( $lstabp_bg, '#000000' ), $lstabp_ratio( $lstabp_bg, '#ffffff' ) );

	if ( $lstabp_best >= 4.5 ? $lstabp_seen < 4.5 : $lstabp_seen < $lstabp_best - 0.05 ) {
		$lstabp_thin++;
	}

	$lstabp_worst = min( $lstabp_worst, $lstabp_seen );
}

lstabp_assert( 0 === $lstabp_thin, 'Every colour gets text as readable as that colour allows', (string) $lstabp_thin );

foreach ( LSTABP_Rules::palette() as $lstabp_hex => $lstabp_name ) {
	lstabp_assert(
		$lstabp_ratio( $lstabp_hex, LSTABP_Rules::ink( $lstabp_hex ) ) >= 4.5,
		"The palette's {$lstabp_name} is readable",
		sprintf( '%.2f', $lstabp_ratio( $lstabp_hex, LSTABP_Rules::ink( $lstabp_hex ) ) )
	);
	lstabp_assert(
		LSTABP_Rules::ink( $lstabp_hex ) !== $lstabp_hex,
		"The palette's {$lstabp_name} does not print itself on itself"
	);
}

// A colour cannot carry anything out of the attribute it is written into.
lstabp_assert(
	false === strpos( LSTABP_Rules::css_for( '#fff" onmouseover="x' ), 'onmouseover' ),
	'A colour that is not one cannot escape the style attribute',
	LSTABP_Rules::css_for( '#fff" onmouseover="x' )
);

$flood = LSTABP_Rules::sanitize( array_fill( 0, 50, array( 'column' => 'Produkt', 'value' => 'x' ) ) );
lstabp_assert( LSTABP_Rules::MAX_RULES === count( $flood ), 'The number of rules is capped', (string) count( $flood ) );

// Every look has to render as CSS the browser will accept, or a typo here
// would silently colour nothing.
foreach ( LSTABP_Rules::styles() as $lstabp_key => $lstabp_style ) {
	lstabp_assert( '' !== $lstabp_style['label'], "Style {$lstabp_key} is named" );
	lstabp_assert( (bool) preg_match( '~^[a-z-]+:[^;]+;$~', str_replace( ' ', '', $lstabp_style['css'] ) ) || substr_count( $lstabp_style['css'], ';' ) > 1, "Style {$lstabp_key} is a declaration list", $lstabp_style['css'] );
	lstabp_assert( false === strpos( $lstabp_style['css'], '"' ), "Style {$lstabp_key} cannot break out of the attribute" );
}

// Deleting a source takes its rules with it, rather than leaving them to be
// inherited by whatever is created next.
$throwaway = LSTAB_Storage::insert(
	array(
		'title'     => 'Do usunięcia',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/ZZZ/edit#gid=0',
		'sheet_id'  => 'ZZZ',
	)
);
$stored                = LSTABP_Rules::all();
$stored[ $throwaway ]  = array( array( 'column' => 'Produkt', 'operator' => '=', 'value' => 'x', 'style' => 'red', 'scope' => 'cell' ) );
update_option( LSTABP_Rules::OPTION, $stored, false );
lstabp_assert( ! empty( LSTABP_Rules::for_source( $throwaway ) ), 'The throwaway source has a rule to lose' );
LSTAB_Storage::delete( $throwaway );
lstabp_assert( array() === LSTABP_Rules::for_source( $throwaway ), 'Deleting a source deletes its rules' );

// The preview on the source screen has to show what a visitor will see, or the
// person setting up the rules is checking their work against the wrong table.
$previewed = LSTAB_Renderer::render_preview(
	array(
		'headers' => array( 'Produkt', 'Dostępność' ),
		'rows'    => array( array( 'Kask', 'Brak' ) ),
	),
	array( 'source_id' => $source_id )
);
lstabp_assert( false !== strpos( $previewed, 'background-color:#fbd5d5' ), 'The admin preview applies the colour rules' );

$anonymous = LSTAB_Renderer::render_preview(
	array(
		'headers' => array( 'Produkt', 'Dostępność' ),
		'rows'    => array( array( 'Kask', 'Brak' ) ),
	)
);
lstabp_assert( false === strpos( $anonymous, 'background-color:#fbd5d5' ), 'A preview of no particular source has no rules to apply' );

// A save from a screen that never showed the card must leave the rules alone.
// The card is disabled until a sheet has been read, and its fields then submit
// nothing at all — which is indistinguishable from "every rule was removed"
// unless the form says so explicitly.
update_option(
	LSTABP_Rules::OPTION,
	array( $source_id => array( array( 'column' => 'Produkt', 'operator' => '=', 'value' => 'x', 'style' => 'red', 'scope' => 'cell' ) ) ),
	false
);
$lstabp_rules_saver = new LSTABP_Rules();
unset( $_POST['_lstabp_rules_present'], $_POST['lstabp_rules'] );
$lstabp_rules_saver->save( $source_id );
lstabp_assert( 1 === count( LSTABP_Rules::for_source( $source_id ) ), 'A save without the card leaves the rules alone', (string) count( LSTABP_Rules::for_source( $source_id ) ) );

$_POST['_lstabp_rules_present'] = '1';
$_POST['lstabp_rules']          = array();
$lstabp_rules_saver->save( $source_id );
lstabp_assert( array() === LSTABP_Rules::for_source( $source_id ), 'A save from the card clears rules the user removed' );
unset( $_POST['_lstabp_rules_present'], $_POST['lstabp_rules'] );

delete_option( LSTABP_Rules::OPTION );

// ---------------------------------------------------------------------------

lstabp_section( '5c2. Rules meeting the rest of the plugin' );

/*
 * A drawer belongs to the row above it, so a rule that painted the row has to
 * reach the panel that opens under it. It did not: a row picked out in red
 * opened onto a white box.
 */
$rule_config = LSTAB_Storage::get( $source_id )['columns_config'];
$rule_config[3]['detail'] = true;
LSTAB_Storage::update( $source_id, array( 'columns_config' => $rule_config ) );

update_option(
	LSTABP_Rules::OPTION,
	array(
		$source_id => array(
			array(
				'column'   => 'Dostępność',
				'operator' => '=',
				'value'    => 'Brak',
				'style'    => 'grey',
				'scope'    => 'row',
			),
		),
	),
	false
);

$drawer_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );

lstabp_assert( false !== strpos( $drawer_html, 'lstab-detail' ), 'The table has drawers to colour' );
lstabp_assert(
	(bool) preg_match( '#<tr class="lstab-detail"[^>]*class="lstab-ruled"[^>]*style=#', $drawer_html )
		|| (bool) preg_match( '#<tr class="lstab-detail"[^>]*style="[^"]*background#', $drawer_html ),
	'A row picked out by a rule opens onto a drawer of the same colour',
	substr( $drawer_html, (int) strpos( $drawer_html, 'lstab-detail' ) - 40, 260 )
);

// And a row nobody picked out keeps a plain drawer.
lstabp_assert(
	substr_count( $drawer_html, '<tr class="lstab-detail"' ) > substr_count( $drawer_html, 'lstab-detail lstab-ruled' ),
	'A row no rule matched keeps its drawer plain'
);

$rule_config[3]['detail'] = false;
LSTAB_Storage::update( $source_id, array( 'columns_config' => $rule_config ) );

/*
 * Renaming a heading in Google used to kill a rule twice over: it stopped
 * colouring anything, and its column no longer matched any option in the card,
 * so the next save deleted it without a word.
 */
$rule_source = LSTAB_Storage::get( $source_id );
$rule_data   = $rule_source['data'];
$was_heading = $rule_data['headers'][2];

$rule_data['headers'][2] = 'Stan magazynowy';
LSTAB_Storage::record_success( $source_id, $rule_data );

ob_start();
$rules   = LSTABP_Rules::for_source( $source_id );
$headers = LSTAB_Storage::get( $source_id )['data']['headers'];
$is_edit = true;
require LSTABP_PATH . 'includes/views/rules-card.php';
$rules_html = (string) ob_get_clean();

lstabp_assert( false !== strpos( $rules_html, 'lstabp-rules-orphans' ), 'The card says a rule names a column that is gone' );
lstabp_assert( false !== strpos( $rules_html, $was_heading ), 'And names the heading it is looking for', $was_heading );
lstabp_assert(
	(bool) preg_match( '#<option value="' . preg_quote( $was_heading, '#' ) . '" selected>#', $rules_html ),
	'The missing column stays selected, so saving does not silently delete the rule'
);

// Prove that: run the save the form would run, with what the form would send.
$_POST['_lstabp_rules_present'] = '1';
$_POST['lstabp_rules']          = array(
	array(
		'column'   => $was_heading,
		'operator' => '=',
		'value'    => 'Brak',
		'style'    => 'grey',
		'scope'    => 'row',
	),
);
do_action( 'lstab_source_saved', $source_id );
unset( $_POST['_lstabp_rules_present'], $_POST['lstabp_rules'] );

lstabp_assert(
	1 === count( LSTABP_Rules::for_source( $source_id ) ),
	'Saving the screen keeps the rule rather than dropping it',
	(string) count( LSTABP_Rules::for_source( $source_id ) )
);

// Put the sheet back and check the rule starts working again on its own.
$rule_data['headers'][2] = $was_heading;
LSTAB_Storage::record_success( $source_id, $rule_data );
lstabp_assert(
	false !== strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'lstab-ruled' ),
	'And it colours again the moment the heading comes back'
);

delete_option( LSTABP_Rules::OPTION );

lstabp_section( '5d. Taking the table away' );

update_option( LSTABP_Export::OPTION, array( $source_id => true ), true );

$with_buttons = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false !== strpos( $with_buttons, 'lstabp-export-button' ), 'A table can offer a download and a print button' );
lstabp_assert( false !== strpos( $with_buttons, 'lstabp_export' ), 'The download is an ordinary link' );

update_option( LSTABP_Export::OPTION, array(), true );
lstabp_assert( false === strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'lstabp-export-button' ), 'And does not when it is switched off' );
update_option( LSTABP_Export::OPTION, array( $source_id => true ), true );

/*
 * The file has to hold what the page held. An export that reached past the
 * filter would turn a page built to show one category into a way of reading
 * the whole sheet.
 */
$export_all      = LSTAB_Renderer::prepare( LSTAB_Storage::get( $source_id ), array() );
$export_filtered = LSTAB_Renderer::prepare( LSTAB_Storage::get( $source_id ), array( 'filter' => 'Dostępność is Brak' ) );
lstabp_assert( count( $export_filtered['rows'] ) < count( $export_all['rows'] ), 'A filtered table exports only its own rows', count( $export_filtered['rows'] ) . ' of ' . count( $export_all['rows'] ) );
lstabp_assert( 1 === count( $export_filtered['rows'] ), 'Exactly the rows the page showed', (string) count( $export_filtered['rows'] ) );

// A column left out of the table is left out of the file too.
LSTAB_Storage::update( $source_id, array( 'columns_config' => array( 2 => array( 'hidden' => true ) ) ) );
$export_hidden = LSTAB_Renderer::prepare( LSTAB_Storage::get( $source_id ), array() );
lstabp_assert( ! in_array( 'Dostępność', $export_hidden['headers'], true ), 'A hidden column is not in the file', wp_json_encode( $export_hidden['headers'] ) );
LSTAB_Storage::update( $source_id, array( 'columns_config' => array() ) );

// A row taken out of the table is out of the file too. The competitor this
// plugin exists to improve on has this exact complaint on its support forum:
// hidden things reappearing in the download.
$hidden_row = LSTAB_Storage::get( $source_id )['data']['rows'][0];
$hidden_key = LSTAB_Hidden_Rows::key_for( $hidden_row );
LSTAB_Storage::update( $source_id, array( 'hidden_rows' => array( LSTAB_Hidden_Rows::entry_for( $hidden_row, 0 ) ) ) );
$export_rows = LSTAB_Renderer::prepare( LSTAB_Storage::get( $source_id ), array() );
$exported    = wp_json_encode( $export_rows['rows'] );
lstabp_assert( false === strpos( (string) $exported, $hidden_key ), 'A hidden row is not in the file either', (string) $exported );
lstabp_assert( count( $export_rows['rows'] ) === count( $export_all['rows'] ) - 1, 'And the file is exactly one row shorter' );
LSTAB_Storage::update( $source_id, array( 'hidden_rows' => array() ) );

// The link is signed, or the filter could be edited out in the address bar.
$signed = LSTABP_Export::signature( $source_id, 'Dostępność is Brak' );
lstabp_assert( '' !== $signed, 'Download links carry a signature' );
lstabp_assert( ! hash_equals( $signed, LSTABP_Export::signature( $source_id, '' ) ), 'Changing the filter invalidates it' );
lstabp_assert( ! hash_equals( $signed, LSTABP_Export::signature( $source_id + 1, 'Dostępność is Brak' ) ), 'So does changing the source' );

// Deleting a source takes the setting with it.
$export_throwaway = LSTAB_Storage::insert(
	array(
		'title'     => 'Do usunięcia',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/YYY/edit#gid=0',
		'sheet_id'  => 'YYY',
	)
);
update_option( LSTABP_Export::OPTION, array( $source_id => true, $export_throwaway => true ), true );
LSTAB_Storage::delete( $export_throwaway );
lstabp_assert( ! LSTABP_Export::is_enabled( $export_throwaway ), 'Deleting a source forgets its export setting' );

delete_option( LSTABP_Export::OPTION );

lstabp_section( '5e. The Excel file itself' );

lstabp_assert( LSTABP_Xlsx::is_available(), 'This server can build Excel files' );

$xlsx_path = LSTABP_Xlsx::build(
	array( 'Usługa', 'Cena', 'Uwagi' ),
	array(
		array( 'Serwis', '1 215,50', 'Uwaga „z cudzysłowem” & znakiem' ),
		array( 'Kask', '89,00', '' ),
		array( 'Zestaw', '12,00 zł', 'Cena z walutą' ),
	),
	'Cennik'
);

lstabp_assert( ! is_wp_error( $xlsx_path ) && file_exists( $xlsx_path ), 'A workbook is written' );

$xlsx = new ZipArchive();
lstabp_assert( true === $xlsx->open( $xlsx_path ), 'And it is a readable zip' );

$sheet_xml = (string) $xlsx->getFromName( 'xl/worksheets/sheet1.xml' );
lstabp_assert( '' !== $sheet_xml, 'It holds a worksheet' );
lstabp_assert( false !== $xlsx->getFromName( '[Content_Types].xml' ), 'And the parts list every reader opens first' );
lstabp_assert( false !== $xlsx->getFromName( 'xl/workbook.xml' ), 'And a workbook' );
lstabp_assert( false !== $xlsx->getFromName( 'xl/styles.xml' ), 'And a stylesheet' );

// The XML has to be well formed, or Excel refuses the file outright rather
// than showing what it can.
$xlsx_dom = simplexml_load_string( $sheet_xml );
lstabp_assert( false !== $xlsx_dom, 'The worksheet XML parses' );

lstabp_assert(
	false !== strpos( $sheet_xml, '<c r="B2"><v>1215.5</v></c>' ),
	'A Polish price arrives as a number, not as text',
	$sheet_xml
);
lstabp_assert(
	false !== strpos( $sheet_xml, 'Cena z walutą' ) && false === strpos( $sheet_xml, '<v>12</v>' ),
	'A price written with its currency keeps the currency, and stays text',
	$sheet_xml
);
lstabp_assert(
	false !== strpos( $sheet_xml, 'Uwaga „z cudzysłowem” &amp; znakiem' ),
	'An ampersand in a cell is escaped rather than breaking the file',
	$sheet_xml
);
lstabp_assert(
	false !== strpos( $sheet_xml, 'ySplit="1"' ),
	'The heading row is frozen, so it stays put while scrolling'
);
lstabp_assert(
	false !== strpos( $sheet_xml, '<c r="A1" s="1"' ),
	'And drawn in the heading style',
	$sheet_xml
);

$workbook_xml = (string) $xlsx->getFromName( 'xl/workbook.xml' );
lstabp_assert( false !== strpos( $workbook_xml, 'name="Cennik"' ), 'The sheet tab is named after the table', $workbook_xml );

$xlsx->close();
wp_delete_file( $xlsx_path );

// A control character pasted into a sheet must not cost the whole file.
$xlsx_path = LSTABP_Xlsx::build( array( 'A' ), array( array( "one\x07two" ) ), 'Odd/Name: with * bad [chars] that Excel refuses outright' );
$xlsx      = new ZipArchive();
$xlsx->open( $xlsx_path );
$odd_sheet    = (string) $xlsx->getFromName( 'xl/worksheets/sheet1.xml' );
$odd_workbook = (string) $xlsx->getFromName( 'xl/workbook.xml' );
lstabp_assert( false !== simplexml_load_string( $odd_sheet ), 'A control character in a cell does not break the file' );
lstabp_assert( false !== strpos( $odd_sheet, 'onetwo' ), 'It is dropped, and the text around it kept', $odd_sheet );
lstabp_assert(
	false === strpos( $odd_workbook, '/' ) || false === strpos( $odd_workbook, 'name="Odd/' ),
	'A tab name Excel would refuse is cleaned',
	$odd_workbook
);
lstabp_assert(
	(bool) preg_match( '#name="([^"]{1,31})"#u', $odd_workbook ),
	'And cut to the 31 characters Excel allows',
	$odd_workbook
);
$xlsx->close();
wp_delete_file( $xlsx_path );

// Column letters, which nothing else would notice going wrong until a sheet
// grew past twenty-six columns.
lstabp_assert( 'A' === LSTABP_Xlsx::column_name( 0 ), 'Column 1 is A' );
lstabp_assert( 'Z' === LSTABP_Xlsx::column_name( 25 ), 'Column 26 is Z' );
lstabp_assert( 'AA' === LSTABP_Xlsx::column_name( 26 ), 'Column 27 is AA' );
lstabp_assert( 'AB' === LSTABP_Xlsx::column_name( 27 ), 'Column 28 is AB' );
lstabp_assert( 'BA' === LSTABP_Xlsx::column_name( 52 ), 'Column 53 is BA' );

// And the button that leads to it.
update_option( LSTABP_Export::OPTION, array( $source_id => true ), true );
$xlsx_buttons = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false !== strpos( $xlsx_buttons, 'format=xlsx' ), 'The table offers an Excel download' );
lstabp_assert( false !== strpos( $xlsx_buttons, 'format=csv' ), 'And still offers CSV beside it' );
delete_option( LSTABP_Export::OPTION );

lstabp_section( '5e2. Attacking the download on purpose' );

/*
 * A downloaded table is opened in a spreadsheet, and a spreadsheet runs what
 * looks like a formula. The sheet behind a table is not necessarily written
 * only by people the site owner trusts — a sheet fed by a form holds whatever
 * somebody typed into it — so what leaves here has to be inert.
 */
$formulas = array(
	'a DDE command'        => "=cmd|'/c calc'!A0",
	'a plain formula'      => '=1+1',
	'a plus'               => '+1+1',
	'an at sign'           => '@SUM(1+1)',
	'a hyperlink that phones home' => '=HYPERLINK("https://example.com/?"&A1,"Faktura")',
	'a leading tab'        => "\tcmd",
);

foreach ( $formulas as $label => $attempt ) {
	$defused = LSTABP_Export::defuse( $attempt );
	lstabp_assert(
		0 === strpos( $defused, "'" ),
		"A cell is not left runnable: {$label}",
		$defused
	);
}

// And the ordinary values it must not touch, or a price list stops adding up.
$untouched = array( '-15', '-1 215,50', '120,00', 'Rower górski', '', '+48 600 700 800' );
foreach ( $untouched as $plain ) {
	lstabp_assert(
		$plain === LSTABP_Export::defuse( $plain ),
		'An ordinary value is left exactly as it is: ' . ( '' === $plain ? '(empty)' : $plain ),
		LSTABP_Export::defuse( $plain )
	);
}

// The same, through the real endpoint rather than the helper.
LSTAB_Storage::update(
	$source_id,
	array()
);
$attack_source = LSTAB_Storage::insert(
	array(
		'title'     => 'Formuły',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/ZZZ/edit#gid=0',
		'sheet_id'  => 'ZZZ',
	)
);
LSTAB_Storage::record_success(
	$attack_source,
	array(
		'headers' => array( 'Nazwa', 'Cena' ),
		'rows'    => array(
			array( "=cmd|'/c calc'!A0", '-15' ),
			array( 'Rower', '1 215,50' ),
		),
	)
);
update_option( LSTABP_Export::OPTION, array( $attack_source => true ), true );

$prepared_attack = LSTAB_Renderer::prepare( LSTAB_Storage::get( $attack_source ), array() );

// phpcs:ignore WordPress.WP.AlternativeFunctions
$csv_handle = fopen( 'php://memory', 'w+' );
fputcsv( $csv_handle, array_map( array( 'LSTABP_Export', 'defuse' ), $prepared_attack['headers'] ), ',', '"', '' );
foreach ( $prepared_attack['rows'] as $attack_row ) {
	fputcsv( $csv_handle, array_map( array( 'LSTABP_Export', 'defuse' ), (array) $attack_row ), ',', '"', '' );
}
rewind( $csv_handle );
$csv_text = (string) stream_get_contents( $csv_handle );
fclose( $csv_handle );

lstabp_assert( false === strpos( $csv_text, '"=cmd' ), 'The written file has no cell starting with =', $csv_text );
lstabp_assert( false !== strpos( $csv_text, "'=cmd" ), 'It is marked as text instead', $csv_text );
lstabp_assert( false !== strpos( $csv_text, '-15' ) && false === strpos( $csv_text, "'-15" ), 'And a negative number is untouched', $csv_text );

// The Excel file has no formulas at all: a value is written as text or as a
// number, and neither is a formula in that format.
$attack_xlsx = LSTABP_Xlsx::build( $prepared_attack['headers'], $prepared_attack['rows'], 'Formuły' );
$attack_zip  = new ZipArchive();
$attack_zip->open( $attack_xlsx );
$attack_sheet = (string) $attack_zip->getFromName( 'xl/worksheets/sheet1.xml' );
$attack_zip->close();
wp_delete_file( $attack_xlsx );

lstabp_assert( false === strpos( $attack_sheet, '<f>' ), 'The Excel file holds no formula cells', $attack_sheet );
lstabp_assert(
	false !== strpos( $attack_sheet, 'cmd|' ) && false !== strpos( $attack_sheet, 't="inlineStr"' ),
	'The text is carried through as text',
	$attack_sheet
);
lstabp_assert( false !== strpos( $attack_sheet, '<v>-15</v>' ), 'And a negative number is still a number', $attack_sheet );

// The link is signed. Every way of editing it must be refused.
$attack_sig = LSTABP_Export::signature( $attack_source, '' );
lstabp_assert( ! hash_equals( $attack_sig, LSTABP_Export::signature( $attack_source, 'Cena is 1' ) ), 'A filter cannot be added to a signed link' );
lstabp_assert( ! hash_equals( $attack_sig, LSTABP_Export::signature( $attack_source + 1, '' ) ), 'And the link cannot be pointed at another table' );

// A table that does not offer downloads must not serve one, signature or not.
update_option( LSTABP_Export::OPTION, array(), true );
lstabp_assert( ! LSTABP_Export::is_enabled( $attack_source ), 'A table with downloads switched off refuses them even with a valid signature' );

LSTAB_Storage::delete( $attack_source );
delete_option( LSTABP_Export::OPTION );

lstabp_section( '5f. Pointing at what you want gone' );

// With the add-on running, hiding is honoured outright rather than on borrowed
// time, and the countdown notice has nothing to count.
lstabp_assert( LSTAB_Limits::pro_effective(), 'Choices are honoured while the add-on is running' );
ob_start();
LSTAB_Admin::print_grace_notice();
lstabp_assert( '' === trim( (string) ob_get_clean() ), 'And no countdown is shown' );


$picker_source = LSTAB_Storage::get( $source_id );
$picker        = new LSTABP_Picker();

ob_start();
$picker->render_card( $picker_source, true );
$card = (string) ob_get_clean();

lstabp_assert( false !== strpos( $card, 'lstabp-picker' ), 'The add-on prints the picker on the source screen' );
lstabp_assert( false !== strpos( $card, 'data-lstabp-row=' ), 'Every row is clickable' );
lstabp_assert( false !== strpos( $card, 'data-lstabp-column=' ), 'So is every heading' );
lstabp_assert( false !== strpos( $card, 'name="hidden_rows[]"' ) || false !== strpos( $card, 'lstabp-hidden-rows-fields' ), 'What it collects is inside the form' );
lstabp_assert( false !== strpos( $card, '_lstab_hidden_rows_present' ), 'And it says it was there, so a save elsewhere cannot wipe the list' );

// Adding a source has nothing to point at yet.
ob_start();
$picker->render_card( null, false );
lstabp_assert( '' === trim( (string) ob_get_clean() ), 'Nothing is printed while a source is being added' );

/*
 * A row is remembered by the line it is on, so two rows that say exactly the
 * same thing are still two separate choices. Remembering what the row said
 * instead made them one, and taking out a duplicate quietly took its twin out
 * of the table as well, somewhere else entirely.
 */
$twins = array(
	array( 'Kask', '100', 'W magazynie' ),
	array( 'Kask', '120', 'Brak' ),
	array( 'Kask', '100', 'W magazynie' ),
);

$twin_source                = $picker_source;
$twin_source['data']        = array(
	'headers' => array( 'Produkt', 'Cena', 'Stan' ),
	'rows'    => $twins,
	'offset'  => 1,
);
$twin_source['hidden_rows'] = array( LSTAB_Hidden_Rows::entry_for( $twins[0], 0 ) );

$left = LSTAB_Hidden_Rows::filter_rows( $twins, $twin_source['data']['headers'], $twin_source, array() );
lstabp_assert( 2 === count( $left ), 'One row is taken out, not everything that reads like it', (string) count( $left ) );
lstabp_assert( array( 'Kask', '100', 'W magazynie' ) === $left[1], 'Its identical twin further down the sheet stays', wp_json_encode( $left ) );

/*
 * Which means the screen has to say which line each choice is on: "Kask · 100"
 * is not an answer to "which one did I take out?" when two rows say it.
 */
ob_start();
$picker->render_card( $twin_source, true );
$twin_card = (string) ob_get_clean();
lstabp_assert( false !== strpos( $twin_card, 'data-lstabp-line="2"' ), 'The screen names the line each choice is on' );
lstabp_assert( false !== strpos( $twin_card, 'data-lstabp-line="4"' ), 'And numbers every row the way Google does' );
lstabp_assert( false !== strpos( $twin_card, 'data-lstabp-present="1"' ), 'A choice matching its line is marked as working' );

// Said before anything is clicked: moving the row or the column in Google is
// what breaks a choice, and that is worth knowing in advance.
lstabp_assert( false !== strpos( $twin_card, 'lstabp-picker-note' ), 'The screen warns that moving things in Google undoes a choice' );

/*
 * A choice whose line no longer holds what it did is kept and shown as
 * dormant. Dropping it would be the plugin deciding on someone's behalf that
 * they no longer want that row hidden; keeping it means the choice starts
 * working again the moment the sheet is put back.
 */
$orphan_source                = $twin_source;
$orphan_source['hidden_rows'] = array(
	array(
		'index' => 0,
		'name'  => 'Coś, czego już nie ma',
		'sig'   => str_repeat( 'b', 32 ),
		'label' => 'Coś, czego już nie ma',
	),
);
ob_start();
$picker->render_card( $orphan_source, true );
$orphan_card = (string) ob_get_clean();
lstabp_assert( false !== strpos( $orphan_card, 'data-lstabp-present="0"' ), 'A choice that no longer matches its line is marked as such rather than dropped' );
lstabp_assert( false !== strpos( $orphan_card, 'Coś, czego już nie ma' ), 'And still says what it was about' );

// ---------------------------------------------------------------------------

lstabp_section( '5f2. Letting a visitor narrow the table' );

/*
 * A filter is only worth offering on a column whose values repeat. The card
 * counts rather than guesses, and the counting is what decides which columns it
 * marks as worth it — so the counting is what gets tested.
 */
$lstabp_rows = LSTAB_Storage::get( $source_id )['data']['rows'];

$lstabp_stock = LSTABP_Facets::tally( $lstabp_rows, 2 );
lstabp_assert( 3 === count( $lstabp_stock ), 'A column of repeated values offers each of them once', (string) count( $lstabp_stock ) );
lstabp_assert( 'W magazynie' === array_key_first( $lstabp_stock ), 'Commonest first, because that is what somebody is most likely to want', array_key_first( $lstabp_stock ) );
lstabp_assert( 5 === $lstabp_stock['W magazynie'], 'And each value says how many rows are behind it', (string) $lstabp_stock['W magazynie'] );

$lstabp_names = LSTABP_Facets::tally( $lstabp_rows, 0 );
lstabp_assert(
	count( $lstabp_names ) === count( $lstabp_rows ),
	'A column where every row differs offers as many choices as it has rows — which the card says out loud',
	count( $lstabp_names ) . '/' . count( $lstabp_rows )
);

/*
 * The question the card exists to answer is a ratio, not a count. Forty towns
 * across five hundred rows is twelve rows behind every choice and a fine
 * filter; four values across seven rows is not. And the counting must never be
 * capped by what the menu can show — a card describing a column of five hundred
 * different values as having sixty would be reporting its own limit.
 */
$lstabp_many = array();
for ( $lstabp_i = 0; $lstabp_i < 500; $lstabp_i++ ) {
	$lstabp_many[] = array( 'Wiersz ' . $lstabp_i, 'Miasto ' . ( $lstabp_i % 40 ), 'Nr ' . $lstabp_i );
}

$lstabp_towns = LSTABP_Facets::tally( $lstabp_many, 1 );
lstabp_assert( 40 === count( $lstabp_towns ), 'A column of forty repeated values counts forty', (string) count( $lstabp_towns ) );
lstabp_assert( 500 === array_sum( $lstabp_towns ), 'Across every row, not just the ones that fit a menu', (string) array_sum( $lstabp_towns ) );

$lstabp_unique = LSTABP_Facets::tally( $lstabp_many, 2 );
lstabp_assert(
	500 === count( $lstabp_unique ),
	'A column where every row differs says so, rather than stopping at the menu limit',
	(string) count( $lstabp_unique )
);
lstabp_assert( LSTABP_Facets::MAX_VALUES < count( $lstabp_unique ), 'Which is more than a menu would ever show' );

update_option( LSTABP_Facets::OPTION, array( $source_id => array( 'Dostępność' ) ), false );

$lstabp_unfiltered = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false !== strpos( $lstabp_unfiltered, 'lstabp-facets' ), 'A chosen column puts a filter above the table' );
lstabp_assert( 7 === substr_count( $lstabp_unfiltered, '<tr role="row"' ) - 1, 'And changes nothing until it is used', (string) ( substr_count( $lstabp_unfiltered, '<tr role="row"' ) - 1 ) );
lstabp_assert( false === strpos( $lstabp_unfiltered, 'lstabp-facets-clear' ), 'With nothing to clear, there is no clearing link' );

$_GET[ 'lstab-f2-' . $source_id ] = 'W magazynie';
$lstabp_filtered                  = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( 5 === substr_count( $lstabp_filtered, '<tr role="row"' ) - 1, 'Choosing a value narrows the table to it', (string) ( substr_count( $lstabp_filtered, '<tr role="row"' ) - 1 ) );
// Named by their product rather than by the value filtered on, which is also
// printed in the menu of the filter itself.
lstabp_assert( false === strpos( $lstabp_filtered, 'Rękawiczki' ), 'And the rows it excludes really are gone' );
lstabp_assert( false !== strpos( $lstabp_filtered, 'lstabp-facets-clear' ), 'A filter in use offers a way out of it' );
lstabp_assert( false !== strpos( $lstabp_filtered, '7' ), 'Which says how many rows clearing it gives back' );

$_GET[ 'lstab-f2-' . $source_id ] = 'W magazynie|Brak';
$lstabp_two                       = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( 6 === substr_count( $lstabp_two, '<tr role="row"' ) - 1, 'Two values are both let through', (string) ( substr_count( $lstabp_two, '<tr role="row"' ) - 1 ) );

/*
 * The value arrives from the address, so it is the one thing here a stranger
 * controls. A value the column does not hold is not a filter that matches
 * nothing — it is not a filter at all, or a mistyped link would show an empty
 * table and read as a broken page.
 */
$_GET[ 'lstab-f2-' . $source_id ] = '<script>alert(1)</script>';
$lstabp_bogus                     = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( 7 === substr_count( $lstabp_bogus, '<tr role="row"' ) - 1, 'A value the column does not hold is ignored, not obeyed', (string) ( substr_count( $lstabp_bogus, '<tr role="row"' ) - 1 ) );
lstabp_assert( false === strpos( $lstabp_bogus, '<script>alert' ), 'And cannot reach the page as markup' );

// A column renamed in Google takes its filter with it, rather than filtering on
// whatever column has ended up in that position.
update_option( LSTABP_Facets::OPTION, array( $source_id => array( 'Kolumna, której nie ma' ) ), false );
$_GET[ 'lstab-f2-' . $source_id ] = 'W magazynie';
$lstabp_gone                      = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstabp_assert( false === strpos( $lstabp_gone, 'lstabp-facets' ), 'A filter on a column the sheet no longer has shows nothing' );
lstabp_assert( 7 === substr_count( $lstabp_gone, '<tr role="row"' ) - 1, 'And narrows nothing', (string) ( substr_count( $lstabp_gone, '<tr role="row"' ) - 1 ) );

unset( $_GET[ 'lstab-f2-' . $source_id ] );

// Deleting a source takes its filters with it.
update_option( LSTABP_Facets::OPTION, array( $source_id => array( 'Dostępność' ), 999 => array( 'Cokolwiek' ) ), false );
do_action( 'lstab_source_deleted', 999 );
lstabp_assert( ! array_key_exists( 999, LSTABP_Facets::all() ), 'Deleting a source deletes its filters' );
lstabp_assert( array_key_exists( $source_id, LSTABP_Facets::all() ), 'And leaves everybody else alone' );

delete_option( LSTABP_Facets::OPTION );

// ---------------------------------------------------------------------------

lstabp_section( '5g. Where the add-on lives, and how to leave' );

// The add-on is a tab across the top of the plugin's own screens, and a line
// under it in the sidebar: somebody looking for what an add-on added looks
// down the list of plugins on the left first.
$lstabp_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $lstabp_admin ? (int) $lstabp_admin[0] : 1 );

$tabs = LSTAB_Admin::tabs();
lstabp_assert( isset( $tabs[ LSTABP_Settings::PAGE_SLUG ] ), 'The add-on adds itself to the row of tabs', wp_json_encode( array_keys( $tabs ) ) );

global $submenu;
$submenu = array();
do_action( 'admin_menu' );
$lstabp_lines = isset( $submenu[ LSTAB_Admin::MENU_SLUG ] ) ? wp_list_pluck( $submenu[ LSTAB_Admin::MENU_SLUG ], 2 ) : array();
lstabp_assert(
	in_array( LSTABP_Settings::PAGE_SLUG, $lstabp_lines, true ),
	'And a line in the sidebar under the free plugin',
	wp_json_encode( $lstabp_lines )
);
// The free plugin's own line is not visible from here: it registers its menu
// only inside the dashboard, and this suite is not one. The browser run checks
// the sidebar as somebody actually sees it.

ob_start();
LSTAB_Admin::render_tabs( LSTABP_Settings::PAGE_SLUG );
$tab_html = (string) ob_get_clean();
lstabp_assert( false !== strpos( $tab_html, LSTABP_Settings::PAGE_SLUG ), 'And is reachable from them' );
lstabp_assert( false !== strpos( $tab_html, 'nav-tab-active' ), 'And marked when you are on it' );

/*
 * Cancelling is put where someone would look for it. A subscription that takes
 * a support ticket to leave is one people leave angrily, and say so in public.
 */
ob_start();
( new LSTABP_Settings() )->render_licence_section( LSTAB_Settings::all() );
$licence = (string) ob_get_clean();
lstabp_assert( false !== strpos( $licence, 'lstabp-licence-card' ), 'The settings screen carries a subscription section' );
lstabp_assert( false !== strpos( $licence, LSTABP_Settings::account_url() ), 'With a way out of it' );
lstabp_assert( false !== strpos( $licence, 'is-active' ), 'And it says the licence is running' );

// With the add-on gone, the same section counts down instead.
add_filter( 'lstab_is_pro', '__return_false', 99 );
update_option( LSTAB_Limits::SEEN_OPTION, time() - DAY_IN_SECONDS, true );
ob_start();
( new LSTABP_Settings() )->render_licence_section( LSTAB_Settings::all() );
$lapsed = (string) ob_get_clean();
remove_filter( 'lstab_is_pro', '__return_false', 99 );
lstabp_assert( false !== strpos( $lapsed, 'is-grace' ), 'Once it stops, the same section says what happens next' );
update_option( LSTAB_Limits::SEEN_OPTION, time(), true );

wp_set_current_user( 0 );

// ---------------------------------------------------------------------------

lstabp_section( '6. The free plugin is untouched' );

// Pro must reach the free plugin only through published hooks: no edits to its
// tables, no new columns, nothing it would have to know about.
global $wpdb;
$columns = $wpdb->get_col( 'DESC ' . LSTAB_Storage::table(), 0 );
lstabp_assert( ! in_array( 'private', $columns, true ), 'Pro added no column to the free plugin\'s table' );
lstabp_assert( ! in_array( 'google_token', $columns, true ), 'Credentials are not stored in the free schema' );
lstabp_assert( is_array( get_option( LSTABP_Private_Sheets::META_OPTION, array() ) ), 'Pro keeps its own settings in its own option' );

// ---------------------------------------------------------------------------

lstabp_section( '7. Deleting the add-on takes the key to Google with it' );

/*
 * A refresh token opens the connected account's spreadsheets for as long as it
 * exists, so it must not outlive the code that used it. Everything else here
 * is somebody's configuration and follows the free plugin's "delete everything"
 * setting, because deleting a plugin to reinstall it is a normal thing to do.
 *
 * The real uninstall.php runs, in its own process, exactly as WordPress runs
 * it — asserting on the file's contents would prove nothing.
 */
$lstabp_seed_options = static function () {
	update_option( 'lstabp_google_token', array( 'refresh_token' => 'test-refresh-token', 'access_token' => 'test-access', 'expires_at' => time() + 3600 ), false );
	update_option( 'lstabp_google_client', array( 'client_id' => 'test.apps.googleusercontent.com', 'client_secret' => 'test-secret' ) );
	update_option( 'lstabp_rules', array( 1 => array( array( 'column' => 'Cena', 'op' => 'gt', 'value' => '10', 'style' => '#fbd5d5' ) ) ) );
	update_option( 'lstabp_facets', array( 1 => array( 'Cena' ) ) );
	update_option( 'lstabp_export_sources', array( 1 => array( 'csv' ) ) );
	update_option( 'lstabp_private_sources', array( 1 => true ) );
	set_transient( 'lstabp_oauth_state_1', 'half-finished-handshake', HOUR_IN_SECONDS );
};

$lstabp_uninstall = static function () use ( $wp_root ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __FILE__ ) . '/harness/run-uninstall.php' )
		. ' ' . escapeshellarg( $wp_root ) . ' ' . escapeshellarg( 'live-sheets-table-pro/live-sheets-table-pro.php' ) . ' 2>&1';

	exec( $command, $output, $status );

	wp_cache_flush();

	return 0 === $status;
};

// First: nobody asked for their settings to be removed.
$lstabp_settings = get_option( LSTAB_Settings::OPTION, array() );
$lstabp_settings['delete_on_uninstall'] = false;
update_option( LSTAB_Settings::OPTION, $lstabp_settings );

$lstabp_seed_options();
lstabp_assert( $lstabp_uninstall(), 'The add-on has an uninstall routine, and it runs' );

lstabp_assert( false === get_option( 'lstabp_google_token' ), 'The key to Google is gone even though nothing else was asked for' );
lstabp_assert( false === get_transient( 'lstabp_oauth_state_1' ), 'And so is a half-finished connection' );
lstabp_assert( false !== get_option( 'lstabp_google_client' ), 'The Google application details are kept, because nobody asked to lose them' );
lstabp_assert( false !== get_option( 'lstabp_rules' ), 'Colour rules survive a plugin being reinstalled' );
lstabp_assert( false !== get_option( 'lstabp_facets' ), 'So do the filters visitors use' );
lstabp_assert( false !== get_option( 'lstabp_export_sources' ), 'So does which sheets may be exported' );
lstabp_assert( false !== get_option( 'lstabp_private_sources' ), 'So does which sheets are private' );

// Then: the site said it wants everything gone.
$lstabp_settings['delete_on_uninstall'] = true;
update_option( LSTAB_Settings::OPTION, $lstabp_settings );

$lstabp_seed_options();
$lstabp_uninstall();

lstabp_assert( false === get_option( 'lstabp_google_token' ), 'Asked to remove everything, the key goes' );
lstabp_assert( false === get_option( 'lstabp_google_client' ), 'And the Google application details' );
lstabp_assert( false === get_option( 'lstabp_rules' ), 'And the colour rules' );
lstabp_assert( false === get_option( 'lstabp_facets' ), 'And the filters' );
lstabp_assert( false === get_option( 'lstabp_export_sources' ), 'And the export settings' );
lstabp_assert( false === get_option( 'lstabp_private_sources' ), 'And which sheets were private' );

// Nothing of the free plugin's is this file's to touch, either way.
lstabp_assert( false !== get_option( LSTAB_Settings::OPTION ), 'The free plugin\'s own settings are left alone' );
lstabp_assert( ! empty( $wpdb->get_col( 'DESC ' . LSTAB_Storage::table(), 0 ) ), 'And its table is still standing' );

$lstabp_settings['delete_on_uninstall'] = false;
update_option( LSTAB_Settings::OPTION, $lstabp_settings );

// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat( '─', 60 ) . "\n";
printf(
	"  \033[32m%d passed\033[0m, %s\n",
	$GLOBALS['lstab_passed'],
	$GLOBALS['lstab_failed'] ? "\033[31m{$GLOBALS['lstab_failed']} failed\033[0m" : '0 failed'
);
echo str_repeat( '─', 60 ) . "\n";

exit( $GLOBALS['lstab_failed'] > 0 ? 1 : 0 );
