import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';

const en = fs.readFileSync('POKAZ-en.html', 'utf8');
const pl = fs.readFileSync('POKAZ-pl.html', 'utf8');

// Divi wstawia <br /> w każdym złamanym wierszu poza <style> i <script>
const divi = (s) => {
  const cz = s.split(/(<style>[\s\S]*?<\/style>|<script>[\s\S]*?<\/script>)/);
  return cz.map((c, i) => (i % 2 ? c : c.split('\n').join('<br />\n'))).join('');
};

const strona = (t, { wrogi = false, bezJs = false } = {}) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Pokaz</title>
<style>html,body{margin:0;background:#232a29;color:#eaf3f1;font-family:system-ui}
.et_pb_section{padding:40px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}</style>
${wrogi ? '<style>div,span,p,button,td,th{border:2px solid #f0a!important}p,td{margin:40px!important;background:#ff0!important;font-family:"Comic Sans MS"!important;text-transform:uppercase!important}button{color:#00f!important;font-family:"Comic Sans MS"!important}</style>' : ''}
</head><body>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik">A paragraph.</p></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module">
${bezJs ? t.replace(/<script>[\s\S]*?<\/script>/g, '') : t}
</div></div></div></div></body></html>`;

fs.writeFileSync('/tmp/p-zwykla.html', strona(en));
fs.writeFileSync('/tmp/p-br.html', strona(divi(en)));
fs.writeFileSync('/tmp/p-wrogi.html', strona(en, { wrogi: true }));
fs.writeFileSync('/tmp/p-bezjs.html', strona(en, { bezJs: true }));
fs.writeFileSync('/tmp/p-pl.html', strona(pl));

const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
let pass = 0, fail = 0;
const ok = (n, w, d) => { if (w) { pass++; console.log('  ✓ ', n, ' — ', d); } else { fail++; console.log('  ✗ ', n, ' — ', d); } };

async function otworz(n, w = 1400, h = 1100) {
  const c = await b.newContext({ viewport: { width: w, height: h }, ignoreHTTPSErrors: true, acceptDownloads: true });
  const p = await c.newPage();
  const bledy = [];
  p.on('pageerror', e => bledy.push(e.message));
  p.on('console', m => { const t = m.text(); if (m.type() === 'error' && !/ERR_|Failed to load resource/.test(t)) bledy.push(t); });
  await p.goto(`file:///tmp/${n}.html`);
  await p.waitForTimeout(700);
  return { p, c, bledy };
}

// w komórce siedzi ukryta nazwa pola ("Product"), więc czytamy samą wartość
const tekst = (k) => (k.querySelector('.lst-pok-wartosc') || k.querySelector('.lst-pok-znacznik') || k).textContent.trim();
const widoczne = (p) => p.$$eval('.lst-pok-tabela tbody tr', rs => rs.filter(r => getComputedStyle(r).display !== 'none')
  .map(r => { const k = r.cells[0]; return (k.querySelector('.lst-pok-wartosc') || k).textContent.trim(); }));
const kolumna = (p, nr) => p.$$eval('.lst-pok-tabela tbody tr', (rs, nr) => rs.filter(r => getComputedStyle(r).display !== 'none')
  .map(r => { const k = r.cells[nr]; return (k.querySelector('.lst-pok-wartosc') || k.querySelector('.lst-pok-znacznik') || k).textContent.trim(); }), nr);

