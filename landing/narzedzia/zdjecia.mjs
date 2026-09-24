// Zrzuty z prawdziwej wtyczki na lokalnym WordPressie.
// Każdy przycięty do tego, co ma pokazać — bez paska WordPressa i bocznego menu.
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';

const BAZA = 'http://127.0.0.1:8088';
const KAT = 'zrzuty';
const STAN = '../wp/wp-content/lstab-pokaz-stan.json';
fs.mkdirSync(KAT, { recursive: true });

const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const c = await b.newContext({ viewport: { width: 1500, height: 1200 }, deviceScaleFactor: 2 });
const p = await c.newPage();

const zrobione = [];

// Zrzut z marginesem wokół elementu — inaczej karty wyglądają jak ucięte.
async function kadr(nazwa, selektor, luz = 16) {
  const el = await p.locator(selektor).first();
  await el.scrollIntoViewIfNeeded();
  await p.waitForTimeout(250);
  const r = await el.boundingBox();
  const strona = await p.evaluate(() => ({ w: document.documentElement.clientWidth, h: document.documentElement.clientHeight }));
  const clip = {
    x: Math.max(0, r.x - luz),
    y: Math.max(0, r.y - luz),
    width: Math.min(strona.w - Math.max(0, r.x - luz), r.width + luz * 2),
    height: Math.min(strona.h - Math.max(0, r.y - luz), r.height + luz * 2),
  };
  await p.screenshot({ path: `${KAT}/${nazwa}.png`, clip });
  zrobione.push(`${nazwa}  ${Math.round(clip.width)}x${Math.round(clip.height)}`);
  console.log(`  \u2713 ${nazwa}  ${Math.round(clip.width)}x${Math.round(clip.height)}`);
}

function stan(o) {
  fs.writeFileSync(STAN, JSON.stringify({ jezyk: 'en', tryb: 'ok', karta: 'cennik', ...o }));
}

async function karta(tytul) {
  return p.locator('.lstab-card', { hasText: tytul }).first();
}

// ---- logowanie ----
await p.goto(`${BAZA}/wp-login.php`);
await p.fill('#user_login', 'admin');
await p.fill('#user_pass', 'admin123');
await p.click('#wp-submit');
await p.waitForLoadState('networkidle');

