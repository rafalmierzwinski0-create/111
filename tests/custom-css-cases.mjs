/**
 * The cases tests/custom-css-browser.mjs puts a table's own CSS through.
 *
 * Each is [ name, css, check, options ]. The check runs in the page, where T()
 * is the Opening hours table (Bordered, first column pinned), cell( label, row )
 * one of its cells, cs() a computed value, and HOURS and OTHER the two tables'
 * ids. It answers [ passed, what was seen ]. The colour asked for is almost
 * always rgb(1, 2, 3), which nothing in any skin happens to be.
 *
 * Options: media (emulated media features), width (viewport), php (the last
 * column moves into the details row for that case).
 */

export const cases = [
	// ---------- selectors ----------
	[ 'attr ^= with i flag', 'td[data-label^="river" i] { color: rgb(1,2,3); }', () => [ cs( cell( 'Riverside' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Riverside' ), 'color' ) ] ],
	[ 'attr $= and *=', 'td[data-label$="side"] { color: rgb(1,2,3); } td[data-label*="Town"] { color: rgb(4,5,6); }', () => [ cs( cell( 'Lakeside' ), 'color' ) === 'rgb(1, 2, 3)' && cs( cell( 'Old Town' ), 'color' ) === 'rgb(4, 5, 6)', cs( cell( 'Lakeside' ), 'color' ) + ' / ' + cs( cell( 'Old Town' ), 'color' ) ] ],
	[ '& inside a quoted attribute value', 'td[data-label="R&D"], td[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '& inside content text', 'td[data-label="Day"]::after { content: " & co"; }', () => [ cs( cell( 'Day' ), 'content', '::after' ) === '" & co"', cs( cell( 'Day' ), 'content', '::after' ) ] ],
	[ 'comma inside a quoted attribute', 'td[data-label="a, b"], td[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'escaped space in an unquoted attribute', 'td[data-label=Old\\ Town] { color: rgb(1,2,3); }', () => [ cs( cell( 'Old Town' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Old Town' ), 'color' ) ] ],
	[ 'nth-child of S', 'tbody tr:nth-child(odd of :not([hidden])) > td { background-color: rgb(1,2,3); }', () => [ cs( cell( 'Old Town', 3 ), 'background-color' ) === 'rgb(1, 2, 3)' && cs( cell( 'Old Town', 2 ), 'background-color' ) !== 'rgb(1, 2, 3)', cs( cell( 'Old Town', 3 ), 'background-color' ) ] ],
	[ 'nth-last-child / last-of-type', 'tbody tr:nth-last-child(1) > td:last-of-type { color: rgb(1,2,3); }', () => [ cs( cell( 'Lakeside', 7 ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Lakeside', 7 ), 'color' ) ] ],
	[ ':has() with a child combinator', 'tbody tr:has(> td[data-label="Lakeside"]) > td[data-label="Day"] { font-weight: 900; }', () => [ cs( cell( 'Day' ), 'font-weight' ) === '900', cs( cell( 'Day' ), 'font-weight' ) ] ],
	[ '&:has() on the table itself', '&:has(tbody tr:nth-child(7)) { outline: 3px solid rgb(1,2,3); }', () => [ cs( T(), 'outline-color' ) === 'rgb(1, 2, 3)', cs( T(), 'outline-color' ) ] ],
	[ ':where() still beats the built-in look', ':where(td) { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'sibling combinators + and ~', 'tbody tr + tr > td[data-label="Day"] { color: rgb(1,2,3); } tbody tr ~ tr ~ tr > td[data-label="Day"] { color: rgb(4,5,6); }', () => [ cs( cell( 'Day', 1 ), 'color' ) !== 'rgb(1, 2, 3)' && cs( cell( 'Day', 2 ), 'color' ) === 'rgb(1, 2, 3)' && cs( cell( 'Day', 3 ), 'color' ) === 'rgb(4, 5, 6)', [ 1, 2, 3 ].map( ( r ) => cs( cell( 'Day', r ), 'color' ) ).join( ' ' ) ] ],
	[ 'selector starting with >', '> .lstab-controls { outline: 2px solid rgb(1,2,3); }', () => [ cs( T( '.lstab-controls' ), 'outline-color' ) === 'rgb(1, 2, 3)', cs( T( '.lstab-controls' ), 'outline-color' ) ] ],
	[ 'comment inside a selector', 'td/* x */[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ ':is(&) and context before &', ':is(&) td[data-label="Day"] { color: rgb(1,2,3); } body & td[data-label="Lakeside"] { color: rgb(4,5,6); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)' && cs( cell( 'Lakeside' ), 'color' ) === 'rgb(4, 5, 6)', cs( cell( 'Day' ), 'color' ) + ' / ' + cs( cell( 'Lakeside' ), 'color' ) ] ],
	[ 'body, html and :root stay inside the table', 'body { background: rgb(1,2,3) !important; } html { font-size: 40px !important; } :root { --wp--preset--color--base: red; }', () => [ cs( document.body, 'background-color' ) !== 'rgb(1, 2, 3)' && cs( document.documentElement, 'font-size' ) !== '40px', cs( document.body, 'background-color' ) ] ],
	[ 'universal selector', '* { letter-spacing: 3px; }', () => [ cs( cell( 'Day' ), 'letter-spacing' ) === '3px' && cs( document.body, 'letter-spacing' ) !== '3px', cs( cell( 'Day' ), 'letter-spacing' ) ] ],
	[ '::placeholder on the search box', '.lstab-search-input::placeholder { color: rgb(1,2,3); }', () => [ cs( T( '.lstab-search-input' ), 'color', '::placeholder' ) === 'rgb(1, 2, 3)', cs( T( '.lstab-search-input' ), 'color', '::placeholder' ) ] ],
	[ '::first-letter and ::selection', 'td[data-label="Day"]::first-letter { color: rgb(1,2,3); } td::selection { background: rgb(4,5,6); }', () => [ cs( cell( 'Day' ), 'color', '::first-letter' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color', '::first-letter' ) ] ],
	[ 'own scrollbar look', '.lstab-scroll::-webkit-scrollbar { height: 12px; } .lstab-scrollbar-thumb { background-color: rgb(1,2,3) !important; }', () => [ cs( T( '.lstab-scrollbar-thumb' ), 'background-color' ) === 'rgb(1, 2, 3)', cs( T( '.lstab-scrollbar-thumb' ), 'background-color' ) ] ],
	[ 'unicode and emoji', 'td[data-label="Lakeside"]::before { content: "🌿 Día · "; }', () => [ cs( cell( 'Lakeside' ), 'content', '::before' ) === '"🌿 Día · "', cs( cell( 'Lakeside' ), 'content', '::before' ) ] ],
	[ 'text before the pinned first cell', 'td[data-label="Day"] .lstab-cell-value::before { content: "🌿 "; }', () => [ cs( cell( 'Day' ).querySelector( '.lstab-cell-value' ), 'content', '::before' ) === '"🌿 "', cs( cell( 'Day' ).querySelector( '.lstab-cell-value' ), 'content', '::before' ) ] ],
	[ 'CSS escapes in strings', 'td[data-label="Day"]::after { content: "\\201C\\"}{;\\201D"; }', () => [ cs( cell( 'Day' ), 'content', '::after' ) === '"“\\"}{;”"', cs( cell( 'Day' ), 'content', '::after' ) ] ],
	[ '100 selectors in one list', Array.from( { length: 100 }, ( _, i ) => `.nope-${ i }` ).join( ', ' ) + ', td[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],

	// ---------- at-rules ----------
	[ '@media range syntax', '@media (100px <= width <= 5000px) { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '@media list with a comma', '@media print, (min-width: 1px) { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '@media hover and pointer', '@media (hover: hover) and (pointer: fine) { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '@media prefers-color-scheme dark', '@media (prefers-color-scheme: dark) { & { --lstab-bg: rgb(1,2,3); --lstab-fg: rgb(250,250,250); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(250, 250, 250)', cs( cell( 'Day' ), 'color' ) ], { media: { colorScheme: 'dark' } } ],
	[ '@media prefers-reduced-motion', '@media (prefers-reduced-motion: reduce) { & { animation: none; outline: 1px solid rgb(1,2,3); } }', () => [ cs( T(), 'outline-color' ) === 'rgb(1, 2, 3)', cs( T(), 'outline-color' ) ], { media: { reducedMotion: 'reduce' } } ],
	[ '@media print', '@media print { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ], { media: { media: 'print' } } ],
	[ '@supports selector() and not', '@supports selector(:has(a)) and (not (display: nonsense)) { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '@container lstab at phone width', '@container lstab (max-width: 500px) { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ], { width: 390 } ],
	[ '@layer statement + blocks', '@layer base, mine; @layer mine { td[data-label="Day"] { text-decoration-line: underline; } } @layer base { td[data-label="Day"] { text-decoration-line: overline; } }', () => [ cs( cell( 'Day' ), 'text-decoration-line' ) === 'underline', cs( cell( 'Day' ), 'text-decoration-line' ) ] ],
	[ '@scope (root) to (limit)', '@scope (.lstab-table) to (thead) { td { color: rgb(1,2,3); } th { color: rgb(4,5,6); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)' && cs( T( 'thead th' ), 'color' ) !== 'rgb(4, 5, 6)', cs( cell( 'Day' ), 'color' ) + ' / ' + cs( T( 'thead th' ), 'color' ) ] ],
	[ '@scope with no root', '@scope { td[data-label="Day"] { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '@scope cannot reach siblings or the page', '@scope (.lstab-table) { :scope ~ *, :scope + * { outline: 5px solid rgb(1,2,3) !important; } } @scope (body) { :scope { background: rgb(1,2,3) !important; } td { color: rgb(1,2,3); } } @scope (&) { :scope ~ * { outline: 5px solid rgb(1,2,3) !important; } }', () => {
		const outside = [ document.body, document.querySelector( '[data-lstab-id="' + OTHER + '"]' ), T( '.lstab-meta' ), T().nextElementSibling, T().parentElement.nextElementSibling ].filter( Boolean );
		const hit = outside.filter( ( el ) => cs( el, 'outline-color' ) === 'rgb(1, 2, 3)' || cs( el, 'background-color' ) === 'rgb(1, 2, 3)' );
		return [ 0 === hit.length && cs( cell( 'Day' ), 'color' ) !== 'rgb(1, 2, 3)', hit.map( ( el ) => el.className || el.tagName ).join( ', ' ) || 'nothing outside touched' ];
	} ],
	[ 'background inside @scope reaches the pinned cell', '@scope (.lstab-table) { tbody tr:nth-child(2) > td { background-color: rgb(1,2,3); } }', () => [ cs( cell( 'Day', 2 ), 'background-color', '::before' ) === 'rgb(1, 2, 3)', cs( cell( 'Day', 2 ), 'background-color', '::before' ) + ' (other cells: ' + cs( cell( 'Old Town', 2 ), 'background-color' ) + ', @scope adds no weight)' ] ],
	[ 'a plain cell background beats the built-in look', 'td { background-color: rgb(1,2,3); }', () => [ cs( cell( 'Old Town', 4 ), 'background-color' ) === 'rgb(1, 2, 3)' && cs( cell( 'Day', 4 ), 'background-color', '::before' ) === 'rgb(1, 2, 3)' && cs( cell( 'Day', 4 ), 'background-color' ) === 'rgba(0, 0, 0, 0)', cs( cell( 'Old Town', 4 ), 'background-color' ) + ' pinned layer ' + cs( cell( 'Day', 4 ), 'background-color', '::before' ) + ' pinned cell ' + cs( cell( 'Day', 4 ), 'background-color' ) ] ],
	[ 'plain padding and border beat the built-in look', 'td { padding: 3px 5px; border-bottom: 3px solid rgb(1,2,3); } th { font-size: 9px; }', () => [ cs( cell( 'Old Town', 4 ), 'padding-top' ) === '3px' && cs( cell( 'Old Town', 4 ), 'border-bottom-color' ) === 'rgb(1, 2, 3)' && cs( T( 'thead th' ), 'font-size' ) === '9px', cs( cell( 'Old Town', 4 ), 'padding-top' ) + ' ' + cs( cell( 'Old Town', 4 ), 'border-bottom-color' ) + ' ' + cs( T( 'thead th' ), 'font-size' ) ] ],
	[ 'background in a nested @media reaches the pinned cell', 'tbody tr:nth-child(3) > td { @media (min-width: 1px) { background-color: rgb(1,2,3); } }', () => [ cs( cell( 'Day', 3 ), 'background-color', '::before' ) === 'rgb(1, 2, 3)', cs( cell( 'Day', 3 ), 'background-color', '::before' ) ] ],
	[ '@starting-style', 'td[data-label="Day"] { color: rgb(1,2,3); transition: opacity 0.2s; } @starting-style { td[data-label="Day"] { opacity: 0; } }', () => [ ( document.querySelector( 'style[data-lstab-css="' + HOURS + '"]' ).textContent.includes( '@starting-style' ) ), document.querySelector( 'style[data-lstab-css="' + HOURS + '"]' ).textContent.slice( -140 ) ] ],
	[ '@font-face with local()', '@font-face { font-family: "Mine"; src: local("DejaVu Serif"), local("Liberation Serif"); } td[data-label="Day"] { font-family: "Mine", serif; }', () => [ cs( cell( 'Day' ), 'font-family' ).includes( 'Mine' ), cs( cell( 'Day' ), 'font-family' ) ] ],
	[ '@counter-style', '@counter-style leaf { system: cyclic; symbols: "🍃"; suffix: " "; } & { counter-reset: d; } tbody tr { counter-increment: d; } td[data-label="Lakeside"]::before { content: counter(d, leaf); }', () => [ cs( cell( 'Lakeside' ), 'content', '::before' ).includes( 'leaf' ), cs( cell( 'Lakeside' ), 'content', '::before' ) ] ],
	[ '@property + animation', '@property --spin { syntax: "<angle>"; inherits: false; initial-value: 0deg; } @keyframes turn { to { --spin: 360deg; } } thead th { animation: turn 4s linear infinite; background: conic-gradient(from var(--spin), rgb(1,2,3), white); }', () => [ cs( T( 'thead th:nth-child(2)' ), 'background-image' ).startsWith( 'conic-gradient' ), cs( T( 'thead th:nth-child(2)' ), 'background-image' ).slice( 0, 60 ) ] ],
	[ 'nested at-rules three deep', '@supports (display: grid) { @media (min-width: 1px) { @layer x { td[data-label="Day"] { color: rgb(1,2,3); } } } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'unknown at-rule dropped, rest kept', '@nonsense foo { td { color: red; } } td[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '@page does not reach the page', '@page { margin: 7cm; }', () => [ ! ( document.querySelector( 'style[data-lstab-css="' + HOURS + '"]' )?.textContent || '' ).includes( '@page' ), document.querySelector( 'style[data-lstab-css="' + HOURS + '"]' )?.textContent || 'no style block: nothing was left to print' ] ],

	// ---------- nesting ----------
	[ 'nesting four levels', '.lstab-table { tbody { tr { &:nth-child(2) > td[data-label="Day"] { color: rgb(1,2,3); } } } }', () => [ cs( cell( 'Day', 2 ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day', 2 ), 'color' ) ] ],
	[ 'nested @media inside a rule', 'td[data-label="Day"] { color: red; @media (min-width: 1px) { color: rgb(1,2,3); } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ '& { nested rules }', '& { .lstab-table { td[data-label="Day"] { color: rgb(1,2,3); } } }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'nested background reaches the pinned cell', 'tbody { tr:nth-child(2) > td { background-color: rgb(1,2,3); } }', () => [ cs( cell( 'Day', 2 ), 'background-color', '::before' ) === 'rgb(1, 2, 3)', cs( cell( 'Day', 2 ), 'background-color', '::before' ) ] ],

	// ---------- values ----------
	[ 'scroll-behavior survives', '.lstab-scroll { scroll-behavior: smooth; overscroll-behavior-x: contain; }', () => [ cs( T( '.lstab-scroll' ), 'scroll-behavior' ) === 'smooth' && cs( T( '.lstab-scroll' ), 'overscroll-behavior-x' ) === 'contain', cs( T( '.lstab-scroll' ), 'scroll-behavior' ) + ' / ' + cs( T( '.lstab-scroll' ), 'overscroll-behavior-x' ) ] ],
	[ 'inline SVG data URI with </svg>', 'td[data-label="Day"] { background-image: url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'8\' height=\'8\'><circle cx=\'4\' cy=\'4\' r=\'4\' fill=\'red\'/></svg>"); }', () => [ /<\/svg>|%3C\/svg/i.test( decodeURIComponent( cs( cell( 'Day' ), 'background-image', '::before' ) ) ), cs( cell( 'Day' ), 'background-image', '::before' ).slice( -60 ) ] ],
	[ 'unquoted base64 data URI with ;', 'td[data-label="Day"] { background-image: url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==); }', () => [ cs( cell( 'Day' ), 'background-image', '::before' ).includes( 'base64,R0lG' ), cs( cell( 'Day' ), 'background-image', '::before' ).slice( 0, 50 ) ] ],
	[ 'modern colour functions', 'td[data-label="Lakeside"] { color: oklch(40% 0.1 200); background-color: color-mix(in oklab, rgb(1,2,3) 50%, white); border-color: rgb(from red r g b / 50%); }', () => [ cs( cell( 'Lakeside' ), 'color' ).startsWith( 'oklch' ) && cs( cell( 'Lakeside' ), 'background-color' ).includes( 'oklab' ), cs( cell( 'Lakeside' ), 'color' ) + ' | ' + cs( cell( 'Lakeside' ), 'background-color' ) ] ],
	[ 'colour function reaches the pinned cell', 'td[data-label="Day"] { background-color: color-mix(in oklab, rgb(1,2,3) 50%, white); }', () => [ cs( cell( 'Day' ), 'background-color', '::before' ).includes( 'oklab' ), cs( cell( 'Day' ), 'background-color', '::before' ) ] ],
	[ 'calc clamp min max', 'td[data-label="Day"] { padding-inline-start: clamp(10px, calc(1px + 2vw), max(30px, 1em)); }', () => [ parseFloat( cs( cell( 'Day' ), 'padding-left' ) ) >= 10, cs( cell( 'Day' ), 'padding-left' ) ] ],
	[ 'every style token at once', '& { --lstab-head-bg: rgb(1,2,3); --lstab-head-fg: rgb(255,255,255); --lstab-border: rgb(4,5,6); --lstab-radius: 22px; --lstab-hover: rgb(7,8,9); --lstab-font-size: 20px; }', () => [ cs( T( 'thead th:nth-child(2)' ), 'background-color' ) === 'rgb(1, 2, 3)' && cs( T( '.lstab-scroll' ), 'border-top-left-radius' ) === '22px' && cs( T( 'thead th:first-child' ), 'background-color', '::before' ) === 'rgb(1, 2, 3)', cs( T( 'thead th:nth-child(2)' ), 'background-color' ) + ' r=' + cs( T( '.lstab-scroll' ), 'border-top-left-radius' ) + ' pin=' + cs( T( 'thead th:first-child' ), 'background-color', '::before' ) ] ],
	[ '!important beats the built-in look', '.lstab-table thead th { background-color: rgb(1,2,3) !important; }', () => [ cs( T( 'thead th:nth-child(2)' ), 'background-color' ) === 'rgb(1, 2, 3)' && cs( T( 'thead th:first-child' ), 'background-color', '::before' ) === 'rgb(1, 2, 3)', cs( T( 'thead th:first-child' ), 'background-color', '::before' ) ] ],
	[ 'CRLF line endings', 'td[data-label="Day"] {\r\n  color: rgb(1,2,3);\r\n}\r\n', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'missing closing brace at the end', 'td[data-label="Lakeside"] { color: rgb(4,5,6); } td[data-label="Day"] { color: rgb(1,2,3);', () => [ cs( cell( 'Lakeside' ), 'color' ) === 'rgb(4, 5, 6)', cs( cell( 'Day' ), 'color' ) + ' / ' + cs( cell( 'Lakeside' ), 'color' ) ] ],
	[ 'stray closing braces', '}}} td[data-label="Day"] { color: rgb(1,2,3); } }}', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'unclosed comment at the end', 'td[data-label="Day"] { color: rgb(1,2,3); } /* forgot', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'empty rules and lone semicolons', 'td {} td[data-label="Day"] { ; color: rgb(1,2,3);; }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],
	[ 'just under the size limit', '/*' + 'x'.repeat( 19800 ) + '*/ td[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'color' ) ] ],

	// ---------- safety ----------
	[ 'closing the style tag', 'td { color: red; } </style><img src=x onerror="window.pwned=1"><style>', () => [ ! window.pwned && ! document.querySelector( 'img[src="x"]' ), String( window.pwned ) ] ],
	[ 'closing tag in odd case and with spaces', 'td { color: red; } </STYLE  ><script>window.pwned=1</script>', () => [ ! window.pwned, String( window.pwned ) ] ],
	[ '@import in any spelling', '@IMPORT "https://example.com/a.css"; @import url(https://example.com/b.css) screen; td[data-label="Day"] { color: rgb(1,2,3); }', () => [ ! [ ...document.styleSheets ].some( ( s ) => s.href && s.href.includes( 'example.com' ) ) && cs( cell( 'Day' ), 'color' ) === 'rgb(1, 2, 3)', document.querySelector( 'style[data-lstab-css="' + HOURS + '"]' ).textContent.slice( 0, 120 ) ] ],
	[ 'escaping the scope with braces in a string', 'td::after { content: "}"; } body { background: rgb(1,2,3) !important; }', () => [ cs( document.body, 'background-color' ) !== 'rgb(1, 2, 3)', cs( document.body, 'background-color' ) ] ],
	[ 'escaping with a comma in :is()', ':is(td, body) { outline: 5px solid rgb(1,2,3) !important; }', () => [ cs( document.body, 'outline-color' ) !== 'rgb(1, 2, 3)', cs( document.body, 'outline-style' ) ] ],
	[ 'reaching the other table', 'td { color: rgb(1,2,3); }', () => [ cs( document.querySelector( '[data-lstab-id="' + OTHER + '"] td' ), 'color' ) !== 'rgb(1, 2, 3)', cs( document.querySelector( '[data-lstab-id="' + OTHER + '"] td' ), 'color' ) ] ],

	// ---------- behaviour ----------
	[ 'same table twice: both styled', 'td[data-label="Day"] { color: rgb(1,2,3); }', () => [ cs( T( 'td[data-label="Day"]', 1 ), 'color' ) === 'rgb(1, 2, 3)' && 1 === document.querySelectorAll( 'style[data-lstab-css="' + HOURS + '"]' ).length, document.querySelectorAll( 'style[data-lstab-css="' + HOURS + '"]' ).length + ' style blocks' ] ],
	[ 'search: own stripes follow the visible rows', 'tbody tr:nth-child(even of :not([hidden])) > td { background-color: rgb(1,2,3); }', async () => {
		const input = T( '.lstab-search-input' );
		input.value = 'day';
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		await new Promise( ( r ) => setTimeout( r, 600 ) );
		const shown = TA( 'tbody tr:not([hidden])' );
		return [ shown.length > 2 && cs( shown[ 1 ].querySelector( 'td:nth-child(2)' ), 'background-color' ) === 'rgb(1, 2, 3)' && cs( shown[ 0 ].querySelector( 'td:nth-child(2)' ), 'background-color' ) !== 'rgb(1, 2, 3)', shown.length + ' rows shown' ];
	} ],
	[ 'sorting: own sorted header look', 'thead th[aria-sort] { color: rgb(1,2,3) !important; } tbody tr:nth-child(1) > td { font-weight: 900; }', async () => {
		T( 'thead th:nth-child(2) .lstab-sort, thead th:nth-child(2) button, thead th:nth-child(2) a' ).click();
		await new Promise( ( r ) => setTimeout( r, 800 ) );
		const th = T( 'thead th[aria-sort]:not([aria-sort="none"])' ) || T( 'thead th[aria-sort]' );
		return [ cs( th, 'color' ) === 'rgb(1, 2, 3)' && cs( T( 'tbody tr:nth-child(1) > td' ), 'font-weight' ) === '900', ( th ? th.getAttribute( 'aria-sort' ) : 'none' ) + ' ' + cs( th, 'color' ) ];
	} ],
	[ 'keyboard focus ring of its own', '.lstab-sort:focus-visible, th button:focus-visible, th a:focus-visible { outline: 4px dashed rgb(1,2,3) !important; }', async () => {
		T( '.lstab-search-input' ).focus();
		return [ true, 'checked below with real keys' ];
	} ],
	[ '&:focus-within', '&:focus-within { outline: 3px solid rgb(1,2,3); }', async () => {
		T( '.lstab-search-input' ).focus();
		return [ cs( T(), 'outline-color' ) === 'rgb(1, 2, 3)', cs( T(), 'outline-color' ) ];
	} ],
	[ 'right to left page', 'td[data-label="Day"] { padding-inline-start: 40px; border-inline-start: 6px solid rgb(1,2,3); }', async () => {
		document.documentElement.dir = 'rtl';
		await new Promise( ( r ) => setTimeout( r, 200 ) );
		return [ cs( cell( 'Day' ), 'padding-right' ) === '40px' && cs( cell( 'Day' ), 'border-right-color' ) === 'rgb(1, 2, 3)', cs( cell( 'Day' ), 'padding-right' ) ];
	} ],
	[ 'details row (hidden columns)', 'tr.lstab-detail td { background-color: rgb(1,2,3); } .lstab-detail-key { color: rgb(4,5,6); }', async () => {
		const toggle = T( '.lstab-detail-toggle, button[aria-expanded]' );
		if ( toggle ) {
			toggle.click();
			await new Promise( ( r ) => setTimeout( r, 300 ) );
		}
		const key = T( '.lstab-detail-key' );
		return [ !! key && cs( key, 'color' ) === 'rgb(4, 5, 6)', key ? cs( key, 'color' ) : 'no details row' ];
	}, { php: [ true, false ] } ],
];

