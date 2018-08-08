<?php

namespace UAMS\Content_Syndicate;

add_action( 'plugins_loaded', 'UAMS\Content_Syndicate\bootstrap' );

/**
 * Loads the UAMSWP Content Syndicate base.
 *
 * @since 1.0.0
 */
function bootstrap() {
	include_once __DIR__ . '/class-uams-syndicate-shortcode-base.php';

	add_action( 'init', 'UAMS\Content_Syndicate\activate_shortcodes' );
	add_action( 'save_post_post', 'UAMS\Content_Syndicate\clear_local_content_cache' );
	add_action( 'save_post_page', 'UAMS\Content_Syndicate\clear_local_content_cache' );
}

/**
 * Activates the shortcodes built in with UAMSWP Content Syndicate.
 *
 * @since 1.0.0
 */
function activate_shortcodes() {
	include_once dirname( __FILE__ ) . '/class-uams-syndicate-shortcode-news.php';

	// Add the [uamswp_json] shortcode to pull standard post content.
	new \UAMS_Syndicate_Shortcode_News();

	do_action( 'uamswp_content_syndicate_shortcodes' );
}

/**
 * Clear the last changed cache for local results whenever
 * a post is saved.
 *
 * @since 1.4.0
 */
function clear_local_content_cache() {
	wp_cache_set( 'last_changed', microtime(), 'uamswp-content' );
}