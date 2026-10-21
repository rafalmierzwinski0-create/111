/**
 * The demo pages, written as block trees and saved through the block editor
 * itself, so the markup is exactly what WordPress makes and the editor shows
 * no "unexpected content" warning in the editor screenshot.
 *
 * Each block is [ name, attributes, inner blocks ]. Photos are the CC0 images
 * that ship with Twenty Twenty-Five and Twenty Twenty-Four.
 */

const fern = { cream: '#f6f1e7', forest: '#1f3b2d', moss: '#2f6b4f', ink: '#24231f', muted: '#6b665c' };
const walk = { ivory: '#fbf8f2', navy: '#18222f', gold: '#c4923c', ink: '#1d232b', muted: '#5f6670' };

const photo = ( base, theme, name ) => `${ base }/wp-content/themes/${ theme }/assets/images/${ name }`;
const pad = ( top, bottom = top, sides = '32px' ) => ( { padding: { top, bottom, left: sides, right: sides } } );
const text = ( content, style = {}, extra = {} ) => [ 'core/paragraph', { content, style, ...extra } ];
const heading = ( level, content, size, family, colour, extra = {} ) => [ 'core/heading', {
	level,
	content,
	fontFamily: family,
	style: { typography: { fontSize: size, lineHeight: '1.08', fontWeight: '500', letterSpacing: '-0.02em' }, ...( colour ? { color: { text: colour } } : {} ) },
	...extra,
} ];
const button = ( label, solid, colour, ink = '#ffffff' ) => [ 'core/button', solid
	? { text: label, style: { color: { background: colour, text: ink }, border: { radius: '999px' }, spacing: { padding: { top: '15px', bottom: '15px', left: '28px', right: '28px' } } } }
	: { text: label, style: { color: { background: 'transparent', text: colour }, border: { radius: '999px', width: '1.5px', color: colour }, spacing: { padding: { top: '13.5px', bottom: '13.5px', left: '26px', right: '26px' } } } } ];
const table = ( sourceId, attrs = {} ) => [ 'live-sheets-table/sheet-table', { sourceId, ...attrs } ];

const careCard = ( base, image, title, line, price ) => [ 'core/column', {}, [
	[ 'core/image', { url: photo( base, 'twentytwentyfive', image ), alt: '', aspectRatio: '4/3', scale: 'cover', style: { border: { radius: '20px' } } } ],
	heading( 3, title, '24px', 'literata', fern.ink, { style: { typography: { fontSize: '24px', fontWeight: '500', lineHeight: '1.2' }, spacing: { margin: { top: '20px' } } } } ),
	text( line, { color: { text: fern.muted }, typography: { fontSize: '16px' } } ),
	text( price, { color: { text: fern.moss }, typography: { fontSize: '15px', fontWeight: '700' } } ),
] ];

export const plants = ( t, base ) => [
	[ 'core/group', { align: 'full', style: { color: { background: fern.cream }, spacing: pad( '48px', '56px' ) }, layout: { type: 'constrained', wideSize: '1240px' } }, [
		[ 'core/columns', { align: 'wide', verticalAlignment: 'center', style: { spacing: { blockGap: { left: '72px' } } } }, [
			[ 'core/column', { width: '54%', verticalAlignment: 'center' }, [
				text( 'Price list · updated every hour', { color: { text: fern.moss }, typography: { fontSize: '14px', fontWeight: '700', letterSpacing: '0.12em', textTransform: 'uppercase' } } ),
				heading( 1, 'Every plant on our shelves, with today’s price.', '54px', 'literata', fern.ink, { style: { typography: { fontSize: '54px', fontWeight: '500', lineHeight: '1.06', letterSpacing: '-0.025em' }, spacing: { margin: { top: '16px' } } } } ),
				text( 'This list comes straight from the spreadsheet our greenhouse team fills in as plants arrive. When something sells out, it says so here within the hour.', { color: { text: fern.muted }, typography: { fontSize: '19px', lineHeight: '1.6' }, spacing: { margin: { top: '22px' } } } ),
				[ 'core/buttons', { style: { spacing: { margin: { top: '32px' }, blockGap: '12px' } } }, [
					button( 'Shop all plants', true, fern.forest ),
					button( 'Book a workshop', false, fern.forest ),
				] ],
				text( '<strong>★★★★★</strong>  4.9 from 1,240 reviews by local plant lovers', { color: { text: fern.muted }, typography: { fontSize: '14px' }, spacing: { margin: { top: '28px' } } } ),
			] ],
			[ 'core/column', { width: '46%' }, [
				[ 'core/image', { url: photo( base, 'twentytwentyfive', 'botany-flowers.webp' ), alt: '', aspectRatio: '16/11', scale: 'cover', style: { border: { radius: '32px' } } } ],
			] ],
		] ],
	] ],
	[ 'core/group', { align: 'full', style: { spacing: pad( '64px', '96px' ) }, layout: { type: 'constrained', wideSize: '1240px' } }, [
		[ 'core/group', { align: 'wide', layout: { type: 'flex', justifyContent: 'space-between', verticalAlignment: 'bottom', flexWrap: 'wrap' }, style: { spacing: { margin: { bottom: '36px' } } } }, [
			[ 'core/group', { layout: { type: 'default' } }, [
				heading( 2, 'In the greenhouse today', '44px', 'literata', fern.ink ),
				text( 'Search for a plant, or sort by light, pot size or price.', { color: { text: fern.muted }, typography: { fontSize: '17px' }, spacing: { margin: { top: '12px' } } } ),
			] ],
			text( '18 plants · 3 greenhouses', { color: { text: fern.muted }, typography: { fontSize: '15px' } } ),
		] ],
		table( t.plants, { align: 'wide' } ),
	] ],
	[ 'core/group', { align: 'full', style: { color: { background: fern.cream }, spacing: pad( '88px', '96px' ) }, layout: { type: 'constrained', wideSize: '1240px' } }, [
		heading( 2, 'Not sure where to start?', '40px', 'literata', fern.ink, { align: 'wide' } ),
		[ 'core/columns', { align: 'wide', style: { spacing: { blockGap: { left: '32px' }, margin: { top: '36px' } } } }, [
			careCard( base, 'category-anthuriums.webp', 'Bright-shade lovers', 'Happy a few steps from a sunny window.', 'From $32' ),
			careCard( base, 'category-cactus.webp', 'Thrive on neglect', 'Water once a month and forget about them.', 'From $18' ),
			careCard( base, 'category-sunflowers.webp', 'Seasonal flowers', 'Cut fresh every Friday morning.', 'From $12' ),
		] ],
	] ],
];

