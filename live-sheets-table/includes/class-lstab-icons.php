<?php
/**
 * The drawn parts of the dashboard: icons and spot illustrations.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every picture in this plugin, drawn as SVG in the page itself.
 *
 * Nothing here is a file the browser has to fetch, which matters more than it
 * sounds: an icon font or a sprite image is a request that can be slow, blocked
 * or cached wrong, and a dashboard whose icons arrive late looks broken in
 * exactly the moment somebody is deciding whether to trust it.
 *
 * They are also all drawn in `currentColor`, so they take the colour of the
 * text around them. Change the accent in one place and every drawing follows.
 */
class LSTAB_Icons {

	/**
	 * One icon, as inline SVG.
	 *
	 * @param string $name  Icon name.
	 * @param string $class Extra class attribute.
	 * @return string Escaped, ready to echo.
	 */
	public static function icon( $name, $class = '' ) {
		$paths = self::paths();

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="lstab-icon%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
			$class ? ' ' . esc_attr( $class ) : '',
			$paths[ $name ]
		);
	}

	/**
	 * An icon on a coloured tile, for the head of a section.
	 *
	 * A settings screen made of white boxes reads as a form somebody has to get
	 * through. One coloured mark per section gives the eye somewhere to land,
	 * makes the screen scannable from a distance, and — because the hue is
	 * fixed per section — becomes a landmark people navigate by without ever
	 * reading the heading twice.
	 *
	 * The glyph is the same line work as everywhere else, drawn in white on the
	 * tile, so nothing here is a second icon set to keep in step with the first.
	 *
	 * @param string $name Icon name.
	 * @param string $hue  One of the hues in the stylesheet: teal, indigo,
	 *                     amber, rose, violet, sky, slate.
	 * @return string
	 */
	public static function badge( $name, $hue = 'teal' ) {
		$paths = self::paths();

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$hues = array( 'teal', 'indigo', 'amber', 'rose', 'violet', 'sky', 'slate' );

		if ( ! in_array( $hue, $hues, true ) ) {
			$hue = 'teal';
		}

		return sprintf(
			'<span class="lstab-badge lstab-badge--%1$s" aria-hidden="true">'
				. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">%2$s</svg>'
				. '</span>',
			esc_attr( $hue ),
			$paths[ $name ]
		);
	}

	/**
	 * The line work, one entry per icon.
	 *
	 * Kept as plain path data on a 24-unit grid with one stroke weight, so the
	 * set reads as one family rather than as icons collected from three places.
	 *
	 * @return array<string,string>
	 */
	protected static function paths() {
		return array(
			'grid'     => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9.5h18M9 9.5V20"></path>',
			'columns'  => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M9.5 4v16M15 4v16"></path>',
			'check'    => '<circle cx="12" cy="12" r="8.6"></circle><path d="m8.4 12.2 2.6 2.6 4.7-5.1"></path>',
			'alert'    => '<path d="M12 4.2 21 19.6H3z"></path><path d="M12 10v4.1M12 17.1h.01"></path>',
			'cross'    => '<circle cx="12" cy="12" r="8.6"></circle><path d="m9.2 9.2 5.6 5.6M14.8 9.2l-5.6 5.6"></path>',
			'refresh'  => '<path d="M20.4 12a8.4 8.4 0 1 1-2.5-6"></path><path d="M18.4 2.6v3.6h-3.6"></path>',
			'copy'     => '<rect x="9" y="9" width="11.5" height="11.5" rx="2.4"></rect><path d="M15 6.2V5.4A2 2 0 0 0 13 3.4H5.6a2 2 0 0 0-2 2V13a2 2 0 0 0 2 2h.9"></path>',
			'clock'    => '<circle cx="12" cy="12" r="8.6"></circle><path d="M12 7v5.3l3.4 2"></path>',
			'plus'     => '<path d="M12 5.2v13.6M5.2 12h13.6"></path>',
			'sliders'  => '<path d="M4 7.4h14.6M4 16.6h11"></path><circle cx="16.2" cy="7.4" r="2.3"></circle><circle cx="8.6" cy="16.6" r="2.3"></circle>',
			'spark'    => '<path d="m12 3.6 2 5.6 5.6 2-5.6 2-2 5.6-2-5.6-5.6-2 5.6-2z"></path>',
			'pencil'   => '<path d="M4 20.1h4.1L20 8.2a2.1 2.1 0 0 0 0-3l-1.2-1.2a2.1 2.1 0 0 0-3 0L4 15.9z"></path>',
			'layers'   => '<path d="M12 3.6 3.4 8.3 12 13l8.6-4.7z"></path><path d="m3.4 13.9 8.6 4.7 8.6-4.7"></path>',
			'doc'      => '<path d="M6 3.4h7.6L19 8.8V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.4a1 1 0 0 1 1-1z"></path><path d="M13.4 3.6v5.4H19"></path>',
			'brush'    => '<path d="M6 14.4 14.6 5.8a2.4 2.4 0 0 1 3.4 3.4L9.4 17.8"></path><path d="M6 14.4c-1.6 1-1.4 3.4-2.6 4.6 1.9.6 4.6.5 6-1.2"></path>',
			'eye'      => '<path d="M2.6 12S6.4 5.8 12 5.8 21.4 12 21.4 12 17.6 18.2 12 18.2 2.6 12 2.6 12z"></path><circle cx="12" cy="12" r="2.9"></circle>',
			'link'     => '<path d="M10.4 13.6a3.6 3.6 0 0 0 5.2 0l2.8-2.8a3.7 3.7 0 0 0-5.2-5.2l-1.4 1.4"></path><path d="M13.6 10.4a3.6 3.6 0 0 0-5.2 0l-2.8 2.8a3.7 3.7 0 0 0 5.2 5.2l1.4-1.4"></path>',
			'shield'   => '<path d="M12 3.2 5 5.8v5.4c0 4.2 2.9 7.6 7 9.6 4.1-2 7-5.4 7-9.6V5.8z"></path><path d="m9.2 12 2 2 3.6-3.8"></path>',
			'bolt'     => '<path d="M13.4 2.6 4.8 13.4h6L10 21.4l8.6-10.8h-6z"></path>',
			'lock'     => '<rect x="4.6" y="10.4" width="14.8" height="10" rx="2.2"></rect><path d="M8.2 10.4V7.6a3.8 3.8 0 0 1 7.6 0v2.8"></path>',
			'play'     => '<circle cx="12" cy="12" r="8.8"></circle><path d="m10.2 8.6 6 3.4-6 3.4z"></path>',
			'trash'    => '<path d="M4.6 6.6h14.8M9.4 6.6V4.8a1.2 1.2 0 0 1 1.2-1.2h2.8a1.2 1.2 0 0 1 1.2 1.2v1.8"></path><path d="M6.6 6.6 7.4 20a1.2 1.2 0 0 0 1.2 1.1h6.8a1.2 1.2 0 0 0 1.2-1.1l.8-13.4"></path>',
			'external' => '<path d="M14 4.6h5.4V10"></path><path d="M19.4 4.6 11 13"></path><path d="M18.4 14.4v4.2a1.4 1.4 0 0 1-1.4 1.4H5.4A1.4 1.4 0 0 1 4 18.6V7a1.4 1.4 0 0 1 1.4-1.4h4.2"></path>',
		);
	}

	/**
	 * One spot illustration, as inline SVG.
	 *
	 * These stand where somebody is stuck — a screen with nothing on it yet, or
	 * one where something has gone wrong. They are the only decoration in the
	 * plugin, and each is allowed to exist in exactly one place.
	 *
	 * @param string $name Illustration name.
	 * @return string
	 */
	public static function art( $name ) {
		$art = self::drawings();

		if ( ! isset( $art[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="lstab-art lstab-art--%s" viewBox="%s" fill="none" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( $name ),
			esc_attr( $art[ $name ]['box'] ),
			$art[ $name ]['body']
		);
	}

	/**
	 * The illustrations themselves.
	 *
	 * @return array<string,array{box:string,body:string}>
	 */
	protected static function drawings() {
		return array(
			// A spreadsheet becoming a table: the whole promise of the plugin,
			// and the only thing worth drawing on a screen with nothing on it.
			'start'   => array(
				'box'  => '0 0 270 106',
				'body' => '<rect x="8" y="17" width="76" height="72" rx="6" stroke="currentColor" stroke-width="2" opacity=".5"></rect>
					<path d="M8 35h76M34 35v54" stroke="currentColor" stroke-width="2" opacity=".38"></path>
					<path d="M46 49h26M46 61h26M46 73h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".26"></path>
					<path d="M18 47h6M18 59h6M18 71h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".5"></path>
					<path d="M100 53h54" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="1 7"></path>
					<path d="m148 46 8 7-8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
					<rect x="176" y="17" width="84" height="72" rx="6" stroke="currentColor" stroke-width="2"></rect>
					<path d="M176 38h84" stroke="currentColor" stroke-width="2"></path>
					<path d="M188 27h22M224 27h24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".5"></path>
					<path d="M188 50h24M224 50h24M188 64h32M224 64h16M188 78h20M224 78h24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".28"></path>
					<rect x="255" y="84" width="9" height="9" rx="1.5" fill="currentColor"></rect>',
			),

			// Nothing matched. Rows behind the glass, and the glass empty.
			'nothing' => array(
				'box'  => '0 0 200 76',
				'body' => '<path d="M40 18h120M40 38h84M40 58h104" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".14"></path>
					<circle cx="92" cy="36" r="25" stroke="currentColor" stroke-width="2.5"></circle>
					<path d="m110 54 16 16" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
					<path d="M83 36h18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".55"></path>',
			),

			// Google is silent; the saved copy stands next to it and works.
			'offline' => array(
				'box'  => '0 0 200 76',
				'body' => '<path d="M52 32a15 15 0 0 1 29-4 12 12 0 0 1 16 11 10 10 0 0 1-10 10H61a14 14 0 0 1-9-17z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" opacity=".4"></path>
					<path d="M74 54v7M60 58v7M88 58v7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="2 6" opacity=".4"></path>
					<rect x="112" y="30" width="76" height="40" rx="5" stroke="currentColor" stroke-width="2.5"></rect>
					<path d="M112 43h76" stroke="currentColor" stroke-width="2.5"></path>
					<path d="m128 56 5 5 10-11" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
					<path d="M152 57h22M152 65h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".3"></path>
					<rect x="183" y="65" width="8" height="8" rx="1.4" fill="currentColor"></rect>',
			),

			// Everything is current. Quiet is information too.
			'calm'    => array(
				'box'  => '0 0 200 76',
				'body' => '<circle cx="100" cy="38" r="32" stroke="currentColor" stroke-width="2" opacity=".14"></circle>
					<circle cx="100" cy="38" r="23" stroke="currentColor" stroke-width="2" opacity=".28"></circle>
					<circle cx="100" cy="38" r="15" stroke="currentColor" stroke-width="2.5"></circle>
					<path d="m93 38 5 5 10-11" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
					<path d="M30 38h22M148 38h22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".2"></path>',
			),
		);
	}
}
