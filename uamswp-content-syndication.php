<?php
/*
Plugin Name: UAMSWP Content Syndication
Plugin URI: -
Description: Content Syndication plugin for uams.edu & uamshealth.com
Author: uams, Todd McKee, MEd
Author URI: http://www.uams.edu/
Version: 1.4.5
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// This plugin uses namespaces and requires PHP 5.3 or greater.
if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	add_action( 'admin_notices', create_function( '', // phpcs:ignore WordPress.PHP.RestrictedPHPFunctions.create_function_create_function
	"echo '<div class=\"error\"><p>" . __( 'UAMSWP Content Syndicate requires PHP 5.3 to function properly. Please upgrade PHP or deactivate the plugin.', 'wsuwp-content-syndicate' ) . "</p></div>';" ) );
	return;
} else {
	include_once __DIR__ . '/includes/content-syndicate.php';
}