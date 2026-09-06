<?php
/**
 * Marking the part of a cell the search actually matched.
 *
 * A search that hides seven rows and leaves five has answered the question it
 * was asked, and left a second one: what, in these five, was the match? On a
 * five-column table of long descriptions that is a real hunt, and the answer is
 * often in a column nobody was looking at.
 *
 * Marked text answers it at a glance, and it is worth saying why this is not
 * cosmetic: it is the only way to tell a search that worked from one that
 * matched something you did not mean.
 *
 * The work happens twice, because searching does. A table short enough to send
 * in one piece is searched in the browser, so the marking is done there too; a
 * paged table is searched on the server across the whole sheet, so the marking
 * is written into the page it sends back. This class is the second of those.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search-term marking for server-rendered tables.
 */
class LSTAB_Highlight {

	/**
	 * What is being searched for while one table is drawn.
	 *
	 * @var string
	 */
	protected static $needle = '';

	/**
	 * Register hooks.
	 *
	 * After the linker at priority 5, so a match inside a web address is
	 * marked within the link rather than instead of it.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'lstab_render_cell', array( __CLASS__, 'cell' ), 20, 5 );
	}

	/**
	 * Start marking, for the table about to be drawn.
	 *
	 * Named by the renderer rather than read from the address here, because
	 * only the renderer knows whether this table is the one being searched:
	 * two tables on a page carry their own search arguments, and a table that
	 * searches in the browser must not be marked twice.
	 *
	 * @param string $needle What was searched for.
	 * @return void
	 */
	public static function begin( $needle ) {
		self::$needle = trim( (string) $needle );
	}

	/**
	 * Stop marking.
	 *
	 * @return void
	 */
	public static function end() {
		self::$needle = '';
	}

	/**
	 * Mark the search term inside one cell.
	 *
	 * @param string|null $html      Replacement HTML so far, or null for the default.
	 * @param string      $value     Raw cell value.
	 * @param int         $col_index Column index.
	 * @param int         $row_index Row index.
	 * @param array       $source    Source row.
	 * @return string|null
	 */
	public static function cell( $html, $value, $col_index = 0, $row_index = 0, $source = array() ) {
		if ( '' === self::$needle ) {
			return $html;
		}

		// Whatever the cell was going to be — a plain value, a link, an
		// add-on's replacement — is what gets marked.
		$marked = self::mark( null === $html ? esc_html( (string) $value ) : (string) $html );

		return null === $html && $marked === esc_html( (string) $value ) ? null : $marked;
	}

	/**
	 * Wrap every occurrence of the search term in a mark element.
	 *
	 * The needle is escaped before it is looked for, so that searching for an
	 * ampersand finds the "&amp;" the escaped cell actually holds — and so
	 * that nothing a visitor types can reach the page as markup, since the
	 * only thing written around it here is a fixed pair of tags.
	 *
	 * @param string $html Cell HTML, already escaped.
	 * @return string
	 */
	public static function mark( $html ) {
		$needle = esc_html( self::$needle );

		if ( '' === $needle ) {
			return $html;
		}

		$pattern = '~' . preg_quote( $needle, '~' ) . '~iu';

		// Tags and text alternate, and only the text is looked at: a search for
		// "class" must not find the word inside an attribute and cut the tag in
		// half.
		$parts = preg_split( '~(<[^>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = '';

		foreach ( (array) $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( '<' === $part[0] ) {
				$out .= $part;
				continue;
			}

			$replaced = preg_replace( $pattern, '<mark class="lstab-hit">$0</mark>', $part );

			// A needle the pattern engine choked on — a lone invalid byte, say
			// — leaves the cell as it was rather than emptying it.
			$out .= null === $replaced ? $part : $replaced;
		}

		return $out;
	}
}
