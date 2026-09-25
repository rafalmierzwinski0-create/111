// Czy w podglądzie naprawdę działa sortowanie i szukanie — klikamy jak człowiek.
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const c = await b.newContext({ viewport: { width: 1400, height: 1100 } });
const p = await c.newPage();
const bledy = []; p.on('pageerror', e => bledy.push(e.message));
await p.goto('file://' + process.cwd() + '/tabela-ladna.html');
await p.waitForTimeout(900);
const kolumna = (i) => p.evaluate((i) => [...document.querySelectorAll('.lstab-table')][0]
  .querySelectorAll('tbody tr')
  .values().toArray().map(r => { const c = r.children[i], v = c.querySelector('.lstab-cell-value');
    return (v ? v.textContent : c.textContent).trim(); }), i);

console.log('przed        ', (await kolumna(1)).join(' '));
await p.locator('.lstab-table thead th').nth(1).locator('button').first().click();
await p.waitForTimeout(250);
console.log('po kliknięciu', (await kolumna(1)).join(' '));
await p.locator('.lstab-table thead th').nth(1).locator('button').first().click();
await p.waitForTimeout(250);
console.log('drugi klik   ', (await kolumna(1)).join(' '));

await p.locator('.lstab-table thead th').nth(3).locator('button').first().click();
await p.waitForTimeout(250);
console.log('miejsca rosnąco', (await kolumna(3)).join(' '));

await p.locator('.lstab-search-input').first().fill('studio');
await p.waitForTimeout(350);
console.log('szukanie "studio":', await p.evaluate(() => ({
  widoczne: [...document.querySelectorAll('.lstab-table')][0].querySelectorAll('tbody tr:not([hidden])').length,
  licznik: document.querySelector('.lstab-count').textContent.trim(),
  podswietlone: document.querySelectorAll('.lstab-hit').length })));
await p.locator('.lstab-search-input').first().fill('status');
await p.waitForTimeout(350);
console.log('szukanie "status":', await p.evaluate(() => ({
  widoczne: [...document.querySelectorAll('.lstab-table')][0].querySelectorAll('tbody tr:not([hidden])').length,
  licznik: document.querySelector('.lstab-count').textContent.trim() })));
console.log('błędy:', bledy.length ? bledy : 'brak');
await b.close();
