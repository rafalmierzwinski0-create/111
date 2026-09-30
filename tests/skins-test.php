<?php
/**
 * Whole-table skins, and the two dials that work on top of them.
 *
 * Boots the real site, makes a real source for every combination of skin, row
 * height and lines, syncs each one from the mocked spreadsheet and publishes
 * real pages that show them. Nothing here is a hand-typed imitation of the
 * plugin's output: what it writes into fixtures/skins-php-said.json is the set
 * of pages it actually published, and tests/skins-browser.mjs then opens those
 * pages in a browser and measures what a visitor would see.
 *
 * Run this one first; the browser suite reads the file it writes.
 *
 * Usage: php tests/skins-test.php /absolute/path/to/wp [base-url]
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$base    = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : 'http://127.0.0.1:8089';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/skins-test.php /path/to/wordpress [base-url]\n" );
	exit( 1 );
}

$host                      = preg_replace( '#^https?://#', '', $base );
$_SERVER['HTTP_HOST']      = $host;
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/*
 * As an administrator, because two things here are gated on a capability: the
 * table's own CSS field, and raw HTML in a page. A run with nobody signed in
 * has both quietly stripped, and the skin that needs a gradient behind it then
 * gets measured against a white wall.
 */
wp_set_current_user( 1 );

$GLOBALS['lstab_passed'] = 0;
$GLOBALS['lstab_failed'] = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $label     Test name.
 * @param string $detail    Extra context printed on failure.
 * @return bool
 */
function lstab_skins_assert( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['lstab_passed']++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
		return true;
	}

	$GLOBALS['lstab_failed']++;
	echo "  \033[31mFAIL\033[0m  {$label}\n";
	if ( '' !== $detail ) {
		echo "        {$detail}\n";
	}
	return false;
}

/**
 * Section header.
 *
 * @param string $title Title.
 * @return void
 */
function lstab_skins_section( $title ) {
	echo "\n\033[1m{$title}\033[0m\n";
}

echo "\n\033[1mWhole-table skins — " . $wp_root . "\033[0m\n";

if ( ! is_plugin_active( 'live-sheets-table-pro/live-sheets-table-pro.php' ) ) {
	fwrite( STDERR, "The Pro add-on has to be active: the skins are premium.\n" );
	fwrite( STDERR, "  php tests/harness/activate.php {$wp_root} 8089 live-sheets-table-pro/live-sheets-table-pro.php\n" );
	exit( 1 );
}

// ---------------------------------------------------------------- registry

lstab_skins_section( 'The premium skins are registered' );

$all = LSTAB_Styles::all();

foreach ( array( 'cards', 'terminal', 'glass', 'contrast', 'ledger' ) as $slug ) {
	lstab_skins_assert( isset( $all[ $slug ] ), "{$slug} is a preset" );
	lstab_skins_assert( ! empty( $all[ $slug ]['pro'] ), "{$slug} is premium" );
	lstab_skins_assert( '' !== (string) $all[ $slug ]['description'], "{$slug} says what it looks like" );
	lstab_skins_assert( $slug === LSTAB_Styles::sanitize( $slug ), "{$slug} renders under Pro" );
}

lstab_skins_assert( 10 === count( LSTAB_Styles::available() ), 'Ten skins available under Pro', (string) count( LSTAB_Styles::available() ) );

/*
 * Every skin needs its own block of rules in the stylesheet, or the name in the
 * picker is the only thing that exists. This is what catches a preset that was
 * registered and then never drawn.
 */
$css = file_get_contents( dirname( __DIR__ ) . '/live-sheets-table/assets/css/lstab-table.css' );

foreach ( array_keys( $all ) as $slug ) {
	if ( 'clean' === $slug ) {
		// Clean is the stylesheet's own defaults; it adds nothing of its own.
		continue;
	}
	lstab_skins_assert(
		false !== strpos( $css, '.lstab-style-' . $slug ),
		"The stylesheet has rules for {$slug}"
	);
}

// ------------------------------------------------------------------- dials

lstab_skins_section( 'The two dials' );

$metrics = LSTAB_Customizer::metrics();

lstab_skins_assert( isset( $metrics['density'] ), 'Row height is a setting of its own' );
lstab_skins_assert( isset( $metrics['lines'] ), 'Lines is a setting of its own' );
lstab_skins_assert(
	array( 'grid', 'normal', 'none' ) === array_keys( $metrics['lines']['choices'] ),
	'Lines offers a full grid, rows only and none',
	implode( ',', array_keys( $metrics['lines']['choices'] ) )
);

