<?php
/**
 * Extracts translatable strings from both plugins and writes languages/*.pot,
 * plus a .po/.mo pair for any translation table under the plugin's translation
 * directory. Pro ships the catalogue only — its strings are English and there
 * is no locale to compile yet — but the catalogue is what a translator needs,
 * and without one in the archive the add-on cannot be translated at all.
 *
 * WordPress ships no gettext binaries, so this leans on core's own POMO
 * classes for writing the catalogues.
 *
 * Usage: php tools/make-pot.php /path/to/wordpress
 *
 * @package LiveSheetsTable\Tools
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
if ( ! $wp_root || ! file_exists( $wp_root . '/wp-includes/pomo/po.php' ) ) {
	fwrite( STDERR, "Usage: php tools/make-pot.php /path/to/wordpress\n" );
	exit( 1 );
}

// POMO leans on core's polyfills (array_last() and friends).
require_once $wp_root . '/wp-includes/compat.php';
require_once $wp_root . '/wp-includes/pomo/translations.php';
require_once $wp_root . '/wp-includes/pomo/po.php';
require_once $wp_root . '/wp-includes/pomo/mo.php';

/** The plugins to extract, each with its own domain and translation tables. */
$lstab_projects = array(
	array(
		'dir'          => dirname( __DIR__ ) . '/live-sheets-table',
		'domain'       => 'live-sheets-table',
		'name'         => 'Live Sheets Table',
		'translations' => __DIR__ . '/translations',
	),
	array(
		'dir'          => dirname( __DIR__ ) . '/live-sheets-table-pro',
		'domain'       => 'live-sheets-table-pro',
		'name'         => 'Live Sheets Table Pro',
		'translations' => __DIR__ . '/translations-pro',
	),
);

/** Functions whose arguments hold translatable text, mapped to their shape. */
const LSTAB_FUNCTIONS = array(
	'__'            => 'single',
	'_e'            => 'single',
	'esc_html__'    => 'single',
	'esc_attr__'    => 'single',
	'esc_html_e'    => 'single',
	'esc_attr_e'    => 'single',
	'_x'            => 'context',
	'esc_html_x'    => 'context',
	'esc_attr_x'    => 'context',
	'_n'            => 'plural',
	'_nx'           => 'plural_context',
);

/**
 * Record one extracted string.
 *
 * @param array  $entries  Accumulator, by reference.
 * @param array  $entry    Entry fields.
 * @return void
 */
function lstab_add_entry( &$entries, $entry ) {
	$key = ( isset( $entry['context'] ) ? $entry['context'] . "\4" : '' ) . $entry['singular'];

	if ( isset( $entries[ $key ] ) ) {
		$entries[ $key ]['references'] = array_unique(
			array_merge( $entries[ $key ]['references'], $entry['references'] )
		);
		if ( empty( $entries[ $key ]['comment'] ) && ! empty( $entry['comment'] ) ) {
			$entries[ $key ]['comment'] = $entry['comment'];
		}
		return;
	}

	$entries[ $key ] = $entry;
}

/**
 * Walk a PHP file's tokens looking for translation calls.
 *
 * @param string $file     Absolute path.
 * @param string $relative Path shown in the catalogue.
 * @param array  $entries  Accumulator, by reference.
 * @return void
 */
