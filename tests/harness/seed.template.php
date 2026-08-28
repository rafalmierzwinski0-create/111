<?php
/**
 * Seeds a demo source and a page that uses both the block and the shortcode,
 * so the browser suite has something realistic to drive.
 *
 * __SITE__ and __PORT__ are substituted by tests/setup-env.sh.
 *
 * @package LiveSheetsTable\Tests
 */

$_SERVER['HTTP_HOST']      = '127.0.0.1:__PORT__';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once '__SITE__/wp-load.php';

// Start from a healthy sheet.
file_put_contents(
	WP_CONTENT_DIR . '/lstab-mock-state.json',
	wp_json_encode(
		array(
			'mode' => 'ok',
			'tab'  => 'main',
		)
	)
);

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Cennik rowerowy',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Cennik',
		'sync_interval' => 900,
		'style_preset'  => 'striped',
	)
);

LSTAB_Sync::run( $source_id );

// Backdate the success stamp so the "updated N ago" label has something to say.
global $wpdb;
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 7 * MINUTE_IN_SECONDS ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);
LSTAB_Storage::flush_cache( $source_id );

$existing_page = get_page_by_path( 'cennik', OBJECT, 'page' );
if ( $existing_page ) {
	wp_delete_post( $existing_page->ID, true );
}

$content  = "<!-- wp:paragraph -->\n<p>Aktualny cennik pobierany automatycznie z arkusza Google. Strona renderuje się z lokalnej kopii, więc ładuje się natychmiast.</p>\n<!-- /wp:paragraph -->\n\n";
// Wide alignment gives a five-column table the room it needs, so it renders as
// a real table on desktop and only becomes cards on a phone.
$content .= '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . $source_id . ',"align":"wide","showSearch":true,"showSort":true,"showUpdated":true,"caption":"Cennik rowerowy – sierpień 2026"} /-->' . "\n\n";
$content .= "<!-- wp:heading -->\n<h2>Ten sam arkusz przez shortcode</h2>\n<!-- /wp:heading -->\n\n";
$content .= "<!-- wp:paragraph -->\n<p>Ten sam arkusz w wąskiej kolumnie treści. Pięć kolumn się tu nie mieści, więc tabela zachowuje swój kształt i pełny rozmiar tekstu, a pod nią pojawia się suwak do przewijania w lewo i w prawo.</p>\n<!-- /wp:paragraph -->\n\n";
$content .= "<!-- wp:paragraph -->\n<p>[sheet_table id=\"{$source_id}\" style=\"bordered\" search=\"no\" caption=\"Wariant bordered, wąska kolumna\"]</p>\n<!-- /wp:paragraph -->";

$page_id = wp_insert_post(
	array(
		'post_title'   => 'Cennik',
		'post_name'    => 'cennik',
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( false );

echo "  seeded source {$source_id}, page " . (int) $page_id . ' at ' . get_permalink( $page_id ) . "\n";
