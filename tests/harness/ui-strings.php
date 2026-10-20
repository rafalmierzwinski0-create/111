<?php
/**
 * Every piece of text the two plugins show, with where it is written, for the
 * audits that read help text. Reads the source rather than a running site, so
 * text that only appears in one state of one screen is still seen.
 *
 * Usage: php tests/harness/ui-strings.php <repo root>   (prints JSON)
 */
$root  = rtrim( $argv[1], '/' );
$found = array();
$call  = '/(?<![\w>])(?:__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x|esc_attr_x)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\')?/';

foreach ( array( 'live-sheets-table', 'live-sheets-table-pro' ) as $plugin ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$plugin", FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		$ext = pathinfo( (string) $file, PATHINFO_EXTENSION );
		if ( ! in_array( $ext, array( 'php', 'js' ), true ) ) {
			continue;
		}
		$source = (string) file_get_contents( (string) $file );
		if ( ! preg_match_all( $call, $source, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			continue;
		}
		foreach ( $m as $hit ) {
			$line    = substr_count( substr( $source, 0, $hit[0][1] ), "\n" ) + 1;
			$before  = substr( $source, max( 0, $hit[0][1] - 400 ), min( 400, $hit[0][1] ) );
			$comment = '';
			$open    = strrpos( $before, '/* translators:' );
			if ( false !== $open ) {
				$close = strpos( $before, '*/', $open );
				if ( false !== $close && ! preg_match( '/(?:__|_e|_n|_x)\(/', substr( $before, $close ) ) ) {
					$comment = trim( substr( $before, $open + 15, $close - $open - 15 ) );
				}
			}
			$found[] = array(
				'text'     => stripcslashes( str_replace( "\\'", "'", $hit[1][0] ) ),
				'plural'   => isset( $hit[2] ) && 0 === strpos( $hit[0][0], '_n(' ) ? stripcslashes( str_replace( "\\'", "'", $hit[2][0] ) ) : '',
				'where'    => substr( (string) $file, strlen( $root ) + 1 ) . ':' . $line,
				'comment'  => $comment,
				'function' => substr( $hit[0][0], 0, strpos( $hit[0][0], '(' ) ),
			);
		}
	}
}

echo json_encode( $found, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
