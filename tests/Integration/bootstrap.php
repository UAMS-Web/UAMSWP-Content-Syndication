<?php

/**
 * Boot real WordPress from the integration suite's isolated Composer setup.
 */

declare(strict_types=1);

// Refuse a missing isolated autoloader instead of loading the root PHPUnit.
if (! is_readable(__DIR__.'/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install --working-dir=tests/Integration first.\n");
    exit(1);
}

// Load PHPUnit 9.6 and the WordPress test-library environment.
require_once __DIR__.'/vendor/autoload.php';
$wordpress = rtrim((string) (getenv('WP_TESTS_ABSPATH') ?: dirname(__DIR__, 5)), '/').'/';
putenv('WP_TESTS_ABSPATH='.$wordpress);

// Require a readable WordPress core fixture.
if (! is_readable($wordpress.'wp-settings.php')) {
    fwrite(STDERR, "No WordPress fixture at {$wordpress}; set WP_TESTS_ABSPATH.\n");
    exit(1);
}

// Allow only the explicitly disposable database, never an arbitrary override.
$testDatabase = (string) (getenv('WP_TESTS_DB_NAME') ?: 'syndication_tests');
if ($testDatabase !== 'syndication_tests') {
    fwrite(STDERR, "Refusing to rebuild any database except syndication_tests.\n");
    exit(1);
}

// Require a clean core copy, rejecting both supported site-config locations.
foreach ([$wordpress.'wp-config.php', dirname($wordpress).'/wp-config.php'] as $liveConfig) {
    if (file_exists($liveConfig) || is_link($liveConfig)) {
        fwrite(STDERR, "Refusing a configured WordPress fixture at {$liveConfig}; use a clean core copy.\n");
        exit(1);
    }
}

// Use this suite's configuration and register the plugin before plugins_loaded.
define('WP_TESTS_CONFIG_FILE_PATH', __DIR__.'/wp-tests-config.php');
$wpPhpunit = (string) getenv('WP_PHPUNIT__DIR');
require_once $wpPhpunit.'/includes/functions.php';
tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__, 2).'/uamswp-content-syndication.php';
});

// Boot WordPress and then load its thin integration base case.
require $wpPhpunit.'/includes/bootstrap.php';
require_once __DIR__.'/../Support/IntegrationTestCase.php';