function lstab_scan_php( $file, $relative, &$entries ) {
	$tokens  = token_get_all( (string) file_get_contents( $file ) );
	$count   = count( $tokens );
	$comment = '';

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( is_array( $token ) && T_COMMENT === $token[0] && false !== stripos( $token[1], 'translators:' ) ) {
			$comment = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', $token[1] ) );
			continue;
		}

		if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
			continue;
		}

		$name = $token[1];
		if ( ! isset( LSTAB_FUNCTIONS[ $name ] ) ) {
			continue;
		}

		// Skip method calls and definitions such as $obj->__() or function __().
		$previous = $i > 0 ? $tokens[ $i - 1 ] : null;
		if ( is_array( $previous ) && in_array( $previous[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}

		// Collect the literal string arguments of this call.
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( '(' !== $tokens[ $j ] ) {
			continue;
		}

		$depth = 0;
		$args  = array();
		$part  = null;

		for ( ; $j < $count; $j++ ) {
			$inner = $tokens[ $j ];

			if ( '(' === $inner ) {
				$depth++;
				continue;
			}
			if ( ')' === $inner ) {
				$depth--;
				if ( 0 === $depth ) {
					$args[] = $part;
					break;
				}
				continue;
			}
			if ( ',' === $inner && 1 === $depth ) {
				$args[] = $part;
				$part   = null;
				continue;
			}
			if ( is_array( $inner ) && T_CONSTANT_ENCAPSED_STRING === $inner[0] && 1 === $depth && null === $part ) {
				$part = stripcslashes( substr( $inner[1], 1, -1 ) );
				continue;
			}
			if ( is_array( $inner ) && T_WHITESPACE === $inner[0] ) {
				continue;
			}
			if ( 1 === $depth && null === $part ) {
				// A non-literal argument (variable, concatenation): mark it unusable.
				$part = false;
			}
		}

		$shape     = LSTAB_FUNCTIONS[ $name ];
		$reference = $relative . ':' . $token[2];
		$entry     = array(
			'references' => array( $reference ),
			'comment'    => $comment,
		);
		$comment   = '';

		if ( 'single' === $shape ) {
			if ( ! is_string( $args[0] ?? null ) ) {
				continue;
			}
			$entry['singular'] = $args[0];
		} elseif ( 'context' === $shape ) {
			if ( ! is_string( $args[0] ?? null ) || ! is_string( $args[1] ?? null ) ) {
				continue;
			}
			$entry['singular'] = $args[0];
			$entry['context']  = $args[1];
		} elseif ( 'plural' === $shape ) {
			if ( ! is_string( $args[0] ?? null ) || ! is_string( $args[1] ?? null ) ) {
				continue;
			}
			$entry['singular'] = $args[0];
			$entry['plural']   = $args[1];
		} else {
			if ( ! is_string( $args[0] ?? null ) || ! is_string( $args[1] ?? null ) || ! is_string( $args[3] ?? null ) ) {
				continue;
			}
			$entry['singular'] = $args[0];
			$entry['plural']   = $args[1];
			$entry['context']  = $args[3];
		}

		lstab_add_entry( $entries, $entry );
	}
}

/**
 * Pull __( '…', 'domain' ) out of the plain-JS editor scripts.
 *
 * @param string $file     Absolute path.
 * @param string $relative Path shown in the catalogue.
 * @param array  $entries  Accumulator, by reference.
 * @param string $domain   Text domain to look for.
 * @return void
 */
function lstab_scan_js( $file, $relative, &$entries, $domain ) {
	$source = (string) file_get_contents( $file );

	$pattern = "#__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'" . preg_quote( $domain, '#' ) . "'\s*\)#";

	if ( ! preg_match_all( $pattern, $source, $matches, PREG_OFFSET_CAPTURE ) ) {
		return;
	}

	foreach ( $matches[1] as $match ) {
		$line = substr_count( substr( $source, 0, $match[1] ), "\n" ) + 1;
		lstab_add_entry(
			$entries,
			array(
				'singular'   => stripcslashes( $match[0] ),
				'references' => array( $relative . ':' . $line ),
				'comment'    => '',
			)
		);
	}
}

/**
 * Build a PO object from the extracted entries.
 *
 * @param array $entries      Extracted entries.
 * @param array $headers      Catalogue headers.
 * @param array $translations Optional msgid => translation(s) map.
 * @return PO
 */
function lstab_build_po( $entries, $headers, $translations = array() ) {
	$po = new PO();
	$po->set_headers( $headers );

	foreach ( $entries as $entry ) {
		$args = array(
			'singular'            => $entry['singular'],
			'references'          => $entry['references'],
			'extracted_comments'  => $entry['comment'],
		);

		if ( isset( $entry['plural'] ) ) {
			$args['plural']    = $entry['plural'];
			$args['is_plural'] = true;
		}
		if ( isset( $entry['context'] ) ) {
			$args['context'] = $entry['context'];
		}

		$key = ( isset( $entry['context'] ) ? $entry['context'] . "\4" : '' ) . $entry['singular'];

		if ( isset( $translations[ $key ] ) ) {
			$args['translations'] = (array) $translations[ $key ];
		}

		$po->add_entry( new Translation_Entry( $args ) );
	}

	return $po;
}

