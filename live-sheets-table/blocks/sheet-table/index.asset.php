<?php

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
	'version'      => (string) ( @filemtime( __DIR__ . '/index.js' ) ?: '1.0.0' ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
);
