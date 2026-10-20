<?php
/**
 * Plugin Name: Screenshot site – system emoji
 * Description: Leaves emoji to the system font instead of images from s.w.org, which a site with no internet cannot load. Copied in by tools/wporg-shots/seed.php; never ship this.
 */

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
add_filter( 'emoji_svg_url', '__return_false' );
