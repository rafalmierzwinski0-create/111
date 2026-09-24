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
    const rozm = (s) => Math.round(parseFloat(getComputedStyle(t(s)).fontSize));
    const pary = [...document.querySelectorAll('.lst-2m-para')];
    return {
      par: pary.length,
      okien: document.querySelectorAll('.lst-2m-okno').length,
      liczb: document.querySelectorAll('.lst-2m-liczba').length,
      // strona kroku: czy okno jest z lewej czy z prawej
      strony: pary.map(x => {
        const tr = x.querySelector('.lst-2m-tresc').getBoundingClientRect();
        const ok = x.querySelector('.lst-2m-okno').getBoundingClientRect();
        return ok.left > tr.left ? 'P' : 'L';
      }).join(''),
      rozmiary: ['.lst-2m-adres', '.lst-2m-krok', '.lst-2m-opis',
        '.lst-2m-pod', '.lst-2m-tekst', '.lst-2m-nazwa-okna', '.lst-2m-ekran'].map(rozm).join('/'),
      naglowkow: document.querySelectorAll('.lst-2m-nad, .lst-2m-tyt, h1, h2, h3').length,
      tloOkna: getComputedStyle(t('.lst-2m-okno')).backgroundColor,
      tloBelki: getComputedStyle(t('.lst-2m-belka')).backgroundColor,
      tloStrony: getComputedStyle(document.body).backgroundColor,
      tloLiczby: getComputedStyle(t('.lst-2m-liczba')).backgroundColor,
      lewa: Math.round(t('.lst-2m-rama').getBoundingClientRect().left),
      odn: Math.round(t('#odnosnik').getBoundingClientRect().left),
      wys: Math.round(t('.lst-2m').getBoundingClientRect().height),
      poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      nawiasy: t('.lst-2m-para:nth-child(3) .lst-2m-ekran').textContent.trim(),
      wynikow: document.querySelectorAll('.lst-2m-wynik').length,
      kreska: getComputedStyle(document.querySelector('.lst-2m-para:nth-child(2)')).borderTopColor,
      kolory: {
        opis: getComputedStyle(t('.lst-2m-opis')).color,
        adres: getComputedStyle(t('.lst-2m-adres')).color,
        nazwaOkna: getComputedStyle(t('.lst-2m-nazwa-okna')).color,
        wynik: getComputedStyle(t('.lst-2m-wynik')).color,
        strona: getComputedStyle(document.body).backgroundColor,
        belka: getComputedStyle(t('.lst-2m-belka')).backgroundColor,
        ekranTlo: getComputedStyle(t('.lst-2m-okno')).backgroundColor,
        plyta: getComputedStyle(t('.lst-2m-tresc')).backgroundColor,
      },
    };
  });
  ok('trzy kroki, trzy okna, trzy liczby', r.par === 3 && r.okien === 3 && r.liczb === 3, `${r.par}/${r.okien}/${r.liczb}`);
  ok('kroki naprzemiennie', r.strony === 'PLP', r.strony);
  ok('rozmiary tylko 14, 18 i 20', r.rozmiary.split('/').every(x => ['14', '18', '20'].includes(x)), r.rozmiary);
  ok('bez tytułu i etykiety sekcji', r.naglowkow === 0, `${r.naglowkow} nagłówków w module`);

  const jEkran = jasnosc(r.tloOkna), jStrona = jasnosc(r.tloStrony), jLiczba = jasnosc(r.tloLiczby);
  ok('podgląd wyraźnie ciemniejszy od strony', jStrona - jEkran >= 15, `ekran ${jEkran} vs strona ${jStrona} (różnica ${jStrona - jEkran})`);
  ok('podgląd wyraźnie ciemniejszy od kafli liczb', jLiczba - jEkran >= 15, `ekran ${jEkran} vs kafel ${jLiczba} (różnica ${jLiczba - jEkran})`);
  ok('belka okna jaśniejsza niż jego ekran', jasnosc(r.tloBelki) > jEkran, `belka ${jasnosc(r.tloBelki)} vs ekran ${jEkran}`);
  ok('równo z resztą strony, bez suwaka', Math.abs(r.lewa - r.odn) <= 1 && r.poziom === 0, `${r.lewa} vs ${r.odn}, suwak ${r.poziom}`);
  ok('shortcode w nawiasach, nie wykonany', r.nawiasy.includes('[sheet_table id="1"]'), r.nawiasy);
  ok('każde okienko ma drugą linijkę', r.wynikow === 3, `${r.wynikow} z 3`);
  const kOpis = kontrast(r.kolory.opis, r.kolory.strona);
  const kAdres = kontrast(r.kolory.adres, r.kolory.strona);
  const kNazwa = kontrast(r.kolory.nazwaOkna, r.kolory.belka);
  const kWynik = kontrast(r.kolory.wynik, r.kolory.ekranTlo);
  ok('drobny tekst czytelny na tle strony (>= 4,5:1)', kAdres >= 4.5, `adres kroku ${kAdres}:1`);
  ok('opis czytelny na tle strony (>= 4,5:1)', kOpis >= 4.5, `opis ${kOpis}:1`);
  ok('napisy w okienku czytelne (>= 4,5:1)', kNazwa >= 4.5 && kWynik >= 4.5, `nazwa okna ${kNazwa}:1, druga linijka ${kWynik}:1`);
  // kreska jest półprzezroczysta — mieszamy ją z tłem, żeby zmierzyć to, co widać
  const mieszaj = (przod, tyl) => {
    const a = przod.match(/[\d.]+/g).map(Number), b = tyl.match(/\d+/g).map(Number);
    const al = a.length > 3 ? a[3] : 1;
    return `rgb(${Math.round(al * a[0] + (1 - al) * b[0])}, ${Math.round(al * a[1] + (1 - al) * b[1])}, ${Math.round(al * a[2] + (1 - al) * b[2])})`;
  };
  const kKreska = kontrast(mieszaj(r.kreska, r.kolory.strona), r.kolory.strona);
  // najgorszy przypadek: tekst dokładnie na jasnym środku poświaty tła
  const POSWIATA = 'rgb(61, 90, 86)';
  const podPlyta = mieszaj(r.kolory.plyta, POSWIATA);
  const kOpisBlask = kontrast(r.kolory.opis, podPlyta);
  const kAdresBlask = kontrast(r.kolory.adres, podPlyta);
  ok('tekst ma własną płytę', r.kolory.plyta !== 'rgba(0, 0, 0, 0)', r.kolory.plyta);
  ok('czytelny nawet na środku poświaty (>= 4,5:1)', kOpisBlask >= 4.5 && kAdresBlask >= 4.5,
     `opis ${kOpisBlask}:1, adres ${kAdresBlask}:1 (bez płyty było 3.4 i 2.89)`);
  ok('kreska między krokami widoczna', kKreska >= 1.9, `kreska ${kKreska}:1 do tła`);
  ok('bez błędów w konsoli', bledy.length === 0, bledy.length ? bledy.join(' | ') : '0');
  console.log('     wysokość sekcji:', r.wys, 'px');
  await c.close();
}

