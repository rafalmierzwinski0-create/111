<?php
/**
 * Zasiewa czyste dane pokazowe: jedno źródło i jedną stronę z tabelą.
 *
 * Uruchamiane ręcznie, tylko na lokalnym WordPressie do zrzutów.
 *
 * @package LiveSheetsTable\Pokaz
 */

$_SERVER['HTTP_HOST']      = '127.0.0.1:8088';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../wp/wp-load.php';

$jezyk = isset( $argv[1] ) ? $argv[1] : 'en';

file_put_contents(
	WP_CONTENT_DIR . '/lstab-pokaz-stan.json',
	wp_json_encode( array( 'jezyk' => $jezyk, 'tryb' => 'ok', 'karta' => 'cennik' ) )
);

foreach ( LSTAB_Storage::get_all() as $stare ) {
	LSTAB_Storage::delete( $stare['id'] );
}

$tytul = 'pl' === $jezyk ? 'Cennik rowerowy' : 'Bike shop price list';
$karta = 'pl' === $jezyk ? 'Cennik' : 'Price list';
$podpis = 'pl' === $jezyk ? 'Cennik — wrzesień 2026' : 'Price list — September 2026';

$id = LSTAB_Storage::insert(
	array(
		'title'         => $tytul,
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => $karta,
		'sync_interval' => 900,
		'style_preset'  => 'striped',
	)
);

LSTAB_Sync::run( $id );

// Cofamy znacznik udanego pobrania, żeby napis „zaktualizowano" miał co powiedzieć.
global $wpdb;
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 4 * MINUTE_IN_SECONDS ) ),
	array( 'id' => $id ),
	array( '%s' ),
	array( '%d' )
);

$stara = get_page_by_path( 'pokaz', OBJECT, 'page' );

if ( $stara ) {
	wp_delete_post( $stara->ID, true );
}

$tresc = '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . $id
	. ',"showSearch":true,"showSort":true,"showUpdated":true,"caption":"' . $podpis . '"} /-->';

$strona = wp_insert_post(
	array(
		'post_title'   => 'pl' === $jezyk ? 'Cennik' : 'Price list',
		'post_name'    => 'pokaz',
		'post_content' => $tresc,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( false );

$dane = LSTAB_Storage::get( $id );

echo 'zrodlo=' . $id . ' strona=' . $strona . ' url=' . get_permalink( $strona ) . "\n";
echo 'wierszy=' . ( isset( $dane['row_count'] ) ? $dane['row_count'] : '?' ) . "\n";