console.log('\n1400 px — co widać na starcie');
{
  const { p, c, bledy } = await otworz('p-zwykla');
  const r = await p.evaluate(() => ({
    wszystkich: document.querySelectorAll('.lst-pok-tabela tbody tr').length,
    kolumn: document.querySelectorAll('.lst-pok-tabela th').length,
    licznik: document.querySelector('.lst-pok-ile').textContent.trim(),
    strona: document.querySelector('.lst-pok-ktora').textContent.trim(),
    wstecz: document.querySelector('.lst-pok-strona[data-krok="-1"]').disabled,
    filtryUkryte: getComputedStyle(document.querySelector('.lst-pok-filtry')).display === 'none',
    pobieranieUkryte: getComputedStyle(document.querySelector('.lst-pok-pobieranie')).display === 'none',
    lewa: Math.round(document.querySelector('.lst-pok-rama').getBoundingClientRect().left),
    odn: Math.round(document.querySelector('#odnosnik').getBoundingClientRect().left),
    poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  }));
  const naStronie = await widoczne(p);
  ok('14 wierszy w kodzie, 5 na pierwszej stronie', r.wszystkich === 14 && naStronie.length === 5, `${r.wszystkich} / ${naStronie.length}`);
  ok('licznik i numer strony', r.licznik === '14 rows' && r.strona === '1 of 3', `${r.licznik} · ${r.strona}`);
  ok('„poprzednia" wyłączona na pierwszej stronie', r.wstecz === true, String(r.wstecz));
  ok('rzeczy z Pro schowane do czasu włączenia', r.filtryUkryte && r.pobieranieUkryte, `filtry ${r.filtryUkryte}, pobieranie ${r.pobieranieUkryte}`);
  ok('równo z resztą strony, bez suwaka', Math.abs(r.lewa - r.odn) <= 1 && r.poziom === 0, `${r.lewa} vs ${r.odn}, suwak ${r.poziom}`);
  ok('bez błędów w konsoli', bledy.length === 0, bledy.length ? bledy.join(' | ') : '0');
  await c.close();
}

console.log('\nszukanie');
{
  const { p, c } = await otworz('p-zwykla');
  await p.fill('.lst-pok-szukaj input', 'bike');
  await p.waitForTimeout(250);
  const po = await widoczne(p);
  const r = await p.evaluate(() => ({
    licznik: document.querySelector('.lst-pok-ile').textContent.trim(),
    znacznikow: document.querySelectorAll('.lst-pok-tabela mark').length,
    kasuj: !document.querySelector('.lst-pok-kasuj').hidden,
  }));
  ok('szukanie zawęża tabelę', po.length === 3 && r.licznik === '3 rows', `${r.licznik}: ${po.join(', ')}`);
  ok('trafienia podświetlone', r.znacznikow >= 3, `${r.znacznikow} podświetleń`);

  // polskie ogonki: "odziez" ma znaleźć "Odzież"
  await p.fill('.lst-pok-szukaj input', 'zzzz');
  await p.waitForTimeout(200);
  const nic = await p.evaluate(() => ({ pusto: !document.querySelector('.lst-pok-pusto').hidden, ile: document.querySelector('.lst-pok-ile').textContent.trim() }));
  ok('brak wyników mówi o tym wprost', nic.pusto && nic.ile === '0 rows', `${nic.ile}, komunikat: ${nic.pusto}`);

  await p.click('.lst-pok-kasuj');
  await p.waitForTimeout(200);
  const wrocilo = await widoczne(p);
  ok('kasowanie wraca do pełnej tabeli', wrocilo.length === 5 && r.kasuj, `${wrocilo.length} wierszy`);

  // po wyczyszczeniu nie może zostać ani jeden <mark>
  const zostalo = await p.$$eval('.lst-pok-tabela mark', m => m.length);
  ok('podświetlenia znikają bez zjadania treści', zostalo === 0, `${zostalo} podświetleń`);
  await c.close();
}

