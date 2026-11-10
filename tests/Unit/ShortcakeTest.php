<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function Brain\Monkey\Actions\expectAdded;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Isolate optional-plugin presence because PHP cannot unload a class alias.
 *
 * PHPUnit attributes provide process isolation for this test under Pest.
 *
 * @command  vendor/bin/pest tests/Unit/ShortcakeTest.php
 */
final class ShortcakeTest extends TestCase
{
    /**
     * Pin editor registration without leaking Shortcake presence to other tests.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_optional_shortcake_hooks_and_editor_assets(): void
    {
        // Start a fresh mock scope in the isolated process.
        setUp();
        try {
            expect('add_shortcode')->once()->with('uamswp_news', Mockery::type('array'));
            when('plugins_url')->returnArg();
            expect('add_editor_style')->once()->with('/css/uams-syndication-admin.css');
            Mockery::mock('alias:UAMS_Shortcakes');
            $enqueue = null;
            expectAdded('enqueue_shortcode_ui')->once()->with(Mockery::on(function (mixed $callback) use (&$enqueue): bool {
                $enqueue = $callback;

                return $callback instanceof Closure;
            }));

            // Verify the public hook and invoke its captured editor callback.
            $news = new UAMS_Syndicate_Shortcode_News;
            $this->assertSame(10, has_action('admin_init', [$news, 'build_shortcake']));
            expect('wp_enqueue_script')->once()->with('uams_syndications_editor_js', '/js/uams-syndication-shortcake.js');
            $enqueue();
            $this->assertGreaterThan(0, Mockery::getContainer()->mockery_getExpectationCount());
        } finally {
            // Restore mocks even if an assertion fails; the class dies with PHP.
            tearDown();
        }
    }
}
