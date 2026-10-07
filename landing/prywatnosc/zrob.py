# -*- coding: utf-8 -*-
"""
Podstrona „Privacy and terms” / „Prywatność i regulamin”, jeden moduł Kod.

Dwa dokumenty na jednej stronie: polityka prywatności (z informacją o
ciasteczkach) i regulamin (z licencją, płatnościami, zwrotami i reklamacjami).
Na górze krótkie podsumowanie, z boku spis treści, który na komputerze jedzie
razem z tekstem.

SKĄD TREŚĆ. To, co dokumenty mówią o wtyczce, jest sprawdzone w jej kodzie:
  - darmowa wersja łączy się wyłącznie z docs.google.com (class-lstab-fetcher,
    class-lstab-url), nie ustawia odwiedzającym ciasteczek i nic nie wysyła
    autorom;
  - Pro łączy się z kontem Google przez klienta OAuth z projektu Google Cloud
    WŁAŚCICIELA strony, wyłącznie z zakresem spreadsheets.readonly, a tokeny
    trzyma w jego bazie (class-lstabp-google-auth) — autorzy nie są
    pośrednikiem;
  - dane wtyczki znikają przy odinstalowaniu tylko po zaznaczeniu tego
    w ustawieniach (uninstall.php);
  - obie wersje są na GPL-2.0-or-later;
  - wymagania: WordPress 6.7+, PHP 7.4+ (readme.txt).
Strona: jedyne, co przekazuje dane poza serwer, to kroje z Google Fonts.

CZEGO NIE WIEM, A MUSI BYĆ WPISANE — słowa wielkimi literami, „zamień
wszystkie” w edytorze:
  NAZWA-FIRMY      pełna nazwa działalności (np. „Jan Kowalski Rizznet”)
  ADRES-FIRMY      adres z CEIDG / KRS
  NIP-FIRMY        NIP
  EMAIL-KONTAKT    adres do spraw danych i reklamacji
  HOSTING-FIRMA    firma hostingowa (np. „OVH sp. z o.o.”)
  POCZTA-FIRMA     dostawca poczty, przez którego idą maile z formularza
  PLATNOSCI-FIRMA  platforma sprzedaży Pro (np. „Paddle.com Market Ltd”)

DECYZJE, KTÓRE PODJĄŁEM ZA CIEBIE (łatwo zmienić niżej): zwrot pieniędzy
w ciągu 14 dni bez podawania przyczyny; plany odnawiają się co rok, z
przypomnieniem przed odnowieniem; po wygaśnięciu planu wtyczka działa dalej,
tylko bez aktualizacji i wsparcia.

To NIE jest porada prawna. Dokumenty są napisane starannie pod RODO, ustawę
o prawach konsumenta i ustawę o świadczeniu usług drogą elektroniczną, ale
przed startem sprzedaży warto, żeby przejrzał je prawnik.

Uruchomienie: python3 landing/prywatnosc/zrob.py
"""

import pathlib
import re

TU = pathlib.Path( __file__ ).resolve().parent

DATA_EN = '7 October 2026'
DATA_PL = '7 października 2026'

# Adres podstrony kontaktu i cennika na tej witrynie.
KONTAKT_EN, KONTAKT_PL = '/contact/', '/kontakt/'
CENNIK_EN, CENNIK_PL = '/#pricing', '/#cennik'


# ------------------------------------------------------------------ treść EN
#
# Dokument to lista sekcji: ( kotwica, tytuł, [ akapity ] ). Akapit zaczynający
# się od „- ” jest punktem listy.

