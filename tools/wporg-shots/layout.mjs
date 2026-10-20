/**
 * The two demo pages, written as block trees and saved through the block
 * editor itself, so the markup is exactly what WordPress makes and the editor
 * shows no "unexpected content" warning in screenshot 10.
 *
 * Each block is [ name, attributes, inner blocks ].
 */

const green = 'linear-gradient(135deg,#0d3326 0%,#17573f 55%,#2f8f66 100%)';
const ink = '#0d3326';

const pad = ( top, bottom = top, sides = '24px' ) => ( { padding: { top, bottom, left: sides, right: sides } } );

const hero = ( eyebrow, title, text, buttons ) => [ 'core/group', {
	align: 'full',
	style: { color: { gradient: green, text: '#ffffff' }, spacing: pad( '72px', '80px' ), elements: { link: { color: { text: '#ffffff' } } } },
	layout: { type: 'constrained', contentSize: '760px' },
}, [
	[ 'core/paragraph', { content: eyebrow, style: { typography: { fontSize: '14px', letterSpacing: '0.14em', textTransform: 'uppercase', fontWeight: '600' }, color: { text: '#b9ecd3' } } } ],
	[ 'core/heading', { level: 1, content: title, style: { typography: { fontSize: '54px', lineHeight: '1.05', fontWeight: '600' }, spacing: { margin: { top: '12px' } } } } ],
	[ 'core/paragraph', { content: text, style: { typography: { fontSize: '21px', lineHeight: '1.5' }, color: { text: '#e2f6ec' } } } ],
	...( buttons ? [ [ 'core/buttons', { style: { spacing: { margin: { top: '32px' } } } }, buttons.map( ( [ label, solid ] ) => [ 'core/button', solid
		? { text: label, style: { color: { background: '#ffffff', text: ink }, border: { radius: '999px' }, spacing: { padding: { top: '14px', bottom: '14px', left: '28px', right: '28px' } } } }
		: { text: label, style: { color: { background: 'transparent', text: '#ffffff' }, border: { radius: '999px', width: '2px', color: '#ffffff' }, spacing: { padding: { top: '12px', bottom: '12px', left: '26px', right: '26px' } } } } ] ) ] ] : [] ),
] ];

const section = ( inner, extra = {} ) => [ 'core/group', {
	align: 'full',
	style: { spacing: pad( '72px', '24px' ), ...( extra.style || {} ) },
	layout: { type: 'constrained', wideSize: '1180px' },
	...( extra.attrs || {} ),
}, inner ];

const title = ( text, sub ) => [
	[ 'core/heading', { level: 2, content: text, style: { typography: { fontSize: '36px', fontWeight: '600' } } } ],
	...( sub ? [ [ 'core/paragraph', { content: sub, style: { typography: { fontSize: '18px' }, color: { text: '#4b5a53' } } } ] ] : [] ),
];

const table = ( sourceId, attrs = {} ) => [ 'live-sheets-table/sheet-table', { sourceId, ...attrs } ];

const card = ( emoji, head, text ) => [ 'core/column', {}, [
	[ 'core/group', {
		style: { color: { background: '#f3f8f5' }, border: { radius: '16px' }, spacing: { padding: { top: '28px', bottom: '28px', left: '28px', right: '28px' } } },
		layout: { type: 'default' },
	}, [
		[ 'core/paragraph', { content: emoji, style: { typography: { fontSize: '30px', lineHeight: '1' } } } ],
		[ 'core/heading', { level: 3, content: head, style: { typography: { fontSize: '21px', fontWeight: '600' } } } ],
		[ 'core/paragraph', { content: text, style: { typography: { fontSize: '16px' }, color: { text: '#4b5a53' } } } ],
	] ],
] ];