$grid = LSTAB_Customizer::css_map( array( 'lines' => 'grid' ) );
lstab_skins_assert( '1px' === ( isset( $grid['--lstab-col-line'] ) ? $grid['--lstab-col-line'] : '' ), 'A full grid turns the column line on' );
lstab_skins_assert( '1px' === ( isset( $grid['--lstab-row-line'] ) ? $grid['--lstab-row-line'] : '' ), 'A full grid keeps the row line on' );

$bare = LSTAB_Customizer::css_map( array( 'lines' => 'none' ) );
lstab_skins_assert( '0px' === ( isset( $bare['--lstab-row-line'] ) ? $bare['--lstab-row-line'] : '' ), 'No lines takes the row line off' );
lstab_skins_assert( '0px' === ( isset( $bare['--lstab-col-line'] ) ? $bare['--lstab-col-line'] : '' ), 'No lines takes the column line off' );

$following = LSTAB_Customizer::css_map( array( 'lines' => 'normal' ) );
lstab_skins_assert(
	! isset( $following['--lstab-row-line'] ) && ! isset( $following['--lstab-col-line'] ),
	'Left alone, the setting says nothing and the skin decides'
);

lstab_skins_assert(
	'normal' === LSTAB_Customizer::sanitize( array( 'lines' => 'diagonal' ) )['lines'],
	'A choice nobody offered falls back to following the skin'
);

// Both dials at once, which is what "optional and changeable" has to mean.
$both = LSTAB_Customizer::inline_style(
	array(
		'density' => 'roomy',
		'lines'   => 'grid',
	)
);
lstab_skins_assert( false !== strpos( $both, '--lstab-pad-y:1.05em' ), 'Row height reaches the style attribute', $both );
lstab_skins_assert( false !== strpos( $both, '--lstab-col-line:1px' ), 'Lines reaches the style attribute', $both );

// -------------------------------------------------------- the live tables

lstab_skins_section( 'Real sources, one for every combination' );

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

/** Every skin, drawn three ways: as it comes, tight with a grid, and airy with nothing. */
$variants = array(
	array(
		'slug'    => 'jak-jest',
		'title'   => 'as the skin comes',
		'density' => 'normal',
		'lines'   => 'normal',
	),
	array(
		'slug'    => 'ciasno-siatka',
		'title'   => 'compact, full grid',
		'density' => 'compact',
		'lines'   => 'grid',
	),
	array(
		'slug'    => 'luzno-bez-linii',
		'title'   => 'roomy, no lines',
		'density' => 'roomy',
		'lines'   => 'none',
	),
);

$skins    = array_keys( LSTAB_Styles::available() );
$manifest = array(
	'base'   => $base,
	'pages'  => array(),
	'skins'  => $skins,
	'dials'  => $variants,
);

$made = 0;

