// Sprawdzenie strony pokazowej: czy się rysuje, czy nic nie wystaje w bok,
// czy sortowanie i szukanie z prawdziwego skryptu wtyczki działa, i czy tekst
// jest czytelny na każdym z trzech teł.
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';

/*
 * Strona ma własne <!doctype html> i to jest tu sprawdzane, a nie doklejane.
 * Bez doctype przeglądarka wchodzi w tryb zgodności ze starociami, w którym
 * <table> nie dziedziczy koloru tekstu po pudełku, w którym stoi — bierze go
 * z <body>. Na tej stronie znaczyło to bladą miętę ze strony w każdej komórce
 * każdej tabeli, więc na jasnym papierze napisu prawie nie było widać.
 * Sprawdzenie to zamiatało wcześniej pod dywan, doklejając doctype samo z
 * siebie: plik był zepsuty, a sprawdzenie zielone.
 */
const zrodlo = fs.readFileSync('/home/user/111/landing/szlaki/SZLAKI.html', 'utf8');

if ( ! /^<!doctype html>/i.test( zrodlo ) ) {
	console.error( 'BŁĄD: SZLAKI.html nie zaczyna się od <!doctype html> — tabele stracą kolor tekstu' );
	process.exit( 1 );
}
const plik = 'file:///home/user/111/landing/szlaki/';
const b = await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p = await b.newPage({viewport:{width:1280,height:1000}, deviceScaleFactor:2});
const errs=[]; p.on('pageerror',e=>errs.push(e.message));
await p.goto(plik); await p.setContent(zrodlo,{waitUntil:'load'});
const stan = await p.evaluate(()=>({
  tabel: document.querySelectorAll('.lstab').length,
  szer: document.documentElement.scrollWidth <= window.innerWidth + 1,
  wystaje: [...document.querySelectorAll('*')].filter(el=>el.getBoundingClientRect().right > window.innerWidth+1).map(el=>el.className.toString().slice(0,40)).slice(0,5),
}));
console.log('tabel', stan.tabel, 'bez przewijania w bok:', stan.szer, stan.wystaje);
// sortowanie w pierwszej tabeli
const pierwsza = '.lstab:first-of-type';
const przed = await p.$$eval('.gora .lstab tbody tr.lstab-row td:first-child .lstab-cell-value', n=>n.map(x=>x.textContent.trim()));
await p.click('.gora .lstab thead th:nth-child(3) .lstab-sort');
await p.waitForTimeout(200);
const po = await p.$$eval('.gora .lstab tbody tr.lstab-row td:nth-child(3) .lstab-cell-value', n=>n.map(x=>x.textContent.trim()));
console.log('śnieg po kliknięciu:', po.join(' '));
// szukanie
await p.fill('.gora .lstab .lstab-search-input', 'zawrat');
await p.waitForTimeout(250);
const widoczne = await p.$$eval('.gora .lstab tbody tr.lstab-row', n=>n.filter(x=>!x.hidden).length);
console.log('po szukaniu widocznych wierszy:', widoczne);
await p.fill('.gora .lstab .lstab-search-input', '');
await p.waitForTimeout(150);
// kontrast
const kontrast = await p.evaluate(()=>{
  // color-mix() wraca jako "color(srgb 0.28 0.55 0.5)" — składowe od zera do
  // jedynki, a nie od zera do 255 — a tusz pigułki to właśnie color-mix().
  // Czytnik napisany pod samo rgb() zwracał tu null i cicho pomijał dokładnie
  // to, co miało być zmierzone: słowo w plakietce.
  const parse=(c)=>{c=(c||'').trim();const m=c.match(/^(?:rgba?|color)\(([^)]+)\)/);if(!m)return null;const skala=c.startsWith('color(')?255:1;const a=m[1].replace(/^srgb\s+/,'').split(/[\s,\/]+/).filter(Boolean).map(Number);return {r:a[0]*skala,g:a[1]*skala,b:a[2]*skala,a:a[3]===undefined?1:a[3]};};
  const over=(t,b)=>({r:t.r*t.a+b.r*(1-t.a),g:t.g*t.a+b.g*(1-t.a),b:t.b*t.a+b.b*(1-t.a),a:1});
  const behind=(el)=>{let n=el;const L=[];while(n){const s=getComputedStyle(n);const c=parse(s.backgroundColor);const img=s.backgroundImage;
    if(img&&img!=='none'){const st=[...img.matchAll(/rgba?\([^)]+\)/g)].map(x=>parse(x[0])).filter(x=>x.a>0.9);if(st.length){L.push(st[Math.floor(st.length/2)]);break;}}
    if(c&&c.a>0){L.push(c); if(c.a>=0.999) break;} n=n.parentElement;}
   let o=L[L.length-1]||{r:255,g:255,b:255,a:1}; if(o.a<0.999)o=over(o,{r:255,g:255,b:255,a:1});
   for(let i=L.length-2;i>=0;i--)o=over(L[i],o); return o;};
  const lum=(c)=>{const f=(v)=>{const s=v/255;return s<=0.03928?s/12.92:Math.pow((s+0.055)/1.055,2.4)};return 0.2126*f(c.r)+0.7152*f(c.g)+0.0722*f(c.b)};
  const ratio=(el)=>{const ink=parse(getComputedStyle(el).color);if(!ink)return null;const pap=behind(el);const f=ink.a>=0.999?ink:over(ink,pap);const a=lum(f),b=lum(pap);return Math.round(((Math.max(a,b)+0.05)/(Math.min(a,b)+0.05))*100)/100;};
  const out={};
  document.querySelectorAll('h1,h2,.wstep,.opis,.metka,.etykieta,.co-robi dd,.koniec').forEach((el,i)=>{
    const r=ratio(el); if(r===null)return; const k=el.tagName+'.'+(el.className||'').split(' ')[0];
    if(!out[k]||r<out[k]) out[k]=r;
  });
  document.querySelectorAll('.lstab tbody .lstab-cell-value').forEach((el)=>{
    const wrap=el.closest('.lstab'); const k='tabela:'+[...wrap.classList].find(c=>c.startsWith('lstab-style-'));
    const r=ratio(el); if(r===null)return; if(!out[k]||r<out[k]) out[k]=r;
  });
  return out;
});
console.log(JSON.stringify(kontrast,null,1));
// Nie sam wydruk: najsłabszy napis na stronie musi trzymać 4,5 : 1. Wcześniej
// to był wydruk i nic więcej, więc pigułki po 3 : 1 przejeżdżały na zielono.
const najslabszy = Object.entries(kontrast).sort((a,b)=>a[1]-b[1])[0];
console.log('najsłabszy napis:', najslabszy[0], najslabszy[1]);
if (najslabszy[1] < 4.5) { console.error('BŁĄD: napis pod progiem czytelności'); process.exitCode = 1; }
// Tusz w komórkach musi być tuszem szablonu, a nie kolorem strony: to jest
// dokładnie to, co gubił tryb zgodności.
const tusz = await p.evaluate(()=>{
  const out={};
  document.querySelectorAll('.lstab').forEach((wrap)=>{
    const k=[...wrap.classList].find(c=>c.startsWith('lstab-style-'))||'?';
    const td=wrap.querySelector('tbody td'); if(!td) return;
    const chce=getComputedStyle(wrap).getPropertyValue('--lstab-fg').trim();
    out[k]={ma:getComputedStyle(td).color, chce};
  });
  return out;
});
const rozne = Object.entries(tusz).filter(([,v])=>v.ma==='rgb(233, 244, 241)');
console.log('tusz komórek bierze się z szablonu:', rozne.length===0 ? 'tak' : JSON.stringify(rozne));
if (rozne.length) { console.error('BŁĄD: tabele wzięły kolor tekstu ze strony, a nie z szablonu'); process.exitCode = 1; }

console.log('błędy skryptu:', errs.length, errs.slice(0,2));
const m = await b.newPage({viewport:{width:390,height:900}});
await m.goto(plik); await m.setContent(zrodlo,{waitUntil:'load'});
console.log('telefon bez przewijania w bok:', await m.evaluate(()=>document.documentElement.scrollWidth <= window.innerWidth+1));
await b.close();
