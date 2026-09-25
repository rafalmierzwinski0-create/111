// Cztery układy strony, w których pusty pas nad hero bierze się z czegoś innego.
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
let ok = 0, zle = 0;
const spr = (n, w, d) => { if (w) { ok++; console.log('  ✓', n, '—', d); } else { zle++; console.log('  ✗', n, '—', d); } };
const modul = fs.readFileSync('HERO-en.html', 'utf8');

const glowa = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300;0,400;1,400&display=swap">
<style>html,body{margin:0;background:#232a29;color:#eef5f3;font-family:system-ui}
.bar{height:74px;background:#1b2221;border-bottom:1px solid rgba(138,168,163,.18)}
.menu{background:#1b2221;border-bottom:1px solid rgba(138,168,163,.18);padding:16px 20px;font-family:"IBM Plex Mono",monospace;font-size:14px;color:#b3c6c3}
.et_pb_section{padding:54px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}
.dalej{padding:80px 0;text-align:center;color:#8fa5a2}</style></head><body>`;
const sekcjaHero = `<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module et_pb_code">${modul}</div></div></div></div>`;
const potem = `<div class="et_pb_section"><div class="et_pb_row"><p class="dalej">następna sekcja</p></div></div></body></html>`;

const uklady = {
  // pasek menu w osobnym nagłówku Kreatora Motywu — hero nie ma sąsiada nad sobą
  'nagłówek z Kreatora Motywu': `${glowa}<header class="et-l--header"><div class="bar"></div><div class="menu">A Start &nbsp; B How it works &nbsp; C Why it's different</div></header>
    <div class="et-l--body" style="padding-top:113px">${sekcjaHero}${potem}`,
  // pusty wiersz Divi nad modułem, w tej samej sekcji
  'pusty wiersz nad modułem': `${glowa}<div class="bar"></div><div class="menu">A Start</div>
    <div class="et_pb_section" style="padding:0"><div class="et_pb_row" style="height:113px"></div><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module et_pb_code">${modul}</div></div></div></div>${potem}`,
  // margines sekcji ustawiony w Divi
  'margines sekcji w Divi': `${glowa}<div class="bar"></div><div class="menu">A Start</div>
    <div class="et_pb_section" style="margin-top:113px"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module et_pb_code">${modul}</div></div></div></div>${potem}`,
  // przyklejony nagłówek + miejsce pod niego
  'przyklejony nagłówek': `${glowa}<div style="position:fixed;top:0;left:0;right:0;z-index:9"><div class="bar"></div><div class="menu">A Start</div></div>
    <div style="padding-top:240px">${sekcjaHero}${potem}`,
};

for (const [nazwa, html] of Object.entries(uklady)) {
  fs.writeFileSync('/tmp/u.html', html);
  const c = await b.newContext({ viewport: { width: 1600, height: 900 } });
  const p = await c.newPage();
  const bledy = []; p.on('pageerror', e => bledy.push(e.message));
  await p.goto('file:///tmp/u.html');
  await p.waitForTimeout(3600);
  const r = await p.evaluate(() => {
    const hero = document.querySelector('.lst-h-hero').getBoundingClientRect();
    // spód tego, co maluje nad hero
    let spod = 0;
    for (const el of document.body.getElementsByTagName('*')) {
      const q = el.getBoundingClientRect();
      if (q.bottom > hero.top + 1 || q.height < 4 || q.width < hero.width * 0.4) continue;
      const s = getComputedStyle(el);
      if (s.backgroundColor === 'rgba(0, 0, 0, 0)' && s.backgroundImage === 'none' && parseFloat(s.borderBottomWidth) === 0) continue;
      spod = Math.max(spod, q.bottom);
    }
    return { pustyPas: Math.round(hero.top - spod), spodHero: Math.round(hero.bottom), okno: innerHeight,
             odNapisu: Math.round(document.querySelector('.lst-h-nadpis').getBoundingClientRect().top - spod),
             scrollWidoczny: document.querySelector('.lst-h-przewin').getBoundingClientRect().bottom <= innerHeight };
  });
  console.log('\n' + nazwa);
  spr('pusty pas nad hero zniknął', r.pustyPas <= 1, `${r.pustyPas} px`);
  spr('napis blisko paska', r.odNapisu <= 60, `${r.odNapisu} px od spodu paska`);
  spr('hero kończy się na dole ekranu', Math.abs(r.spodHero - r.okno) <= 2 && r.scrollWidoczny, `spód ${r.spodHero} px z ${r.okno} px`);
  spr('bez błędów', bledy.length === 0, bledy.join(' | ') || '0');
  await c.close();
}
console.log(`\n${ok} PASS, ${zle} FAIL`);
await b.close();
process.exit(zle ? 1 : 0);
