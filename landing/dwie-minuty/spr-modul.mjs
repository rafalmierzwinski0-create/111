import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';

const en = fs.readFileSync('DWIE-MINUTY-en.html', 'utf8');
const pl = fs.readFileSync('DWIE-MINUTY-pl.html', 'utf8');

// Divi wstawia <br /> w każdym złamanym wierszu poza <style>
const divi = (s) => {
  const cz = s.split(/(<style>[\s\S]*?<\/style>)/);
  return cz.map((c, i) => (i % 2 ? c : c.split('\n').join('<br />\n'))).join('');
};

const strona = (t, wrogi = false) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Dwie minuty</title>
<style>html,body{margin:0;background:#232a29;color:#eaf3f1;font-family:system-ui}
.et_pb_section{padding:40px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}</style>
${wrogi ? '<style>div,span,p{border:2px solid #f0a!important}p{margin:40px!important;background:#ff0!important;font-family:"Comic Sans MS"!important;text-transform:uppercase!important}</style>' : ''}
</head><body>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik">A paragraph.</p></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module">
${t}
</div></div></div></div></body></html>`;

fs.writeFileSync('/tmp/m-zwykla.html', strona(en));
fs.writeFileSync('/tmp/m-br.html', strona(divi(en)));
fs.writeFileSync('/tmp/m-wrogi.html', strona(en, true));
fs.writeFileSync('/tmp/m-pl.html', strona(pl));

const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
let pass = 0, fail = 0;
const ok = (n, w, d) => { if (w) { pass++; console.log('  ✓ ', n, ' — ', d); } else { fail++; console.log('  ✗ ', n, ' — ', d); } };

async function otworz(n, w = 1400, h = 1000) {
  const c = await b.newContext({ viewport: { width: w, height: h }, ignoreHTTPSErrors: true });
  const p = await c.newPage();
  const bledy = [];
  p.on('pageerror', e => bledy.push(e.message));
  p.on('console', m => { const t = m.text(); if (m.type() === 'error' && !/ERR_|Failed to load resource/.test(t)) bledy.push(t); });
  await p.goto(`file:///tmp/${n}.html`);
  await p.waitForTimeout(900);
  return { p, c, bledy };
}

const lin = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
const swiatlo = (rgb) => { const m = rgb.match(/\d+/g).map(Number); return 0.2126 * lin(m[0]) + 0.7152 * lin(m[1]) + 0.0722 * lin(m[2]); };
const kontrast = (a, b) => { const x = swiatlo(a), y = swiatlo(b); return +((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)).toFixed(2); };
const jasnosc = (rgb) => { const m = rgb.match(/\d+/g).map(Number); return Math.round(0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2]); };

