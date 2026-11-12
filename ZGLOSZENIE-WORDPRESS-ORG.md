# Zgłoszenie wtyczki do WordPress.org — lista

Spisane 2026-10-08, uzupełnione 2026-10-13. Dotyczy darmowej wtyczki
`tabliko/`. Pro nie idzie do katalogu WordPress.org i nie musi być
gotowe, żeby wysłać darmową.

**Strona nie musi być gotowa ani stać na domenie.** WordPress.org ocenia samą
wtyczkę: kod, bezpieczeństwo, licencję i opis. Wyjątek: odsyłacze z wtyczki
muszą prowadzić pod istniejące adresy.

## 1. Blokady Pro w darmowej wtyczce — ZROBIONE (3.43.0 / Pro 1.33.0)

Zasada 5 katalogu („Trialware is not permitted”): w darmowej wtyczce nie może
być funkcji zablokowanych do czasu zapłaty i nie może być funkcji, które
wyłączają się po czasie albo po limicie. Płatne rzeczy siedzą w osobnej
wtyczce (Pro), która dokłada je przez hooki.

| Było w darmowej | Jest teraz |
|---|---|
| Limit 6 tabel, Pro go znosi | Brak limitu. Osobny commit, łatwy do cofnięcia, ale bez niego wtyczka nie przejdzie recenzji. |
| Ukryte kolumny i wiersze wracały na stronę 10 dni po wyłączeniu Pro | Ukrywanie kolumn, wierszy i kolumny „w szczegółach” jest w darmowej, razem z wyborem przez klikanie w obrazek arkusza. Nic się samo nie wyłącza. |
| 7 z 10 stylów z kłódką | Darmowa ma 4 style: Clean, Striped, Bordered, Midnight. Pozostałe 6 i ich wygląd (CSS) są w Pro. |
| Odświeżanie co 1 i 5 minut schowane | Darmowa: od 15 minut. Opcje co 1 i co 5 minut dokłada Pro. |
| „Buy Pro”, „Compare Free and Pro”, odsyłacz do example.com, wzmianki o Pro w opisie | Usunięte. Wrócą w aktualizacji, gdy będzie sklep (skromnie i z możliwością zamknięcia, zasada 11). |

## 2. Plugin Check — ZROBIONE

Oficjalne narzędzie, które WordPress.org uruchamia przy wysyłce (wersja 2.1.0
ze źródeł na GitHubie, na WordPressie 7.1, także testy eksperymentalne).
Wynik po poprawkach: **0 błędów, 0 ostrzeżeń.**

Poprawione: zabezpieczenie w `blocks/sheet-table/index.asset.php`, nazwa
wtyczki bez słowa „WordPress” (teraz „Tabliko”), przestarzałe
`seems_utf8()`, zapytania SQL z `%i`, nazwy hooków innych wtyczek oznaczone,
historia zmian w `readme.txt` skrócona poniżej 5 000 znaków (pełna historia
jest w `CHANGELOG.md` w repozytorium).

## 3. Pozostałe

1. **Usuwanie wtyczki** kończyło się błędem krytycznym (plik odinstalowania
   sięgał do klasy, której WordPress wtedy nie ładuje). Naprawione, z testem,
   który usuwa wtyczkę tak jak WordPress.
2. **`readme.txt`**: bez sekcji o Pro, bez wzmianek o ustawieniu języka
   i polskim tłumaczeniu, z darmowym ukrywaniem i czterema stylami. Zrobione.
3. **`== External services ==`** — jest. Zrobione.
4. **„Tested up to: 7.1”** — zgodne z najnowszym WordPressem. Sprawdzić
   jeszcze raz w dniu wysyłki.
5. **Zrzuty ekranu** w `wporg-assets/` podmienione na aktualne, bez Pro.
6. **Contributors** w `readme.txt`: `blackkiwi534`, nazwa właściciela na
   wordpress.org. Zrobione.

## Po stronie właściciela

- Konto na wordpress.org (`blackkiwi534`). Zrobione.
- Włączone logowanie dwuetapowe (2FA), wymagane od autorów wtyczek.
- Później, przed sprzedażą Pro: adres strony z ceną Pro.

## Kolejność

1. Właściciel podaje nazwę użytkownika → Claude wpisuje ją do `readme.txt`
   i daje gotowy `tabliko.zip` (`tools/build-zip.sh`).
2. Właściciel wysyła zip: https://wordpress.org/plugins/developers/add/
3. Recenzja trwa od kilku dni do kilku tygodni. Odpowiadamy na maila
   z uwagami.
4. Po akceptacji: wgranie do SVN (`trunk/`, `tags/<wersja>/`, grafiki
   w `assets/`).

## Równolegle, zanim ruszy sprzedaż Pro

- Płatności (pośrednik, który rozlicza VAT).
- Klucze licencyjne i automatyczne aktualizacje w Pro. Dziś w Pro ich nie ma.
- Odsyłacz „Manage or cancel subscription” w Pro prowadzi dziś na
  example.com — podmienić na adres konta w sklepie.
- Odsyłacze do sklepu w darmowej wtyczce: dodać w aktualizacji, skromnie
  i z możliwością zamknięcia (zasada 11).
- Formularz na stronie Kontakt: czeka na shortcode z wtyczki formularzy.
- Dane firmy w polityce prywatności i regulaminie (NAZWA-FIRMY, ADRES-FIRMY,
  NIP-FIRMY, EMAIL-KONTAKT, HOSTING-FIRMA, POCZTA-FIRMA, PLATNOSCI-FIRMA).

## Odpowiedź na pierwszą recenzję (5.11.2026)

Wgrać `build/tabliko.zip` na stronie „Add your plugin” (konto blackkiwi534), potem odpisać w tym samym wątku:

> Hi, thank you for the review. I've uploaded an updated version.
>
> Please change the slug to **tabliko**. The plugin is now called "Tabliko – Tables for Google Sheets", and the text domain is already tabliko.
>
> One note: the public admin-ajax action `lstab_keep_current` (wp_ajax_nopriv) has no nonce on purpose. Visitors' browsers call it from pages that are often cached, where a nonce would expire. It changes no settings or content: it only lets the plugin re-read a table from Google when the stored copy is older than the interval the site owner chose. Table IDs are cast to integers, at most 20 are accepted per request, and each table has a cooldown.
>
> Thanks!

## Odpowiedź na drugą recenzję (12.11.2026)

Wgrać nowy `build/tabliko.zip`, potem odpisać w tym samym wątku:

> Hi, thank you for the second review. I've uploaded an updated version.
>
> The /tabliko/v1/sources endpoint now requires the capability that manages tables (the one chosen in the plugin's settings) instead of edit_posts, and the Elementor widget's table list follows the same rule. The arrays read from $_POST (settings, columns, hidden rows, appearance) are now sanitized with map_deep() and sanitize_text_field() as soon as they are read, and then validated key by key.
>
> Thanks!
