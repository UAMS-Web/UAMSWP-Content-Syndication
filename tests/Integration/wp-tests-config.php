<?php

/**
 * Disposable WordPress test configuration, never a deployment configuration.
 */

declare(strict_types=1);

// Select the clean core fixture and the only permitted disposable database.
define('ABSPATH', rtrim((string) (getenv('WP_TESTS_ABSPATH') ?: dirname(__DIR__, 5)), '/').'/');
define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'syndication_tests');
define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'syndication_tests');
define('DB_PASSWORD', getenv('WP_TESTS_DB_PASSWORD') ?: 'syndication_disposable');
define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: '127.0.0.1:3307');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'wptests_';

// Give the fixture deterministic site metadata and block external HTTP.
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Content Syndication Tests');
define('WP_PHP_BINARY', 'php');
define('WP_HTTP_BLOCK_EXTERNAL', true);
