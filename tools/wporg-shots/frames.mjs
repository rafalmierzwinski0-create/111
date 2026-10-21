/**
 * The frames round the screenshots: a browser window for the desktop shots,
 * a phone with its status bar and Safari's address bar for the phone shots,
 * and the card grid for the four styles. Each frame is a small HTML page drawn
 * by the same browser and saved as the final picture, so the screenshots
 * inside stay pixel for pixel what the plugin drew.
 */
import fs from 'fs';
import path from 'path';

const FONTS = '/tmp/lstab-env/wpshots/wp-content/themes/twentytwentyfive/assets/fonts';

const head = ( background ) => `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face { font-family: Manrope; font-weight: 200 800; src: url("file://${ FONTS }/manrope/Manrope-VariableFont_wght.woff2"); }
* { box-sizing: border-box; margin: 0; }
html, body { width: 100%; height: 100%; }
body { font-family: Manrope, sans-serif; background: ${ background }; overflow: hidden; position: relative; -webkit-font-smoothing: antialiased; }
img { display: block; }
.win { position: absolute; border-radius: 22px; overflow: hidden; background: #fff; box-shadow: 0 60px 140px -40px rgba(12, 20, 32, 0.5), 0 18px 40px -20px rgba(12, 20, 32, 0.25), 0 0 0 1px rgba(12, 20, 32, 0.08); }
.bar { height: 72px; background: linear-gradient(#f7f7f8, #eff0f2); border-bottom: 1px solid #e3e5e8; display: flex; align-items: center; padding: 0 28px; }
.dots { display: flex; gap: 12px; width: 180px; }
.dots i { width: 17px; height: 17px; border-radius: 50%; display: block; }
.url { flex: 0 1 760px; margin: 0 auto; height: 44px; border-radius: 12px; background: #fff; border: 1px solid #e1e4e8; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 19px; font-weight: 500; color: #3f4650; }
.url svg { width: 16px; height: 16px; }
.bar .end { width: 180px; display: flex; justify-content: flex-end; gap: 22px; color: #9aa1ab; }
.bar .end svg { width: 22px; height: 22px; }
.phone { position: absolute; border-radius: 108px; padding: 7px; background: linear-gradient(140deg, #d9dbde 0%, #8e9196 22%, #f2f3f4 48%, #7d8085 74%, #c9cbcf 100%); box-shadow: 0 80px 140px -40px rgba(10, 18, 28, 0.55), 0 30px 60px -30px rgba(10, 18, 28, 0.4); }
.phone .body { border-radius: 101px; padding: 16px; background: #050506; }
.phone .screen { position: relative; border-radius: 86px; overflow: hidden; background: #fff; }
.phone .btn { position: absolute; width: 7px; border-radius: 4px; background: linear-gradient(90deg, #7d8085, #d4d6d9, #8e9196); }
.island { position: absolute; left: 50%; top: 17px; width: 194px; height: 57px; margin-left: -97px; border-radius: 40px; background: #000; z-index: 3; }
.status { position: absolute; left: 0; right: 0; top: 0; height: 72px; display: flex; align-items: center; justify-content: space-between; padding: 6px 58px 0 66px; font-weight: 700; font-size: 26px; letter-spacing: -0.01em; z-index: 2; }
.status .icons { display: flex; gap: 10px; align-items: center; }
.safari { position: absolute; left: 0; right: 0; bottom: 0; height: 114px; padding: 14px 22px 0; background: rgba(246, 246, 247, 0.96); border-top: 1px solid rgba(0, 0, 0, 0.06); z-index: 2; }
.safari .pill { height: 58px; border-radius: 18px; background: #fff; box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 24px; font-weight: 600; color: #1c1c1e; }
.safari .pill .aa { position: absolute; left: 48px; font-size: 22px; color: #1c1c1e; }
.home { position: absolute; left: 50%; bottom: 10px; width: 206px; height: 8px; margin-left: -103px; border-radius: 8px; background: #111; z-index: 4; }
.card { position: absolute; background: #fff; border-radius: 30px; padding: 40px 44px 44px; box-shadow: 0 40px 90px -40px rgba(12, 20, 32, 0.35), 0 0 0 1px rgba(12, 20, 32, 0.06); }
.card h3 { font-size: 30px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 14px; }
.card h3 span { font-size: 15px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #0f766e; background: #e6f4f1; padding: 6px 12px; border-radius: 999px; }
.card p { font-size: 20px; color: #5b6472; margin: 8px 0 26px; }
</style></head><body>`;