foreach ( $variants as $variant ) {
	$tables = array();
	$blocks = '';

	foreach ( $skins as $skin ) {
		$style_vars = array(
			'density' => $variant['density'],
			'lines'   => $variant['lines'],
		);

		/*
		 * Glass is the one skin that means nothing on plain paper: it is a
		 * window, and a window onto a white wall is a white rectangle. It gets
		 * the gradient it was designed for, through the table's own CSS field —
		 * which is also how an author would do it — so that what is measured is
		 * the skin in the situation it is for, white text and all.
		 */
		$custom_css = 'glass' === $skin
			? "&{background-image:linear-gradient(135deg,#1f6f8b,#6b3fa0 55%,#c2557a);padding:22px;border-radius:20px}"
			: '';

		$source_id = LSTAB_Storage::insert(
			array(
				'title'         => ucfirst( $skin ) . ' — ' . $variant['title'],
				'custom_css'    => $custom_css,
				'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
				'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
				'sheet_kind'    => 'doc',
				'gid'           => '0',
				'tab_name'      => 'Cennik',
				'sync_interval' => 900,
				'style_preset'  => $skin,
				// The breakpoints decide, which is the only setting under which
				// a table folds into cards at all — and the folding is half of
				// what this suite is here to check.
				'layout'        => 'auto',
				'style_vars'    => $style_vars,
			)
		);

		if ( ! $source_id ) {
			lstab_skins_assert( false, "A source was made for {$skin} / {$variant['slug']}" );
			continue;
		}

		LSTAB_Sync::run( $source_id );
		++$made;

		$stored = LSTAB_Storage::get( $source_id );
		$vars   = isset( $stored['style_vars'] ) ? $stored['style_vars'] : array();

		lstab_skins_assert(
			$skin === $stored['style_preset'],
			"{$skin} survives being stored",
			(string) $stored['style_preset']
		);
		lstab_skins_assert(
			$variant['lines'] === ( isset( $vars['lines'] ) ? $vars['lines'] : '' ),
			"Its Lines setting survives being stored ({$variant['slug']})",
			wp_json_encode( $vars )
		);

		$html = LSTAB_Renderer::render( array( 'source_id' => $source_id ) );

		lstab_skins_assert(
			false !== strpos( $html, 'lstab-style-' . $skin ),
			"The rendered table wears the {$skin} class"
		);

		// A skin is a class and the dials are custom properties on the very same
		// element: that is the whole reason a skin can be modified afterwards
		// rather than being a take-it-or-leave-it package.
		if ( 'normal' !== $variant['lines'] ) {
			lstab_skins_assert(
				false !== strpos( $html, '--lstab-col-line' ),
				"{$skin} carries the Lines override inline ({$variant['slug']})"
			);
		}

		$tables[] = array(
			'id'      => (int) $source_id,
			'skin'    => $skin,
			'density' => $variant['density'],
			'lines'   => $variant['lines'],
		);

		$blocks .= '<!-- wp:heading {"level":2} --><h2>' . esc_html( ucfirst( $skin ) ) . '</h2><!-- /wp:heading -->' . "\n";
		$blocks .= '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . (int) $source_id . ',"align":"wide","showSearch":true,"showSort":true,"showUpdated":true} /-->' . "\n\n";
	}

	$slug     = 'lstab-skins-' . $variant['slug'];
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Skins — ' . $variant['title'],
			'post_name'    => $slug,
			'post_content' => $blocks,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);

	lstab_skins_assert( $page_id > 0, "A page shows every skin {$variant['title']}" );

	$manifest['pages'][] = array(
		'slug'    => $slug,
		'url'     => get_permalink( $page_id ),
		'density' => $variant['density'],
		'lines'   => $variant['lines'],
		'tables'  => $tables,
	);
}

lstab_skins_assert( count( $skins ) * count( $variants ) === $made, 'Every combination has a table of its own', (string) $made );

// ------------------------------------- a skin picked and then modified by hand

lstab_skins_section( 'A skin picked, then changed by hand' );

/*
 * The point of the whole arrangement: choosing Cards and then disagreeing with
 * it about four things. Each of those four is a custom property set inline on
 * the wrapper, and an inline property beats the preset's own class rule — so
 * this is the case that proves a template is a starting point rather than a
 * package.
 */
$tuned_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Cards, tuned by hand',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Cennik',
		'sync_interval' => 900,
		'style_preset'  => 'cards',
		'layout'        => 'auto',
		'style_vars'    => array(
			'background' => '#fff7ed',
			'text'       => '#432818',
			'accent'     => '#b45309',
			'border'     => '#e7c9a9',
			/*
			 * The two a skin can swallow: Cards used to write its heading
			 * colours as properties rather than tokens, so these two had
			 * nothing to override and the picker did nothing at all.
			 */
			'headerBg'   => '#2b1a4a',
			'headerText' => '#ffe9c7',
			'density'    => 'roomy',
			'lines'      => 'grid',
			'corners'    => 'square',
		),
	)
);

LSTAB_Sync::run( $tuned_id );

$tuned_html = LSTAB_Renderer::render( array( 'source_id' => $tuned_id ) );

lstab_skins_assert( false !== strpos( $tuned_html, 'lstab-style-cards' ), 'It is still the Cards skin' );

foreach ( array(
	'--lstab-bg:#fff7ed'   => 'its own background colour',
	'--lstab-fg:#432818'   => 'its own text colour',
	'--lstab-accent:#b45309' => 'its own accent',
	'--lstab-border:#e7c9a9' => 'its own line colour',
	'--lstab-pad-y:1.05em' => 'a roomier row',
	'--lstab-col-line:1px' => 'lines it asked for itself',
	'--lstab-radius:0'     => 'square corners',
	'--lstab-head-bg:#2b1a4a'  => 'its own heading colour',
	'--lstab-head-fg:#ffe9c7'  => 'its own heading ink',
) as $needle => $what ) {
	lstab_skins_assert( false !== strpos( $tuned_html, $needle ), "Cards, with {$what}", $needle );
}