console.log('\nsortowanie');
{
  const { p, c } = await otworz('p-zwykla');
  // kolumna 3 = Price, liczbowa
  await p.click('.lst-pok-tabela th[data-kolumna="2"] .lst-pok-sort');
  await p.waitForTimeout(250);
  const rosnaco = await kolumna(p, 2);
  await p.click('.lst-pok-tabela th[data-kolumna="2"] .lst-pok-sort');
  await p.waitForTimeout(250);
  const malejaco = await kolumna(p, 2);
  const liczba = (s) => parseFloat(s.replace(/[^0-9.]/g, ''));
  ok('ceny rosnąco, po liczbie a nie po napisie', liczba(rosnaco[0]) === 29 && liczba(rosnaco[4]) === 249, rosnaco.join(' · '));
  ok('drugie kliknięcie odwraca', liczba(malejaco[0]) === 4199.99, malejaco.slice(0, 3).join(' · '));

  await p.click('.lst-pok-tabela th[data-kolumna="0"] .lst-pok-sort');
  await p.waitForTimeout(250);
  const alfabet = await widoczne(p);
  ok('nazwy alfabetycznie', alfabet[0].startsWith('Abus'), alfabet.slice(0, 3).join(' · '));

  const strzalka = await p.evaluate(() => {
    const th = document.querySelector('.lst-pok-tabela th[data-kolumna="0"]');
    const inne = [...document.querySelectorAll('.lst-pok-tabela th[data-kierunek]')].length;
    return { kierunek: th.getAttribute('data-kierunek'), ilePodswietlonych: inne };
  });
  ok('tylko jedna kolumna pokazuje strzałkę', strzalka.ilePodswietlonych === 1 && strzalka.kierunek === 'rosnaco', `${strzalka.ilePodswietlonych}, kierunek ${strzalka.kierunek}`);
  await c.close();
}

console.log('\nstrony');
{
  const { p, c } = await otworz('p-zwykla');
  const pierwsza = await widoczne(p);
  await p.click('.lst-pok-strona[data-krok="1"]');
  await p.waitForTimeout(250);
  const druga = await widoczne(p);
  await p.click('.lst-pok-strona[data-krok="1"]');
  await p.waitForTimeout(250);
  const trzecia = await widoczne(p);
  const r = await p.evaluate(() => ({
    ktora: document.querySelector('.lst-pok-ktora').textContent.trim(),
    dalej: document.querySelector('.lst-pok-strona[data-krok="1"]').disabled,
  }));
  const razem = [...pierwsza, ...druga, ...trzecia];
  ok('trzy strony pokrywają wszystkie 14 wierszy', new Set(razem).size === 14, `${razem.length} wierszy, unikalnych ${new Set(razem).size}`);
  ok('ostatnia strona ma 4 wiersze i wyłącza „następną"', trzecia.length === 4 && r.dalej === true, `${r.ktora}, ${trzecia.length} wierszy`);
  await c.close();
}

console.log('\nszerokość — tabela sama składa się w karty');
{
  const { p, c } = await otworz('p-zwykla');
  const szeroko = await p.evaluate(() => ({
    display: getComputedStyle(document.querySelector('.lst-pok-tabela')).display,
    etykieta: getComputedStyle(document.querySelector('.lst-pok-etykieta')).display,
    szer: Math.round(document.querySelector('.lst-pok-scena').getBoundingClientRect().width),
  }));
  await p.click('.lst-pok-szer[data-szer="waski"]');
  await p.waitForTimeout(700);
  const wasko = await p.evaluate(() => ({
    display: getComputedStyle(document.querySelector('.lst-pok-tabela')).display,
    etykieta: getComputedStyle(document.querySelector('.lst-pok-etykieta')).display,
    glowa: getComputedStyle(document.querySelector('.lst-pok-tabela thead')).position,
    szer: Math.round(document.querySelector('.lst-pok-scena').getBoundingClientRect().width),
    etykiety: [...document.querySelectorAll('.lst-pok-tabela tbody tr:not([style*="none"]) .lst-pok-etykieta')].slice(0, 5).map(e => e.textContent.trim()),
  }));
  ok('szeroko to prawdziwa tabela, bez etykiet', szeroko.display === 'table' && szeroko.etykieta === 'none', `${szeroko.display} / etykieta ${szeroko.etykieta} / ${szeroko.szer} px`);
  ok('wąsko wiersze stają się kartami z nazwami pól', wasko.display === 'block' && wasko.etykieta === 'block' && wasko.szer === 390,
     `${wasko.display} / ${wasko.szer} px / ${wasko.etykiety.join(', ')}`);
  ok('nagłówek tabeli chowa się dla oka, nie dla czytnika', wasko.glowa === 'absolute', wasko.glowa);

  // szukanie musi dalej działać po zwężeniu
  await p.fill('.lst-pok-szukaj input', 'helmet');
  await p.waitForTimeout(250);
  const poSzukaniu = await widoczne(p);
  ok('szukanie działa też w układzie kart', poSzukaniu.length === 1, poSzukaniu.join(', '));
  const jeden = await p.$eval('.lst-pok-ile', e => e.textContent.trim());
  ok('jeden wiersz to „1 row", nie „1 rows"', jeden === '1 row', jeden);
  await p.locator('.lst-pok-scena').screenshot({ path: 'pokaz-telefon.png' });
  await c.close();
}

