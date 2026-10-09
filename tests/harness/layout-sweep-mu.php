<?php
/**
 * Test helper for tests/layout-sweep.mjs, copied into mu-plugins for the run.
 *
 * ?lstab-rtl=1 draws the page right to left. ?lstab-hostile=1 adds the kind of
 * table, button and field rules aggressive themes and page builders ship.
 */
if ( isset( $_GET['lstab-rtl'] ) ) {
	add_action(
		'init',
		static function () {
			global $wp_locale;
			$wp_locale->text_direction = 'rtl';
		},
		0
	);
}

if ( isset( $_GET['lstab-hostile'] ) ) {
	add_action(
		'wp_head',
		static function () {
			echo '<style id="qa-hostile">
table { border: 2px solid #333; margin: 0 0 2.5em; width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 2px; overflow: hidden; font-size: 13px; }
.entry-content table, .wp-block-post-content table { display: block; overflow-x: auto; max-width: 100%; }
th, td { border: 1px solid #ccc; padding: 14px 18px; text-align: center; vertical-align: middle; word-break: break-all; position: relative; line-height: 1.2; }
thead th { background: #222; color: #fff; text-transform: uppercase; white-space: nowrap; }
.entry-content tbody tr:nth-child(odd) td, tbody tr:nth-child(odd) td { background-color: #f3f3f3; }
tr:hover td { background: #ffe; }
button, .entry-content button, input[type=submit] { background: #e2401c; color: #fff; border: 0; border-radius: 0; padding: 14px 28px; text-transform: uppercase; letter-spacing: .1em; font-weight: 800; min-height: 48px; box-shadow: 0 4px 0 #a0300f; font-size: 15px; }
button:hover, button:focus { background: #000; color: #ff0; text-decoration: underline; }
input[type=search], .entry-content input[type=text], .entry-content input[type=search], input { height: 54px; padding: 0 22px; border: 2px solid #000; border-radius: 30px; background: #fafafa; font-size: 18px; width: 100%; }
a, .entry-content a { color: #c00; text-decoration: underline; border-bottom: 2px dotted #c00; }
p { margin: 0 0 2em; line-height: 2; }
nav { display: block; background: #eee; padding: 20px; }
span { line-height: 2; }
</style>';
		},
		99
	);
}