EN = {
	'oko': 'Legal',
	'tytul': 'Privacy and terms',
	'wstep': 'Two documents in plain language: what happens to your data, and the rules for using and buying '
		'Live Sheets Table. If anything here is unclear, <a href="' + KONTAKT_EN + '">write to us</a>.',
	'data': 'Last updated: ' + DATA_EN,
	'skrot_tytul': 'In short',
	'skrot': (
		( 'tarcza', 'The plugin does not report to us', 'It talks to Google and nowhere else. No analytics, no tracking, no account with us.' ),
		( 'baza', 'Your data stays on your site', 'Sheets are stored in your WordPress database. Pro connects to Google through your own Google project.' ),
		( 'ciastko', 'No tracking cookies here', 'This website sets no analytics or advertising cookies, so there is no cookie banner.' ),
		( 'zwrot', '14 days to change your mind', 'Bought Pro and it is not for you? Ask within 14 days and you get your money back.' ),
	),
	'spis': 'On this page',
	'dokumenty': (
		( 'privacy', 'Privacy policy', (
			( 'kto', '1. Who is responsible for your data', (
				'The controller of personal data collected through this website and in connection with purchases of '
				'Live Sheets Table Pro is NAZWA-FIRMY, ADRES-FIRMY, Poland, tax ID (NIP) NIP-FIRMY ("we", "us").',
				'For anything about your data, write to EMAIL-KONTAKT or use the <a href="' + KONTAKT_EN + '">contact form</a>. '
				'We have not appointed a data protection officer, because the law does not require it for a business of our size.',
			) ),
			( 'wtyczka', '2. The plugin itself', (
				'Live Sheets Table runs on your WordPress site, not on ours. When you use it:',
				'- It downloads the sheets you configure from docs.google.com and stores a copy in your own WordPress database. '
				'It sends nothing to us and contacts no other service.',
				'- It sends no analytics, sets no cookies for your visitors and has no account system.',
				'- Live Sheets Table Pro can read private sheets. It connects to Google through an OAuth client from your own '
				'Google Cloud project, asks only for read-only access to spreadsheets, and keeps the access token in your database. '
				'We never see your sheets, your Google credentials or your token.',
				'- When you delete the plugin, its data stays in your database unless you choose "delete data on uninstall" in its settings.',
				'Because the plugin runs on your site, you decide what data your tables show and you are the controller of that data '
				'towards your visitors. We do not process it on your behalf.',
			) ),
			( 'strona', '3. This website', (
				'<strong>Server logs.</strong> Like every website, our server records technical data about each request: '
				'IP address, date and time, the page requested and the browser\'s user agent. We use it only to keep the site '
				'secure and working (legal basis: our legitimate interest, Art. 6(1)(f) GDPR). The logs are kept by our hosting '
				'provider for a limited time and then deleted.',
				'<strong>Fonts.</strong> The pages load typefaces from Google Fonts, a service of Google Ireland Ltd and Google LLC. '
				'To deliver them, your browser connects to Google\'s servers, which see your IP address. We use it so that text '
				'looks the same on every device (legitimate interest, Art. 6(1)(f) GDPR). Google may process this data in the United '
				'States; Google LLC is certified under the EU-U.S. Data Privacy Framework.',
				'<strong>Contact form.</strong> When you write to us, we receive your name, e-mail address and message. We use them '
				'only to answer you (Art. 6(1)(b) GDPR when you ask about a purchase, otherwise our legitimate interest in replying, '
				'Art. 6(1)(f)). We keep the conversation for as long as it takes to deal with your matter and then for up to three '
				'years, in case it comes back or a claim needs to be answered.',
				'<strong>Cookies.</strong> This website sets no analytics, advertising or tracking cookies. WordPress sets technical '
				'cookies only if you log in or leave a comment; they are needed for that to work and do not need your consent. '
				'If we ever add analytics, we will ask for your consent first and update this policy.',
			) ),
			( 'zakupy', '4. When you buy Pro', (
				'Pro is sold through PLATNOSCI-FIRMA, which acts as the reseller (merchant of record): it takes the payment, charges '
				'the right tax and issues the invoice. It processes your payment details under its own privacy policy; we never see '
				'your card number.',
				'We receive from it what we need to provide the plan: your name, e-mail address, country, company name and tax '
				'number if you give them, what you bought and when, and your licence key. Legal basis: performing the contract '
				'(Art. 6(1)(b) GDPR) and our duties under tax and accounting law (Art. 6(1)(c)).',
				'We keep purchase records for as long as the plan lasts and then for as long as Polish tax law requires: '
				'five years from the end of the year in which the tax for the purchase was due.',
			) ),
			( 'odbiorcy', '5. Who else sees the data', (
				'We do not sell personal data and do not share it for marketing. It reaches only the providers we need to run '
				'the service, each bound by a data processing agreement or acting as a separate controller:',
				'- HOSTING-FIRMA — hosting of this website and its server logs;',
				'- POCZTA-FIRMA — delivery and storage of e-mail, including messages from the contact form;',
				'- PLATNOSCI-FIRMA — sales, payments, tax and invoices for Pro;',
				'- Google Ireland Ltd / Google LLC — fonts on this website;',
				'- public authorities, when the law requires it.',
				'Where data leaves the European Economic Area, the transfer relies on an adequacy decision (such as the EU-U.S. '
				'Data Privacy Framework) or the European Commission\'s standard contractual clauses.',
			) ),
			( 'prawa', '6. Your rights', (
				'You have the right to access your data, to have it corrected or deleted, to restrict its processing, to receive '
				'it in a portable format, and to object to processing based on our legitimate interest. Where processing is based '
				'on your consent, you can withdraw it at any time without affecting what was done before.',
				'To use any of these rights, write to EMAIL-KONTAKT. We answer within one month.',
				'You can also complain to the supervisory authority: in Poland, the President of the Personal Data Protection '
				'Office (Prezes Urzędu Ochrony Danych Osobowych), ul. Stawki 2, 00-193 Warszawa, uodo.gov.pl, or the authority in '
				'the EU country where you live or work.',
				'Giving us your data is voluntary, but without it we cannot answer your message or sell you a plan. We do not '
				'make automated decisions about you and do not profile you.',
			) ),
			( 'zmiany-p', '7. Changes', (
				'When something in this policy changes, we update it here and change the date at the top. If the change matters '
				'for customers, we also tell them by e-mail.',
			) ),
		) ),
		( 'terms', 'Terms of service', (
			( 'definicje', '1. What these terms cover', (
				'These terms apply to the website rizznet.pl, to Live Sheets Table (the free plugin) and to paid plans of '
				'Live Sheets Table Pro. They are provided by NAZWA-FIRMY, ADRES-FIRMY, Poland, tax ID (NIP) NIP-FIRMY. '
				'Contact: EMAIL-KONTAKT or the <a href="' + KONTAKT_EN + '">contact form</a>.',
				'"Consumer" means a person buying outside their business. Under Polish law, a sole trader who buys for their business '
				'something that is not of a professional nature for them (judged by the business activity they have registered) has '
				'the same protection as a consumer.',
			) ),
			( 'uslugi', '2. What we provide', (
				'- <strong>The free plugin</strong>, distributed through WordPress.org at no charge.',
				'- <strong>Pro plans</strong> (currently Pro and Agency, described on the <a href="' + CENNIK_EN + '">pricing page</a>): '
				'access to the Pro plugin, its updates and support, for one year, on the number of sites the plan allows.',
				'- <strong>This website and its contact form</strong>, free of charge. You can stop using them at any time.',
				'Requirements: WordPress 6.7 or newer, PHP 7.4 or newer, an up-to-date web browser, and a Google Sheet shared as '
				'"Anyone with the link — Viewer". Reading private sheets in Pro also needs an OAuth client in your own Google Cloud project.',
			) ),
			( 'licencja', '3. Licence', (
				'Both the free plugin and Pro are free software, licensed under the GNU General Public License, version 2 or later. '
				'Nothing in these terms limits the rights that licence gives you.',
				'What a plan buys is a service: downloads of Pro, its updates, and support, for the plan\'s period and number of sites. '
				'When a plan ends, the plugin keeps working on your sites; you simply stop receiving updates and support until you renew.',
				'The GPL covers the code, not the name. "Live Sheets Table" and its logo may not be used in a way that suggests '
				'a product comes from us when it does not.',
			) ),
			( 'platnosci', '4. Prices and payment', (
				'Prices are shown on the <a href="' + CENNIK_EN + '">pricing page</a> before you buy, together with the currency and '
				'the tax that applies to you. The sale and payment are handled by PLATNOSCI-FIRMA as the reseller; its terms '
				'apply to the payment itself, and it issues the invoice.',
				'A plan lasts one year and renews automatically for another year at the price then in force. We e-mail you before '
				'each renewal, and you can turn renewal off at any time; the plan then runs until the end of the period you paid for.',
			) ),
			( 'zwroty', '5. Refunds and withdrawal', (
				'If Pro is not right for you, ask for a refund within 14 days of buying or renewing and you get the full amount back. '
				'You do not have to give a reason. The refund goes back the way you paid, and the licence is switched off.',
				'This covers, and goes further than, the consumer\'s statutory right to withdraw from a contract for digital content '
				'within 14 days: you keep the right to a refund even after downloading the plugin.',
			) ),
			( 'wsparcie', '6. Support', (
				'Pro and Agency customers get support by e-mail; we aim to answer within 24 hours on working days. Support covers '
				'installing, setting up and using the plugin and fixing faults in it. It does not cover building your site or '
				'changing your theme.',
				'Users of the free plugin can ask questions on the WordPress.org support forum. We help there as time allows.',
			) ),
			( 'reklamacje', '7. Complaints', (
				'If the plugin does not work as described, tell us at EMAIL-KONTAKT or through the contact form. Please say which plan '
				'and version you use, what happens and what you expected. We answer within 14 days.',
				'If you are a consumer and the plugin does not conform to the contract, you have the rights given by Polish consumer '
				'law: to have it brought into conformity, and if that fails, to a price reduction or to withdraw from the contract. '
				'We are liable for non-conformity for as long as the law provides: for the plugin as delivered, two years from '
				'delivery; for updates, throughout the plan.',
			) ),
			( 'obowiazki', '8. Your responsibilities', (
				'You are responsible for what your sheets contain and what your tables show, including any personal data in them, '
				'and for following Google\'s terms for Google Sheets.',
				'Do not use the website or the contact form to send unlawful content, malware or spam.',
			) ),
			( 'odpowiedzialnosc', '9. Liability', (
				'The free plugin is provided free of charge and, as the GPL says, without warranty. For paid plans, our liability to '
				'business customers is limited to the amount paid for the plan in the last twelve months and does not cover lost '
				'profits or lost data.',
				'We are not responsible for Google\'s services: if Google changes, limits or stops access to a sheet, the plugin keeps '
				'showing the last copy it stored, but cannot fetch a new one.',
				'Nothing in these terms limits liability that cannot be limited by law, including towards consumers or for damage '
				'caused intentionally.',
			) ),
			( 'zmiany-t', '10. Changes to these terms', (
				'We may change these terms for good reason, such as a change in the law or in what we offer. We publish the new '
				'version here and tell customers by e-mail at least 14 days before it applies. A change never affects a plan '
				'already paid for until its current period ends.',
			) ),
			( 'prawo', '11. Law and disputes', (
				'These terms are governed by Polish law. If you are a consumer, you keep the protection given by the law of the '
				'country where you live.',
				'If we cannot agree, a consumer in Poland can turn for free help to the local consumer ombudsman (rzecznik '
				'konsumentów) or the Trade Inspection (Inspekcja Handlowa). Disputes go to the competent common court.',
			) ),
			( 'znaki', '12. Trademarks', (
				'Google Sheets is a trademark of Google LLC. Live Sheets Table is not affiliated with, endorsed or sponsored by Google.',
			) ),
		) ),
	),
}


