<?php
/**
 * Ustawia w prawdziwym WordPressie jedno źródło z arkuszem szlaków — ze
 * skórką, regułami kolorów, wyglądem kolumn i filtrami — renderuje je w kilku
 * wariantach i zapisuje gotowy kod do pliku JSON.
 *
 * Nic na stronie pokazowej nie jest dorysowane: każdy wiersz, każda pigułka i
 * każdy słupek wychodzi z LSTAB_Renderer, LSTABP_Rules i LSTABP_Column_Looks.
 *
 * Użycie: php landing/szlaki/zbierz.php /ścieżka/do/wp [adres] [plik-wyjściowy]
 *
 * @package LiveSheetsTable\Landing
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '/tmp/lstab-env/wp71';
$base    = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : 'http://127.0.0.1:8089';
$out     = isset( $argv[3] ) ? $argv[3] : __DIR__ . '/markup.json';

if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Nie ma tam WordPressa: {$wp_root}\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = preg_replace( '#^https?://#', '', $base );
$_SERVER['REQUEST_URI']    = '/szlaki/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'live-sheets-table-pro/live-sheets-table-pro.php' ) ) {
	fwrite( STDERR, "Dodatek Pro musi być włączony — reguły i wygląd kolumn są z niego.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

/*
 * Cała strona jest po polsku, więc i tabela: wtyczka ma własny wybór języka,
 * niezależny od języka witryny, i to jest dokładnie to pole. Bez niego
 * „Search…”, „Show only:” i „Download for Excel” zostają po angielsku na
 * polskiej stronie.
 */
$lstab_settings = get_option( LSTAB_Settings::OPTION, array() );
$lstab_settings = is_array( $lstab_settings ) ? $lstab_settings : array();
$lstab_settings['locale'] = 'pl_PL';
update_option( LSTAB_Settings::OPTION, $lstab_settings );
LSTAB_Locale::forget();

// Arkusz, który Google ma podać: mock odpowiada z tego pliku.
copy( __DIR__ . '/szlaki.csv', WP_CONTENT_DIR . '/lstab-mock-custom.csv' );
file_put_contents(
	WP_CONTENT_DIR . '/lstab-mock-state.json',
	wp_json_encode( array( 'mode' => 'custom' ) )
);

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

/** Kolory tej strony: mięta na nocnej zieleni. */
$mieta  = '#5fe3cf';
$bursztyn = '#f2b544';
$czerwien = '#ff8d8d';

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Warunki na szlakach',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Szlaki',
		'sync_interval' => 900,
		'style_preset'  => 'glass',
		'layout'        => 'auto',
		'style_vars'    => array(),
	)
);

LSTAB_Sync::run( $source_id );

// Reguły kolorów: zamknięty szlak maluje cały wiersz, status nosi pigułkę,
// trudność kropkę. Dokładnie te same pola, które ma karta „Reguły” w kokpicie.
update_option(
	'lstabp_rules',
	array(
		$source_id => array(
			array( 'column' => 'Status',   'operator' => '=', 'value' => 'Zamknięty', 'style' => '#8c3b46', 'scope' => 'row' ),
			array( 'column' => 'Status',   'operator' => '=', 'value' => 'Otwarty',   'style' => $mieta,    'scope' => 'pill' ),
			array( 'column' => 'Status',   'operator' => '=', 'value' => 'Ostrożnie', 'style' => $bursztyn, 'scope' => 'pill' ),
			array( 'column' => 'Status',   'operator' => '=', 'value' => 'Zamknięty', 'style' => $czerwien, 'scope' => 'pill' ),
			array( 'column' => 'Trudność', 'operator' => '=', 'value' => 'Łatwy',     'style' => $mieta,    'scope' => 'dot' ),
			array( 'column' => 'Trudność', 'operator' => '=', 'value' => 'Średni',    'style' => $bursztyn, 'scope' => 'dot' ),
			array( 'column' => 'Trudność', 'operator' => '=', 'value' => 'Trudny',    'style' => $czerwien, 'scope' => 'dot' ),
		),
	),
	false
);

// Wygląd kolumn: śnieg dostaje słupek, kamera przycisk.
update_option(
	'lstabp_column_looks',
	array(
		$source_id => array(
			'Śnieg (cm)' => array( 'look' => 'bar', 'tint' => $mieta, 'ink' => '', 'label' => '' ),
			'Kamera'     => array( 'look' => 'button', 'tint' => $mieta, 'ink' => '#08201c', 'label' => 'Podgląd' ),
		),
	),
	false
);

// Filtry, z których korzysta odwiedzający, i pobieranie pod tabelą.
update_option( 'lstabp_facets', array( $source_id => array( 'Trudność', 'Status' ) ), false );
update_option( 'lstabp_export_sources', array( $source_id => true ), true );

/*
 * Cofnięta godzina ostatniego udanego pobrania, żeby wiersz pod tabelą miał co
 * powiedzieć: świeżo zsynchronizowany arkusz mówi „przed chwilą”, co niczego
 * nie pokazuje.
 */
global $wpdb;
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 12 * MINUTE_IN_SECONDS ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);

LSTAB_Storage::flush_cache( $source_id );

/**
 * Wyrenderuj tabelę tak, jak zobaczy ją odwiedzający.
 *
 * @param int                  $source_id Źródło.
 * @param array<string,mixed>  $args      Nadpisania renderera.
 * @return string
 */
function lstab_szlaki_render( $source_id, $args = array() ) {
	return LSTAB_Renderer::render( array_merge( array( 'source_id' => $source_id ), $args ) );
}

$warianty = array(
	'glass'    => array( 'style' => 'glass' ),
	'cards'    => array( 'style' => 'cards' ),
	'contrast' => array( 'style' => 'contrast' ),
	'terminal' => array( 'style' => 'terminal' ),
	'editorial'=> array( 'style' => 'editorial' ),
);

$zebrane = array( 'warianty' => array() );

foreach ( $warianty as $klucz => $args ) {
	$zebrane['warianty'][ $klucz ] = lstab_szlaki_render( $source_id, $args );
}

/*
 * Pokrętła: to samo źródło, raz ciasno z pełną siatką, raz luźno bez linii.
 * Ustawienia idą tą samą drogą co z ekranu „Wygląd” — przez style_vars.
 */
$pokretla = array(
	'ciasno' => array(
		'density' => 'compact',
		'lines'   => 'grid',
	),
	'zwykle' => array(),
	'luzno'  => array(
		'density' => 'roomy',
		'lines'   => 'none',
	),
);

foreach ( $pokretla as $klucz => $vars ) {
	LSTAB_Storage::update( $source_id, array( 'style_vars' => $vars ) );
	LSTAB_Storage::flush_cache( $source_id );
	$zebrane['pokretla'][ $klucz ] = lstab_szlaki_render( $source_id, array( 'style' => 'contrast' ) );
}

LSTAB_Storage::update( $source_id, array( 'style_vars' => array() ) );
LSTAB_Storage::flush_cache( $source_id );

$zebrane['id']    = (int) $source_id;
$stored = LSTAB_Storage::get( $source_id );
$zebrane["wiersze"] = isset( $stored["rows"] ) ? count( (array) $stored["rows"] ) : 0;

file_put_contents( $out, wp_json_encode( $zebrane, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo "  źródło {$source_id}, wariantów: " . count( $zebrane['warianty'] ) . ", zapisane w {$out}\n";
