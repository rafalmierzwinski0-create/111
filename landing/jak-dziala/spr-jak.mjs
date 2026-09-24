import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
let pass=0, fail=0;
const ok=(n,w,d)=>{ if(w){pass++;console.log('  ✓ ',n,' — ',d);} else {fail++;console.log('  ✗ ',n,' — ',d);} };
const c = await b.newContext({ viewport: { width: 1500, height: 1100 } });
const p = await c.newPage();
const bledy=[]; p.on('pageerror',e=>bledy.push(e.message));
await p.goto('file://' + process.cwd() + '/proba-jak.html');
await p.waitForTimeout(800);
// obrazki mają loading="lazy" — bez przewinięcia strony te niżej się nie wczytają
await p.evaluate(async () => {
  for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 90)); }
  window.scrollTo(0, 0);
});
await p.waitForTimeout(1500);
const r = await p.evaluate(() => ({
  blokow: document.querySelectorAll('.lst-jak-blok').length,
  obrazkow: document.querySelectorAll('.lst-jak-okno img').length,
  zalowane: [...document.querySelectorAll('.lst-jak-okno img')].filter(i => i.complete && i.naturalWidth > 0).length,
  puste: [...document.querySelectorAll('.lst-jak-okno img')].filter(i => !i.complete || i.naturalWidth === 0).map(i => i.getAttribute('src')),
  szerokich: document.querySelectorAll('.lst-jak-blok.jest-szeroki').length,
  strony: [...document.querySelectorAll('.lst-jak-blok:not(.jest-szeroki)')].map(x => {
    const t = x.querySelector('.lst-jak-tresc').getBoundingClientRect();
    const o = x.querySelector('.lst-jak-okno').getBoundingClientRect();
    return o.left > t.left ? 'P' : 'L';
  }).join(''),
  rozmiary: ['.lst-jak-wstep','.lst-jak-numer','.lst-jak-tytul','.lst-jak-opis','.lst-jak-nazwa-okna','.lst-jak-dopisek','.lst-jak-kod','.lst-jak-pro-tytul','.lst-jak-pro-wstep','.lst-jak-pro-etykieta']
    .map(s => { const e = document.querySelector(s); return e ? Math.round(parseFloat(getComputedStyle(e).fontSize)) : 0; }),
  poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  wysokosc: Math.round(document.querySelector('.lst-jak').getBoundingClientRect().height),
  telefonWys: Math.round(document.querySelector('.lst-jak-okno.jest-wysoki').getBoundingClientRect().height),
}));
ok('dziesięć bloków, dziesięć zrzutów', r.blokow === 10 && r.obrazkow === 10, `${r.blokow} / ${r.obrazkow}`);
ok('wszystkie obrazki się wczytały', r.zalowane === 10, r.puste.length ? 'puste: ' + r.puste.join(', ') : '10 z 10');
ok('bloki obok siebie idą naprzemiennie', r.strony === 'PLPLPL', r.strony);
ok('szerokie zrzuty na całą szerokość', r.szerokich === 4, r.szerokich + ' z 10');
ok('rozmiary tylko 14, 18 i 20', r.rozmiary.every(x => [14,18,20].includes(x)), r.rozmiary.join('/'));
ok('bez suwaka poziomego', r.poziom === 0, String(r.poziom));
ok('wysoki zrzut z telefonu przycięty', r.telefonWys <= 640, r.telefonWys + ' px');
ok('bez błędów', bledy.length === 0, bledy.join(' | ') || '0');
console.log('     wysokość strony:', r.wysokosc, 'px');
await p.locator('.lst-jak').screenshot({ path: 'jak-1500.png' });

const c2 = await b.newContext({ viewport: { width: 390, height: 900 } });
const p2 = await c2.newPage();
await p2.goto('file://' + process.cwd() + '/proba-jak.html');
await p2.waitForTimeout(1200);
const m = await p2.evaluate(() => ({
  kolumny: getComputedStyle(document.querySelector('.lst-jak-blok')).gridTemplateColumns.split(' ').length,
  poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
}));
ok('na telefonie jedna kolumna, bez suwaka', m.kolumny === 1 && m.poziom === 0, `${m.kolumny} kolumna, suwak ${m.poziom}`);
console.log(`\n${pass} PASS, ${fail} FAIL`);
await b.close();
process.exit(fail ? 1 : 0);
