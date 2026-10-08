<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Links {
	const PATTERN = '~(https?://[^\s<>"\']+|www\.[^\s<>"\']+|[^\s<>"\'@,;]+@[^\s<>"\'@,;]+\.[A-Za-z]{2,})~u';

	public function register() {
		add_filter( 'lstab_render_cell', array( __CLASS__, 'render' ), 5, 5 );
	}

	public static function render( $html, $value, $col_index, $row_index, $source ) {
		if ( null !== $html ) {
			return $html;
		}

		if ( empty( $source['link_cells'] ) ) {
			return null;
		}

		$linked = self::linkify( (string) $value );

		return null === $linked ? null : $linked;
	}

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

	protected static function anchor( $match ) {
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

		if ( '' === $safe ) {
			return null;
		}

		return sprintf(
			'<a href="%1$s" rel="nofollow ugc">%2$s</a>%3$s',
			$safe,
			esc_html( $match ),
			esc_html( $trailing )
		);
	}
}