console.log('\n1400 px, zwykła strona');
{
  const { p, c, bledy } = await otworz('m-zwykla');
  const r = await p.evaluate(() => {
    const t = (s) => document.querySelector(s);
    const wszystkie = (s) => [...document.querySelectorAll(s)];
    const rozm = (s) => Math.round(parseFloat(getComputedStyle(t(s)).fontSize));
    const swiecace = wszystkie('.lst-2m-kafel, .lst-2m-liczba');
    const szer = (s) => Math.round(t(s).getBoundingClientRect().width);
    return {
      kafli: wszystkie('.lst-2m-kafel').length,
      powodow: wszystkie('.lst-2m-powod').length,
      krokow: wszystkie('.lst-2m-krok').length,
      zetonow: wszystkie('.lst-2m-zeton').length,
      liczb: wszystkie('.lst-2m-liczba').length,
      przyciskow: wszystkie('.lst-2m-przycisk').length,
      adresy: wszystkie('.lst-2m-przycisk').map(e => e.getAttribute('href')).join(' '),
      szerRobi: szer('.lst-2m-kafel.jest-robi'), szerKomu: szer('.lst-2m-kafel.jest-komu'),
      szerJak: szer('.lst-2m-kafel.jest-jak'), szerUfac: szer('.lst-2m-kafel.jest-ufac'),
      szerDalej: szer('.lst-2m-kafel.jest-dalej'), szerRamy: szer('.lst-2m-rama'),
      rozmiary: ['.lst-2m-etykieta', '.lst-2m-kafel-tekst', '.lst-2m-krok-tytul', '.lst-2m-krok-tekst',
        '.lst-2m-powod-tytul', '.lst-2m-powod-tekst', '.lst-2m-zeton', '.lst-2m-pod', '.lst-2m-tekst'].map(rozm).join('/'),
      naglowkow: document.querySelectorAll('.lst-2m-nad, .lst-2m-tyt, h1, h2, h3').length,
      tloStrony: getComputedStyle(document.body).backgroundColor,
      tloEkranu: getComputedStyle(t('.lst-2m-strona')).backgroundColor,
      tloKafla: getComputedStyle(t('.lst-2m-kafel')).backgroundColor,
      lewa: Math.round(t('.lst-2m-rama').getBoundingClientRect().left),
      odn: Math.round(t('#odnosnik').getBoundingClientRect().left),
      wys: Math.round(t('.lst-2m').getBoundingClientRect().height),
      poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      przyklad: t('.lst-2m-kod').textContent.trim(),
      zmiana: t('.lst-2m-komorki .jest-zmiana').textContent.trim(),
      nowy: wszystkie('.lst-2m-wiersze .jest-nowy').map(e => e.textContent.trim()).join(' '),
      /*
       * Światło biegnące po krawędzi: koło koloru („conic-gradient")
       * przycięte maską do samej ramki, obracane w kółko — na każdym kaflu
       * i każdym bloku z liczbą. Sprawdzane po trzech rzeczach naraz, bo
       * każda z nich osobno potrafi zniknąć po cichu: że to jest koło koloru,
       * że jest w kolorze akcentu i że coś je obraca.
       */
      swiatlo: swiecace.filter(e => {
        const tlo = getComputedStyle(e, '::before').backgroundImage;
        return /conic-gradient/.test(tlo) && /95,\s*227,\s*207/.test(tlo);
      }).length,
      obieg: swiecace.filter(e => 'none' !== getComputedStyle(e).animationName).length,
      poswiata: swiecace.filter(e => /blur/.test(getComputedStyle(e, '::after').filter)).length,
      swiecacych: swiecace.length,
      zaokraglone: swiecace.filter(e => parseFloat(getComputedStyle(e).borderTopLeftRadius) >= 14).length,
      zcieniem: swiecace.filter(e => 'none' !== getComputedStyle(e).boxShadow).length,
      odstep: parseFloat(getComputedStyle(t('.lst-2m-kafle')).columnGap) || 0,
      przyrzadow: wszystkie('.lst-2m-przyrzad').length,
      ikon: wszystkie('.lst-2m-powod .lst-2m-ikona svg').length,
      kolory: {
        tekst: getComputedStyle(t('.lst-2m-kafel-tekst')).color,
        etykieta: getComputedStyle(t('.lst-2m-etykieta')).color,
        powod: getComputedStyle(t('.lst-2m-powod-tekst')).color,
        stopka: getComputedStyle(t('.lst-2m-stopka-strony')).color,
        kafel: getComputedStyle(t('.lst-2m-kafel')).backgroundColor,
        ekran: getComputedStyle(t('.lst-2m-strona')).backgroundColor,
      },
    };
  });
  ok('pięć kafli: co robi, komu, jak, dlaczego, co dalej', r.kafli === 5 && r.powodow === 4 && r.krokow === 3 && r.zetonow === 8,
    `${r.kafli} kafli, ${r.powodow} powody, ${r.krokow} kroki, ${r.zetonow} żetonów`);
  ok('pod nimi trzy liczby, każda ze swoim przyrządem', r.liczb === 3 && r.przyrzadow === 3, `${r.liczb} liczby, ${r.przyrzadow} przyrządy`);
  ok('siatka nierówna: 7 i 5, potem 5 i 7, pas na całą szerokość',
    r.szerRobi > r.szerKomu && r.szerUfac > r.szerJak && Math.abs(r.szerDalej - r.szerRamy) <= 1,
    `${r.szerRobi}/${r.szerKomu}, ${r.szerJak}/${r.szerUfac}, pas ${r.szerDalej} z ${r.szerRamy}`);
  ok('rozmiary tylko 14, 18 i 20', r.rozmiary.split('/').every(x => ['14', '18', '20'].includes(x)), r.rozmiary);
  ok('bez tytułu i etykiety sekcji', r.naglowkow === 0, `${r.naglowkow} nagłówków w module`);
  ok('po krawędzi każdego kafla i bloku biegnie światło', r.swiatlo === 8 && r.obieg === 8,
    `kół koloru ${r.swiatlo}, obracanych ${r.obieg} z ${r.swiecacych}`);
  ok('i ciągnie za sobą poświatę', r.poswiata === 8, `${r.poswiata} z ${r.swiecacych}`);
  ok('kafle zaokrąglone i z cieniem, z odstępem między sobą', r.zaokraglone === 8 && r.zcieniem === 8 && r.odstep >= 12,
    `zaokrąglonych ${r.zaokraglone}, z cieniem ${r.zcieniem}, odstęp ${Math.round(r.odstep)} px`);
  ok('każdy powód ma swoją ikonkę', r.ikon === 4, `${r.ikon} z 4`);
  const jEkran = jasnosc(r.tloEkranu), jStrona = jasnosc(r.tloStrony), jKafel = jasnosc(r.tloKafla);
  ok('podgląd strony wyraźnie ciemniejszy od kafla', jKafel - jEkran >= 15, `ekran ${jEkran} vs kafel ${jKafel}`);
  ok('zmieniona komórka i ten sam wiersz na stronie', r.zmiana.includes('→') && r.nowy.includes('319'), `${r.zmiana} → ${r.nowy}`);
  ok('przyciski prowadzą do cennika i porównania', r.przyciskow === 2 && r.adresy === '#pricing #compare', r.adresy);
  ok('równo z resztą strony, bez suwaka', Math.abs(r.lewa - r.odn) <= 1 && r.poziom === 0, `${r.lewa} vs ${r.odn}, suwak ${r.poziom}`);
  ok('shortcode w nawiasach, nie wykonany', r.przyklad === '[sheet_table id="X"]', r.przyklad);
  const kTekst = kontrast(r.kolory.tekst, r.kolory.kafel);
  const kEtykieta = kontrast(r.kolory.etykieta, r.kolory.kafel);
  const kPowod = kontrast(r.kolory.powod, r.kolory.kafel);
  const kStopka = kontrast(r.kolory.stopka, r.kolory.ekran);
  ok('tekst kafli czytelny (>= 4,5:1)', kTekst >= 4.5 && kPowod >= 4.5, `tekst ${kTekst}:1, powód ${kPowod}:1`);
  ok('drobny tekst czytelny (>= 4,5:1)', kEtykieta >= 4.5 && kStopka >= 4.5, `etykieta ${kEtykieta}:1, stopka podglądu ${kStopka}:1`);

  /*
   * Ikonki budzą się pod kursorem: kwadracik zapala się na mięto, a numer
   * kroku tak samo. Mierzone po kolorze tła po najechaniu.
   */
  await p.hover('.lst-2m-powod.jest-tarcza');
  await p.waitForTimeout(400);
  const ikona = await p.evaluate(() => getComputedStyle(document.querySelector('.lst-2m-powod.jest-tarcza .lst-2m-ikona')).backgroundColor);
  await p.hover('.lst-2m-krok:nth-child(2)');
  await p.waitForTimeout(400);
  const nr = await p.evaluate(() => getComputedStyle(document.querySelector('.lst-2m-krok:nth-child(2) .lst-2m-nr')).backgroundColor);
  /*
   * Każdy z trzech bloków z liczbą odpowiada na kursor, także tarcza
   * z kwadransem: wskazówka robi obieg, a tarcza jaśnieje.
   */
  await p.hover('.lst-2m-liczba.jest-zegar');
  await p.waitForTimeout(450);
  const zegar = await p.evaluate(() => ({
    obrot: getComputedStyle(document.querySelector('.lst-2m-wskazowka')).animationName,
    tarcza: getComputedStyle(document.querySelector('.lst-2m-tarcza')).opacity,
  }));
  ok('tarcza zegara budzi się pod kursorem', 'lst-2m-obrot' === zegar.obrot && parseFloat(zegar.tarcza) > .5,
    `obrót ${zegar.obrot}, tarcza ${zegar.tarcza}`);

  // Kreska nad liczbami w połowie: tyle samo od kafli co do bloków.
  const kreska = await p.evaluate(() => {
    const kafle = [...document.querySelectorAll('.lst-2m-kafel')];
    const dolKafli = Math.max(...kafle.map(e => e.getBoundingClientRect().bottom));
    const pas = document.querySelector('.lst-2m-liczby').getBoundingClientRect();
    const blok = document.querySelector('.lst-2m-liczba').getBoundingClientRect();
    return { nad: Math.round(pas.top - dolKafli), pod: Math.round(blok.top - pas.top - 2) };
  });
  ok('kreska nad liczbami w równym odstępie', Math.abs(kreska.nad - kreska.pod) <= 2, `nad ${kreska.nad} px, pod ${kreska.pod} px`);

  ok('ikonka i numer kroku zapalają się pod kursorem', /95,\s*227,\s*207(,\s*1)?\)/.test(ikona) && /95,\s*227,\s*207(,\s*1)?\)/.test(nr), `ikonka ${ikona}, numer ${nr}`);
  ok('bez błędów w konsoli', bledy.length === 0, bledy.length ? bledy.join(' | ') : '0');
  console.log('     wysokość sekcji:', r.wys, 'px');
  await c.close();
}

