<?php
/**
 * Clickable links in cells.
 *
 * A published sheet is very often a directory, a contact list or a catalogue,
 * and those hold addresses. Left as plain text they have to be selected and
 * copied by hand, which on a phone is close to impossible — so a column of
 * links reads as broken even though nothing failed.
 *
 * The linking is done on the raw value and each piece is escaped as it is
 * written, rather than by escaping first and then trying to find links in the
 * escaped text: an "&" in a query string would already have become "&amp;" and
 * the address would be wrong.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cell auto-linking.
 */
class LSTAB_Links {

	/**
	 * Addresses worth linking.
	 *
	 * Deliberately narrow: an http(s) address, a bare "www." host, or an
	 * e-mail. Nothing else becomes a link, so a value can never turn into a
	 * scheme the sheet's author did not write.
	 */
	const PATTERN = '~(https?://[^\s<>"\']+|www\.[^\s<>"\']+|[^\s<>"\'@,;]+@[^\s<>"\'@,;]+\.[A-Za-z]{2,})~u';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Ahead of the default priority, so an add-on replacing a cell outright
		// still wins.
		add_filter( 'lstab_render_cell', array( __CLASS__, 'render' ), 5, 5 );
	}

	/**
	 * Turn addresses in a cell into links.
	 *
	 * @param string|null         $html      Replacement HTML from an earlier filter.
	 * @param string              $value     Raw cell value.
	 * @param int                 $col_index Column index.
	 * @param int                 $row_index Row index.
	 * @param array<string,mixed> $source    Source row.
	 * @return string|null
	 */
	public static function render( $html, $value, $col_index, $row_index, $source ) {
		if ( null !== $html ) {
			return $html;
		}

		if ( empty( $source['link_cells'] ) ) {
			return null;
		}

		$linked = self::linkify( (string) $value );

		// Returning null leaves the renderer to escape the value itself, which
		// is the cheaper path and the one most cells take.
		return null === $linked ? null : $linked;
	}

	/**
	 * Build the HTML for a value, or null when it holds no address.
	 *
	 * @param string $value Raw cell value.
	 * @return string|null
	 */
	public static function linkify( $value ) {
		if ( '' === $value || ! preg_match( self::PATTERN, $value ) ) {
			return null;
		}

		$pieces = preg_split( self::PATTERN, $value, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $pieces ) ) {
			return null;
		}

		$html    = '';
		$is_link = false;
		$linked  = false;

		foreach ( $pieces as $piece ) {
			if ( ! $is_link ) {
				$html .= esc_html( $piece );
			} else {
				$anchor = self::anchor( $piece );

				if ( null === $anchor ) {
					$html .= esc_html( $piece );
				} else {
					$html  .= $anchor;
					$linked = true;
				}
			}

			$is_link = ! $is_link;
		}

		return $linked ? $html : null;
	}

	/**
	 * One address as an anchor, or null when it cannot be linked safely.
	 *
	 * @param string $match Matched address.
	 * @return string|null
	 */
	protected static function anchor( $match ) {
		// Sentences end in punctuation and addresses do not, so a trailing
		// full stop or bracket belongs to the text around the link.
		$trailing = '';

		while ( '' !== $match && false !== strpos( '.,;:!?)]}', substr( $match, -1 ) ) ) {
			$trailing = substr( $match, -1 ) . $trailing;
			$match    = substr( $match, 0, -1 );
		}

		if ( '' === $match ) {
			return null;
		}

		if ( false !== strpos( $match, '@' ) && false === strpos( $match, '/' ) ) {
			$href = 'mailto:' . $match;
		} elseif ( 0 === stripos( $match, 'www.' ) ) {
			$href = 'https://' . $match;
		} else {
			$href = $match;
		}

		$safe = esc_url( $href );

		// esc_url() empties anything outside its allowed protocols, so a value
		// that somehow reached here carrying another scheme stays plain text.
		if ( '' === $safe ) {
			return null;
		}

		return sprintf(
			// The addresses come from a spreadsheet the site owner may not
			// control, so they are marked as such rather than passing on the
			// site's own standing.
			'<a href="%1$s" rel="nofollow ugc">%2$s</a>%3$s',
			$safe,
			esc_html( $match ),
			esc_html( $trailing )
		);
	}
}