/*
 * Glass, with a colour of somebody's own on the pane — and the worst colour
 * there is. "Glass colour" takes an ordinary opaque colour, as every well on
 * that screen does, and the pane lays it on at a seventh so the page still
 * shows through. A tint can only lighten, so white is the case that decides
 * whether the setting is safe to offer at all; the browser suite measures what
 * it does to the ink.
 */
$glass_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Glass, tinted white',
		'custom_css'    => '&{background-image:linear-gradient(135deg,#ffffff,#f4f6f8 60%,#ffffff);padding:22px;border-radius:20px}',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Cennik',
		'sync_interval' => 900,
		'style_preset'  => 'glass',
		'layout'        => 'auto',
		'style_vars'    => array( 'glassTint' => '#ffffff' ),
	)
);

LSTAB_Sync::run( $glass_id );

$glass_html = LSTAB_Renderer::render( array( 'source_id' => $glass_id ) );

lstab_skins_assert(
	false !== strpos( $glass_html, '--lstab-glass-tint:#ffffff' ),
	'A colour chosen for the glass reaches the table',
	$glass_html
);

$glass_slug = 'lstab-skins-szklo';
$glass_old  = get_page_by_path( $glass_slug, OBJECT, 'page' );
if ( $glass_old ) {
	wp_delete_post( $glass_old->ID, true );
}

$glass_page = wp_insert_post(
	array(
		'post_title'   => 'Skins — glass, tinted white',
		'post_name'    => $glass_slug,
		'post_content' => '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . (int) $glass_id . ',"align":"wide","showSearch":true,"showSort":true,"showUpdated":true} /-->',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$manifest['glass'] = array(
	'id'  => (int) $glass_id,
	'url' => get_permalink( $glass_page ),
);

$slug     = 'lstab-skins-wlasne';
$existing = get_page_by_path( $slug, OBJECT, 'page' );
if ( $existing ) {
	wp_delete_post( $existing->ID, true );
}

$tuned_page = wp_insert_post(
	array(
		'post_title'   => 'Skins — tuned by hand',
		'post_name'    => $slug,
		'post_content' => '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . (int) $tuned_id . ',"align":"wide","showSearch":true,"showSort":true,"showUpdated":true} /-->',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$manifest['tuned'] = array(
	'id'     => (int) $tuned_id,
	'url'    => get_permalink( $tuned_page ),
	'expect' => array(
		'--lstab-head-bg'  => '#2b1a4a',
		'--lstab-head-fg'  => '#ffe9c7',
		'--lstab-bg'       => '#fff7ed',
		'--lstab-border'   => '#e7c9a9',
		'--lstab-pad-y'    => '1.05em',
		'--lstab-col-line' => '1px',
		'--lstab-radius'   => '0',
	),
);

// ---------------------------------------- a pill in every skin, in awkward colours

lstab_skins_section( 'A pill keeps its word readable, whatever colour it is given' );

/*
 * A pill's word is not painted in the rule's colour outright: it is mixed with
 * the table's own ink, because the palette the dashboard offers is full of
 * colours no eye can read on paper. What decides whether that mixing is enough
 * is the share, and a share that is too generous stays invisible until somebody
 * picks a pale colour on a pale skin — which is exactly how a bright mint came
 * to be 3.44 to 1 on warm paper.
 *
 * So: the four nastiest colours anybody can choose, on every skin there is,
 * rendered by the plugin itself rather than described. The browser run measures
 * the word inside each badge against what is actually behind it.
 */
$pill_colours = array(
	'Produkt'      => '#ffffff',
	'Cena netto'   => '#5fe3cf',
	'Dostępność'   => '#000000',
	'Zaktualizowano' => '#f2b544',
);

/*
 * And the same badges again on a row a colour rule has already painted. That
 * is the harder case and the one nobody thinks of: on a painted row the badge
 * mixes with the ink the rule worked out for its own background, not with the
 * table's, so a badge that is comfortable on paper can be marginal on a row.
 * Both colours here are ones the dashboard would accept without a murmur.
 */
$pill_rows = array(
	array( 'column' => 'Dostępność', 'operator' => '=', 'value' => 'W magazynie', 'style' => '#8c3b46', 'scope' => 'row' ),
	array( 'column' => 'Dostępność', 'operator' => '=', 'value' => 'Na zamówienie', 'style' => '#e8f3c8', 'scope' => 'row' ),
);

$pill_tables = array();
$pill_blocks = '';
$pill_looks  = get_option( 'lstabp_column_looks', array() );
$pill_looks  = is_array( $pill_looks ) ? $pill_looks : array();
$pill_rules  = get_option( 'lstabp_rules', array() );
$pill_rules  = is_array( $pill_rules ) ? $pill_rules : array();

