import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
let ok = 0, zle = 0;
const spr = (n, w, d) => { if (w) { ok++; console.log('  ✓', n, '—', d); } else { zle++; console.log('  ✗', n, '—', d); } };
const plik = 'file://' + process.cwd() + '/proba.html';
const PELNE = 'Your Google Sheet, always up to date on your site.';

// Divi wstawia <br /> poza <style> i <script>
const modul = fs.readFileSync('HERO-en.html', 'utf8');
const divi = (s) => s.split(/(<style>[\s\S]*?<\/style>|<script>[\s\S]*?<\/script>)/)
  .map((c, i) => (i % 2 ? c : c.split('\n').join('<br />\n'))).join('');
const strona = (m, wrogi) => fs.readFileSync('proba.html', 'utf8').replace(modul, m)
  .replace('</head>', (wrogi ? '<style>p,h1,a,span{border:2px solid #f0a!important;font-family:"Comic Sans MS"!important;text-transform:uppercase!important}p{margin:40px!important;background:#ff0!important;text-align:left!important}</style>' : '') + '</head>');
fs.writeFileSync('/tmp/h-br.html', strona(divi(modul), false));
fs.writeFileSync('/tmp/h-wrogi.html', strona(modul, true));

console.log('\nmoduł nie wychodzi poza siebie');
{
  const c = await b.newContext({ viewport: { width: 1280, height: 720 } });
  const p = await c.newPage();
  const bledy = []; p.on('pageerror', e => bledy.push(e.message));
  await p.goto(plik);
  await p.waitForTimeout(3800);
  const r = await p.evaluate(() => {
    const body = getComputedStyle(document.body);
    const hero = document.querySelector('.lst-h-hero').getBoundingClientRect();
    const dalej = document.querySelector('.dalej').getBoundingClientRect();
    const sekcja = document.querySelector('.et_pb_section:has(.lst-h-hero)');
    const ss = getComputedStyle(sekcja);
    return {
      tloStrony: body.backgroundColor,
      siatkaStrony: body.backgroundImage.includes('linear-gradient'),
      dopelnienieSekcji: ss.paddingTop + ' / ' + ss.paddingBottom,
      przerwaPodHero: Math.round(dalej.top - hero.bottom),
      spodHero: Math.round(hero.bottom),
      gornaDalej: Math.round(document.querySelector('.et_pb_section:has(.lst-h-hero)').nextElementSibling.getBoundingClientRect().top),
      spodScrolla: Math.round(document.querySelector('.lst-h-przewin').getBoundingClientRect().bottom),
      okno: window.innerHeight,
      tytul: document.querySelector('#lst-h-tytul').textContent.replace(/\s+/g, ' ').trim(),
      pismo: Math.round(parseFloat(getComputedStyle(document.querySelector('.lst-h-tytul')).fontSize)),
    };
  });
  spr('tło strony zostało nietknięte', r.tloStrony === 'rgb(35, 42, 41)' && r.siatkaStrony, `${r.tloStrony}, siatka ${r.siatkaStrony}`);
  spr('Divi nie zostawia dopełnienia wokół hero', r.dopelnienieSekcji === '0px / 0px', r.dopelnienieSekcji);
  spr('pod hero nie ma pustego ekranu', r.przerwaPodHero < 120, `${r.przerwaPodHero} px do następnej sekcji`);
  spr('hero sięga dokładnie dolnej krawędzi okna', Math.abs(r.spodHero - r.okno) <= 2, `spód hero ${r.spodHero} px, okno ${r.okno} px`);
  spr('następna sekcja zaczyna się dopiero pod ekranem', r.gornaDalej >= r.okno, `zaczyna się na ${r.gornaDalej} px`);
  spr('całość ze „scroll" na pierwszym ekranie', r.spodScrolla <= r.okno, `spód „scroll" ${r.spodScrolla} px, okno ${r.okno} px`);
  spr('napis dopisany do końca', r.tytul === PELNE, `pismo ${r.pismo} px`);
  const uchwyt = await p.evaluate(() => {
    const k = document.querySelector('.lst-h-komorka');
    const b = k.getBoundingClientRect();
    const przed = getComputedStyle(k, '::before'), po = getComputedStyle(k, '::after');
    return { poziomo: Math.round((b.right - parseFloat(po.right)) - (b.right - parseFloat(przed.right))),
             pionowo: Math.round((b.bottom - parseFloat(po.bottom)) - (b.bottom - parseFloat(przed.bottom))) };
  });
  // nic z modułu nie może malować prostokąta pod napisem
  const tlo = await p.evaluate(() => {
    const el = [...document.querySelectorAll('.lst-h-hero, .lst-h-hero *')];
    const malujace = el.filter(e => {
      const s = getComputedStyle(e);
      const szer = e.getBoundingClientRect().width, wys = e.getBoundingClientRect().height;
      const duze = szer > 320 && wys > 120;   // wielkości, w których widać łatę
      const mgla = s.backdropFilter !== 'none' || s.mixBlendMode !== 'normal';
      const malowane = s.backgroundImage !== 'none' || s.backgroundColor !== 'rgba(0, 0, 0, 0)';
      return (duze && malowane) || mgla;
    }).map(e => e.className + ' → ' + getComputedStyle(e).backgroundColor + ' / ' + getComputedStyle(e).backgroundImage.slice(0, 40) + ' / mix ' + getComputedStyle(e).mixBlendMode);
    const h = getComputedStyle(document.querySelector('.lst-h-hero'));
    const m = getComputedStyle(document.querySelector('.et_pb_module:has(.lst-h-hero)'));
    return { malujace, heroKolor: h.backgroundColor, heroObraz: h.backgroundImage,
             modulKolor: m.backgroundColor, modulObraz: m.backgroundImage,
             pseudo: ['::before', '::after'].map(x => getComputedStyle(document.querySelector('.lst-h-hero'), x).content) };
  });
  spr('hero nie maluje tła', tlo.heroKolor === 'rgba(0, 0, 0, 0)' && tlo.heroObraz === 'none', `${tlo.heroKolor}, obraz ${tlo.heroObraz}`);
  spr('moduł Divi też nie maluje tła', tlo.modulKolor === 'rgba(0, 0, 0, 0)' && tlo.modulObraz === 'none', `${tlo.modulKolor}, obraz ${tlo.modulObraz}`);
  spr('żadna warstwa modułu nie kładzie łaty', tlo.malujace.length === 0, tlo.malujace.join(' | ') || 'brak dużych malowanych warstw i mieszania warstw');

  const ramka = await p.evaluate(() => {
    const st = getComputedStyle(document.querySelector('.lst-h-komorka'), '::before');
    return { wypelnienie: st.backgroundColor, kreska: st.borderTopWidth };
  });
  // etykieta jako zaznaczona kolumna: kratki obok, nic nie wisi luzem
  const etykieta = await p.evaluate(() => {
    const el = document.querySelector('.lst-h-nadpis');
    const tu = el.querySelector('.lst-h-kol-tu');
    const st = (x, s) => getComputedStyle(x, s);
    return {
      pseudo: ['::before', '::after'].map(x => st(el, x).content),
      kratek: el.querySelectorAll(':scope > span').length,
      ukryte: [...el.querySelectorAll(':scope > .lst-h-kol')].every(s => s.getAttribute('aria-hidden') === 'true'),
      zaznaczona: st(tu, '').borderTopColor,
      wypelnienie: st(tu, '').backgroundColor,
      kreska: st(tu, '').borderTopWidth,
      sklejone: Math.round(el.querySelectorAll(':scope > span')[1].getBoundingClientRect().right
                         - tu.getBoundingClientRect().left),
    };
  });
  spr('przy etykiecie nic nie wisi luzem', etykieta.pseudo.every(x => x === 'none'), etykieta.pseudo.join(' / '));
  spr('etykieta w zaznaczonej kolumnie, obok cztery sąsiednie', etykieta.kratek === 5 && etykieta.ukryte,
    `${etykieta.kratek} kratki, litery ukryte dla czytnika: ${etykieta.ukryte}`);
  spr('zaznaczona kolumna miętowa, bez wypełnienia', etykieta.zaznaczona.startsWith('rgba(95, 227, 207') && etykieta.wypelnienie === 'rgba(0, 0, 0, 0)' && etykieta.kreska === '1px',
    `${etykieta.zaznaczona}, tło ${etykieta.wypelnienie}`);
  spr('krawędzie kratek sklejone w jedną kreskę', etykieta.sklejone === 1, `zachodzą na ${etykieta.sklejone} px`);

  spr('ramka to sama kreska, bez własnego tła', ramka.wypelnienie === 'rgba(0, 0, 0, 0)' && ramka.kreska === '1px', `tło ${ramka.wypelnienie}, kreska ${ramka.kreska}`);

  // komórki po bokach nie mogą wejść na tekst ani wystawać poza ekran
  const boki = await p.evaluate(() => {
    const kom = [...document.querySelectorAll('.lst-h-komorka-bok')];
    // akapity są na całą szerokość, a napis w nich jest wyśrodkowany —
    // liczy się miejsce zajęte przez SAM tekst, więc mierzymy zakresem
    const tekst = [...document.querySelectorAll('.lst-h-tytul, .lst-h-lead, .lst-h-akcje, .lst-h-nadpis, .lst-h-fakty, .lst-h-przewin')]
      .map(e => { const rg = document.createRange(); rg.selectNodeContents(e); return rg.getBoundingClientRect(); });
    const zachodzi = kom.filter(k => { const r = k.getBoundingClientRect();
      return tekst.some(b => r.left < b.right && r.right > b.left && r.top < b.bottom && r.bottom > b.top); });
    const pozaEkranem = kom.filter(k => { const r = k.getBoundingClientRect();
      return r.left < 0 || r.right > innerWidth || r.top < 0 || r.bottom > innerHeight; });
    return { ile: kom.length, zachodzi: zachodzi.length, poza: pozaEkranem.length,
             ukryte: [...document.querySelectorAll('.lst-h-boki')].map(e => e.getAttribute('aria-hidden')) };
  });
  spr('sześć komórek po bokach, żadna nie wchodzi na tekst', boki.ile === 6 && boki.zachodzi === 0, `${boki.ile} komórek, zachodzi ${boki.zachodzi}`);
  spr('żadna nie wystaje poza ekran', boki.poza === 0, `${boki.poza} poza ekranem`);
  spr('warstwa ukryta dla czytnika ekranu', boki.ukryte.every(x => x === 'true'), boki.ukryte.join(','));

  const paralaksa = await p.evaluate(async () => {
    const r = document.querySelector('.lst-h-hero-rama');
    const przed = r.getBoundingClientRect().top;
    window.scrollTo(0, 200);
    await new Promise(x => setTimeout(x, 300));
    const po = r.getBoundingClientRect().top;
    const krycie = parseFloat(getComputedStyle(r).opacity);
    window.scrollTo(0, 0);
    await new Promise(x => setTimeout(x, 300));
    // strona przesunęła się o 200 px, treść ma zostać w tyle
    return { przesuniecie: Math.round(przed - po), krycie: +krycie.toFixed(2),
             wrocilo: Math.round(r.getBoundingClientRect().top) };
  });
  spr('treść odjeżdża wolniej niż strona', paralaksa.przesuniecie > 100 && paralaksa.przesuniecie < 180, `strona 200 px, treść ${paralaksa.przesuniecie} px`);
  spr('i po drodze gaśnie', paralaksa.krycie > 0.3 && paralaksa.krycie < 0.85, `krycie ${paralaksa.krycie}`);

  spr('uchwyt siedzi w rogu ramki', uchwyt.poziomo === 0 && uchwyt.pionowo === 0, `odchylenie ${uchwyt.poziomo} / ${uchwyt.pionowo} px`);
  spr('bez błędów w konsoli', bledy.length === 0, bledy.join(' | ') || '0');
  await p.screenshot({ path: 'modul-strona.png', fullPage: false });
  await c.close();
}

