<?php
/**
 * Renders the table this subpage shows, using the real plugin on a real site.
 *
 * Same idea as landing/szlaki/zbierz.php: a source is created, given a skin,
 * colour rules, column looks and visitor filters exactly the way somebody would
 * in the dashboard, and then rendered. What lands in markup.json is what
 * LSTAB_Renderer actually produced — the page around it only wraps it.
 *
 * Usage: php landing/mozliwosci/zbierz.php /path/to/wp [base-url] [out.json]
 *
 * @package LiveSheetsTable\Landing
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '/tmp/lstab-env/wp71';
$base    = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : 'http://127.0.0.1:8089';
$out     = isset( $argv[3] ) ? $argv[3] : __DIR__ . '/markup.json';

if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "No WordPress there: {$wp_root}\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = preg_replace( '#^https?://#', '', $base );
$_SERVER['REQUEST_URI']    = '/trails/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'live-sheets-table-pro/live-sheets-table-pro.php' ) ) {
	fwrite( STDERR, "The Pro add-on has to be active: the rules and the column looks come from it.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

// The page is English, so the table is too. The plugin has a language of its
// own, apart from the site's; this is that setting.
$settings           = get_option( LSTAB_Settings::OPTION, array() );
$settings           = is_array( $settings ) ? $settings : array();
$locale_before      = $settings;
$settings['locale'] = 'en_US';
update_option( LSTAB_Settings::OPTION, $settings );
LSTAB_Locale::forget();

copy( __DIR__ . '/trails.csv', WP_CONTENT_DIR . '/lstab-mock-custom.csv' );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'custom' ) ) );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

/*
 * The page's own palette, worn by the table.
 *
 * The table used to be the one warm, light thing on a dark page. It read as a
 * sheet of paper somebody had dropped on the site: nothing else up there is
 * cream, and the one object that is supposed to say "this is what your page
 * gets" looked as though it came from another site altogether.
 *
 * So it wears the site's colours instead — the same near-black screen, the
 * same mint, the same amber and coral as everything else. And it gets them the
 * way a customer would: a style picked, then disagreed with, one colour well
 * at a time. That is the product's own promise, demonstrated on the page that
 * makes it rather than described.
 */
$mieta      = '#5fe3cf';
$bursztyn   = '#f2b544';
$koral      = '#ff8d8d';
$atrament   = '#06100f';
$ekran      = '#0a1110';
$ekran_gora = '#131d1b';

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Trail conditions',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Trails',
		'sync_interval' => 900,
		'style_preset'  => 'cards',
		'layout'        => 'auto',
		/*
		 * Karty, a potem odmalowane.
		 *
		 * Ta strona pokazuje, co wtyczka potrafi, a nie że umie narysować
		 * kratkę. Karty są tu najlepszym dowodem: każdy wiersz staje się
		 * osobnym przedmiotem z własnym tłem i zaokrągleniem, więc pomalowany
		 * wiersz jest naprawdę pomalowaną KARTĄ, a pigułka, kropka i słupek
		 * leżą na niej, a nie w komórce tabeli. To samo ustawienie robi z tego
		 * na telefonie listę kart, więc strona i telefon wyglądają jak jedno.
		 *
		 * Styl przychodzi jasny, więc każdy kolor, który decyduje o nastroju,
		 * jest ustawiony ręcznie na kolor tej strony: ekran, na którym rysowane
		 * są okna niżej, mięta od wszystkiego, co żyje, i linie na tyle ciche,
		 * żeby karty trzymały się kupy samym tłem.
		 */
		'style_vars'    => array(
			'text'       => '#eef7f4',
			'background' => '#16211f',
			'headerText' => '#93aca7',
			// Pasek nagłówków ma swój kolor, jaśniejszy od kart pod nim.
			// Karty przychodzą z nagłówkiem przezroczystym, więc nazwy kolumn
			// leżały wprost na ekranie okienka i nic ich nie trzymało razem.
			'headerBg'   => '#1f312d',
			'border'     => '#2b3c37',
			'hover'      => '#1d2c29',
			'accent'     => $mieta,
			'lines'      => 'normal',
			// Luźniej: karta, w której napisy leżą przy krawędziach, nie
			// wygląda na kartę, tylko na wiersz, który komuś urósł.
			'density'    => 'roomy',
		),
	)
);