console.log('\nprzełączniki Pro');
{
  const { p, c } = await otworz('p-zwykla');

  await p.click('.lst-pok-pro[data-pro="reguly"]');
  await p.waitForTimeout(300);
  const reguly = await p.evaluate(() => {
    const brak = [...document.querySelectorAll('.lst-pok-tabela tbody tr[data-stan="brak"]')].find(r => getComputedStyle(r).display !== 'none');
    const jest = [...document.querySelectorAll('.lst-pok-tabela tbody tr[data-stan="jest"]')].find(r => getComputedStyle(r).display !== 'none');
    return {
      brak: brak ? getComputedStyle(brak.cells[0]).backgroundColor : 'brak wiersza',
      jest: jest ? getComputedStyle(jest.cells[0]).backgroundColor : 'brak wiersza',
      dioda: getComputedStyle(document.querySelector('.lst-pok-pro[data-pro="reguly"] .lst-pok-dioda')).backgroundColor,
    };
  });
  ok('reguła maluje tylko braki', reguly.brak !== reguly.jest && reguly.brak.includes('232'), `brak: ${reguly.brak}, jest: ${reguly.jest}`);
  ok('dioda przełącznika się zapala', reguly.dioda === 'rgb(95, 227, 207)', reguly.dioda);

  await p.click('.lst-pok-pro[data-pro="filtry"]');
  await p.waitForTimeout(300);
  const widacFiltry = await p.evaluate(() => getComputedStyle(document.querySelector('.lst-pok-filtry')).display !== 'none');
  await p.click('.lst-pok-filtr[data-filtr="bikes"]');
  await p.waitForTimeout(300);
  const poFiltrze = await widoczne(p);
  const licznikPoFiltrze = await p.$eval('.lst-pok-ile', e => e.textContent.trim());
  ok('filtr zawęża do kategorii', widacFiltry && poFiltrze.length === 3 && licznikPoFiltrze === '3 rows', `${licznikPoFiltrze}: ${poFiltrze.join(', ')}`);

  // wyłączenie filtrów musi też skasować wybraną kategorię
  await p.click('.lst-pok-pro[data-pro="filtry"]');
  await p.waitForTimeout(300);
  const poWylaczeniu = await p.$eval('.lst-pok-ile', e => e.textContent.trim());
  ok('wyłączenie filtrów zdejmuje też wybraną kategorię', poWylaczeniu === '14 rows', poWylaczeniu);

  await p.click('.lst-pok-pro[data-pro="pobierz"]');
  await p.waitForTimeout(300);
  const pobranie = p.waitForEvent('download');
  await p.click('.lst-pok-csv');
  const plik = await pobranie;
  const sciezka = await plik.path();
  const tresc = fs.readFileSync(sciezka, 'utf8');
  const linii = tresc.trim().split('\n').length;
  ok('pobiera prawdziwy plik CSV', plik.suggestedFilename() === 'price-list.csv' && linii === 15, `${plik.suggestedFilename()}, ${linii} linii (nagłówek + 14)`);
  ok('CSV ma polskie znaki i cudzysłowy w porządku', tresc.startsWith('﻿"Product"') && tresc.includes('"Trek Marlin 7 mountain bike"'), tresc.slice(1, 60).replace(/\n/g, ' / '));
  await c.close();
}

