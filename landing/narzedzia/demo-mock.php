<?php
/**
 * Plugin Name: Live Sheets Table – dane do zrzutów (tylko lokalnie)
 * Description: Podaje wtyczce czysty, pokazowy arkusz zamiast fixture'a testowego, w którym siedzą próbki XSS. Wisi na tym samym filtrze co mock testowy, ale odpowiada PO nim — pre_http_request to łańcuch, więc wygrywa ten, kto mówi ostatni. Nigdy tego nie wysyłamy dalej.
 *
 * @package LiveSheetsTable\Pokaz
 */

defined( 'ABSPATH' ) || exit;

define( 'LSTAB_POKAZ_STAN', WP_CONTENT_DIR . '/lstab-pokaz-stan.json' );

/**
 * Bieżące ustawienie pokazu.
 *
 * @return array<string,string>
 */
function lstab_pokaz_stan() {
	$domyslne = array(
		'jezyk' => 'en',
		'tryb'  => 'ok',
		'karta' => 'cennik',
	);

	if ( ! file_exists( LSTAB_POKAZ_STAN ) ) {
		return $domyslne;
	}

	$odczyt = json_decode( (string) file_get_contents( LSTAB_POKAZ_STAN ), true );

	return is_array( $odczyt ) ? array_merge( $domyslne, $odczyt ) : $domyslne;
}

/**
 * Arkusz pokazowy jako CSV.
 *
 * @param string $jezyk en albo pl.
 * @param string $karta cennik albo dostawcy.
 * @return string
 */
function lstab_pokaz_csv( $jezyk, $karta ) {
	if ( 'dostawcy' === $karta ) {
		if ( 'pl' === $jezyk ) {
			return "\xEF\xBB\xBFDostawca,Miasto,Termin,Kontakt\n"
				. "Velo Hurt,Poznań,2 dni,zamowienia@velohurt.pl\n"
				. "Rowerownia,Kraków,4 dni,biuro@rowerownia.pl\n"
				. "BikeParts,Wrocław,1 dzień,sklep@bikeparts.pl\n";
		}

		return "\xEF\xBB\xBFSupplier,City,Lead time,Contact\n"
			. "Velo Wholesale,Manchester,2 days,orders@velowholesale.com\n"
			. "Cycle Depot,Bristol,4 days,hello@cycledepot.com\n"
			. "BikeParts,Leeds,1 day,shop@bikeparts.com\n";
	}

	if ( 'pl' === $jezyk ) {
		return "\xEF\xBB\xBFProdukt,Cena,Dostępność,Opis,Zaktualizowano\n"
			. "\"Rower górski Trek Marlin 7\",\"4 199,99\",W magazynie,\"Rama aluminiowa, widelec 120 mm\",2026-09-22\n"
			. "Kask Lazer Compact,\"349,00\",W magazynie,\"Rozmiary S, M, L\",2026-09-21\n"
			. "Lampka Lezyne 800,\"289,00\",Brak,\"USB-C, 800 lumenów\",2026-09-20\n"
			. "Bidon termiczny 750 ml,\"29,00\",W magazynie,\"Trzyma chłód 12 godzin\",2026-09-23\n"
			. "\"Rękawiczki Pro Gel\",\"159,00\",Na zamówienie,\"Rozmiary S-XL\",2026-09-19\n"
			. "Zamek szyfrowy Abus Granit,\"1 215,50\",W magazynie,,2026-09-23\n"
			. "Bagażnik tylny Topeak,\"87,00\",W magazynie,\"Pasuje do kół 26-29 cali\",2026-09-18\n"
			. "Licznik Garmin Edge 540,\"1 799,00\",Brak,\"GPS, 26 godzin pracy\",2026-09-17\n";
	}

	return "\xEF\xBB\xBFProduct,Price,Availability,Notes,Updated\n"
		. "\"Trek Marlin 7 mountain bike\",\"4,199.99\",In stock,\"Aluminium frame, 120 mm fork\",2026-09-22\n"
		. "Lazer Compact helmet,\"349.00\",In stock,\"Sizes S, M, L\",2026-09-21\n"
		. "Lezyne 800 front light,\"289.00\",Out of stock,\"USB-C, 800 lumens\",2026-09-20\n"
		. "Insulated bottle 750 ml,\"29.00\",In stock,\"Keeps cold for 12 hours\",2026-09-23\n"
		. "Pro Gel gloves,\"159.00\",On order,\"Sizes S-XL\",2026-09-19\n"
		. "Abus Granit combination lock,\"1,215.50\",In stock,,2026-09-23\n"
		. "Topeak rear rack,\"87.00\",In stock,\"Fits 26-29 inch wheels\",2026-09-18\n"
		. "Garmin Edge 540 computer,\"1,799.00\",Out of stock,\"GPS, 26 hours of battery\",2026-09-17\n";
}

/**
 * Motyw ścieśnia treść do ~670 px i szeroka tabela się w tym nie mieści.
 * Na potrzeby zrzutów poszerzamy kolumnę treści — tylko po ?szeroko=1.
 */
add_action(
	'wp_head',
	function () {
		if ( ! isset( $_GET['szeroko'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		echo '<style>.wp-site-blocks, .entry-content, .wp-block-post-content, main, .is-layout-constrained > * { max-width: 1240px !important; }'
			. 'body { --wp--style--global--content-size: 1240px; --wp--style--global--wide-size: 1240px; }'
			. 'header.wp-block-template-part, footer.wp-block-template-part, .wp-block-post-title, .entry-header { display: none !important; }'
			. '.wp-site-blocks { padding-top: 24px !important; }</style>';
	}
);

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'docs.google.com' ) ) {
			return $preempt;
		}

		$stan = lstab_pokaz_stan();

		// Awaria po stronie Google — wtyczka ma pokazać kopię i powiedzieć, co się stało.
		if ( 'awaria' === $stan['tryb'] ) {
			return new WP_Error( 'http_request_failed', 'Mocked outage.' );
		}

		// Druga karta arkusza, żeby przełącznik kart miał co pokazać.
		$karta = $stan['karta'];

		if ( false !== strpos( $url, 'gid=1' ) ) {
			$karta = 'dostawcy';
		}

		// Widok HTML — z niego wtyczka czyta nazwy kart.
		if ( false !== strpos( $url, '/htmlview' ) || false !== strpos( $url, 'format=html' ) ) {
			$nazwy = 'pl' === $stan['jezyk']
				? array( 'Cennik', 'Dostawcy' )
				: array( 'Price list', 'Suppliers' );

			$html = '<html><body><ul id="sheet-menu">'
				. '<li id="sheet-button-0"><a href="#gid=0">' . $nazwy[0] . '</a></li>'
				. '<li id="sheet-button-1"><a href="#gid=1">' . $nazwy[1] . '</a></li>'
				. '</ul></body></html>';

			return array(
				'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array( 'content-type' => 'text/html; charset=UTF-8' ) ),
				'body'     => $html,
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		return array(
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array( 'content-type' => 'text/csv; charset=UTF-8' ) ),
			'body'     => lstab_pokaz_csv( $stan['jezyk'], $karta ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	99,
	3
);
