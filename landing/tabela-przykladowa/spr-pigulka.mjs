// Pigułka jest ozdobą wartości — sprawdzamy, że da się ją przeczytać i że
// nie zmienia tekstu, po którym wtyczka sortuje, szuka i eksportuje.
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const c = await b.newContext({ viewport: { width: 1400, height: 1100 } });
const p = await c.newPage();
await p.goto('file://' + process.cwd() + '/tabela-ladna.html');
await p.waitForTimeout(1500);
const r = await p.evaluate(() => {
  const lum = (s) => { const skala = s.startsWith('color(') ? 1 : 255;
    const [r, g, b] = s.match(/[\d.]+/g).slice(0, 3).map(Number)
      .map(v => { v /= skala; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
  const kontrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((m, n) => n - m); return +((x + 0.05) / (y + 0.05)).toFixed(2); };
  const td = document.querySelector('.lstabp-pill');
  const val = td.querySelector('.lstab-cell-value');
  const st = getComputedStyle(val);
  const tlo = getComputedStyle(td.closest('tr')).backgroundColor !== 'rgba(0, 0, 0, 0)'
    ? getComputedStyle(td.closest('tr')).backgroundColor
    : getComputedStyle(document.querySelector('.lstab')).backgroundColor;
  return {
    tekst: val.textContent,                        // to, co widzi sortowanie i eksport
    narysowane: st.textTransform,
    ramka: st.borderTopColor, wypelnienie: st.backgroundColor, tusz: st.color,
    ksztalt: st.borderRadius, tlo,
    kontrastTuszu: kontrast(st.color, tlo),
    ile: document.querySelectorAll('.lstabp-pill').length,
  };
});
console.log(r);
// sortowanie i szukanie czytają .lstab-cell-value, więc pigułka ich nie dotyka
const sort = await p.evaluate(() => {
  const val = (row, i) => { const c = row.children[i]; const v = c.querySelector('.lstab-cell-value');
    return (v ? v.textContent : c.textContent).trim(); };
  const rows = [...document.querySelectorAll('.lstab-table tbody tr')];
  return { statusy: rows.map(r => val(r, 4)), miejsca: rows.map(r => val(r, 3)) };
});
console.log('status wg tekstu:', sort.statusy.join(', '));
console.log('miejsca wg tekstu:', sort.miejsca.join(', '));
await b.close();