console.log('\nbez JavaScriptu');
{
  const { p, c } = await otworz('p-bezjs');
  const r = await p.evaluate(() => ({
    widocznych: [...document.querySelectorAll('.lst-pok-tabela tbody tr')].filter(r => getComputedStyle(r).display !== 'none').length,
    tresc: document.querySelector('.lst-pok-tabela tbody tr').textContent.includes('Trek'),
  }));
  ok('cała tabela jest widoczna bez skryptu', r.widocznych === 14 && r.tresc, `${r.widocznych} wierszy`);
  await c.close();
}

console.log('\nDivi wstawia <br />');
{
  const { p, c, bledy } = await otworz('p-br');
  const r = await p.evaluate(() => ({
    wierszy: document.querySelectorAll('.lst-pok-tabela tbody tr').length,
    brWidoczne: [...document.querySelectorAll('.lst-pok br')].some(x => getComputedStyle(x).display !== 'none'),
  }));
  const naStronie = await widoczne(p);
  ok('układ i skrypt przeżywają <br />', r.wierszy === 14 && naStronie.length === 5 && !r.brWidoczne && bledy.length === 0,
     `${r.wierszy} wierszy, ${naStronie.length} na stronie, błędy: ${bledy.length}`);
  await c.close();
}

console.log('\nwrogi motyw');
{
  const { p, c } = await otworz('p-wrogi');
  const r = await p.evaluate(() => {
    const st = (s) => getComputedStyle(document.querySelector(s));
    return {
      krojKomorki: st('.lst-pok-tabela td').fontFamily.split(',')[0].replace(/"/g, ''),
      wersaliki: st('.lst-pok-tabela td').textTransform,
      tloKomorki: st('.lst-pok-tabela td').backgroundColor,
      krojWstepu: st('.lst-pok-wstep').fontFamily.split(',')[0].replace(/"/g, ''),
      marginesWstepu: st('.lst-pok-wstep').marginLeft,
      krojPrzycisku: st('.lst-pok-szer').fontFamily.split(',')[0].replace(/"/g, ''),
    };
  });
  ok('motyw nie przejmuje komórek', r.krojKomorki === 'IBM Plex Sans' && r.wersaliki === 'none' && r.tloKomorki === 'rgba(0, 0, 0, 0)', `${r.krojKomorki} / ${r.wersaliki} / ${r.tloKomorki}`);
  ok('motyw nie przejmuje tekstu ani przycisków', r.krojWstepu === 'IBM Plex Sans' && r.marginesWstepu === '0px' && r.krojPrzycisku === 'IBM Plex Mono', `${r.krojWstepu}, margines ${r.marginesWstepu}, przycisk ${r.krojPrzycisku}`);
  await c.close();
}

console.log('\nrozmiary pisma');
{
  const { p, c } = await otworz('p-zwykla');
  const r = await p.evaluate(() => {
    const lista = ['.lst-pok-wstep', '.lst-pok-szukaj input', '.lst-pok-szer', '.lst-pok-sort',
      '.lst-pok-tabela td', '.lst-pok-znacznik', '.lst-pok-dol', '.lst-pok-strona',
      '.lst-pok-pro-nazwa', '.lst-pok-pro-opis', '.lst-pok-plakietka', '.lst-pok-kod'];
    return lista.map(s => Math.round(parseFloat(getComputedStyle(document.querySelector(s)).fontSize)));
  });
  ok('tylko 14, 18 i 20', r.every(x => [14, 18, 20].includes(x)), r.join('/'));
  await c.close();
}

console.log('\n390 px, prawdziwy telefon');
{
  const { p, c } = await otworz('p-zwykla', 390, 1000);
  const r = await p.evaluate(() => ({
    display: getComputedStyle(document.querySelector('.lst-pok-tabela')).display,
    przelacznikSzer: getComputedStyle(document.querySelector('.lst-pok-szerokosci')).display,
    kolumnyPro: getComputedStyle(document.querySelector('.lst-pok-przelaczniki')).gridTemplateColumns.split(' ').length,
    poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  }));
  ok('na telefonie od razu karty, bez przełącznika szerokości', r.display === 'block' && r.przelacznikSzer === 'none', `${r.display}, przełącznik ${r.przelacznikSzer}`);
  ok('przełączniki Pro jeden pod drugim, bez suwaka', r.kolumnyPro === 1 && r.poziom === 0, `${r.kolumnyPro} kolumna, suwak ${r.poziom}`);
  await c.close();
}

console.log('\nwersja polska');
{
  const { p, c } = await otworz('p-pl');
  await p.fill('.lst-pok-szukaj input', 'odziez');
  await p.waitForTimeout(250);
  const r = await p.evaluate(() => ({
    licznik: document.querySelector('.lst-pok-ile').textContent.trim(),
    ktora: document.querySelector('.lst-pok-ktora').textContent.trim(),
    cena: document.querySelector('.lst-pok-tabela tbody tr:not([style*="none"]) td:nth-child(3) .lst-pok-wartosc').textContent.trim(),
  }));
  ok('szukanie bez ogonków znajduje „Odzież"', r.licznik === '5 wierszy', `"odziez" → ${r.licznik}`);
  ok('polskie napisy i złotówki', r.ktora.includes(' z ') && r.cena.includes('zł'), `${r.ktora} · ${r.cena}`);
  const odmiany = await p.evaluate(async () => {
    const i = document.querySelector('.lst-pok-szukaj input');
    const daj = async (co) => { i.value = co; i.dispatchEvent(new Event('input')); await new Promise(r => setTimeout(r, 120)); return document.querySelector('.lst-pok-ile').textContent.trim(); };
    return { jeden: await daj('Topeak'), trzy: await daj('bike'), duzo: await daj('') };
  });
  ok('polska odmiana: wiersz / wiersze / wierszy', odmiany.jeden === '1 wiersz' && odmiany.trzy === '2 wiersze' && odmiany.duzo === '14 wierszy',
     `${odmiany.jeden} · ${odmiany.trzy} · ${odmiany.duzo}`);
  ok('nazwa kolumny nie pasuje do wszystkiego', await p.evaluate(async () => { const i = document.querySelector('.lst-pok-szukaj input'); i.value = 'cena'; i.dispatchEvent(new Event('input')); await new Promise(r => setTimeout(r, 150)); return document.querySelector('.lst-pok-ile').textContent.trim(); }) === '0 wierszy', 'szukanie "cena" → 0 wierszy');

  await p.fill('.lst-pok-szukaj input', '');
  await p.click('.lst-pok-tabela th[data-kolumna="2"] .lst-pok-sort');
  await p.waitForTimeout(250);
  const ceny = await kolumna(p, 2);
  ok('sortowanie rozumie „1 215,50 zł"', ceny[0].startsWith('29,00') && ceny[4].startsWith('249,00'), ceny.join(' · '));
  await c.close();
}

// zrzuty
for (const [n, w, h, plik] of [['p-zwykla', 1400, 1100, 'pokaz-1400.png'], ['p-pl', 1400, 1100, 'pokaz-pl.png']]) {
  const c = await b.newContext({ viewport: { width: w, height: h } });
  const p = await c.newPage();
  await p.goto(`file:///tmp/${n}.html`);
  await p.waitForTimeout(800);
  await p.locator('.lst-pok').screenshot({ path: plik });
  await c.close();
}

console.log(`\n${pass} PASS, ${fail} FAIL`);
await b.close();
process.exit(fail ? 1 : 0);
