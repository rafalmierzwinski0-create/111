/**
 * The wording itself. A note for translators that promises a number the text
 * never shows, a word the plugin stopped using on one screen but not another,
 * a sentence so long the reader loses its start, straight quotes in prose: none
 * of it breaks anything a test of behaviour would see, and all of it is the
 * first thing a reader notices.
 *
 * Reads every translatable string in both plugins, and the readme.
 *
 * Usage: node tests/texts-audit.mjs
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = process.env.LSTAB_REPO || path.join( here, '..' );

let passed = 0;
let failed = 0;

const check = ( ok, what, detail = '' ) => {
	if ( ok ) {
		passed += 1;
		console.log( '  \x1b[32mPASS\x1b[0m ', what );
		return;
	}
	failed += 1;
	console.log( '  \x1b[31mFAIL\x1b[0m ', what );
	if ( detail ) {
		console.log( '        ', String( detail ).slice( 0, 600 ) );
	}
};

const strings = JSON.parse( execFileSync( 'php', [ path.join( here, 'harness/ui-strings.php' ), repo ] ).toString() );
const readme = fs.readFileSync( path.join( repo, 'live-sheets-table/readme.txt' ), 'utf8' );

console.log( `\n\x1b[1mThe notes for translators match their text\x1b[0m  (${ strings.length } strings)` );

const holes = ( text ) => {
	const numbered = [ ...text.matchAll( /%(\d+)\$[sd]/g ) ].map( ( m ) => Number( m[ 1 ] ) );
	const plain = [ ...text.matchAll( /%(?!\d+\$)[sd]/g ) ].length;
	return numbered.length ? new Set( numbered ).size : plain;
};

const mismatched = [];
const unexplained = [];
for ( const s of strings ) {
	const count = Math.max( holes( s.text ), s.plural ? holes( s.plural ) : 0 );
	if ( ! s.comment ) {
		if ( count > 0 && ! strings.some( ( o ) => o.comment && o.text === s.text ) ) {
			unexplained.push( `${ s.where }: ${ s.text.slice( 0, 60 ) }` );
		}
		continue;
	}
	const named = new Set( [ ...s.comment.matchAll( /(?:^|[\s,;])(?:%?(\d+)(?:\$[sd])?):/g ) ].map( ( m ) => m[ 1 ] ) ).size || ( /%[sd]:/.test( s.comment ) ? 1 : 0 );
	if ( named !== count ) {
		mismatched.push( `${ s.where }: the note names ${ named }, the text has ${ count } — “${ s.text.slice( 0, 70 ) }”` );
	}
}
check( 0 === mismatched.length, 'every note for translators describes as many placeholders as its text has', mismatched.join( '\n         ' ) );
check( 0 === unexplained.length, 'every text with a placeholder has a note saying what goes in it', unexplained.join( '\n         ' ) );

console.log( '\n\x1b[1mOne name for one thing\x1b[0m' );

/*
 * Words the plugin no longer uses, each with the one it uses instead. A reader
 * who meets both takes them for two different things.
 */
const retired = [
	[ /\bsheet sources?\b/i, 'table' ],
	[ /\bsync(?:s|ed|ing)?\b/i, 'update' ],
	[ /\bfetch(?:es|ed|ing)?\b/i, 'read' ],
	[ /\bSheets Tables\b/, 'Live Sheets Table' ],
	[ /\bparser\b/i, 'the plugin' ],
	[ /\bHTTP \d{3}\b/, 'error 403 and the like' ],
	[ /\bsaved source\b/i, 'table' ],
];
for ( const [ word, instead ] of retired ) {
	const hits = strings.filter( ( s ) => word.test( s.text ) || word.test( s.plural ) ).map( ( s ) => `${ s.where }: ${ s.text.slice( 0, 80 ) }` );
	const inReadme = readme.split( '\n' ).filter( ( l ) => word.test( l ) && ! /^=|Tags:|`/.test( l ) );
	check( 0 === hits.length && 0 === inReadme.length, `nothing says ${ word.source.replace( /\\b|\(\?:[^)]*\)\??|\?/g, '' ) } (it is “${ instead }” everywhere)`, hits.concat( inReadme.map( ( l ) => 'readme: ' + l.slice( 0, 80 ) ) ).join( '\n         ' ) );
}

console.log( '\n\x1b[1mSentences a reader can hold in their head\x1b[0m' );

const long = [];
for ( const s of strings ) {
	for ( const sentence of s.text.split( /(?<=[.!?])\s+/ ) ) {
		const words = sentence.split( /\s+/ ).filter( Boolean ).length;
		if ( words > 42 ) {
			long.push( `${ s.where } (${ words } words): ${ sentence.slice( 0, 90 ) }…` );
		}
	}
}
check( 0 === long.length, 'no sentence in the interface runs past 42 words', long.join( '\n         ' ) );

console.log( '\n\x1b[1mPunctuation\x1b[0m' );

const straight = strings.filter( ( s ) => /(^|[\s(])"[A-Za-z][^"=]*"(?=[\s.,;:)]|$)/.test( s.text ) && ! /=\s*"/.test( s.text ) ).map( ( s ) => `${ s.where }: ${ s.text.slice( 0, 80 ) }` );
check( 0 === straight.length, 'prose uses curly quotation marks; straight ones only in code a reader types', straight.join( '\n         ' ) );

const doubled = strings.filter( ( s ) => /\b(\w+) \1\b/i.test( s.text.replace( /\b(that|had|is) \1\b/gi, '' ) ) ).map( ( s ) => `${ s.where }: ${ s.text.slice( 0, 80 ) }` );
check( 0 === doubled.length, 'no word is written twice in a row', doubled.join( '\n         ' ) );

const spaces = strings.filter( ( s ) => /\S  +\S|\s[.,;:]/.test( s.text.replace( /\s…/g, '' ) ) && ! /\s[.,]\w/.test( s.text ) ).map( ( s ) => `${ s.where }: ${ s.text.slice( 0, 80 ) }` );
check( 0 === spaces.length, 'no double spaces, and no space before a full stop or comma', spaces.join( '\n         ' ) );

console.log( `\n  ${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