const lock = '<svg viewBox="0 0 16 16" fill="#6b7280"><path d="M4 7V5a4 4 0 1 1 8 0v2h.5A1.5 1.5 0 0 1 14 8.5v5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5v-5A1.5 1.5 0 0 1 3.5 7H4Zm1.6 0h4.8V5a2.4 2.4 0 1 0-4.8 0v2Z"/></svg>';
const chromeEnd = '<div class="end"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="11" height="11" rx="2"/><path d="M9 20h9a2 2 0 0 0 2-2V9"/></svg></div>';

/**
 * A browser window holding a desktop screenshot, on a background. The window
 * may run off the bottom of the picture, as if the page went on.
 */
export const browserWindow = ( { image, url, background, width = 2560, height = 1600, top = 120, side = 160 } ) => {
	const w = width - side * 2;
	return {
		width,
		height,
		html: `${ head( background ) }
<div class="win" style="left:${ side }px;top:${ top }px;width:${ w }px">
	<div class="bar"><div class="dots"><i style="background:#ff5f57"></i><i style="background:#febc2e"></i><i style="background:#28c840"></i></div><div class="url">${ lock }${ url }</div>${ chromeEnd }</div>
	<img src="file://${ image }" style="width:${ w }px">
</div></body></html>`,
	};
};

const statusIcons = ( ink ) => `<span class="icons">
<svg width="34" height="22" viewBox="0 0 17 11" fill="${ ink }"><rect x="0" y="7" width="3" height="4" rx="0.8"/><rect x="4.5" y="5" width="3" height="6" rx="0.8"/><rect x="9" y="2.5" width="3" height="8.5" rx="0.8"/><rect x="13.5" y="0" width="3" height="11" rx="0.8"/></svg>
<svg width="31" height="22" viewBox="0 0 16 11.5" fill="${ ink }"><path d="M8 2.3c2.2 0 4.2.9 5.7 2.3l1.1-1.1A9.6 9.6 0 0 0 8 .7 9.6 9.6 0 0 0 1.2 3.5l1.1 1.1A8 8 0 0 1 8 2.3Zm0 3.2c1.3 0 2.5.5 3.4 1.3l1.1-1.1A6.4 6.4 0 0 0 8 3.9a6.4 6.4 0 0 0-4.5 1.8l1.1 1.1c.9-.8 2.1-1.3 3.4-1.3Zm0 3.2c.5 0 .9.2 1.2.5L8 10.4 6.8 9.2c.3-.3.7-.5 1.2-.5Z"/></svg>
<svg width="52" height="24" viewBox="0 0 27 12.5"><rect x="0.6" y="0.6" width="22.8" height="11.3" rx="3.4" fill="none" stroke="${ ink }" stroke-opacity="0.4" stroke-width="1.1"/><rect x="2.2" y="2.2" width="17" height="8.1" rx="2" fill="${ ink }"/><path d="M25 4.2v4.1c.8-.3 1.4-1.1 1.4-2s-.6-1.8-1.4-2.1Z" fill="${ ink }" fill-opacity="0.45"/></svg>
</span>`;

/**
 * A phone: the screenshot sits between the status bar and Safari's address
 * bar, as a page does on an iPhone. `screen` is the width of the glass.
 */
