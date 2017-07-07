<?php
/*
Plugin Name: UAMSWP Content Syndication
Plugin URI: -
Description: Content Syndication plugin for uams.edu & uamshealth.com
Author: uams, Todd McKee, MEd
Author URI: http://www.uams.edu/
Version: 0.10
*/
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

class UAMS_Content_Syndicate {
	/**
	 * @var UAMS_Content_Syndicate
	 */
	private static $instance;

	/**
	 * Maintain and return the one instance and initiate hooks when
	 * called the first time.
	 *
	 * @return \UAMS_Content_Syndicate
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new UAMS_Content_Syndicate;
			self::$instance->setup_hooks();
		}
		return self::$instance;
	}

	/**
	 * Setup hooks to include and then activate the plugin's shortcodes.
	 */
	public function setup_hooks() {
		add_action( 'init', array( $this, 'activate_shortcodes' ) );
	}

	/**
	 * Include individual and activate individual syndicate shortcodes.
	 */
	public function activate_shortcodes() {
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-base.php' );
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-news.php' );
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-people.php' );
		//require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-teams.php' );

		// Add the [uamswp_json] shortcode to pull standard post content.
		new UAMS_Syndicate_Shortcode_News();

		// Add the [uamswp_people] shortcode to pull profiles from people.uams.edu.
		new UAMS_Syndicate_Shortcode_People();

		// Add the [uamswp_events] shortcode to pull calendar events.
		//new UAMS_Syndicate_Shortcode_Events();
	}
}

add_action( 'after_setup_theme', 'UAMS_Content_Syndicate' );
/**
 * Start things up.
 *
 * @return \UAMS_Content_Syndicate
 */
function UAMS_Content_Syndicate() {
	return UAMS_Content_Syndicate::get_instance();
}