foreach ( $lstab_projects as $lstab_project ) {
	if ( ! is_dir( $lstab_project['dir'] ) ) {
		echo "Skipped {$lstab_project['name']}: no such directory\n";
		continue;
	}

	lstab_make_catalogues( $lstab_project );
}

/**
 * Extract one plugin and write its catalogues.
 *
 * @param array $project Plugin directory, domain, display name and the
 *                       directory its locale tables live in.
 * @return void
 */
function lstab_make_catalogues( $project ) {
	$plugin_dir = $project['dir'];
	$domain     = $project['domain'];
	$entries    = array();

	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir ) );

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		$path     = $file->getPathname();
		$relative = ltrim( str_replace( $plugin_dir, '', $path ), '/' );

		if ( 'php' === $file->getExtension() ) {
			lstab_scan_php( $path, $relative, $entries );
		} elseif ( 'js' === $file->getExtension() ) {
			lstab_scan_js( $path, $relative, $entries, $domain );
		}
	}

	ksort( $entries );

	$languages = $plugin_dir . '/languages';
	if ( ! is_dir( $languages ) ) {
		mkdir( $languages, 0755, true );
	}

	$pot_headers = array(
		'Project-Id-Version'        => $project['name'] . ' 1.0.0',
		'Report-Msgid-Bugs-To'      => 'https://example.com/live-sheets-table/support',
		'POT-Creation-Date'         => gmdate( 'Y-m-d H:iO' ),
		'PO-Revision-Date'          => 'YEAR-MO-DA HO:MI+ZONE',
		'Last-Translator'           => 'FULL NAME <EMAIL@ADDRESS>',
		'Language-Team'             => 'LANGUAGE <LL@li.org>',
		'MIME-Version'              => '1.0',
		'Content-Type'              => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'X-Domain'                  => $domain,
		'Plural-Forms'              => 'nplurals=2; plural=(n != 1);',
	);

	$pot = lstab_build_po( $entries, $pot_headers );
	$pot->export_to_file( $languages . '/' . $domain . '.pot' );
	echo 'Wrote ' . count( $entries ) . " entries to {$domain}/languages/{$domain}.pot\n";

	// Locale catalogues, where a translation table has been written for one.
	// The source language is English, so having none is the normal state for a
	// plugin nobody has translated yet, not a fault.
	foreach ( (array) glob( $project['translations'] . '/*.php' ) as $translation_file ) {
		$locale       = basename( $translation_file, '.php' );
		$translations = require $translation_file;

		$headers                     = $pot_headers;
		$headers['PO-Revision-Date'] = gmdate( 'Y-m-d H:iO' );
		$headers['Language']         = $locale;
		$headers['Last-Translator']  = 'Live Sheets Table';
		$headers['Language-Team']    = $locale;

		if ( 'pl_PL' === $locale ) {
			$headers['Plural-Forms'] = 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);';
		}

		$po = lstab_build_po( $entries, $headers, $translations );
		$po->export_to_file( $languages . '/' . $domain . '-' . $locale . '.po' );

		$mo = new MO();
		$mo->set_headers( $headers );
		foreach ( $po->entries as $entry ) {
			$mo->add_entry( $entry );
		}
		$mo->export_to_file( $languages . '/' . $domain . '-' . $locale . '.mo' );

		$done = 0;
		foreach ( $entries as $key => $entry ) {
			if ( isset( $translations[ $key ] ) ) {
				$done++;
			}
		}
		printf(
			"Wrote %s: %d/%d translated%s\n",
			$locale,
			$done,
			count( $entries ),
			$done === count( $entries ) ? '' : ' — MISSING ' . ( count( $entries ) - $done )
		);

		foreach ( $entries as $key => $entry ) {
			if ( ! isset( $translations[ $key ] ) ) {
				echo '  missing: ' . str_replace( "\4", ' | ', $key ) . "\n";
			}
		}
	}
}