export const prices = ( t ) => [
	hero(
		'Autumn price list',
		'Bikes, gear and repairs at honest prices',
		'Every price on this page comes straight from the sheet our shop floor works from. When a price changes there, it changes here.',
		[ [ 'Book a service', true ], [ 'Find a shop', false ] ]
	),
	section( [
		...title( 'Today’s prices', 'Search for anything, or click a column to sort it. Prices include tax.' ),
		table( t.prices, { align: 'wide' } ),
	] ),
	section( [
		[ 'core/columns', { align: 'wide', style: { spacing: { blockGap: { left: '24px' } } } }, [
			card( '🔧', 'Free fitting', 'Every bike we sell is set up for you, and checked again after a month of riding.' ),
			card( '🚚', 'Delivery in 48 hours', 'Order before 3 pm and it is at your door, or ready in the shop, two days later.' ),
			card( '💬', 'Advice from riders', 'Everyone in the shop rides. Ask us what we would buy with your budget.' ),
		] ],
	], { style: { spacing: pad( '24px', '96px' ) } } ),
];

export const visit = ( t ) => [
	hero( 'Three shops, one team', 'Visit us', 'Opening hours, the service menu and this week’s group rides, kept up to date by the shop staff in one shared sheet.' ),
	section( [
		[ 'core/columns', { align: 'wide', style: { spacing: { blockGap: { left: '40px' } } } }, [
			[ 'core/column', {}, [
				[ 'core/heading', { level: 3, content: 'Opening hours', style: { typography: { fontSize: '24px', fontWeight: '600' } } } ],
				table( t.hours, { showSearch: false, showUpdated: false } ),
			] ],
			[ 'core/column', {}, [
				[ 'core/heading', { level: 3, content: 'Service menu', style: { typography: { fontSize: '24px', fontWeight: '600' } } } ],
				table( t.service, { showSearch: false, showUpdated: false } ),
			] ],
		] ],
	] ),
	section( [
		[ 'core/columns', { align: 'wide', style: { spacing: { blockGap: { left: '40px' } } } }, [
			[ 'core/column', {}, [
				[ 'core/heading', { level: 3, content: 'Our shops', style: { typography: { fontSize: '24px', fontWeight: '600' } } } ],
				table( t.shops, { showSearch: false, showSort: false, showUpdated: false } ),
			] ],
			[ 'core/column', {}, [
				[ 'core/group', {
					style: { color: { background: '#0e131a', text: '#e6edf3' }, border: { radius: '20px' }, spacing: { padding: { top: '28px', bottom: '28px', left: '28px', right: '28px' } } },
					layout: { type: 'default' },
				}, [
					[ 'core/heading', { level: 3, content: 'Group rides', style: { typography: { fontSize: '24px', fontWeight: '600' }, color: { text: '#ffffff' } } } ],
					[ 'core/paragraph', { content: 'Free, open to everyone, lights on after dark.', style: { color: { text: '#9fb0c0' } } } ],
					table( t.rides, { showSearch: false, showUpdated: false } ),
				] ],
			] ],
		] ],
	], { style: { spacing: pad( '32px', '96px' ) } } ),
];

/**
 * Opens a page in the editor, replaces its content with the tree and saves.
 */
export const build = async ( page, base, postId, tree ) => {
	await page.goto( `${ base }/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.waitForFunction( () => window.wp && window.wp.data && window.wp.blocks && window.wp.data.select( 'core/editor' ).getCurrentPostId() );
	await page.evaluate( async ( blocks ) => {
		const make = ( [ name, attrs, inner ] ) => window.wp.blocks.createBlock( name, attrs || {}, ( inner || [] ).map( make ) );
		window.wp.data.dispatch( 'core/block-editor' ).resetBlocks( blocks.map( make ) );
		await window.wp.data.dispatch( 'core/editor' ).savePost();
	}, tree );
	await page.waitForFunction( () => ! window.wp.data.select( 'core/editor' ).isSavingPost() && ! window.wp.data.select( 'core/editor' ).isEditedPostDirty() );
};