console.log('\n1400 px, po wstawieniu <br /> przez Divi');
{
  const { p, c } = await otworz('m-br');
  const r = await p.evaluate(() => ({
    par: document.querySelectorAll('.lst-2m-para').length,
    brWidoczne: [...document.querySelectorAll('.lst-2m br')].some(x => getComputedStyle(x).display !== 'none'),
    wys: Math.round(document.querySelector('.lst-2m').getBoundingClientRect().height),
  }));
  ok('układ przeżywa <br />', r.par === 3 && !r.brWidoczne, `${r.par} kroki, br widoczne: ${r.brWidoczne}`);
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
      krojOpisu: st('.lst-2m-opis').fontFamily.split(',')[0].replace(/"/g, ''),
      wersaliki: st('.lst-2m-opis').textTransform,
      tloOpisu: st('.lst-2m-opis').backgroundColor,
      marginesOpisu: st('.lst-2m-opis').marginLeft,
      krojKroku: st('.lst-2m-krok').fontFamily.split(',')[0].replace(/"/g, ''),
      kolorKroku: st('.lst-2m-krok').color,
    };
  });
  ok('motyw nie przejmuje kroju ani wersalików', r.krojOpisu === 'IBM Plex Sans' && r.wersaliki === 'none', `${r.krojOpisu} / ${r.wersaliki}`);
  ok('motyw nie maluje tła ani marginesów', r.tloOpisu === 'rgba(0, 0, 0, 0)' && r.marginesOpisu === '0px', `${r.tloOpisu}, margines ${r.marginesOpisu}`);
  ok('tytuł kroku trzyma krój i kolor', r.krojKroku === 'IBM Plex Sans' && r.kolorKroku === 'rgb(234, 243, 241)', `${r.krojKroku} / ${r.kolorKroku}`);
  await c.close();
}

