<?php
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'screenshot-showcase', get_stylesheet_uri(), array(), '1.0' );
	}
);
add_action(
	'after_setup_theme',
	function () {
		add_editor_style( 'style.css' );
	}
);
