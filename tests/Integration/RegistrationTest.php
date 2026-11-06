<?php

declare(strict_types=1);

namespace UAMS\ContentSyndication\Tests\Integration;

use UAMS\ContentSyndication\Tests\Support\IntegrationTestCase;
use UAMS_Syndicate_Shortcode_News;

/**
 * Verify registrations made by the real plugin bootstrap, not a manual instance.
 *
 * @command  tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml tests/Integration/RegistrationTest.php
 */
final class RegistrationTest extends IntegrationTestCase
{
    /**
     * Pin hook names, priorities, activation timing, and the shortcode callback.
     */
    public function test_bootstrap_registers_real_wordpress_hooks(): void
    {
        // Check registrations left by muplugins_loaded, plugins_loaded and init.
        $this->assertSame(10, has_action('plugins_loaded', 'UAMS\Content_Syndicate\bootstrap'));
        $this->assertSame(10, has_action('init', 'UAMS\Content_Syndicate\activate_shortcodes'));
        $this->assertSame(10, has_action('save_post_post', 'UAMS\Content_Syndicate\clear_local_content_cache'));
        $this->assertSame(10, has_action('save_post_page', 'UAMS\Content_Syndicate\clear_local_content_cache'));
        $this->assertGreaterThanOrEqual(1, did_action('uamswp_content_syndicate_shortcodes'));

        // Check the registered object is also the stylesheet action's callback.
        $this->assertTrue(shortcode_exists('uamswp_news'));
        $this->assertInstanceOf(UAMS_Syndicate_Shortcode_News::class, $this->news());
        $this->assertSame(10, has_action('wp_enqueue_scripts', [$this->news(), 'enqueue_syndication_stylesheet']));
    }

    /**
     * Prove do_shortcode dispatches and a removed registration does not dispatch.
     */
    public function test_shortcode_dispatch_and_missing_host_error(): void
    {
        // Dispatch through WordPress rather than invoking the callback directly.
        $this->assertSame(
            '<!-- uamswp_news ERROR - an empty host was supplied -->',
            do_shortcode('[uamswp_news host=""]')
        );

        // Exercise a negative control, then restore callback identity.
        $handler = $this->news();
        remove_shortcode('uamswp_news');
        try {
            $this->assertSame('[uamswp_news host=""]', do_shortcode('[uamswp_news host=""]'));
        } finally {
            add_shortcode('uamswp_news', [$handler, 'display_shortcode']);
        }
    }

    /**
     * Pin stylesheet enqueueing only when the current post has the shortcode.
     */
    public function test_frontend_hook_enqueues_styles_for_shortcode_post_only(): void
    {
        // Visit a real post without the shortcode and dispatch the frontend hook.
        $plain = self::factory()->post->create(['post_content' => 'Plain content']);
        $this->go_to(get_permalink($plain));
        wp_dequeue_style('uamswp-syndication-news-style');
        do_action('wp_enqueue_scripts');
        $this->assertFalse(wp_style_is('uamswp-syndication-news-style', 'enqueued'));

        // Visit a real shortcode post and verify the registered stylesheet URL.
        $syndicated = self::factory()->post->create(['post_content' => '[uamswp_news]']);
        $this->go_to(get_permalink($syndicated));
        try {
            do_action('wp_enqueue_scripts');
            $this->assertTrue(wp_style_is('uamswp-syndication-news-style', 'enqueued'));
            $this->assertStringEndsWith('/css/uamswp-syndication-news.css', wp_styles()->registered['uamswp-syndication-news-style']->src);
        } finally {
            wp_dequeue_style('uamswp-syndication-news-style');
        }
    }
}
