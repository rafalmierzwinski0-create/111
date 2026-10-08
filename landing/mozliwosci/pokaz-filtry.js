/*
 * Filtry „Show only” w pokazie na stronie sprzedażowej.
 *
 * We wtyczce filtruje serwer: każda wartość w menu jest odsyłaczem, a strona
 * wraca już przefiltrowana. Tu serwera nie ma i odsyłacze prowadzą do „#”,
 * więc kliknięcie niczego nie zaznaczało, a w pigułce wiecznie stało „any”.
 * Ten skrypt robi to samo na miejscu: zaznacza wartość, wpisuje ją
 * w pigułkę, chowa wiersze, które nie pasują, i liczy, ile zostało.
 *
 * Wartości w jednym filtrze łączy „albo” (Easy albo Hard), różne filtry
 * łączy „i” (Hard i Closed) — tak jak na serwerze. Szukajka wtyczki działa
 * dalej obok: ona chowa wiersze atrybutem `hidden`, a filtry własnym
 * atrybutem, więc żadne nie odkrywa tego, co schowało drugie.
 */
( function () {
	'use strict';

	var zloz = function ( tekst ) {
		return String( tekst ).replace( /\s+/g, ' ' ).trim().toLowerCase();
	};

	var arkusz = document.createElement( 'style' );
	arkusz.textContent = '.lstab tr[data-lst-poza] { display: none !important; }';
	( document.head || document.documentElement ).appendChild( arkusz );

	function uzbroj( root ) {
		var pasek = root.querySelector( '.lstabp-facets' );
		var tabela = root.querySelector( '.lstab-table' );

		if ( ! pasek || ! tabela || ! tabela.tBodies[ 0 ] || pasek.getAttribute( 'data-lst-pokaz' ) ) {
			return;
		}
		pasek.setAttribute( 'data-lst-pokaz', '1' );

		var wiersze = Array.prototype.filter.call( tabela.tBodies[ 0 ].rows, function ( w ) {
			return ! w.classList.contains( 'lstab-detail' );
		} );
		var licznik = root.querySelector( '.lstab-count' );
		var pusto = root.querySelector( '.lstab-no-results' );

		var filtry = Array.prototype.map.call( pasek.querySelectorAll( '.lstabp-facet' ), function ( el ) {
			var nazwa = el.querySelector( 'summary b' );

			return {
				el: el,
				kolumna: nazwa ? nazwa.textContent.replace( /:\s*$/, '' ).trim() : '',
				teraz: el.querySelector( '.lstabp-facet-now' ),
			};
		} );

		// „Wyczyść” jak we wtyczce: pojawia się, kiedy coś jest wybrane.
		var wyczysc = pasek.querySelector( '.lstabp-facets-clear' );
		if ( ! wyczysc ) {
			wyczysc = document.createElement( 'a' );
			wyczysc.className = 'lstabp-facets-clear';
			wyczysc.href = '#';
			wyczysc.textContent = 'Clear filters — show all ' + wiersze.length;
			pasek.appendChild( wyczysc );
		}
		wyczysc.hidden = true;

		function wartosc( wiersz, kolumna ) {
			var komorki = wiersz.querySelectorAll( 'td[data-label]' );

			for ( var i = 0; i < komorki.length; i++ ) {
				if ( komorki[ i ].getAttribute( 'data-label' ) === kolumna ) {
					var w = komorki[ i ].querySelector( '.lstab-cell-value' );

					return zloz( ( w || komorki[ i ] ).textContent );
				}
			}

			return '';
		}

		function wybrane( filtr ) {
			return Array.prototype.map.call( filtr.el.querySelectorAll( '.lstabp-facet-value.is-picked .lstabp-facet-text' ), function ( t ) {
				return t.textContent.trim();
			} );
		}

		function policz() {
			var widac = wiersze.filter( function ( w ) {
				return ! w.hidden && ! w.hasAttribute( 'data-lst-poza' );
			} ).length;

			if ( licznik ) {
				var wzor = licznik.getAttribute( 'data-lstab-count-template' ) || '%1$s of %2$s rows';
				licznik.textContent = wzor.replace( '%1$s', String( widac ) ).replace( '%2$s', String( wiersze.length ) );
			}
			if ( pusto ) {
				pusto.hidden = widac !== 0;
			}
		}

		function przelicz() {
			var wybor = filtry.map( function ( f ) {
				var nazwy = wybrane( f );

				f.el.classList.toggle( 'is-on', nazwy.length > 0 );
				if ( f.teraz ) {
					f.teraz.textContent = nazwy.length ? nazwy.join( ', ' ) : 'any';
				}

				return nazwy.map( zloz );
			} );

			wiersze.forEach( function ( w ) {
				var pasuje = filtry.every( function ( f, i ) {
					return ! wybor[ i ].length || wybor[ i ].indexOf( wartosc( w, f.kolumna ) ) !== -1;
				} );
				var szuflada = w.nextElementSibling && w.nextElementSibling.classList.contains( 'lstab-detail' ) ? w.nextElementSibling : null;

				[ w, szuflada ].forEach( function ( el ) {
					if ( ! el ) {
						return;
					}
					if ( pasuje ) {
						el.removeAttribute( 'data-lst-poza' );
					} else {
						el.setAttribute( 'data-lst-poza', '' );
					}
				} );
			} );

			wyczysc.hidden = ! wybor.some( function ( n ) {
				return n.length;
			} );

			policz();
			root.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		}

		// Szukajka wtyczki przelicza licznik po swojemu, nie wiedząc
		// o filtrach; po niej licznik jest liczony jeszcze raz, z nimi.
		root.addEventListener( 'lstab:resize', policz );

		/*
		 * Na oknie i w fazie przechwytywania, bo skrypt wtyczki łapie
		 * kliknięcia w te odsyłacze wcześniej, na dokumencie, i ściąga
		 * stronę spod ich adresu, żeby podmienić tabelę. Tu adres to „#”,
		 * więc podmieniłby tabelę na nią samą i skasował wybór.
		 */
		window.addEventListener( 'click', function ( e ) {
			var cel = e.target && e.target.closest ? e.target : null;

			if ( ! cel || ! pasek.contains( cel ) ) {
				return;
			}

			var wartoscLink = cel.closest( '.lstabp-facet-value' );
			var czysc = cel.closest( '.lstabp-facets-clear' );

			if ( ! wartoscLink && ! czysc ) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();

			if ( czysc ) {
				Array.prototype.forEach.call( pasek.querySelectorAll( '.lstabp-facet-value.is-picked' ), function ( v ) {
					v.classList.remove( 'is-picked' );
				} );
			} else {
				wartoscLink.classList.toggle( 'is-picked' );
				// Menu zamyka się po wyborze, jak po przeładowaniu strony we wtyczce.
				var menu = wartoscLink.closest( '.lstabp-facet' );
				if ( menu ) {
					menu.open = false;
				}
			}

			przelicz();
		}, true );
	}

	// Kliknięcie poza menu je zamyka; otwarcie jednego zamyka drugie.
	document.addEventListener( 'click', function ( e ) {
		Array.prototype.forEach.call( document.querySelectorAll( '.lstabp-facets[data-lst-pokaz] .lstabp-facet[open]' ), function ( f ) {
			if ( ! f.contains( e.target ) ) {
				f.open = false;
			}
		} );
	} );

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '.lstab' ), uzbroj );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