/*
 * Przykład shortcode'u po zapisie w Divi: encje zamienione na znaki, puste
 * znaczniki wyrzucone. Pusty <span> między nawiasem a nazwą znikał właśnie
 * tak i na żywej stronie shortcode się wykonał.
 */
console.log('\nprzykład po zapisie w Divi');
{
  let poDivi = en.replace(/&#91;|&#x5B;/gi, '[').replace(/&#93;|&#x5D;/gi, ']')
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>');
  for (let przed = ''; przed !== poDivi;) {
    przed = poDivi;
    poDivi = poDivi.replace(/<(span|i|b|em|strong)\b[^>]*><\/\1>/g, '');
  }
  const zbitki = ['[sheet_table', '[live_sheets_table'].filter(z => poDivi.includes(z));
  ok('shortcode nie da się wykonać nawet po zapisie w Divi', zbitki.length === 0, `zbitek ${zbitki.length}`);
  /*
   * Po zapisie w Divi znaczników nie może przybyć. „&lt;table&gt;” w zdaniu
   * stało się na żywej stronie prawdziwą tabelą i rozsypało całą sekcję.
   */
  const znaczniki = (h) => (h.split('<style>')[0].match(/<[a-z]+/g) || []).length;
  const odkodowane = en.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
  ok('po zapisie w Divi nie przybywa znaczników', znaczniki(odkodowane) === znaczniki(en), `${znaczniki(en)} → ${znaczniki(odkodowane)}`);
}

console.log('\n1400 px, po wstawieniu <br /> przez Divi');
{
  const { p, c } = await otworz('m-br');
  const r = await p.evaluate(() => ({
    kafli: document.querySelectorAll('.lst-2m-kafel').length,
    brWidoczne: [...document.querySelectorAll('.lst-2m br')].some(x => getComputedStyle(x).display !== 'none'),
    wys: Math.round(document.querySelector('.lst-2m').getBoundingClientRect().height),
  }));
  ok('układ przeżywa <br />', r.kafli === 5 && !r.brWidoczne, `${r.kafli} kafli, br widoczne: ${r.brWidoczne}`);
  console.log('     wysokość sekcji:', r.wys, 'px');
  await c.close();
}

console.log('\n1400 px, wrogi motyw');
{
  const { p, c } = await otworz('m-wrogi');
  const r = await p.evaluate(() => {
    const t = (s) => document.querySelector(s);
    const st = (s) => getComputedStyle(t(s));
    return {
      krojOpisu: st('.lst-2m-kafel-tekst').fontFamily.split(',')[0].replace(/"/g, ''),
      wersaliki: st('.lst-2m-kafel-tekst').textTransform,
      tloOpisu: st('.lst-2m-kafel-tekst').backgroundColor,
      marginesOpisu: st('.lst-2m-kafel-tekst').marginLeft,
      krojKroku: st('.lst-2m-krok-tytul').fontFamily.split(',')[0].replace(/"/g, ''),
      kolorKroku: st('.lst-2m-krok-tytul').color,
      krojTytulu: st('.lst-2m-kafel-tytul').fontFamily.split(',')[0].replace(/"/g, ''),
    };
  });
  ok('motyw nie przejmuje kroju ani wersalików', r.krojOpisu === 'IBM Plex Sans' && r.wersaliki === 'none', `${r.krojOpisu} / ${r.wersaliki}`);
  ok('motyw nie maluje tła ani marginesów', r.tloOpisu === 'rgba(0, 0, 0, 0)' && r.marginesOpisu === '0px', `${r.tloOpisu}, margines ${r.marginesOpisu}`);
  ok('tytuł kroku trzyma krój i kolor', r.krojKroku === 'IBM Plex Sans' && r.kolorKroku === 'rgb(234, 243, 241)', `${r.krojKroku} / ${r.kolorKroku}`);
  ok('zdanie-tytuł kafla zostaje szeryfowe', r.krojTytulu === 'Inria Serif', r.krojTytulu);
  await c.close();
}

console.log('\n390 px, telefon');
{
  const { p, c } = await otworz('m-zwykla', 390, 900);
  const r = await p.evaluate(() => {
    const rama = Math.round(document.querySelector('.lst-2m-rama').getBoundingClientRect().width);
    return {
      rama,
      szerokosci: [...document.querySelectorAll('.lst-2m-kafel')].map(e => Math.round(e.getBoundingClientRect().width)),
      poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      // na telefonie arkusz nad stroną, a nie obok
      arkuszNad: document.querySelector('.lst-2m-arkusz').getBoundingClientRect().bottom <= document.querySelector('.lst-2m-strona').getBoundingClientRect().top,
    };
  });
  ok('kafle jeden pod drugim, na pełną szerokość', r.szerokosci.every(x => Math.abs(x - r.rama) <= 1), `${r.szerokosci.join(', ')} z ${r.rama}`);
  ok('arkusz nad stroną, bez suwaka', r.arkuszNad && r.poziom === 0, `nad: ${r.arkuszNad}, suwak ${r.poziom}`);
  await c.close();
}

console.log('\n1800 px, szeroki ekran');
{
  const { p, c } = await otworz('m-zwykla', 1900, 1000);
  const r = await p.evaluate(() => ({
    tekst: Math.max(...[...document.querySelectorAll('.lst-2m-kafel-tekst')].map(e => Math.round(e.getBoundingClientRect().width))),
    poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  }));
  ok('wiersz tekstu nie rozciąga się w nieskończoność', r.tekst <= 720, `najszerszy ${r.tekst} px`);
  ok('bez suwaka poziomego', r.poziom === 0, String(r.poziom));
  await c.close();
}

console.log('\nwersja polska');
{
  const { p, c } = await otworz('m-pl');
  const r = await p.evaluate(() => ({
    tytul: document.querySelector('.lst-2m-kafel-tytul').textContent.trim(),
    krok: document.querySelector('.lst-2m-krok-tytul').textContent.trim(),
    adresy: [...document.querySelectorAll('.lst-2m-przycisk')].map(e => e.getAttribute('href')).join(' '),
    etykiety: [...document.querySelectorAll('.lst-2m-etykieta')].map(x => x.textContent.trim()).join(' | '),
  }));
  ok('polskie teksty na miejscu', r.tytul.startsWith('Zmieniasz komórkę') && r.krok === 'Udostępnij arkusz', `${r.tytul} / ${r.krok}`);
  ok('przyciski prowadzą do polskich kotwic', r.adresy === '#cennik #porownanie', r.adresy);
  ok('etykiety bez sklejonych słów', !/[a-ząćęłńóśźż][A-ZĄĆĘŁŃÓŚŹŻ]/.test(r.etykiety), r.etykiety);
  await c.close();
}

console.log('\njasne tło — pomiar, nie test');
{
  fs.writeFileSync('/tmp/m-biala.html', strona(en).replace('background:#232a29', 'background:#ffffff'));
  const { p, c } = await otworz('m-biala');
  const r = await p.evaluate(() => ({
    krok: getComputedStyle(document.querySelector('.lst-2m-krok-tytul')).color,
    opis: getComputedStyle(document.querySelector('.lst-2m-kafel-tekst')).color,
    tlo: getComputedStyle(document.body).backgroundColor,
  }));
  console.log('     tytuł kroku na białym:', kontrast(r.krok, r.tlo) + ':1  (na ciemnej stronie 13,9:1)');
  console.log('     opis na białym:       ', kontrast(r.opis, r.tlo) + ':1');
  console.log('     → moduł jest zbudowany pod ciemną stronę i na białym tle nie działa');
  await p.locator('.lst-2m').screenshot({ path: 'modul-biale-tlo.png' });
  await c.close();
}

// zrzuty
for (const [n, w, h, plik] of [['m-zwykla', 1400, 1000, 'modul-1400.png'], ['m-zwykla', 390, 1400, 'modul-390.png'], ['m-pl', 1400, 1000, 'modul-pl.png']]) {
  const c = await b.newContext({ viewport: { width: w, height: h } });
  const p = await c.newPage();
  await p.goto(`file:///tmp/${n}.html`);
  await p.waitForTimeout(900);
  await p.locator('.lst-2m').screenshot({ path: plik });
  await c.close();
}

console.log(`\n${pass} PASS, ${fail} FAIL`);
await b.close();
process.exit(fail ? 1 : 0);
