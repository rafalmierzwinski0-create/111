<?php
/**
 * Renderuje tabelę, którą pokazuje sekcja pod hero, używając prawdziwej wtyczki.
 *
 * Ta sama droga co landing/mozliwosci/zbierz.php: źródło zakładane tak, jak
 * zakłada się je w kokpicie, szablon wybrany i odmalowany próbnikami wtyczki,
 * reguły kolorów i wygląd kolumn ustawione, a potem render. To, co ląduje
 * w markup.json, wypisał LSTAB_Renderer — sekcja dookoła tylko to opakowuje.
 *
 * Użycie: php landing/przeplyw/zbierz.php /path/to/wp [base-url] [out.json]
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
$_SERVER['REQUEST_URI']    = '/prices/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'live-sheets-table-pro/live-sheets-table-pro.php' ) ) {
	fwrite( STDERR, "The Pro add-on has to be active: the rules and the column looks come from it.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

// Strona jest po angielsku, więc i tabela. Wtyczka ma język niezależny od
// języka witryny i to jest dokładnie to pole.
$settings           = get_option( LSTAB_Settings::OPTION, array() );
$settings           = is_array( $settings ) ? $settings : array();
$locale_before      = $settings;
$settings['locale'] = 'en_US';
update_option( LSTAB_Settings::OPTION, $settings );
LSTAB_Locale::forget();

copy( __DIR__ . '/cennik.csv', WP_CONTENT_DIR . '/lstab-mock-custom.csv' );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'custom' ) ) );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

/*
 * Kolory strony, nie własne. Tabela ma być tym, co odwiedzający dostaje na
 * SWOJEJ stronie, a strona jest ciemna i miętowa, więc szablon Północ wybrany
 * i odmalowany próbnikami wtyczki. To jest zarazem to, co wtyczka obiecuje:
 * wybierz szablon, a potem się z nim nie zgódź.
 */
$mieta      = '#5fe3cf';
$bursztyn   = '#f2b544';
$koral      = '#ff8d8d';
$atrament   = '#06100f';
$ekran      = '#0a1110';
$ekran_gora = '#131d1b';

/*
 * Tabela ma własną barwę, jaśniejszą od ekranu, na którym stoi.
 *
 * Przedtem brała dokładnie kolor okna i przez to znikała: okno, strona i tabela
 * były jednym czarnym polem, w którym widać było tylko pigułki. Tabela na
 * stronie jest przedmiotem leżącym NA stronie, a nie samą stroną, więc jest
 * o kilka stopni jaśniejsza, ma wyraźniejsze linie i jaśniejszy tekst. Nadal
 * jest to ten sam ciemny motyw, tylko widać, gdzie się zaczyna.
 */
$stol       = '#14221f';
$stol_gora  = '#1f312d';
$linia      = '#33463f';

/*
 * Trzy stopnie wypału, trzy odcienie ziarna. Kolor niesie tu znaczenie, a nie
 * tylko ozdobę: jasna palona, średnia, ciemna. To jest dokładnie to, co robi
 * reguła koloru w kokpicie, dwoma kliknięciami.
 */
$jasna      = '#e2c79b';
$srednia    = '#c08a55';
$ciemna     = '#9a6b46';

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Roast list',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Roast list',
		'sync_interval' => 900,
		'style_preset'  => 'midnight',
		'layout'        => 'auto',
		'style_vars'    => array(
			'text'       => '#f1f8f6',
			'background' => $stol,
			'headerText' => '#a9c2bd',
			'headerBg'   => $stol_gora,
			'border'     => $linia,
			'hover'      => '#1b2a27',
			'accent'       => $mieta,
			'lines'        => 'normal',
			/*
			 * Wiersze zwykłej wysokości, mimo że luźniejsze wyglądałyby
			 * dostojniej. „Luźno” dokłada dopełnienie także po bokach, a przy
			 * sześciu kolumnach tabela robi się przez to szersza niż okno,
			 * w którym stoi, i zaczyna się przewijać w bok. Powietrze bierze
			 * się tu skądinąd: z podpisu, dużych nazw kolumn i z tego, że
			 * tabela ma własną, jaśniejszą barwę.
			 */
			'density'      => 'normal',
			// Duże nagłówki: ta tabela jest na stronie sprzedażowej oglądana
			// z daleka, a nazwy kolumn są tym, co ma się przeczytać pierwsze.
			'headFontSize' => 'large',
		),
	)
);

