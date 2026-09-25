// Co wtyczka naprawdę robi z godzinami, datami i innymi "prawie liczbami".
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
const CSS = fs.readFileSync('/home/user/111/live-sheets-table/assets/css/lstab-table.css', 'utf8');
const JS_ = fs.readFileSync('/home/user/111/live-sheets-table/assets/js/lstab-table.js', 'utf8');

const zestawy = {
  'godziny 24h':        ['09:30', '17:45', '13:00', '10:45', '08:05'],
  'godziny bez zera':   ['9:30', '17:45', '13:00', '10:45', '8:05'],
  'godziny am/pm':      ['9:30 am', '5:45 pm', '1:00 pm', '10:45 am', '12:15 am'],
  'daty ISO':           ['2026-03-12', '2025-11-04', '2026-07-28', '2026-01-30', '2024-12-01'],
  'daty po polsku':     ['12.03.2026', '04.11.2025', '28.07.2026', '30.01.2026', '01.12.2024'],
  'czas trwania':       ['45 min', '2 h', '30 min', '1 h 20 min', '3 h'],
  'kwoty':              ['1 215,50', '89,90', '4 315,20', '612,50', '29,00'],
  'procenty':           ['3.2%', '-1.4%', '0.8%', '12%', '-0.5%'],
  'słowa z ogonkami':   ['Łódź', 'Aarhus', 'Zurych', 'Świnoujście', 'Cork'],
};

const wiersze = (lista) => lista.map(v =>
  `<tr class="lstab-row"><td data-label="Wartość"><span class="lstab-cell-label">Wartość</span><span class="lstab-cell-value">${v}</span></td></tr>`).join('');

const strona = Object.entries(zestawy).map(([nazwa, lista], i) => `
<h3>${nazwa}</h3>
<div class="lstab-container"><div class="lstab lstab-style-clean lstab-cols-1" id="t${i}">
<table class="lstab-table"><thead><tr><th data-lstab-col="0" data-lstab-align="start">
<button type="button" class="lstab-sort"><span class="lstab-sort-label">Wartość</span><span class="lstab-sort-icon"></span></button>
</th></tr></thead><tbody>${wiersze(lista)}</tbody></table></div></div>`).join('');

fs.writeFileSync('/tmp/sort.html', `<!doctype html><meta charset="utf-8"><style>${CSS}
body{font-family:system-ui;padding:20px;max-width:420px}h3{font-size:14px;margin:26px 0 6px}</style>
${strona}<script>${JS_}</script>`);

const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await (await b.newContext()).newPage();
await p.goto('file:///tmp/sort.html');
await p.waitForTimeout(500);
const nazwy = Object.keys(zestawy);
for (let i = 0; i < nazwy.length; i++) {
  await p.locator(`#t${i} .lstab-sort`).click();
  await p.waitForTimeout(120);
  const po = await p.evaluate((i) => [...document.querySelectorAll(`#t${i} tbody .lstab-cell-value`)].map(e => e.textContent), i);
  console.log(nazwy[i].padEnd(18), '→', po.join('  '));
}
await b.close();