foreach ( $skins as $skin ) {
	$pill_id = LSTAB_Storage::insert(
		array(
			'title'         => 'Pills — ' . ucfirst( $skin ),
			// Glass is a window: onto a white wall it is a white rectangle, and
			// a badge measured against one says nothing about the skin.
			'custom_css'    => 'glass' === $skin
				? "&{background-image:linear-gradient(135deg,#1f6f8b,#6b3fa0 55%,#c2557a);padding:22px;border-radius:20px}"
				: '',
			'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
			'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
			'sheet_kind'    => 'doc',
			'gid'           => '0',
			'tab_name'      => 'Cennik',
			'sync_interval' => 900,
			'style_preset'  => $skin,
			'layout'        => 'auto',
			'style_vars'    => array(),
		)
	);

	if ( ! $pill_id ) {
		lstab_skins_assert( false, "A source was made for pills on {$skin}" );
		continue;
	}

	LSTAB_Sync::run( $pill_id );

	$wanted = array();

	foreach ( $pill_colours as $column => $colour ) {
		$wanted[ $column ] = array(
			'look'  => 'pill',
			'tint'  => $colour,
			'ink'   => '',
			'label' => '',
		);
	}

	$pill_looks[ $pill_id ] = $wanted;
	$pill_rules[ $pill_id ] = $pill_rows;

	$pill_tables[] = array(
		'id'      => (int) $pill_id,
		'skin'    => $skin,
		'colours' => array_values( $pill_colours ),
	);

	$pill_blocks .= '<!-- wp:heading {"level":2} --><h2>' . esc_html( ucfirst( $skin ) ) . '</h2><!-- /wp:heading -->' . "\n";
	$pill_blocks .= '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . (int) $pill_id . ',"align":"wide","showSearch":false,"showSort":false,"showUpdated":false} /-->' . "\n\n";
}

update_option( 'lstabp_column_looks', $pill_looks, false );
update_option( 'lstabp_rules', $pill_rules, false );

foreach ( $pill_tables as $one ) {
	LSTAB_Storage::flush_cache( $one['id'] );
}

$pill_html = LSTAB_Renderer::render( array( 'source_id' => $pill_tables[0]['id'] ) );

lstab_skins_assert(
	false !== strpos( $pill_html, '--lstabp-pill-ink:' ),
	'The badge carries an ink worked out from the colour chosen',
	substr( $pill_html, 0, 400 )
);

lstab_skins_assert(
	false === strpos( $pill_html, '--lstabp-pill-ink:color-mix(in srgb,#ffffff 55%' ),
	'and that ink no longer keeps more than half the chosen colour'
);

$pill_slug = 'lstab-skins-pigulki';
$pill_old  = get_page_by_path( $pill_slug, OBJECT, 'page' );
if ( $pill_old ) {
	wp_delete_post( $pill_old->ID, true );
}

$pill_page = wp_insert_post(
	array(
		'post_title'   => 'Skins — pills in awkward colours',
		'post_name'    => $pill_slug,
		'post_content' => $pill_blocks,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

lstab_skins_assert( $pill_page > 0, 'A page shows a badge on every skin' );

$manifest['pills'] = array(
	'url'     => get_permalink( $pill_page ),
	'tables'  => $pill_tables,
	'colours' => array_values( $pill_colours ),
);

// ---------------------------------------------------------------- manifest

$out = __DIR__ . '/fixtures/skins-php-said.json';
file_put_contents( $out, wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

lstab_skins_section( 'What the browser run will open' );
lstab_skins_assert( file_exists( $out ), 'The manifest was written', $out );
echo '        ' . count( $manifest['pages'] ) . " pages, " . ( $made + 2 ) . " tables\n";
foreach ( $manifest['pages'] as $page ) {
	echo '        ' . $page['url'] . "\n";
}
echo '        ' . $manifest['tuned']['url'] . "\n";
echo '        ' . $manifest['glass']['url'] . "\n";
echo '        ' . $manifest['pills']['url'] . "\n";

// ------------------------------------------------------------------ result

echo "\n";
echo "  \033[1m{$GLOBALS['lstab_passed']} passed";
if ( $GLOBALS['lstab_failed'] ) {
	echo ", \033[31m{$GLOBALS['lstab_failed']} failed";
}
echo "\033[0m\n\n";

exit( $GLOBALS['lstab_failed'] ? 1 : 0 );
