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
	'version'      => '1.0.0',
);
