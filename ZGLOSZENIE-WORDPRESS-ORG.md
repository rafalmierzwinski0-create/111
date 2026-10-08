# Zgłoszenie wtyczki do WordPress.org — lista na później

Spisane 2026-10-08, żeby wrócić do tego w jednym miejscu. Dotyczy darmowej
wtyczki `live-sheets-table/`. Pro nie idzie do katalogu WordPress.org.

**Strona nie musi być gotowa ani stać na domenie.** WordPress.org ocenia samą
wtyczkę: kod, bezpieczeństwo, licencję i opis. Wyjątek: odsyłacze z wtyczki
muszą prowadzić pod istniejące adresy.

## Do poprawienia we wtyczce przed wysłaniem (robi Claude)

1. **Odsyłacz do Pro.** `includes/class-lstab-limits.php` zwraca dziś
   `https://example.com/live-sheets-table/pro/`. Trzeba wpisać prawdziwy adres
   strony z ceną Pro na rizznet.pl. Potrzebny adres od właściciela.
2. **Nazwa wtyczki.** W nagłówku `live-sheets-table.php` i w tytule
   `readme.txt` jest „Live Sheets Table – Google Sheets to WordPress”.
   Z nazwy powstaje adres wtyczki, a słowo „wordpress” w adresie jest
   zabronione. Zmienić na „Live Sheets Table”, wtedy adres to
   `live-sheets-table`, zgodny z Text Domain.
3. **Sekcja `== External services ==` w `readme.txt`.** Wymagana, bo wtyczka
   pobiera arkusze z `docs.google.com`. Musi mówić, co i kiedy jest wysyłane,
   oraz linkować do warunków (https://policies.google.com/terms) i polityki
   prywatności Google (https://policies.google.com/privacy). Dziś jest tylko
   akapit „= Privacy =”.
4. **„Tested up to”.** Ustawić na aktualne wydanie WordPressa, nie wyższe.
5. **Plugin Check (PCP).** Puścić oficjalne narzędzie na wtyczce w testowym
   WordPressie i poprawić, co zgłosi.
6. **Contributors** w `readme.txt` (dziś `livesheetstable`). Wpisać nazwę
   użytkownika właściciela na wordpress.org.
7. **Grafiki do katalogu.** Ikona, banery i zrzuty są w `wporg-assets/`.
   Sprawdzić, czy pasują do obecnego wyglądu i do listy „== Screenshots ==”.

## Po stronie właściciela

- Konto na wordpress.org i jego nazwa użytkownika.
- Włączone logowanie dwuetapowe (2FA), wymagane od autorów wtyczek.
- Adres strony z ceną Pro.

## Kolejność

1. Poprawki z listy wyżej → Claude daje gotowy `live-sheets-table.zip`
   (`tools/build-zip.sh`).
2. Właściciel wysyła zip: https://wordpress.org/plugins/developers/add/
3. Recenzja trwa od kilku dni do kilku tygodni. Odpowiadamy na maila
   z uwagami.
4. Po akceptacji: wgranie do SVN (`trunk/`, `tags/<wersja>/`, grafiki
   w `assets/`).

## Równolegle, zanim ruszy sprzedaż Pro

- Płatności (pośrednik, który rozlicza VAT).
- Klucze licencyjne i automatyczne aktualizacje w Pro. Dziś w Pro ich nie ma.
- Formularz na stronie Kontakt: czeka na shortcode z wtyczki formularzy.
- Dane firmy w polityce prywatności i regulaminie (NAZWA-FIRMY, ADRES-FIRMY,
  NIP-FIRMY, EMAIL-KONTAKT, HOSTING-FIRMA, POCZTA-FIRMA, PLATNOSCI-FIRMA).
