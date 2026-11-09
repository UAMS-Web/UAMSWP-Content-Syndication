<?php

/**
 * Unit bootstrap.
 *
 * The suite runs with no WordPress: WordPress functions are mocked per test
 * with Brain Monkey.
 */

declare(strict_types=1);

// Give PHPUnit's isolated children the same autoloader as the Pest parent.
if (! \defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', __DIR__.'/../vendor/autoload.php');
}

require_once __DIR__.'/../vendor/autoload.php';

/*
 * Patchwork first: it can only redefine functions declared in files loaded
 * after it, so loading it before the plugin is what lets a test mock one of the
 * plugin's own functions, not only WordPress'.
 */
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

/*
 * Load the files that DEFINE functions and classes, never the main plugin file
 * or includes/content-syndicate.php: they register hooks at file scope, and
 * Brain Monkey's add_action() and add_filter() exist only inside a test. The
 * shortcode classes are definitions only, so they load as is.
 */
require_once __DIR__.'/../includes/class-uams-syndicate-shortcode-base.php';
require_once __DIR__.'/../includes/class-uams-syndicate-shortcode-news.php';
