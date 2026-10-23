<?php
/**
 * Sets the stage for tests/custom-css-browser.mjs, one step at a time.
 *
 *   setup             Two tables from the demo sheet (Opening hours, Bordered,
 *                     first column pinned; Workshops, Striped) and one page
 *                     showing the first twice and the second once. Prints what
 *                     it made as JSON.
 *   css <id> <file>   Gives a table the CSS in <file>. Optional flags after it:
 *                     per_page=N, detail=on|off (the last column moves into
 *                     the details row).
 *   rule on|off       A Pro colour rule painting Friday's row red, or none.
 *   drop              Takes it all away again and puts the mock back the way
 *                     it was found.
 *
 * The browser run sets the CSS case by case rather than having it all made up
 * front, so that each case is the only CSS on the page.
 *
 * Usage: php tests/harness/custom-css.php /absolute/path/to/wp port command [args]
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$port    = isset( $argv[2] ) ? (int) $argv[2] : 8089;
$command = isset( $argv[3] ) ? $argv[3] : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) || '' === $command ) {
	fwrite( STDERR, "Usage: php tests/harness/custom-css.php /path/to/wordpress port setup|css|rule|drop [args]\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:' . $port;
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

// The CSS field is only kept for someone allowed to write unfiltered HTML.
wp_set_current_user( 1 );

$mock      = WP_CONTENT_DIR . '/lstab-mock-state.json';
$kept_mock = WP_CONTENT_DIR . '/lstab-mock-state.custom-css.json';
$slug      = 'lstab-custom-css';

if ( 'setup' === $command ) {
	if ( file_exists( $mock ) && ! file_exists( $kept_mock ) ) {
		copy( $mock, $kept_mock );
	}
	file_put_contents( $mock, wp_json_encode( array( 'mode' => 'demo', 'tab' => 'main' ) ) );

	$sheet = '1FernH0useSh0pSheet2026xYz0aBcDeFgHiJkLmN';
	$made  = array();

	foreach (
		array(
			'hours'     => array( 'Opening hours', '1122334455', 'bordered' ),
			'workshops' => array( 'Workshops', '1734829105', 'striped' ),
		) as $key => $table
	) {
		$id = LSTAB_Storage::insert(
			array(
				'title'         => 'Custom CSS — ' . $table[0],
				'sheet_url'     => "https://docs.google.com/spreadsheets/d/{$sheet}/edit#gid={$table[1]}",
				'sheet_id'      => $sheet,
				'sheet_kind'    => 'doc',
				'gid'           => $table[1],
				'tab_name'      => $table[0],
				'sync_interval' => 86400,
				'style_preset'  => $table[2],
				'layout'        => 'auto',
				'sticky_first'  => 1,
				'sticky_head'   => 1,
			)
		);
		LSTAB_Sync::run( $id );
		$made[ $key ] = (int) $id;
	}

	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}

	$blocks = '';
	foreach ( array( $made['hours'], $made['workshops'], $made['hours'] ) as $id ) {
		$blocks .= '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . $id . '} /-->' . "\n\n";
	}

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Custom CSS',
			'post_name'    => $slug,
			'post_content' => $blocks,
		)
	);

	echo wp_json_encode(
		array(
			'tables' => $made,
			'page'   => (int) $page_id,
			'url'    => get_permalink( $page_id ),
		)
	) . "\n";
	exit( 0 );
}

if ( 'css' === $command ) {
	$id     = isset( $argv[4] ) ? (int) $argv[4] : 0;
	$file   = isset( $argv[5] ) ? $argv[5] : '';
	$update = array( 'custom_css' => (string) file_get_contents( $file ) );

	foreach ( array_slice( $argv, 6 ) as $flag ) {
		list( $name, $value ) = array_pad( explode( '=', $flag, 2 ), 2, '' );

		if ( 'per_page' === $name ) {
			$update['per_page'] = (int) $value;
		} elseif ( 'detail' === $name ) {
			$columns = LSTAB_Storage::get( $id )['columns_config'];
			$last    = max( array_keys( $columns ) );

			$columns[ $last ]['detail'] = 'on' === $value;
			$update['columns_config']   = $columns;
		}
	}

	LSTAB_Storage::update( $id, $update );
	exit( 0 );
}

if ( 'rule' === $command ) {
	$id = isset( $argv[4] ) ? (int) $argv[4] : 0;

	if ( isset( $argv[5] ) && 'on' === $argv[5] ) {
		update_option( 'lstabp_rules', array( $id => array( array( 'column' => 'Day', 'operator' => '=', 'value' => 'Friday', 'style' => 'red', 'scope' => 'row' ) ) ), false );
	} else {
		delete_option( 'lstabp_rules' );
	}
	exit( 0 );
}

if ( 'drop' === $command ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page ) {
		wp_delete_post( $page->ID, true );
	}

	foreach ( LSTAB_Storage::get_all() as $source ) {
		if ( 0 === strpos( (string) $source['title'], 'Custom CSS — ' ) ) {
			LSTAB_Storage::delete( $source['id'] );
		}
	}

	delete_option( 'lstabp_rules' );
	LSTAB_Usage::forget();

	if ( file_exists( $kept_mock ) ) {
		rename( $kept_mock, $mock );
	}

	echo "  removed the custom CSS page and its tables\n";
	exit( 0 );
}

fwrite( STDERR, "Unknown command: {$command}\n" );
exit( 1 );
