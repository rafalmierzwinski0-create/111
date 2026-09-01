<?php
/**
 * Elementor widget checks.
 *
 * Separate from the main suite because Elementor is not part of this plugin's
 * requirements: where it is not installed there is nothing to check, and the
 * run says so rather than quietly passing.
 *
 * Usage: php tests/elementor-test.php /path/to/wordpress
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.NonceVerification, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/elementor-test.php /path/to/wordpress\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

$lstab_pass = 0;
$lstab_fail = 0;

/**
 * Record one assertion.
 *
 * @param bool   $ok      Whether it held.
 * @param string $label   What was being checked.
 * @param string $details Extra context on failure.
 * @return void
 */
function lstab_el_assert( $ok, $label, $details = '' ) {
	global $lstab_pass, $lstab_fail;

	if ( $ok ) {
		$lstab_pass++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
		return;
	}

	$lstab_fail++;
	echo "  \033[31mFAIL\033[0m  {$label}\n";
	if ( '' !== $details ) {
		echo "        {$details}\n";
	}
}

echo "\n\033[1mElementor widget\033[0m\n";

if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	echo "  Elementor is not active on this site — nothing to check.\n";
	exit( 0 );
}

$widget = \Elementor\Plugin::instance()->widgets_manager->get_widget_types( 'lstab-sheet-table' );

lstab_el_assert( (bool) $widget, 'The widget is in Elementor\'s catalogue' );

if ( ! $widget ) {
	exit( 1 );
}

lstab_el_assert( 'Google Sheets Table' === $widget->get_title(), 'It is named for what it does', $widget->get_title() );
lstab_el_assert( in_array( 'live-sheets-table', $widget->get_categories(), true ), 'It sits in its own panel category', implode( ',', $widget->get_categories() ) );
lstab_el_assert( in_array( 'lstab-table', $widget->get_style_depends(), true ), 'It brings the table stylesheet with it' );
lstab_el_assert( in_array( 'lstab-table', $widget->get_script_depends(), true ), 'And the script the slider needs' );

$categories = \Elementor\Plugin::instance()->elements_manager->get_categories();
lstab_el_assert( isset( $categories['live-sheets-table'] ), 'The category is registered so the widget is not orphaned' );

// A source to point the widget at.
foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

$source_id = LSTAB_Storage::insert(
	array(
		'title'     => 'Cennik',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/ELEMENTOR000000000000000000/edit#gid=0',
		'sheet_id'  => 'ELEMENTOR000000000000000000',
	)
);

LSTAB_Storage::record_success(
	$source_id,
	array(
		'headers' => array( 'Produkt', 'Kategoria', 'Cena' ),
		'rows'    => array(
			array( 'Rower', 'Rowery', '4199' ),
			array( 'Kask', 'Akcesoria', '349' ),
			array( 'Bidon', 'Akcesoria', '29' ),
		),
	)
);

lstab_el_assert( isset( LSTAB_Elementor::source_options()[ $source_id ] ), 'Saved sheets are offered in the picker' );

/**
 * Render the widget with a set of panel settings.
 *
 * @param array<string,mixed> $settings Panel settings.
 * @return string
 */
function lstab_el_render( $settings ) {
	$widget = new LSTAB_Elementor_Widget(
		array(
			'id'         => 'lstabtest',
			'elType'     => 'widget',
			'widgetType' => 'lstab-sheet-table',
			'settings'   => $settings,
		),
		array()
	);

	ob_start();
	$widget->render_content();

	return (string) ob_get_clean();
}

$rendered = lstab_el_render(
	array(
		'source_id'    => $source_id,
		'show_search'  => 'yes',
		'show_sort'    => 'yes',
		'show_updated' => 'yes',
		'layout'       => 'inherit',
		'style_preset' => '',
		'caption'      => 'Cennik z Elementora',
	)
);

lstab_el_assert( false !== strpos( $rendered, 'lstab-table' ), 'It renders the table' );
lstab_el_assert( 3 === substr_count( $rendered, '<tr role="row" class="lstab-row"' ), 'With every row', (string) substr_count( $rendered, '<tr role="row" class="lstab-row"' ) );
lstab_el_assert( false !== strpos( $rendered, 'Cennik z Elementora' ), 'And the caption typed into the panel' );
lstab_el_assert( false !== strpos( $rendered, 'lstab-search-input' ), 'The search box is there when asked for' );

// The panel switches have to reach the renderer, or they are decoration.
$plain = lstab_el_render(
	array(
		'source_id'    => $source_id,
		'show_search'  => '',
		'show_sort'    => '',
		'show_updated' => '',
		'layout'       => 'cards',
		'style_preset' => 'bordered',
		'caption'      => '',
	)
);

lstab_el_assert( false === strpos( $plain, 'lstab-search-input' ), 'Switching the search off removes it' );
lstab_el_assert( false === strpos( $plain, 'lstab-sort' ), 'Switching sorting off removes it' );
lstab_el_assert( false !== strpos( $plain, 'lstab-layout-cards' ), 'The layout choice reaches the table' );
lstab_el_assert( false !== strpos( $plain, 'lstab-style-bordered' ), 'So does the preset' );

// Nothing to point at is not an error.
$empty = lstab_el_render( array( 'source_id' => 0 ) );
lstab_el_assert( false === strpos( $empty, 'lstab-table' ), 'A widget with no sheet chosen renders no table' );

echo "\n────────────────────────────────────────────────────────────\n";
echo "  \033[32m{$lstab_pass} passed\033[0m" . ( $lstab_fail ? ", \033[31m{$lstab_fail} failed\033[0m" : ', 0 failed' ) . "\n";
echo "────────────────────────────────────────────────────────────\n";

exit( $lstab_fail ? 1 : 0 );