console.log('\n390 px, telefon');
{
  const { p, c } = await otworz('m-zwykla', 390, 900);
  const r = await p.evaluate(() => {
    const pary = [...document.querySelectorAll('.lst-2m-para')];
    return {
      kolumny: getComputedStyle(document.querySelector('.lst-2m-para')).gridTemplateColumns.split(' ').length,
      // na telefonie tekst ZAWSZE nad oknem
      kolejnosc: pary.map(x => {
        const tr = x.querySelector('.lst-2m-tresc').getBoundingClientRect();
        const ok = x.querySelector('.lst-2m-okno').getBoundingClientRect();
        return ok.top > tr.top ? 'ok' : 'źle';
      }).join(','),
      poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      szerOkna: Math.round(document.querySelector('.lst-2m-okno').getBoundingClientRect().width),
      szerRamy: Math.round(document.querySelector('.lst-2m-rama').getBoundingClientRect().width),
    };
  });
  ok('jedna kolumna, tekst nad oknem', r.kolumny === 1 && !r.kolejnosc.includes('źle'), `${r.kolumny} kolumna, ${r.kolejnosc}`);
  ok('okno na pełną szerokość, bez suwaka', Math.abs(r.szerOkna - r.szerRamy) <= 1 && r.poziom === 0, `okno ${r.szerOkna} / rama ${r.szerRamy}, suwak ${r.poziom}`);
  await c.close();
}

console.log('\n1800 px, szeroki ekran');
{
  const { p, c } = await otworz('m-zwykla', 1900, 1000);
  const r = await p.evaluate(() => {
    const rama = document.querySelector('.lst-2m-rama').getBoundingClientRect();
    const pary = [...document.querySelectorAll('.lst-2m-para')];
    return {
      // ile brakuje okienkom do krawędzi ramy po ich stronie
      luka: pary.map(x => {
        const w = x.querySelector('.lst-2m-okno').getBoundingClientRect();
        return Math.round(w.left > rama.left + rama.width / 2 ? rama.right - w.right : w.left - rama.left);
      }),
      opis: Math.round(document.querySelector('.lst-2m-opis').getBoundingClientRect().width),
      poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
  ok('okienka sięgają krawędzi sekcji', r.luka.every(x => x <= 1), `luki: ${r.luka.join(', ')} px`);
  ok('wiersz tekstu nie rozciąga się w nieskończoność', r.opis <= 720, `opis ${r.opis} px`);
  ok('bez suwaka poziomego', r.poziom === 0, String(r.poziom));
  await c.close();
}

console.log('\nwersja polska');
{
  const { p, c } = await otworz('m-pl');
  const r = await p.evaluate(() => ({
    tytul: document.querySelector('.lst-2m-krok').textContent.trim(),
    kroki: [...document.querySelectorAll('.lst-2m-krok')].map(x => x.textContent.trim()).join(' | '),
    okna: [...document.querySelectorAll('.lst-2m-nazwa-okna')].map(x => x.textContent.trim()).join(' | '),
    spacje: [...document.querySelectorAll('.lst-2m-wiersz')].map(x => x.textContent.trim()).join(' | '),
  }));
  ok('polskie teksty na miejscu', r.tytul === 'Udostępnij arkusz' && r.kroki.split('|').length === 3, r.tytul);
  // wzorzec na sklejone słowa stosujemy tylko do nazw okien — w adresie arkusza
  // "1aZ…" jest celowo i nie jest błędem
  ok('nazwy okien bez sklejonych słów', !/[a-ząćęłńóśźż][A-ZĄĆĘŁŃÓŚŹŻ]/.test(r.okna), r.okna);
  ok('spacja po strzałce zachowana przy kopiowaniu', r.spacje.startsWith('› Udostępnij'), JSON.stringify(r.spacje.slice(0, 30)));
  await c.close();
}

console.log('\njasne tło — pomiar, nie test');
{
  fs.writeFileSync('/tmp/m-biala.html', strona(en).replace('background:#232a29', 'background:#ffffff'));
  const { p, c } = await otworz('m-biala');
  const r = await p.evaluate(() => ({
    krok: getComputedStyle(document.querySelector('.lst-2m-krok')).color,
    opis: getComputedStyle(document.querySelector('.lst-2m-opis')).color,
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
