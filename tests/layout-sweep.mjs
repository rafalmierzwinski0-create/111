/**
 * Every style, in every layout, at phone and desk widths: nothing may spill.
 *
 * Six shapes of sheet (three columns, fourteen, long words and addresses,
 * columns sent to a drawer, a single column, mostly empty cells), each with
 * the first column and headings pinned and not, drawn in all ten styles as a
 * table, as cards and as "cards when it stops fitting". On each page, for
 * every table, the browser is asked:
 *
 *   - does anything push the page wider than the screen;
 *   - does the table, its search box, pager or slider leave its own column;
 *   - do the headings stand over their columns;
 *   - is any cell squeezed until its words stand one letter wide, or does
 *     text run out of its cell;
 *   - does a card stay inside the table;
 *   - and, scrolled sideways, does the pinned column stay at the edge and
 *     stay on top.
 *
 * Three passes: the default theme at a phone and a desk width; the same
 * pages under the rules aggressive themes and page builders ship for tables,
 * buttons and fields; and right to left.
 *
 * Usage: node tests/layout-sweep.mjs   (servers from tests/setup-env.sh; the
 * add-on must be active, as the styles are premium)
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const SITE = '/tmp/lstab-env/wp71';
const MU = `${ SITE }/wp-content/mu-plugins/lstab-layout-sweep.php`;

execFileSync( 'php', [ path.join( here, 'harness/layout-sweep-setup.php' ), SITE, 'teardown' ] );
execFileSync( 'php', [ path.join( here, 'harness/layout-sweep-setup.php' ), SITE ] );
fs.copyFileSync( path.join( here, 'harness/layout-sweep-mu.php' ), MU );

const m = JSON.parse( fs.readFileSync( '/tmp/lstab-env/layout-sweep.json', 'utf8' ) );
const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

const inspect = () => {
  const out = [];
  const vw = document.documentElement.clientWidth;
  if (document.documentElement.scrollWidth > vw + 1) {
    const culprits = [...document.querySelectorAll('body *')].filter(e => e.getBoundingClientRect().right > vw + 1 && getComputedStyle(e).position !== 'fixed').slice(0, 3).map(e => e.className || e.tagName);
    out.push({ table: '-', kind: 'page-overflow', detail: `${document.documentElement.scrollWidth} > ${vw} :: ${culprits.join(' | ')}` });
  }
  const boxes = [...document.querySelectorAll('.lstab-container')];
  boxes.forEach((box, n) => {
    const root = box.querySelector('.lstab');
    if (!root) return;
    const skin = (root.className.match(/lstab-style-([a-z]+)/) || [])[1];
    const tag = `#${n} ${skin}`;
    const bb = box.getBoundingClientRect();
    const parent = box.parentElement.getBoundingClientRect();
    if (bb.right > parent.right + 1 || bb.left < parent.left - 1) out.push({ table: tag, kind: 'container-outside-parent', detail: `${bb.left.toFixed(0)}-${bb.right.toFixed(0)} vs ${parent.left.toFixed(0)}-${parent.right.toFixed(0)}` });
    const rb = root.getBoundingClientRect();
    [...root.querySelectorAll('.lstab-controls > *, .lstab-pager, .lstab-meta, .lstabp-facets, .lstab-scrollbar:not([hidden])')].forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.width && (r.right > rb.right + 1 || r.left < rb.left - 1)) out.push({ table: tag, kind: 'control-outside', detail: (el.className || el.tagName) + ` ${r.left.toFixed(0)}-${r.right.toFixed(0)} vs ${rb.left.toFixed(0)}-${rb.right.toFixed(0)}` });
    });
    const table = root.querySelector('.lstab-table');
    const scroller = root.querySelector('.lstab-scroll');
    if (!table || !scroller) return;
    const asTable = getComputedStyle(table).display === 'table';
    const ths = [...table.querySelectorAll('thead th')];
    const firstRow = table.querySelector('tbody tr.lstab-row:not([hidden])');
    const tds = firstRow ? [...firstRow.children] : [];
    if (asTable && firstRow) {
      if (ths.length !== tds.length) out.push({ table: tag, kind: 'column-count-mismatch', detail: `${ths.length} th vs ${tds.length} td` });
      ths.forEach((th, i) => {
        const a = th.getBoundingClientRect(), c = tds[i] && tds[i].getBoundingClientRect();
        if (c && (Math.abs(a.left - c.left) > 1.5 || Math.abs(a.width - c.width) > 1.5)) out.push({ table: tag, kind: 'header-misaligned', detail: `col ${i}: th ${a.left.toFixed(1)}/${a.width.toFixed(1)} td ${c.left.toFixed(1)}/${c.width.toFixed(1)}` });
      });
    }
    // squeezed cells and text bleeding out of cells
    [...table.querySelectorAll('tbody tr.lstab-row:not([hidden]) > td, thead th')].slice(0, 400).forEach(cell => {
      const text = (cell.textContent || '').trim();
      const w = cell.clientWidth;
      if (asTable && text.length > 3 && w > 0 && w < 34 && !cell.querySelector('.lstab-open')) out.push({ table: tag, kind: 'squeezed-cell', detail: `${w}px "${text.slice(0, 20)}"` });
      const value = cell.querySelector('.lstab-cell-value, .lstab-sort-label') || cell;
      if (value.scrollWidth > value.clientWidth + 2 && getComputedStyle(value).overflowX === 'visible' && value.clientWidth > 0) out.push({ table: tag, kind: 'text-bleeds', detail: `${value.scrollWidth}>${value.clientWidth} "${text.slice(0, 30)}"` });
      const cr = cell.getBoundingClientRect();
      if (cr.height > 600) out.push({ table: tag, kind: 'tall-cell', detail: `${cr.height.toFixed(0)}px "${text.slice(0, 20)}"` });
    });
    // cards: nothing wider than the card
    if (!asTable) {
      [...table.querySelectorAll('tbody tr.lstab-row')].slice(0, 6).forEach(tr => {
        const r = tr.getBoundingClientRect();
        if (r.right > rb.right + 1.5) out.push({ table: tag, kind: 'card-overflow', detail: `${r.right.toFixed(0)} > ${rb.right.toFixed(0)}` });
        [...tr.children].forEach(td => { const q = td.getBoundingClientRect(); if (q.right > r.right + 1.5) out.push({ table: tag, kind: 'card-cell-overflow', detail: `${q.right.toFixed(0)} > ${r.right.toFixed(0)} "${td.textContent.trim().slice(0, 20)}"` }); });
      });
    }
    // pinned column behaviour when scrolled sideways
    const overflowX = scroller.scrollWidth - scroller.clientWidth;
    if (asTable && root.classList.contains('lstab-sticky-first') && overflowX > 4 && firstRow) {
      const sr = scroller.getBoundingClientRect();
      const rtl = getComputedStyle(scroller).direction === 'rtl';
      scroller.scrollLeft = rtl ? -overflowX : overflowX;
      const pin = firstRow.children[0].getBoundingClientRect();
      const thPin = ths[0] && ths[0].getBoundingClientRect();
      const edge = rtl ? Math.abs(pin.right - sr.right) : Math.abs(pin.left - sr.left);
      if (edge > 1.5) out.push({ table: tag, kind: 'pin-not-pinned', detail: `td ${pin.left.toFixed(1)}-${pin.right.toFixed(1)} vs scroller ${sr.left.toFixed(1)}-${sr.right.toFixed(1)}` });
      if (thPin && Math.abs(thPin.left - pin.left) > 1.5) out.push({ table: tag, kind: 'pin-head-misaligned', detail: `th ${thPin.left.toFixed(1)} td ${pin.left.toFixed(1)}` });
      if (pin.width > sr.width * 0.66) out.push({ table: tag, kind: 'pin-too-wide', detail: `${pin.width.toFixed(0)} of ${sr.width.toFixed(0)}` });
      const y = pin.top + pin.height / 2, x = pin.left + Math.min(pin.width - 4, 12);
      const hit = document.elementFromPoint(x, y);
      if (hit && !firstRow.children[0].contains(hit) && y > 0 && y < innerHeight) out.push({ table: tag, kind: 'pin-covered', detail: `hit ${hit.className || hit.tagName}` });
      scroller.scrollLeft = 0;
    }
    if (asTable && overflowX > 4) {
      const bar = root.querySelector('.lstab-scrollbar');
      if (bar && bar.hidden && !root.classList.contains('lstab-layout-cards')) out.push({ table: tag, kind: 'no-slider-when-scrollable', detail: `${overflowX}px hidden` });
    }

  });
  return { out, tables: boxes.length };
};


const passes = [
	{ label: 'default theme', query: '', widths: [ 360, 1024 ] },
	{ label: 'aggressive theme rules', query: '?lstab-hostile=1', widths: [ 390 ] },
	{ label: 'right to left', query: '?lstab-rtl=1', widths: [ 390 ] },
];

let tables = 0;
let failed = 0;

for ( const pass of passes ) {
	const issues = [];

	for ( const width of pass.widths ) {
		const ctx = await b.newContext( { viewport: { width, height: 900 }, deviceScaleFactor: 1 } );
		const p = await ctx.newPage();
		const errs = [];
		p.on( 'pageerror', ( e ) => errs.push( e.message ) );

		for ( const pg of m.pages ) {
			await p.goto( pg.url + pass.query, { waitUntil: 'load' } );
			await p.waitForTimeout( 200 );
			const { out, tables: n } = await p.evaluate( inspect );
			tables += n;
			// The slider floats over the rows at the foot of the screen on purpose.
			out.filter( ( o ) => ! ( 'pin-covered' === o.kind && /lstab-scrollbar/.test( o.detail ) ) )
				.forEach( ( o ) => issues.push( `${ width }px ${ pg.shape }/${ pg.pin }/${ pg.layout } ${ o.table }: ${ o.kind } ${ o.detail }` ) );
		}

		if ( errs.length ) {
			issues.push( `${ width }px: script errors ${ [ ...new Set( errs ) ].join( ' | ' ) }` );
		}

		await ctx.close();
	}

	if ( issues.length ) {
		failed += issues.length;
		console.log( `  \x1b[31mFAIL\x1b[0m  ${ pass.label }: ${ issues.length } problems` );
		issues.slice( 0, 12 ).forEach( ( line ) => console.log( `        ${ line }` ) );
	} else {
		console.log( `  \x1b[32mPASS\x1b[0m  ${ pass.label }: nothing spills, misaligns or uncovers at ${ pass.widths.join( ' and ' ) }px` );
	}
}

await b.close();
fs.unlinkSync( MU );
execFileSync( 'php', [ path.join( here, 'harness/layout-sweep-setup.php' ), SITE, 'teardown' ] );

console.log( `\n  ${ tables } tables looked at, ${ failed } problems` );
process.exit( failed ? 1 : 0 );