LSTAB_Sync::run( $source_id );

// Colour rules, in the words the dashboard uses: when Status is Closed, paint
// the whole row; when it is Open, put the value in a pill; difficulty gets a
// dot, which is the quietest of the three.
$rules = array(
	$source_id => array(
		array( 'column' => 'Status',     'operator' => '=', 'value' => 'Closed',   'style' => '#5a2733', 'scope' => 'row' ),
		array( 'column' => 'Status',     'operator' => '=', 'value' => 'Open',     'style' => $mieta,    'scope' => 'pill' ),
		array( 'column' => 'Status',     'operator' => '=', 'value' => 'Caution',  'style' => $bursztyn, 'scope' => 'pill' ),
		array( 'column' => 'Status',     'operator' => '=', 'value' => 'Closed',   'style' => $koral,    'scope' => 'pill' ),
		array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Easy',     'style' => $mieta,    'scope' => 'dot' ),
		array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Moderate', 'style' => $bursztyn, 'scope' => 'dot' ),
		array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Hard',     'style' => $koral,    'scope' => 'dot' ),
	),
);

update_option( 'lstabp_rules', $rules, false );

$looks = array(
	$source_id => array(
		'Snow (cm)' => array( 'look' => 'bar', 'tint' => $mieta, 'ink' => '', 'label' => '' ),
		'Webcam'    => array( 'look' => 'button', 'tint' => $mieta, 'ink' => $atrament, 'label' => 'Live view' ),
	),
);

update_option( 'lstabp_column_looks', $looks, false );

update_option( 'lstabp_facets', array( $source_id => array( 'Difficulty', 'Status' ) ), false );
update_option( 'lstabp_export_sources', array( $source_id => true ), true );

// Backdated, so the line under the table has something to say.
global $wpdb;
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 9 * MINUTE_IN_SECONDS ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);

LSTAB_Storage::flush_cache( $source_id );

$collected = array(
	'id'   => (int) $source_id,
	'pro'  => LSTAB_Renderer::render( array( 'source_id' => $source_id ) ),
);

/*
 * The same sheet as the free plugin draws it: no rules, no column looks, no
 * filters, no downloads. Everything else — the table, the search, the sorting,
 * the folding into cards — is in the free plugin, and the page says so.
 */
update_option( 'lstabp_rules', array(), false );
update_option( 'lstabp_column_looks', array(), false );
update_option( 'lstabp_facets', array(), false );
update_option( 'lstabp_export_sources', array(), true );
LSTAB_Storage::flush_cache( $source_id );

$collected['free'] = LSTAB_Renderer::render( array( 'source_id' => $source_id ) );

/*
 * Put everything back: the screenshots taken next (landing/mozliwosci/zrzuty.mjs)
 * photograph these very cards, so the site has to be left the way the page
 * describes it.
 */
update_option( 'lstabp_rules', $rules, false );
update_option( 'lstabp_column_looks', $looks, false );
update_option( 'lstabp_facets', array( $source_id => array( 'Difficulty', 'Status' ) ), false );
update_option( 'lstabp_export_sources', array( $source_id => true ), true );
LSTAB_Storage::flush_cache( $source_id );

/*
 * The language setting goes back as well. This script runs against the same
 * test site as tests/run-all.sh, and a stray 'en_US' left behind there makes
 * the translation tests fail on a plugin that is perfectly fine.
 */
update_option( LSTAB_Settings::OPTION, $locale_before );
LSTAB_Locale::forget();

file_put_contents( $out, wp_json_encode( $collected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo '  source ' . $source_id . ', ' . strlen( $collected['pro'] ) . " bytes of table, written to {$out}\n";
