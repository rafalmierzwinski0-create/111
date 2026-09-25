import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const c = await b.newContext({ viewport: { width: 500, height: 1200 } });
const p = await c.newPage();
await p.goto('file://' + process.cwd() + '/tabela-ladna.html');
await p.waitForTimeout(1500);
const r = await p.evaluate(() => {
  // "color(srgb 0.84 0.78 0.61)" podaje składowe w zakresie 0-1, "rgb()" w 0-255
  const lum = (s) => { const skala = s.startsWith('color(') ? 1 : 255;
    const [r, g, b] = s.match(/[\d.]+/g).slice(s.startsWith('color(') ? 0 : 0, 3).map(Number)
      .map(v => { v /= skala; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
  const kontrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((m, n) => n - m); return ((x + 0.05) / (y + 0.05)).toFixed(2); };
  const out = [];
  for (const tr of document.querySelectorAll('.waska .lstab-row')) {
    const td = tr.querySelector('td');
    const tlo = getComputedStyle(td).backgroundColor !== 'rgba(0, 0, 0, 0)' ? getComputedStyle(td).backgroundColor : getComputedStyle(tr).backgroundColor;
    out.push({ wiersz: tr.querySelector('.lstab-cell-value').textContent.slice(0, 22),
      tlo, etykieta: getComputedStyle(tr.querySelector('.lstab-cell-label')).color,
      wartosc: getComputedStyle(tr.querySelector('.lstab-cell-value')).color,
      kEtykieta: kontrast(getComputedStyle(tr.querySelector('.lstab-cell-label')).color, tlo),
      kWartosc: kontrast(getComputedStyle(tr.querySelector('.lstab-cell-value')).color, tlo) });
  }
  return out;
});
console.table(r);
await b.close();