const phone = ( { image, url, x, y, screen = 600, statusBg = '#ffffff', ink = '#111111' } ) => {
	const scale = screen / 390;
	const tall = Math.round( 844 * scale );
	const top = Math.round( 47 * scale );
	const bottom = 114;
	return `<div class="phone" style="left:${ x }px;top:${ y }px">
	<i class="btn" style="left:-4px;top:250px;height:70px"></i>
	<i class="btn" style="left:-4px;top:360px;height:120px"></i>
	<i class="btn" style="left:-4px;top:500px;height:120px"></i>
	<i class="btn" style="right:-4px;top:400px;height:190px"></i>
	<div class="body"><div class="screen" style="width:${ screen }px;height:${ tall }px">
		<div class="island"></div>
		<div class="status" style="background:${ statusBg };color:${ ink }"><span>9:41</span>${ statusIcons( ink ) }</div>
		<img src="file://${ image }" style="position:absolute;left:0;top:${ top }px;width:${ screen }px;height:${ tall - top - bottom }px;object-fit:cover;object-position:top">
		<div class="safari"><div class="pill"><span class="aa">AA</span>${ lock.replace( 'fill="#6b7280"', 'fill="#1c1c1e" width="18" height="18"' ) }${ url }</div></div>
		<i class="home"></i>
	</div></div>
</div>`;
};

// The page height a phone shows between its status bar and Safari's bar.
export const phoneViewport = ( screen = 600 ) => Math.round( ( 844 * screen / 390 - 47 * screen / 390 - 114 ) / ( screen / 390 ) );

export const twoPhones = ( { left, right, background, width = 2560, height = 1800 } ) => {
	const screen = 600;
	const outer = screen + 2 * 16 + 2 * 7;
	const gap = 150;
	const x = ( width - outer * 2 - gap ) / 2;
	const tallest = Math.round( 844 * screen / 390 ) + 46;
	const y = Math.round( ( height - tallest ) / 2 );
	return {
		width,
		height,
		html: `${ head( background ) }${ phone( { ...left, x, y, screen } ) }${ phone( { ...right, x: x + outer + gap, y, screen } ) }</body></html>`,
	};
};

/**
 * Four cards, one per style, each with the style's name, a line about it and
 * the table it drew.
 */
export const styleGrid = ( { cards, background, width = 2560 } ) => {
	const side = 120;
	const gap = 64;
	const cardWidth = ( width - side * 2 - gap ) / 2;
	const inner = cardWidth - 88;
	const heights = cards.map( ( c ) => 40 + 44 + 30 + 8 + 30 + 26 + Math.round( c.height * inner / c.width ) );
	const rowOne = Math.max( heights[ 0 ], heights[ 1 ] );
	const rowTwo = Math.max( heights[ 2 ], heights[ 3 ] );
	const height = 110 + rowOne + gap + rowTwo + 110;
	const place = ( c, i ) => {
		const x = side + ( i % 2 ) * ( cardWidth + gap );
		const y = 110 + ( i < 2 ? 0 : rowOne + gap );
		const h = i < 2 ? rowOne : rowTwo;
		return `<div class="card" style="left:${ x }px;top:${ y }px;width:${ cardWidth }px;height:${ h }px"><h3>${ c.name }<span>${ c.tag }</span></h3><p>${ c.line }</p><img src="file://${ c.image }" style="width:${ inner }px"></div>`;
	};
	return { width, height, html: `${ head( background ) }${ cards.map( place ).join( '' ) }</body></html>` };
};

/**
 * Draws a frame and saves it as a PNG.
 */
export const render = async ( page, frame, out, work ) => {
	const file = path.join( work, `frame-${ path.basename( out, '.png' ) }.html` );
	fs.writeFileSync( file, frame.html );
	await page.setViewportSize( { width: frame.width, height: frame.height } );
	await page.goto( `file://${ file }` );
	await page.evaluate( () => document.fonts.ready );
	await page.waitForFunction( () => [ ...document.images ].every( ( i ) => i.complete && i.naturalWidth > 0 ) );
	await page.screenshot( { path: out } );
};
