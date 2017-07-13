<?php
/*
Plugin Name: UAMSWP Content Syndication
Plugin URI: -
Description: Content Syndication plugin for uams.edu & uamshealth.com
Author: uams, Todd McKee, MEd
Author URI: http://www.uams.edu/
Version: 1.0
*/

namespace UAMS\ContentSyndicate;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'plugins_loaded', 'UAMS\ContentSyndicate\bootstrap' );
/**
 * Loads the WSUWP Content Syndicate base.
 *
 * @since 1.0.0
 */
function bootstrap() {
	include_once __DIR__ . '/includes/class-uams-syndication-shortcode-base.php';

	add_action( 'init', 'UAMS\ContentSyndicate\activate_shortcodes' );
}

/**
 * Activates the shortcodes built in with WSUWP Content Syndicate.
 *
 * @since 1.0.0
 */
function activate_shortcodes() {
	include_once( dirname( __FILE__ ) . '/includes/class-uams-syndication-shortcode-news.php' );

	// Add the [uamswp_news] shortcode to pull standard post content.
	new \UAMS_Syndication_Shortcode_News();

	do_action( 'uamswp_content_syndication_shortcodes' );
}