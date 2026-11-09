<?php

/**
 * Pin the public bootstrap and invalidation callbacks without booting WordPress.
 *
 * @command  vendor/bin/pest tests/Unit/BootstrapTest.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Symfony\Component\Process\Process;

use function Brain\Monkey\Actions\expectDone;
use function UAMS\Content_Syndicate\activate_shortcodes;
use function UAMS\Content_Syndicate\bootstrap;
use function UAMS\Content_Syndicate\clear_local_content_cache;

beforeEach(function (): void {
    // Load definitions once while Brain Monkey owns registration functions.
    if (! function_exists('UAMS\Content_Syndicate\bootstrap')) {
        require __DIR__.'/../../includes/content-syndicate.php';
        expect(has_action('plugins_loaded', 'UAMS\Content_Syndicate\bootstrap'))->toBe(10);
    }
});

/**
 * The eight notice-body mutants inside PHP_VERSION < 5.3 are equivalent for
 * every supported PHP (8.2+): no shortcode input can enter that branch. The
 * notice expression alone is ignored; negating its version predicate must
 * still fail this test. The legacy notice remains in the shipped plugin.
 */
it('loads through the plugin entry point on supported PHP', function (): void {
    // Supply the WordPress bootstrap marker without booting WordPress itself.
    if (! \defined('WPINC')) {
        define('WPINC', 'wp-includes');
    }

    // Verify the entry point returns normally without an obsolete PHP notice.
    require __DIR__.'/../../uamswp-content-syndication.php';

    expect(has_action('admin_notices'))->toBeFalse();
});

it('stops direct access before plugin registration', function (): void {
    // Use a fresh PHP process where WordPress has not defined WPINC.
    $plugin = var_export(realpath(__DIR__.'/../../uamswp-content-syndication.php'), true);
    $process = new Process([PHP_BINARY, '-r', "require {$plugin}; echo 'entry-returned';"], timeout: 10);
    $process->run();

    // A removed guard reaches add_action and fails instead of exiting cleanly.
    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('')
        ->and($process->getErrorOutput())->toBe('');
});

it('registers activation and cache invalidation on the expected hooks', function (): void {
    bootstrap();

    expect(has_action('init', 'UAMS\Content_Syndicate\activate_shortcodes'))->toBe(10)
        ->and(has_action('save_post_post', 'UAMS\Content_Syndicate\clear_local_content_cache'))->toBe(10)
        ->and(has_action('save_post_page', 'UAMS\Content_Syndicate\clear_local_content_cache'))->toBe(10);
});

it('activates the public News shortcode and extension hook', function (): void {
    Functions\expect('add_shortcode')->once()->with('uamswp_news', Mockery::on(
        fn (mixed $callback): bool => \is_array($callback)
            && $callback[0] instanceof UAMS_Syndicate_Shortcode_News
            && $callback[1] === 'display_shortcode',
    ));
    expectDone('uamswp_content_syndicate_shortcodes')->once();

    activate_shortcodes();
});

it('invalidates the local cache with a fresh generation token', function (): void {
    // Bound the token to this call instead of replacing PHP's clock.
    $before = microtime(true);
    Functions\expect('wp_cache_set')->once()
        ->with('last_changed', Mockery::on(function (mixed $token) use ($before): bool {
            if (! \is_string($token) || preg_match('/^0\.\d{8} \d+$/', $token) !== 1) {
                return false;
            }

            [$fraction, $seconds] = explode(' ', $token);
            $time = (float) $fraction + (int) $seconds;

            return $time >= $before && $time <= microtime(true);
        }), 'uamswp-content');

    clear_local_content_cache();
});