# ------------------------------------------------------------------ treść PL

PL = {
	'oko': 'Sprawy prawne',
	'tytul': 'Prywatność i regulamin',
	'wstep': 'Dwa dokumenty, napisane zwykłym językiem: co dzieje się z Twoimi danymi i jakie są zasady korzystania '
		'z Live Sheets Table i jego kupowania. Jeśli coś jest niejasne, <a href="' + KONTAKT_PL + '">napisz do nas</a>.',
	'data': 'Ostatnia zmiana: ' + DATA_PL,
	'skrot_tytul': 'W skrócie',
	'skrot': (
		( 'tarcza', 'Wtyczka nic nam nie raportuje', 'Rozmawia z Google i z nikim więcej. Bez analityki, bez śledzenia, bez konta u nas.' ),
		( 'baza', 'Dane zostają na Twojej stronie', 'Arkusze leżą w Twojej bazie WordPressa. Pro łączy się z Google przez Twój własny projekt Google.' ),
		( 'ciastko', 'Bez ciasteczek śledzących', 'Ta strona nie ustawia ciasteczek analitycznych ani reklamowych, więc nie ma też baneru.' ),
		( 'zwrot', '14 dni na zmianę zdania', 'Kupiłeś Pro i nie pasuje? Napisz w ciągu 14 dni, a oddamy pieniądze.' ),
	),
	'spis': 'Na tej stronie',
	'dokumenty': (
		( 'prywatnosc', 'Polityka prywatności', (
			( 'kto', '1. Kto odpowiada za Twoje dane', (
				'Administratorem danych osobowych zbieranych przez tę stronę i w związku z zakupem Live Sheets Table Pro jest '
				'NAZWA-FIRMY, ADRES-FIRMY, NIP NIP-FIRMY („my”).',
				'We wszystkich sprawach dotyczących danych pisz na EMAIL-KONTAKT albo przez <a href="' + KONTAKT_PL + '">formularz kontaktowy</a>. '
				'Nie wyznaczyliśmy inspektora ochrony danych, bo przy działalności tej wielkości prawo tego nie wymaga.',
			) ),
			( 'wtyczka', '2. Sama wtyczka', (
				'Live Sheets Table działa na Twojej stronie WordPress, a nie na naszej. Kiedy z niej korzystasz:',
				'- Pobiera arkusze, które wskażesz, z docs.google.com i przechowuje ich kopię w Twojej bazie WordPressa. '
				'Niczego nie wysyła do nas i nie łączy się z żadną inną usługą.',
				'- Nie wysyła żadnej analityki, nie ustawia ciasteczek Twoim odwiedzającym i nie ma systemu kont.',
				'- Live Sheets Table Pro potrafi czytać arkusze prywatne. Łączy się z Google przez klienta OAuth z Twojego własnego '
				'projektu Google Cloud, prosi wyłącznie o dostęp do odczytu arkuszy, a token dostępu trzyma w Twojej bazie. '
				'Nigdy nie widzimy Twoich arkuszy, danych logowania do Google ani tokenu.',
				'- Po usunięciu wtyczki jej dane zostają w bazie, chyba że w ustawieniach zaznaczysz „usuń dane przy odinstalowaniu”.',
				'Ponieważ wtyczka działa na Twojej stronie, to Ty decydujesz, jakie dane pokazują Twoje tabele, i wobec swoich '
				'odwiedzających jesteś ich administratorem. Nie przetwarzamy ich w Twoim imieniu.',
			) ),
			( 'strona', '3. Ta strona', (
				'<strong>Logi serwera.</strong> Jak każda strona, nasz serwer zapisuje dane techniczne każdego wejścia: adres IP, '
				'datę i godzinę, otwieraną stronę i typ przeglądarki. Używamy ich wyłącznie po to, żeby strona była bezpieczna '
				'i działała (podstawa: nasz prawnie uzasadniony interes, art. 6 ust. 1 lit. f RODO). Logi przechowuje nasz dostawca '
				'hostingu przez ograniczony czas, a potem je usuwa.',
				'<strong>Kroje pisma.</strong> Strona ładuje kroje z Google Fonts, usługi Google Ireland Ltd i Google LLC. Żeby je '
				'dostarczyć, Twoja przeglądarka łączy się z serwerami Google, które widzą Twój adres IP. Robimy to, żeby tekst '
				'wyglądał tak samo na każdym urządzeniu (prawnie uzasadniony interes, art. 6 ust. 1 lit. f RODO). Google może '
				'przetwarzać te dane w Stanach Zjednoczonych; Google LLC uczestniczy w programie EU-U.S. Data Privacy Framework.',
				'<strong>Formularz kontaktowy.</strong> Kiedy do nas piszesz, dostajemy Twoje imię, adres e-mail i wiadomość. '
				'Używamy ich tylko do odpowiedzi (art. 6 ust. 1 lit. b RODO, gdy pytasz o zakup, a w innych sprawach nasz prawnie '
				'uzasadniony interes w odpowiedzi, art. 6 ust. 1 lit. f). Korespondencję przechowujemy tak długo, jak trwa '
				'załatwianie sprawy, a potem do trzech lat, na wypadek gdyby sprawa wróciła albo trzeba było odpowiedzieć na roszczenie.',
				'<strong>Ciasteczka.</strong> Ta strona nie ustawia ciasteczek analitycznych, reklamowych ani śledzących. WordPress '
				'ustawia ciasteczka techniczne tylko wtedy, gdy się zalogujesz albo dodasz komentarz; są do tego niezbędne i nie '
				'wymagają zgody. Jeśli kiedyś dodamy analitykę, najpierw poprosimy o zgodę i zmienimy tę politykę.',
			) ),
			( 'zakupy', '4. Kiedy kupujesz Pro', (
				'Pro sprzedaje PLATNOSCI-FIRMA jako pośrednik sprzedaży (merchant of record): przyjmuje płatność, nalicza właściwy '
				'podatek i wystawia fakturę. Dane płatnicze przetwarza na podstawie własnej polityki prywatności; numeru karty '
				'nigdy nie widzimy.',
				'Od pośrednika dostajemy to, czego potrzebujemy, żeby świadczyć plan: imię i nazwisko, adres e-mail, kraj, nazwę '
				'firmy i numer podatkowy, jeśli je podasz, co i kiedy kupiłeś oraz klucz licencji. Podstawa: wykonanie umowy '
				'(art. 6 ust. 1 lit. b RODO) i nasze obowiązki z przepisów podatkowych i rachunkowych (art. 6 ust. 1 lit. c).',
				'Dane o zakupie przechowujemy, dopóki trwa plan, a potem tak długo, jak wymagają przepisy podatkowe: pięć lat '
				'od końca roku, w którym upłynął termin płatności podatku za ten zakup.',
			) ),
			( 'odbiorcy', '5. Kto jeszcze widzi dane', (
				'Nie sprzedajemy danych osobowych i nie udostępniamy ich w celach marketingowych. Trafiają tylko do dostawców, '
				'bez których usługa nie działa; każdy z nich jest związany umową powierzenia albo działa jako osobny administrator:',
				'- HOSTING-FIRMA — hosting tej strony i jej logi serwera;',
				'- POCZTA-FIRMA — dostarczanie i przechowywanie poczty, w tym wiadomości z formularza;',
				'- PLATNOSCI-FIRMA — sprzedaż, płatności, podatki i faktury za Pro;',
				'- Google Ireland Ltd / Google LLC — kroje pisma na tej stronie;',
				'- organy publiczne, jeśli wymaga tego prawo.',
				'Jeśli dane trafiają poza Europejski Obszar Gospodarczy, opiera się to na decyzji stwierdzającej odpowiedni '
				'stopień ochrony (np. EU-U.S. Data Privacy Framework) albo na standardowych klauzulach umownych Komisji Europejskiej.',
			) ),
			( 'prawa', '6. Twoje prawa', (
				'Masz prawo dostępu do swoich danych, ich sprostowania lub usunięcia, ograniczenia przetwarzania, otrzymania ich '
				'w przenośnym formacie oraz sprzeciwu wobec przetwarzania opartego na naszym prawnie uzasadnionym interesie. '
				'Jeśli przetwarzanie opiera się na zgodzie, możesz ją w każdej chwili wycofać, bez wpływu na to, co działo się wcześniej.',
				'Żeby skorzystać z któregoś z tych praw, napisz na EMAIL-KONTAKT. Odpowiadamy w ciągu miesiąca.',
				'Możesz też złożyć skargę do Prezesa Urzędu Ochrony Danych Osobowych, ul. Stawki 2, 00-193 Warszawa, uodo.gov.pl.',
				'Podanie danych jest dobrowolne, ale bez nich nie odpowiemy na wiadomość ani nie sprzedamy planu. Nie podejmujemy '
				'wobec Ciebie zautomatyzowanych decyzji i nie profilujemy Cię.',
			) ),
			( 'zmiany-p', '7. Zmiany', (
				'Kiedy coś w tej polityce się zmienia, aktualizujemy ją tutaj i zmieniamy datę na górze. Jeśli zmiana ma znaczenie '
				'dla klientów, informujemy ich też e-mailem.',
			) ),
		) ),
		( 'regulamin', 'Regulamin', (
			( 'definicje', '1. Czego dotyczy regulamin', (
				'Regulamin dotyczy strony rizznet.pl, darmowej wtyczki Live Sheets Table oraz płatnych planów Live Sheets Table Pro. '
				'Usługodawcą jest NAZWA-FIRMY, ADRES-FIRMY, NIP NIP-FIRMY. Kontakt: EMAIL-KONTAKT albo '
				'<a href="' + KONTAKT_PL + '">formularz kontaktowy</a>.',
				'„Konsument” to osoba kupująca poza swoją działalnością. Osoba prowadząca jednoosobową działalność, która zawiera umowę '
				'związaną z działalnością, ale niemającą dla niej charakteru zawodowego (ocenia się to po rodzaju działalności wpisanej '
				'do CEIDG), ma taką samą ochronę jak konsument.',
			) ),
			( 'uslugi', '2. Co świadczymy', (
				'- <strong>Darmową wtyczkę</strong>, udostępnianą bezpłatnie przez WordPress.org.',
				'- <strong>Plany Pro</strong> (obecnie Pro i Agency, opisane w <a href="' + CENNIK_PL + '">cenniku</a>): dostęp do wtyczki Pro, '
				'jej aktualizacji i wsparcia przez rok, na tylu stronach, na ile pozwala plan.',
				'- <strong>Tę stronę i formularz kontaktowy</strong>, bezpłatnie. Możesz przestać z nich korzystać w każdej chwili.',
				'Wymagania: WordPress 6.7 lub nowszy, PHP 7.4 lub nowsze, aktualna przeglądarka i arkusz Google udostępniony jako '
				'„Każda osoba mająca link — Przeglądający”. Czytanie arkuszy prywatnych w Pro wymaga też klienta OAuth we własnym '
				'projekcie Google Cloud.',
			) ),
			( 'licencja', '3. Licencja', (
				'Zarówno darmowa wtyczka, jak i Pro są wolnym oprogramowaniem na licencji GNU General Public License w wersji 2 '
				'lub późniejszej. Nic w tym regulaminie nie ogranicza praw, które daje ta licencja.',
				'Plan to usługa: pobieranie Pro, jego aktualizacje i wsparcie, przez okres planu i na określonej liczbie stron. '
				'Po zakończeniu planu wtyczka dalej działa na Twoich stronach; przestajesz tylko dostawać aktualizacje i wsparcie, '
				'dopóki nie odnowisz planu.',
				'GPL obejmuje kod, nie nazwę. Nazwy „Live Sheets Table” i logo nie wolno używać tak, żeby sugerowały, że produkt '
				'pochodzi od nas, jeśli tak nie jest.',
			) ),
			( 'platnosci', '4. Ceny i płatności', (
				'Ceny są podane w <a href="' + CENNIK_PL + '">cenniku</a> przed zakupem, razem z walutą i podatkiem, który Cię dotyczy. '
				'Sprzedażą i płatnością zajmuje się PLATNOSCI-FIRMA jako pośrednik sprzedaży; do samej płatności stosuje się '
				'jego regulamin i to on wystawia fakturę.',
				'Plan trwa rok i odnawia się automatycznie na kolejny rok po cenie obowiązującej w chwili odnowienia. Przed każdym '
				'odnowieniem wysyłamy e-mail, a odnawianie możesz wyłączyć w każdej chwili; plan trwa wtedy do końca opłaconego okresu.',
			) ),
			( 'zwroty', '5. Zwroty i odstąpienie od umowy', (
				'Jeśli Pro Ci nie odpowiada, poproś o zwrot w ciągu 14 dni od zakupu albo odnowienia, a oddamy całą kwotę. Nie '
				'musisz podawać powodu. Pieniądze wracają tą samą drogą, którą zapłaciłeś, a licencja zostaje wyłączona.',
				'To obejmuje i rozszerza ustawowe prawo konsumenta do odstąpienia w ciągu 14 dni od umowy o dostarczenie treści '
				'cyfrowych: prawo do zwrotu zachowujesz także po pobraniu wtyczki.',
			) ),
			( 'wsparcie', '6. Wsparcie', (
				'Klienci Pro i Agency dostają wsparcie e-mailowe; staramy się odpowiadać w ciągu 24 godzin w dni robocze. Wsparcie '
				'obejmuje instalację, konfigurację i używanie wtyczki oraz naprawę jej błędów. Nie obejmuje budowy Twojej strony '
				'ani zmian w motywie.',
				'Użytkownicy darmowej wtyczki mogą zadawać pytania na forum wsparcia WordPress.org. Pomagamy tam w miarę możliwości.',
			) ),
			( 'reklamacje', '7. Reklamacje', (
				'Jeśli wtyczka nie działa tak, jak opisano, napisz na EMAIL-KONTAKT albo przez formularz kontaktowy. Podaj plan '
				'i wersję, z której korzystasz, co się dzieje i czego się spodziewałeś. Odpowiadamy w ciągu 14 dni.',
				'Jeśli jesteś konsumentem, a wtyczka nie jest zgodna z umową, przysługują Ci prawa z ustawy o prawach konsumenta: '
				'doprowadzenie do zgodności, a gdy to się nie uda — obniżenie ceny albo odstąpienie od umowy. Za brak zgodności '
				'odpowiadamy tak długo, jak przewiduje ustawa: za dostarczoną wtyczkę przez dwa lata od dostarczenia, za aktualizacje '
				'przez cały okres planu.',
			) ),
			( 'obowiazki', '8. Twoje obowiązki', (
				'Odpowiadasz za to, co zawierają Twoje arkusze i co pokazują Twoje tabele, w tym za dane osobowe, które się w nich '
				'znajdą, oraz za przestrzeganie warunków korzystania z Arkuszy Google.',
				'Nie używaj strony ani formularza do przesyłania treści bezprawnych, złośliwego oprogramowania ani spamu.',
			) ),
			( 'odpowiedzialnosc', '9. Odpowiedzialność', (
				'Darmowa wtyczka jest udostępniana bezpłatnie i, zgodnie z GPL, bez gwarancji. Przy płatnych planach nasza '
				'odpowiedzialność wobec przedsiębiorców jest ograniczona do kwoty zapłaconej za plan w ostatnich dwunastu miesiącach '
				'i nie obejmuje utraconych korzyści ani utraty danych.',
				'Nie odpowiadamy za usługi Google: jeśli Google zmieni, ograniczy albo zablokuje dostęp do arkusza, wtyczka dalej '
				'pokazuje ostatnią zapisaną kopię, ale nie może pobrać nowej.',
				'Nic w regulaminie nie ogranicza odpowiedzialności, której prawo ograniczyć nie pozwala, w tym wobec konsumentów '
				'i za szkody wyrządzone umyślnie.',
			) ),
			( 'zmiany-t', '10. Zmiany regulaminu', (
				'Regulamin możemy zmienić z ważnego powodu, np. zmiany prawa albo oferty. Nową wersję publikujemy tutaj i informujemy '
				'klientów e-mailem co najmniej 14 dni przed jej wejściem w życie. Zmiana nie dotyczy opłaconego planu do końca '
				'bieżącego okresu.',
			) ),
			( 'prawo', '11. Prawo i spory', (
				'Regulamin podlega prawu polskiemu. Jeśli jesteś konsumentem, zachowujesz ochronę, którą daje prawo kraju, w którym mieszkasz.',
				'Jeśli nie dojdziemy do porozumienia, konsument może bezpłatnie zwrócić się o pomoc do miejskiego lub powiatowego '
				'rzecznika konsumentów albo do Inspekcji Handlowej. Spory rozstrzyga właściwy sąd powszechny.',
			) ),
			( 'znaki', '12. Znaki towarowe', (
				'Google Sheets (Arkusze Google) jest znakiem towarowym Google LLC. Live Sheets Table nie jest powiązany z Google, '
				'ani przez Google wspierany czy sponsorowany.',
			) ),
		) ),
	),
}