LSTAB_Sync::run( $source_id );

/*
 * Reguła koloru w słowach kokpitu: stan magazynu nosi pigułkę, a wyprzedany
 * towar maluje cały wiersz. Do tego stopień wypału, też pigułką, ale w kolorze
 * ziarna: kolor niesie tam znaczenie, a nie samą ozdobę.
 */
update_option(
	'lstabp_rules',
	array(
		$source_id => array(
			array( 'column' => 'Stock', 'operator' => '=', 'value' => 'Out of stock', 'style' => '#5a2733', 'scope' => 'row' ),
			array( 'column' => 'Stock', 'operator' => '=', 'value' => 'In stock',     'style' => $mieta,    'scope' => 'pill' ),
			array( 'column' => 'Stock', 'operator' => '=', 'value' => 'Backorder',    'style' => $bursztyn, 'scope' => 'pill' ),
			array( 'column' => 'Stock', 'operator' => '=', 'value' => 'Out of stock', 'style' => $koral,    'scope' => 'pill' ),
			array( 'column' => 'Roast', 'operator' => '=', 'value' => 'Light',        'style' => $jasna,    'scope' => 'pill' ),
			array( 'column' => 'Roast', 'operator' => '=', 'value' => 'Medium',       'style' => $srednia,  'scope' => 'pill' ),
			array( 'column' => 'Roast', 'operator' => '=', 'value' => 'Dark',         'style' => $ciemna,   'scope' => 'pill' ),
		),
	),
	false
);

// Wygląd kolumn: stan liczbowy dostaje słupek, adres strony produktu przycisk.
update_option(
	'lstabp_column_looks',
	array(
		$source_id => array(
			'Bags left'    => array( 'look' => 'bar', 'tint' => $mieta, 'ink' => '', 'label' => '' ),
			'Product page' => array( 'look' => 'button', 'tint' => $mieta, 'ink' => $atrament, 'label' => 'Buy' ),
		),
	),
	false
);

update_option( 'lstabp_facets', array( $source_id => array( 'Stock', 'Roast' ) ), false );
update_option( 'lstabp_export_sources', array( $source_id => true ), true );

// Cofnięta godzina ostatniego pobrania, żeby wiersz pod tabelą miał co
// powiedzieć: świeżo zsynchronizowany arkusz mówi „przed chwilą”.
global $wpdb;
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 4 * MINUTE_IN_SECONDS ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);

LSTAB_Storage::flush_cache( $source_id );

$zebrane = array(
	'id'    => (int) $source_id,
	'pro'   => LSTAB_Renderer::render(
		array(
			'source_id' => $source_id,
			// Podpis, który wtyczka rysuje sama, nad paskiem wyszukiwania.
			// Bez myślnika: cała sekcja jest bez myślników.
			'caption'   => 'This week\'s roast list',
		)
	),
	/*
	 * Te same wiersze, jeszcze niczym nie tknięte. Lewa strona sekcji rysuje
	 * z nich arkusz, więc to naprawdę jest ta sama treść po obu stronach
	 * strzałki, a nie dwa osobno napisane przykłady.
	 */
	'rows'  => array(),
);

// Wiersze siedzą w zdjęciu arkusza pod kluczem „data”, nie na wierzchu.
$stored   = LSTAB_Storage::get( $source_id );
$snapshot = isset( $stored['data'] ) && is_array( $stored['data'] ) ? $stored['data'] : array();

if ( isset( $snapshot['headers'] ) ) {
	$zebrane['headers'] = array_values( (array) $snapshot['headers'] );
}

if ( isset( $snapshot['rows'] ) ) {
	foreach ( (array) $snapshot['rows'] as $row ) {
		$zebrane['rows'][] = array_values( (array) $row );
	}
}

/*
 * Język wtyczki wraca na swoje miejsce. Ten skrypt chodzi po tej samej witrynie
 * testowej co tests/run-all.sh, a zostawione tam 'en_US' wywraca testy
 * tłumaczeń na wtyczce, której nic nie dolega.
 */
update_option( LSTAB_Settings::OPTION, $locale_before );
LSTAB_Locale::forget();

file_put_contents( $out, wp_json_encode( $zebrane, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo '  source ' . $source_id . ', ' . count( $zebrane['rows'] ) . ' rows, '
	. strlen( $zebrane['pro'] ) . " bytes of table, written to {$out}\n";
