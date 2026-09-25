<?php
/**
 * Clears away what tests/skins-test.php published.
 *
 * The skins suite leaves nine tables on each of four pages, because the browser
 * run that follows it opens exactly those pages. Left behind afterwards they
 * are not harmless: the end-to-end suite checks that a source nothing points at
 * is reported as being on no page, and a leftover page naming the id that
 * source happens to be given next answers otherwise. Three assertions failed
 * that way before this existed.
 *
 * Usage: php tests/harness/drop-skins.php /absolute/path/to/wp [base-url]
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$base    = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : 'http://127.0.0.1:8089';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/harness/drop-skins.php /path/to/wordpress [base-url]\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = preg_replace( '#^https?://#', '', $base );
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

$gone = 0;

foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => 200 ) ) as $page ) {
	if ( 0 !== strpos( $page->post_name, 'lstab-skins-' ) ) {
		continue;
	}
	wp_delete_post( $page->ID, true );
	++$gone;
}

$sources = 0;

foreach ( LSTAB_Storage::get_all() as $source ) {
	LSTAB_Storage::delete( $source['id'] );
	++$sources;
}

LSTAB_Usage::forget();

echo "  removed {$gone} skin pages and {$sources} sources\n";
