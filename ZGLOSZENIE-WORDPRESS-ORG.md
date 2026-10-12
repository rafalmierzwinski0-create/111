# Zgłoszenie wtyczki do WordPress.org — lista

Spisane 2026-10-08, uzupełnione 2026-10-12. Dotyczy darmowej wtyczki
`live-sheets-table/`. Pro nie idzie do katalogu WordPress.org i nie musi być
gotowe, żeby wysłać darmową.

**Strona nie musi być gotowa ani stać na domenie.** WordPress.org ocenia samą
wtyczkę: kod, bezpieczeństwo, licencję i opis. Wyjątek: odsyłacze z wtyczki
muszą prowadzić pod istniejące adresy.

## 1. Najważniejsze: blokady Pro w darmowej wtyczce

Zasada 5 katalogu („Trialware is not permitted”): w darmowej wtyczce nie może
być funkcji zablokowanych do czasu zapłaty i nie może być funkcji, które
wyłączają się po czasie albo po limicie. Płatne rzeczy mają siedzieć w osobnej
wtyczce (u nas Pro), która dokłada je przez hooki. Plugin Check tego nie
wykrywa, sprawdza to człowiek przy recenzji. Dziś darmowa ma takie blokady:

| Co jest dziś w darmowej | Dlaczego odpadnie | Propozycja |
|---|---|---|
| Limit 6 tabel, Pro go znosi | limit zdejmowany po zapłacie | usunąć limit (innej drogi nie ma) |
| 10 dni, po których ukryte kolumny i wiersze wracają na stronę, gdy Pro zniknie | funkcja wyłączana po czasie | usunąć |
| 7 z 10 stylów z kłódką „Pro”, ich kod jest w darmowej | funkcja w kodzie, zablokowana | przenieść 7 stylów do Pro |
| Odświeżanie co 1 i 5 minut ukryte do czasu Pro | jw. | przenieść te dwie opcje do Pro |
| Ukrywanie kolumn, kolumny „w szczegółach”, ukryte wiersze działają tylko z Pro | jw. | kolumny: za darmo (wybór przy kolumnie); klikanie w obrazek arkusza i ukrywanie wierszy: w całości do Pro |
| Reklamy „Buy Pro”, „Compare Free and Pro” z odsyłaczem `https://example.com/...` | odsyłacz donikąd | na razie usunąć; wrócą w aktualizacji, gdy będzie sklep |

Stan: **czeka na decyzję właściciela**, które funkcje zostają płatne.

## 2. Plugin Check (puszczone 2026-10-12)

Oficjalne narzędzie, które WordPress.org uruchamia przy wysyłce. Wersja 2.1.0
ze źródeł na GitHubie, na WordPressie 7.1. Błąd blokuje wysyłkę, ostrzeżenia
czyta recenzent.

- **Błąd (1):** `blocks/sheet-table/index.asset.php` nie ma na początku
  `defined( 'ABSPATH' ) || exit;`.
- **Ostrzeżenie:** nazwa wtyczki zawiera słowo „WordPress” (nagłówek
  `live-sheets-table.php` i tytuł `readme.txt`). Zmienić na „Live Sheets Table”.
- **Ostrzeżenie:** `seems_utf8()` jest przestarzałe od WordPressa 6.9
  (`includes/class-lstab-csv-parser.php`).
- **Ostrzeżenie:** Changelog w `readme.txt` ma ok. 77 000 znaków, katalog
  pokazuje 5 000. Zostawić ostatnie wydania.
- **Ostrzeżenia (3):** nazwy hooków innych wtyczek do czyszczenia pamięci
  podręcznej (`litespeed_purge_post` itd.). Celowe, oznaczyć `phpcs:ignore`.
- **Ostrzeżenia (7):** nazwa tabeli w zapytaniach SQL w
  `includes/class-lstab-storage.php`. Przejść na `%i` w `$wpdb->prepare()`.

## 3. Pozostałe poprawki

1. **`readme.txt` mówi o ustawieniu języka i polskim tłumaczeniu**, a obu już
   nie ma. Usunąć te dwa zdania.
2. **Sekcja „What Pro adds” i wzmianki o Pro w FAQ:** usunąć do czasu
   startu sprzedaży, razem z reklamami z punktu 1.
3. **`== External services ==`** — jest. Zrobione.
4. **„Tested up to: 7.1”** — zgodne z najnowszym WordPressem. Sprawdzić
   jeszcze raz w dniu wysyłki.
5. **Contributors** w `readme.txt` (dziś `livesheetstable`): wpisać nazwę
   użytkownika właściciela na wordpress.org.
6. **Grafiki do katalogu** są w `wporg-assets/`. Po zmianach sprawdzić, czy
   zrzuty nie pokazują kłódek „Pro” i czy pasują do `== Screenshots ==`.

## Po stronie właściciela

- Decyzja z punktu 1: co zostaje w Pro.
- Konto na wordpress.org i jego nazwa użytkownika.
- Włączone logowanie dwuetapowe (2FA), wymagane od autorów wtyczek.
- Później, przed sprzedażą: adres strony z ceną Pro.

## Kolejność

1. Poprawki z punktów 1–3 → Claude daje gotowy `live-sheets-table.zip`
   (`tools/build-zip.sh`) i jeszcze raz puszcza Plugin Check.
2. Właściciel wysyła zip: https://wordpress.org/plugins/developers/add/
3. Recenzja trwa od kilku dni do kilku tygodni. Odpowiadamy na maila
   z uwagami.
4. Po akceptacji: wgranie do SVN (`trunk/`, `tags/<wersja>/`, grafiki
   w `assets/`).

## Równolegle, zanim ruszy sprzedaż Pro

- Płatności (pośrednik, który rozlicza VAT).
- Klucze licencyjne i automatyczne aktualizacje w Pro. Dziś w Pro ich nie ma.
- Odsyłacze do sklepu w darmowej wtyczce: dodać w aktualizacji, skromnie
  i z możliwością zamknięcia (zasada 11).
- Formularz na stronie Kontakt: czeka na shortcode z wtyczki formularzy.
- Dane firmy w polityce prywatności i regulaminie (NAZWA-FIRMY, ADRES-FIRMY,
  NIP-FIRMY, EMAIL-KONTAKT, HOSTING-FIRMA, POCZTA-FIRMA, PLATNOSCI-FIRMA).
