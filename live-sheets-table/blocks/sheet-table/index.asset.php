<?php
/**
 * Dependency manifest for the block editor script.
 *
 * Hand written rather than generated: the editor script is plain ES5 using the
 * global wp.* objects, so there is no build step to generate one.
 *
 * @package LiveSheetsTable
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-i18n',
		'wp-data',
		'wp-api-fetch',
		'wp-server-side-render',
	),
	// Derived from the script itself rather than hard-coded, so an edited
	// editor script can never be served from a stale browser cache.
	'version'      => (string) ( @filemtime( __DIR__ . '/index.js' ) ?: '1.0.0' ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
);