console.log('\nDivi wstawia <br />');
{
  const c = await b.newContext({ viewport: { width: 1280, height: 720 } });
  const p = await c.newPage();
  const bledy = []; p.on('pageerror', e => bledy.push(e.message));
  await p.goto('file:///tmp/h-br.html');
  await p.waitForTimeout(3600);
  const r = await p.evaluate(() => ({
    tytul: document.querySelector('#lst-h-tytul').textContent.replace(/\s+/g, ' ').trim(),
    brWidoczne: [...document.querySelectorAll('.lst-h-hero br')].some(x => getComputedStyle(x).display !== 'none'),
    wysokosc: Math.round(document.querySelector('.lst-h-hero').getBoundingClientRect().height),
  }));
  spr('układ i pisanie przeżywają <br />', r.tytul === PELNE && !r.brWidoczne && bledy.length === 0, `${r.wysokosc} px, br widoczne: ${r.brWidoczne}`);
  await c.close();
}

console.log('\nwrogi motyw');
{
  const c = await b.newContext({ viewport: { width: 1280, height: 720 } });
  const p = await c.newPage();
  await p.goto('file:///tmp/h-wrogi.html');
  await p.waitForTimeout(3600);
  const r = await p.evaluate(() => {
    const st = (s) => getComputedStyle(document.querySelector(s));
    return { krojLead: st('.lst-h-lead').fontFamily.split(',')[0].replace(/"/g, ''),
             wersaliki: st('.lst-h-lead').textTransform,
             tloLead: st('.lst-h-lead').backgroundColor,
             srodek: st('.lst-h-lead').textAlign,
             krojTytulu: st('.lst-h-tytul').fontFamily.split(',')[0].replace(/"/g, '') };
  });
  spr('motyw nie przejmuje tekstu', r.krojLead === 'IBM Plex Sans' && r.wersaliki === 'none' && r.tloLead === 'rgba(0, 0, 0, 0)' && r.srodek === 'center', `${r.krojLead}, ${r.wersaliki}, ${r.srodek}`);
  spr('tytuł trzyma Inria Serif', r.krojTytulu === 'Inria Serif', r.krojTytulu);
  await c.close();
}

console.log('\nszerokości');
for (const [w, h] of [[1920, 1080], [1440, 900], [1366, 768], [1280, 720], [1024, 600], [390, 844], [360, 640]]) {
  const c = await b.newContext({ viewport: { width: w, height: h } });
  const p = await c.newPage();
  const bledy = []; p.on('pageerror', e => bledy.push(e.message));
  await p.goto(plik);
  await p.waitForTimeout(3600);
  const r = await p.evaluate(() => ({
    poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    spodHero: Math.round(document.querySelector('.lst-h-hero').getBoundingClientRect().bottom),
    odMenu: (() => { const m = document.querySelector('.pasek-menu'); const b = (m.offsetHeight ? m : document.querySelector('.pasek')).getBoundingClientRect().bottom;
      return Math.round(document.querySelector('.lst-h-nadpis').getBoundingClientRect().top - b); })(),
    pustyPas: (() => { const m = document.querySelector('.pasek-menu'); const b = (m.offsetHeight ? m : document.querySelector('.pasek')).getBoundingClientRect().bottom;
      return Math.round(document.querySelector('.lst-h-hero').getBoundingClientRect().top - b); })(),
    gornaDalej: Math.round(document.querySelector('.et_pb_section:has(.lst-h-hero)').nextElementSibling.getBoundingClientRect().top),
    spod: Math.round(document.querySelector('.lst-h-przewin').getBoundingClientRect().bottom),
    okno: window.innerHeight,
    bokiWidoczne: [...document.querySelectorAll('.lst-h-komorka-bok')].filter(e => e.offsetParent !== null).length,
    male: [...document.querySelectorAll('.lst-h-nadpis, .lst-h-fakty, .lst-h-przewin')].map(e => Math.round(parseFloat(getComputedStyle(e).fontSize))),
    srednie: [...document.querySelectorAll('.lst-h-lead, .lst-h-guzik, .lst-h-link')].map(e => Math.round(parseFloat(getComputedStyle(e).fontSize))),
  }));
  spr(`${w}x${h}: „scroll" na pierwszym ekranie, bez suwaka`, r.spod <= r.okno && r.poziom === 0, `spód ${r.spod} px z ${r.okno} px`);
  spr(`${w}x${h}: drobne 14, treść 18 albo 20`, r.male.every(x => x === 14) && r.srednie.every(x => [18, 20].includes(x)), 'drobne ' + [...new Set(r.male)].join('/') + ', treść ' + [...new Set(r.srednie)].join('/'));
  spr(`${w}x${h}: napis trzyma się blisko menu`, r.odMenu <= 60 && r.pustyPas === 0, `${r.odMenu} px pod paskiem menu, pusty pas ${r.pustyPas} px`);
  spr(`${w}x${h}: hero na pełny ekran, dalsza treść pod krawędzią`, Math.abs(r.spodHero - r.okno) <= 2 && r.gornaDalej >= r.okno - 1, `spód hero ${r.spodHero} px, dalej od ${r.gornaDalej} px, okno ${r.okno} px`);
  spr(`${w}x${h}: komórki po bokach tylko tam, gdzie jest miejsce`, w > 1200 ? r.bokiWidoczne === 6 : r.bokiWidoczne === 0, `widoczne ${r.bokiWidoczne}`);
  spr(`${w}x${h}: bez błędów`, bledy.length === 0, bledy.join(' | ') || '0');
  await c.close();
}

console.log(`\n${ok} PASS, ${zle} FAIL`);
await b.close();
process.exit(zle ? 1 : 0);