def _ikona( srodek ):
	return ( '<svg class="lst-pp-rys" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.6" '
		'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + srodek + '</svg>' )


IKONY = {
	'tarcza': _ikona( '<path d="M14 3.5 5.5 7v6.5c0 5 3.6 9.3 8.5 11 4.9-1.7 8.5-6 8.5-11V7Z"></path><path class="lst-pp-dorys" pathLength="1" d="m10 14 3 3 5.5-6"></path>' ),
	'baza': _ikona( '<ellipse cx="14" cy="7" rx="8.5" ry="3"></ellipse><path d="M5.5 7v14c0 1.7 3.8 3 8.5 3s8.5-1.3 8.5-3V7"></path><path class="lst-pp-dorys" pathLength="1" d="M5.5 14c0 1.7 3.8 3 8.5 3s8.5-1.3 8.5-3"></path>' ),
	'ciastko': _ikona( '<path d="M23.5 15.5A9.5 9.5 0 1 1 12.5 4.6a3.5 3.5 0 0 0 4.6 4.6 3.5 3.5 0 0 0 6.4 6.3Z"></path><path class="lst-pp-dorys" pathLength="1" d="M5 23 23 5"></path>' ),
	'zwrot': _ikona( '<path d="M5 12a9 9 0 1 1 2.6 6.4"></path><path class="lst-pp-dorys" pathLength="1" d="M5 6.5V12h5.5"></path>' ),
}

CZCIONKI = ( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500'
	'&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@400&display=swap">' )

STYL = r'''
.lst-pp {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #8fa5a2;
	--lst-promien: 18px;
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--lst-serif: "Inria Serif", "Iowan Old Style", Georgia, serif;
	--lst-sans: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	--lst-pelna: 100vw;

	font-family: var( --lst-sans );
	color: var( --lst-tekst );
	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 1.75rem, 4.2vw, 3.5rem ) 0 clamp( 3rem, 6vw, 5rem );
}

.lst-pp.lst-pp { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-pp * { box-sizing: border-box; }
.lst-pp br { display: none; }

/* Reset stoi PRZED resztą reguł: ma wagę zero. */
.lst-pp :where( div, p, span, a, h1, h2, h3, ul, li, nav, strong ) {
	margin: 0;
	padding: 0;
	background: none;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	text-align: left;
	text-transform: none;
	letter-spacing: normal;
	font: inherit;
	color: inherit;
	list-style: none;
}

.lst-pp .lst-pp-rama { width: 90%; max-width: 1800px; margin-inline: auto; }

/* ---------- góra ---------- */

.lst-pp .lst-pp-oko { font-family: var( --lst-mono ); font-size: .875rem; letter-spacing: .06em; color: rgb( var( --lst-mieta ) ); }

.lst-pp .lst-pp-tytul {
	font-family: var( --lst-serif );
	font-weight: 400;
	font-size: clamp( 2.4rem, 5vw, 4.4rem );
	line-height: 1.05;
	letter-spacing: -.01em;
	margin-top: .4rem;
	text-wrap: balance;
}

.lst-pp .lst-pp-wstep { font-size: 1.125rem; line-height: 1.6; color: var( --lst-tekst-2 ); margin-top: 1.1rem; max-width: 46rem; }
.lst-pp .lst-pp-data { font-family: var( --lst-mono ); font-size: .875rem; color: var( --lst-tekst-3 ); margin-top: .9rem; }

.lst-pp a { color: rgb( var( --lst-mieta ) ) !important; text-decoration: underline !important; text-decoration-thickness: 1px !important; text-underline-offset: 3px; }
.lst-pp a:hover { text-decoration-thickness: 2px !important; }

/* ---------- w skrócie: cztery kafle ---------- */

.lst-pp .lst-pp-skrot-tytul { font-family: var( --lst-mono ); font-size: .875rem; letter-spacing: .1em; text-transform: uppercase; color: var( --lst-tekst-3 ); margin-top: clamp( 1.8rem, 3.5vw, 2.6rem ); }

.lst-pp .lst-pp-skrot {
	display: grid;
	grid-template-columns: repeat( 4, minmax( 0, 1fr ) );
	gap: clamp( .9rem, 1.4vw, 1.25rem );
	margin-top: .9rem;
}

.lst-pp .lst-pp-kafel {
	position: relative;
	padding: clamp( 1.2rem, 2vw, 1.5rem );
	border-radius: var( --lst-promien );
	border: 1px solid rgba( var( --lst-mieta ), .18 ) !important;
	background-color: var( --lst-panel ) !important;
	background-image:
		linear-gradient( 180deg, rgba( 255, 255, 255, .04 ), rgba( 255, 255, 255, 0 ) 42% ),
		radial-gradient( 16rem 10rem at 14% -8%, rgba( var( --lst-mieta ), .14 ), transparent ),
		linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .05 ), 0 20px 44px -26px rgba( 0, 0, 0, .95 );
	animation: lst-pp-obieg 11s linear infinite;
}

.lst-pp .lst-pp-kafel:nth-child( 2 ) { animation-delay: -2.7s; }
.lst-pp .lst-pp-kafel:nth-child( 3 ) { animation-delay: -5.4s; }
.lst-pp .lst-pp-kafel:nth-child( 4 ) { animation-delay: -8.1s; }

.lst-pp .lst-pp-ikona {
	display: grid;
	place-items: center;
	width: 2.75rem;
	height: 2.75rem;
	border-radius: 12px;
	border: 1px solid rgba( var( --lst-mieta ), .26 ) !important;
	background-color: rgba( var( --lst-mieta ), .08 ) !important;
	color: rgb( var( --lst-mieta ) );
}

.lst-pp .lst-pp-rys { width: 1.55rem; height: 1.55rem; overflow: visible; }
.lst-pp .lst-pp-dorys { stroke-dasharray: 1; stroke-dashoffset: 0; }
.lst-pp .lst-pp-kafel-tytul { font-size: 1.125rem; font-weight: 600; line-height: 1.35; margin-top: 1rem; }
.lst-pp .lst-pp-kafel-tekst { font-size: 1.125rem; line-height: 1.5; color: var( --lst-tekst-2 ); margin-top: .35rem; }

/* ---------- spis i tekst ---------- */

.lst-pp .lst-pp-uklad {
	display: grid;
	grid-template-columns: minmax( 13rem, 17rem ) minmax( 0, 1fr );
	gap: clamp( 2rem, 5vw, 5rem );
	margin-top: clamp( 2.4rem, 5vw, 4rem );
	align-items: start;
}

/* Spis jedzie razem z tekstem i stoi pod paskiem witryny (100 px, jak kotwice). */
.lst-pp .lst-pp-spis { position: sticky; top: 110px; }
.lst-pp .lst-pp-spis-tytul { font-family: var( --lst-mono ); font-size: .875rem; letter-spacing: .1em; text-transform: uppercase; color: var( --lst-tekst-3 ); }
.lst-pp .lst-pp-spis-dok { font-family: var( --lst-serif ); font-size: 1.25rem; margin-top: 1.1rem; }
.lst-pp .lst-pp-spis-dok a { color: var( --lst-tekst ) !important; text-decoration: none !important; }
.lst-pp .lst-pp-spis-lista { margin-top: .5rem; border-left: 1px solid rgba( var( --lst-mieta ), .2 ) !important; }
.lst-pp .lst-pp-spis-lista a {
	display: block;
	padding: .28rem 0 .28rem .9rem;
	margin-left: -1px;
	border-left: 2px solid transparent !important;
	font-size: .875rem;
	line-height: 1.4;
	color: var( --lst-tekst-2 ) !important;
	text-decoration: none !important;
	transition: color .2s ease, border-color .2s ease;
}
.lst-pp .lst-pp-spis-lista a:hover { color: var( --lst-tekst ) !important; border-left-color: rgb( var( --lst-mieta ) ) !important; }

.lst-pp .lst-pp-dokument + .lst-pp-dokument { margin-top: clamp( 3rem, 6vw, 5rem ); }

.lst-pp .lst-pp-dok-tytul {
	font-family: var( --lst-serif );
	font-weight: 400;
	font-size: clamp( 2rem, 3.4vw, 2.8rem );
	line-height: 1.1;
	padding-bottom: 1rem;
	border-bottom: 1px solid rgba( var( --lst-mieta ), .25 ) !important;
	scroll-margin-top: 110px;
}

.lst-pp .lst-pp-sekcja { max-width: 46rem; margin-top: 2rem; scroll-margin-top: 110px; }
.lst-pp .lst-pp-sek-tytul { font-size: 1.25rem; font-weight: 600; line-height: 1.35; color: var( --lst-tekst ); }
.lst-pp .lst-pp-akapit { font-size: 1.125rem; line-height: 1.7; color: var( --lst-tekst-2 ); margin-top: .75rem; }
.lst-pp .lst-pp-akapit strong { font-weight: 600; color: var( --lst-tekst ); }
.lst-pp .lst-pp-lista { margin-top: .75rem; display: grid; gap: .45rem; }
.lst-pp .lst-pp-lista li {
	position: relative;
	padding-left: 1.3rem;
	font-size: 1.125rem;
	line-height: 1.65;
	color: var( --lst-tekst-2 );
}
.lst-pp .lst-pp-lista li::before {
	content: "";
	position: absolute;
	left: .1rem;
	top: .7em;
	width: 6px;
	height: 6px;
	border-radius: 99px;
	background-color: rgb( var( --lst-mieta ) );
	opacity: .8;
}
.lst-pp .lst-pp-lista strong { font-weight: 600; color: var( --lst-tekst ); }

/* ---------- światło na krawędzi kafli ---------- */

@property --lst-obrot { syntax: "<angle>"; initial-value: 0deg; inherits: true; }

.lst-pp .lst-pp-kafel::before,
.lst-pp .lst-pp-kafel::after {
	content: "";
	position: absolute;
	inset: -1px;
	border-radius: calc( var( --lst-promien ) + 1px );
	padding: 1.5px;
	pointer-events: none;
	background-image: conic-gradient( from var( --lst-obrot ),
		rgba( var( --lst-mieta ), 0 ) 0turn, rgba( var( --lst-mieta ), .95 ) .06turn,
		rgba( var( --lst-mieta ), .25 ) .12turn, rgba( var( --lst-mieta ), 0 ) .2turn,
		rgba( var( --lst-mieta ), 0 ) 1turn ) !important;
	-webkit-mask-image: linear-gradient( #000 0 0 ), linear-gradient( #000 0 0 );
	-webkit-mask-clip: content-box, border-box;
	-webkit-mask-composite: xor;
	mask-image: linear-gradient( #000 0 0 ), linear-gradient( #000 0 0 );
	mask-clip: content-box, border-box;
	mask-composite: exclude;
}
.lst-pp .lst-pp-kafel::after { padding: 3.5px; filter: blur( 6px ); opacity: .75; }

@keyframes lst-pp-obieg { to { --lst-obrot: 1turn; } }
@keyframes lst-pp-dorysuj { from { stroke-dashoffset: 1; } }

@media ( hover: hover ) and ( pointer: fine ) {
	.lst-pp .lst-pp-kafel { transition: transform .25s cubic-bezier( .23, 1, .32, 1 ), border-color .25s ease; }
	.lst-pp .lst-pp-kafel:hover { transform: translateY( -2px ); border-color: rgba( var( --lst-mieta ), .4 ) !important; }
	.lst-pp .lst-pp-ikona { transition: background-color .25s ease, color .25s ease, transform .3s cubic-bezier( .23, 1, .32, 1 ); }
	.lst-pp .lst-pp-kafel:hover .lst-pp-ikona {
		background-color: rgb( var( --lst-mieta ) ) !important;
		color: #06100f;
		transform: translateY( -2px ) rotate( -4deg );
	}
	.lst-pp .lst-pp-kafel:hover .lst-pp-dorys { animation: lst-pp-dorysuj .5s cubic-bezier( .23, 1, .32, 1 ) both; }
}

/* ---------- utwardzenie na wrogie motywy ---------- */

.lst-pp p,
.lst-pp h1,
.lst-pp h2,
.lst-pp h3,
.lst-pp li {
	margin-inline: 0 !important;
	padding-top: 0 !important;
	padding-bottom: 0 !important;
	background: none !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.lst-pp .lst-pp-lista li { padding-left: 1.3rem !important; }
.lst-pp .lst-pp-oko,
.lst-pp .lst-pp-data,
.lst-pp .lst-pp-skrot-tytul,
.lst-pp .lst-pp-spis-tytul { font-family: var( --lst-mono ) !important; }
.lst-pp .lst-pp-skrot-tytul,
.lst-pp .lst-pp-spis-tytul { text-transform: uppercase !important; letter-spacing: .1em !important; }
.lst-pp .lst-pp-tytul,
.lst-pp .lst-pp-dok-tytul,
.lst-pp .lst-pp-spis-dok { font-family: var( --lst-serif ) !important; color: var( --lst-tekst ) !important; font-weight: 400 !important; }
.lst-pp .lst-pp-sek-tytul,
.lst-pp .lst-pp-kafel-tytul { font-family: var( --lst-sans ) !important; color: var( --lst-tekst ) !important; font-weight: 600 !important; }
.lst-pp .lst-pp-akapit,
.lst-pp .lst-pp-wstep,
.lst-pp .lst-pp-kafel-tekst,
.lst-pp .lst-pp-lista li { font-family: var( --lst-sans ) !important; color: var( --lst-tekst-2 ) !important; }
.lst-pp .lst-pp-dok-tytul { padding-bottom: 1rem !important; }

.et_pb_module:has( .lst-pp ),
.et_pb_column:has( .lst-pp ),
.et_pb_row:has( .lst-pp ) { border: 0 !important; outline: 0 !important; }

@media ( max-width: 1100px ) {
	.lst-pp .lst-pp-skrot { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }
	/* Wąsko spis nie ma gdzie stać obok tekstu: idzie nad tekst, bez przyklejania. */
	.lst-pp .lst-pp-uklad { grid-template-columns: minmax( 0, 1fr ); }
	.lst-pp .lst-pp-spis { position: static; }
	.lst-pp .lst-pp-spis-lista { display: none; }
	.lst-pp .lst-pp-spis-dok { display: inline-block; margin-right: 1.4rem; }
}

@media ( max-width: 640px ) {
	.lst-pp .lst-pp-skrot { grid-template-columns: minmax( 0, 1fr ); }
}

@media ( prefers-reduced-motion: reduce ) {
	.lst-pp [class*="lst-pp-"] { animation: none !important; transition: none !important; }
}

@media print {
	.lst-pp .lst-pp-kafel { animation: none !important; }
	.lst-pp .lst-pp-spis { display: none; }
	.lst-pp .lst-pp-uklad { grid-template-columns: minmax( 0, 1fr ); }
}
'''


def akapity( lista ):
	"""Akapity i listy: kolejne „- ” składają się w jedną listę."""
	html, punkty = [], []
	for a in lista:
		if a.startswith( '- ' ):
			punkty.append( '<li>' + a[ 2: ] + '</li>' )
			continue
		if punkty:
			html.append( '<ul class="lst-pp-lista">' + ''.join( punkty ) + '</ul>' )
			punkty = []
		html.append( '<p class="lst-pp-akapit">' + a + '</p>' )
	if punkty:
		html.append( '<ul class="lst-pp-lista">' + ''.join( punkty ) + '</ul>' )
	return ''.join( html )


def zbuduj( t ):
	skrot = ''.join( '<div class="lst-pp-kafel"><span class="lst-pp-ikona">' + IKONY[ i ] + '</span>'
		'<p class="lst-pp-kafel-tytul">' + kt + '</p><p class="lst-pp-kafel-tekst">' + ko + '</p></div>'
		for i, kt, ko in t[ 'skrot' ] )

	spis, tekst = [], []
	for kotwica, tytul, sekcje in t[ 'dokumenty' ]:
		spis.append( '<p class="lst-pp-spis-dok"><a href="#' + kotwica + '">' + tytul + '</a></p>'
			'<div class="lst-pp-spis-lista">' + ''.join(
				'<a href="#' + kotwica + '-' + k + '">' + st + '</a>' for k, st, _ in sekcje ) + '</div>' )
		tekst.append( '<div class="lst-pp-dokument"><h2 class="lst-pp-dok-tytul" id="' + kotwica + '">' + tytul + '</h2>' + ''.join(
			'<div class="lst-pp-sekcja" id="' + kotwica + '-' + k + '"><h3 class="lst-pp-sek-tytul">' + st + '</h3>' + akapity( a ) + '</div>'
			for k, st, a in sekcje ) + '</div>' )

	znacznik = ( '<div class="lst-pp"><div class="lst-pp-rama">'
		'<p class="lst-pp-oko">' + t[ 'oko' ] + '</p>'
		'<h1 class="lst-pp-tytul">' + t[ 'tytul' ] + '</h1>'
		'<p class="lst-pp-wstep">' + t[ 'wstep' ] + '</p>'
		'<p class="lst-pp-data">' + t[ 'data' ] + '</p>'
		'<p class="lst-pp-skrot-tytul">' + t[ 'skrot_tytul' ] + '</p>'
		'<div class="lst-pp-skrot">' + skrot + '</div>'
		'<div class="lst-pp-uklad"><nav class="lst-pp-spis" aria-label="' + t[ 'spis' ] + '">'
		'<p class="lst-pp-spis-tytul">' + t[ 'spis' ] + '</p>' + ''.join( spis ) + '</nav>'
		'<div class="lst-pp-tekst">' + ''.join( tekst ) + '</div></div>'
		'</div></div>' )

	return ( '<!-- ZANIM WKLEISZ: zamien NAZWA-FIRMY, ADRES-FIRMY, NIP-FIRMY, EMAIL-KONTAKT, HOSTING-FIRMA, POCZTA-FIRMA i PLATNOSCI-FIRMA. -->\n'
		+ CZCIONKI + '\n\n' + znacznik + '\n\n<style>' + STYL + '</style>\n' )


def sprawdz( html, plik ):
	znacznik = html[ html.index( '<div class="lst-pp">' ) : html.index( '<style>' ) ]
	if '\n' in znacznik.strip():
		raise SystemExit( plik + ': znacznik rozbity na kilka linijek — Divi wstawi <br />' )
	for co in ( '[', ']', '&lt;', '&gt;', '&#91;', '<section' ):
		if co in znacznik:
			raise SystemExit( plik + ': w treści jest „' + co + '”' )
	if 'script' in znacznik.lower():
		raise SystemExit( plik + ': w treści jest słowo „script”' )
	bez_uwag = re.sub( r'/\*.*?\*/', '', html[ html.index( '<style>' ) : html.index( '</style>' ) ], flags=re.S )
	if bez_uwag.count( '{' ) != bez_uwag.count( '}' ):
		raise SystemExit( plik + ': klamry w <style> się nie zgadzają' )
	for z in set( re.findall( r'<(\w+)[\s>]', znacznik ) ):
		if znacznik.count( '<' + z + ' ' ) + znacznik.count( '<' + z + '>' ) != znacznik.count( '</' + z + '>' ):
			raise SystemExit( plik + ': <' + z + '> otwarty i zamknięty różną liczbę razy' )
	# każda kotwica ze spisu ma swój cel
	for cel in re.findall( r'href="#([\w-]+)"', znacznik ):
		if 'id="' + cel + '"' not in znacznik:
			raise SystemExit( plik + ': spis prowadzi do #' + cel + ', którego nie ma' )


for nazwa, t in ( ( 'PRYWATNOSC-en.html', EN ), ( 'PRYWATNOSC-pl.html', PL ) ):
	html = zbuduj( t )
	sprawdz( html, nazwa )
	( TU / nazwa ).write_text( html )
	print( nazwa, len( html ) // 1024, 'kB' )