// =====================================================================
console.log('\n1. wklejasz link i widzisz tabelę zanim zapiszesz');
stan({});
await p.goto(`${BAZA}/wp-admin/admin.php?page=live-sheets-table-edit`);
await p.waitForTimeout(500);
await p.fill('#lstab-sheet-url', 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0');
await p.click('text=Load preview');
await p.waitForTimeout(2500);
await kadr('01-podglad-przed-zapisem', '.lstab-editor-grid', 10);

// podgląd sam w sobie, wąsko — pokazuje przełącznik szerokości
const szer = p.locator('button', { hasText: 'Phone' }).first();
if (await szer.count()) {
  await szer.click();
  await p.waitForTimeout(900);
  await kadr('02-podglad-telefon', '.lstab-editor-grid', 10);
}

// =====================================================================
console.log('\n2. arkusz z kilkoma kartami dostaje przełącznik');
const zakladki = p.locator('.lstab-tabs').first();
if (await zakladki.count()) {
  await kadr('03-karty-arkusza', '.lstab-card:has(.lstab-tabs)', 14);
} else {
  console.log('  (brak przełącznika kart na tym ekranie)');
}

// =====================================================================
console.log('\n3. wygląd i kolumny — edycja istniejącego źródła');
await p.goto(`${BAZA}/wp-admin/admin.php?page=live-sheets-table-edit&id=1`);
await p.waitForTimeout(1200);

const paski = p.locator('.lstab-pane-tab');
const ile = await paski.count();
console.log('  zakładek:', ile, await paski.allTextContents());

await kadr('04-ekran-edycji', '.lstab-editor-grid', 10);

if (ile > 1) {
  await paski.nth(1).click();
  await p.waitForTimeout(700);
  await kadr('05-wyglad', '.lstab-editor-grid', 10);
  const kolory = await karta('Fine-tune the look');
  if (await kolory.count()) { await kadr('06-kolory', '.lstab-card.lstab-appearance', 14); }
}

if (ile > 2) {
  await paski.nth(2).click();
  await p.waitForTimeout(700);
  await kadr('07-kolumny', '.lstab-editor-grid', 10);
}

// =====================================================================
console.log('\n4. karty z Pro');
await paski.nth(0).click();
await p.waitForTimeout(500);
for (const [nazwa, tytul] of [['08-pro-pobieranie', 'Downloads and printing']]) {
  const k = await karta(tytul);
  if (await k.count()) { await kadr(nazwa, `.lstab-card:has-text("${tytul}")`, 14); }
}

if (ile > 1) {
  await paski.nth(1).click();
  await p.waitForTimeout(600);
  for (const [nazwa, tytul] of [['09-pro-reguly', 'Colour rules'], ['10-pro-filtry', 'Let visitors narrow the table']]) {
    const k = await karta(tytul);
    if (await k.count()) { await kadr(nazwa, `.lstab-card:has-text("${tytul}")`, 14); }
  }
}

// =====================================================================
console.log('\n5. lista źródeł');
await p.goto(`${BAZA}/wp-admin/admin.php?page=live-sheets-table`);
await p.waitForTimeout(900);
await kadr('11-lista-zrodel', '#wpbody-content .lstab-admin', 10);

// =====================================================================
console.log('\n6. tabela na stronie');
await p.goto(`${BAZA}/pokaz/?szeroko=1`);
await p.waitForTimeout(1200);
const tabela = p.locator('.lstab-container, .lstab').first();
await kadr('12-tabela-na-stronie', '.lstab-container, .lstab', 14);

// szukanie z podświetleniem
const pole = p.locator('.lstab-search input, input[type=search]').first();
if (await pole.count()) {
  await pole.fill('stock');
  await p.waitForTimeout(900);
  await kadr('13-szukanie', '.lstab-container, .lstab', 14);
  await pole.fill('');
  await p.waitForTimeout(500);
}

// =====================================================================
console.log('\n7. telefon');
const t = await b.newContext({ viewport: { width: 420, height: 1000 }, deviceScaleFactor: 3 });
const pt = await t.newPage();
await pt.goto(`${BAZA}/pokaz/?szeroko=1`);
await pt.waitForTimeout(1200);
{
  const el = pt.locator('.lstab-container, .lstab').first();
  const r = await el.boundingBox();
  await pt.screenshot({ path: `${KAT}/14-telefon.png`, clip: { x: 0, y: Math.max(0, r.y - 10), width: 420, height: Math.min(980, r.height + 20) } });
  console.log('  \u2713 14-telefon  420x' + Math.round(Math.min(980, r.height + 20)));
  zrobione.push('14-telefon  420x' + Math.round(Math.min(980, r.height + 20)));
}
await t.close();

// =====================================================================
console.log('\n8. awaria po stronie Google');
stan({ tryb: 'awaria' });
await p.goto(`${BAZA}/wp-admin/admin.php?page=live-sheets-table`);
await p.waitForTimeout(500);
const odswiez = p.locator('button:has-text("Check now"), a:has-text("Check now"), button:has-text("Sync")').first();
if (await odswiez.count()) { await odswiez.click(); await p.waitForTimeout(2500); }
await kadr('15-awaria-panel', '#wpbody-content .lstab-admin', 10);

await p.goto(`${BAZA}/pokaz/?szeroko=1`);
await p.waitForTimeout(1200);
await kadr('16-awaria-strona', '.lstab-container, .lstab', 14);
stan({});

console.log('\n' + zrobione.length + ' zrzutów:\n' + zrobione.join('\n'));
await b.close();