const tick = ( line ) => text( `<span style="color:${ walk.gold }">✓</span>  ${ line }`, { typography: { fontSize: '16px' }, spacing: { margin: { top: '10px' } } } );

export const walks = ( t, base ) => [
	[ 'core/cover', {
		url: photo( base, 'twentytwentyfour', 'tourist-and-building.webp' ),
		dimRatio: 60,
		customOverlayColor: walk.navy,
		minHeight: 380,
		align: 'full',
		contentPosition: 'bottom left',
		style: { spacing: pad( '72px', '64px', '80px' ) },
		layout: { type: 'constrained', contentSize: '1240px' },
	}, [
		text( 'Athens · small-group walks', { color: { text: '#e8c98f' }, typography: { fontSize: '14px', fontWeight: '700', letterSpacing: '0.14em', textTransform: 'uppercase' } } ),
		heading( 1, 'Dates and seats for the next two weeks', '64px', 'platypi', '#ffffff', { style: { typography: { fontSize: '64px', fontWeight: '500', lineHeight: '1.04', letterSpacing: '-0.02em' }, color: { text: '#ffffff' }, spacing: { margin: { top: '14px' } } } } ),
		text( 'Twelve people at most, guides who grew up here, and seats that update live from our booking sheet.', { color: { text: '#e9e4da' }, typography: { fontSize: '19px' }, spacing: { margin: { top: '18px' } } } ),
	] ],
	[ 'core/group', { align: 'full', style: { color: { background: walk.ivory }, spacing: pad( '72px', '96px' ) }, layout: { type: 'constrained', wideSize: '1240px' } }, [
		[ 'core/columns', { align: 'wide', style: { spacing: { blockGap: { left: '48px' } } } }, [
			[ 'core/column', { width: '71%' }, [
				heading( 2, 'Upcoming departures', '40px', 'platypi', walk.navy ),
				text( 'Pick a walk and book a seat. “Full” walks open again when someone cancels.', { color: { text: walk.muted }, typography: { fontSize: '17px' }, spacing: { margin: { top: '10px', bottom: '28px' } } } ),
				table( t.walks ),
			] ],
			[ 'core/column', { width: '29%' }, [
				[ 'core/group', { style: { color: { background: '#ffffff' }, border: { radius: '24px', width: '1px', color: '#ece4d4' }, spacing: { padding: { top: '32px', bottom: '32px', left: '30px', right: '30px' } } }, layout: { type: 'default' } }, [
					heading( 3, 'How booking works', '24px', 'platypi', walk.navy, { style: { typography: { fontSize: '24px', fontWeight: '500', lineHeight: '1.2' } } } ),
					tick( 'Pay when you meet your guide' ),
					tick( 'Free cancellation until the day before' ),
					tick( 'Meeting point sent the day before' ),
					[ 'core/buttons', { style: { spacing: { margin: { top: '26px' } } } }, [ button( 'Book a walk', true, walk.gold, '#1b1408' ) ] ],
				] ],
				[ 'core/image', { url: photo( base, 'twentytwentyfive', 'parthenon-square.webp' ), alt: '', aspectRatio: '4/3', scale: 'cover', style: { border: { radius: '24px' }, spacing: { margin: { top: '24px' } } } } ],
			] ],
		] ],
	] ],
];

const shown = ( id, preset, extra = {} ) => table( id, { stylePreset: preset, showSearch: false, showUpdated: false, ...extra } );

export const styles = ( t ) => [
	[ 'core/group', { align: 'full', style: { spacing: pad( '48px', '48px' ) }, layout: { type: 'constrained', contentSize: '760px' } }, [
		shown( t.shops, 'clean', { layout: 'table' } ),
		[ 'core/spacer', { height: '48px' } ],
		shown( t.workshops, 'striped' ),
		[ 'core/spacer', { height: '48px' } ],
		shown( t.hours, 'bordered' ),
		[ 'core/spacer', { height: '48px' } ],
		shown( t.delivery, 'midnight' ),
	] ],
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
