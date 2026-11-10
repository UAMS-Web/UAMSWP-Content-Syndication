<?php

declare(strict_types=1);

namespace UAMS\ContentSyndication\Tests\Integration;

use UAMS\ContentSyndication\Tests\Support\IntegrationTestCase;

/**
 * Pin network routing and the restored switches inside core's get_home_url().
 *
 * @command  WP_MULTISITE=1 tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml tests/Integration/MultisiteRoutingTest.php
 */
final class MultisiteRoutingTest extends IntegrationTestCase
{
    /**
     * Pin the same-site route on a subsite, including the hard-coded site ID.
     */
    public function test_current_subsite_is_local_but_currently_reports_site_id_one(): void
    {
        // Require a real network rather than pretending single-site can switch.
        if (! is_multisite()) {
            $this->markTestSkipped('Requires WP_MULTISITE=1.');
        }

        $origin = get_current_blog_id();
        $site = self::factory()->blog->create(['path' => '/synd-fixture/']);
        $this->assertIsInt($site);
        switch_to_blog($site);

        // Build the subsite request and prove the plugin leaves the switch alone.
        try {
            $home = home_url('/');
            $atts = $this->news()->process_attributes(['site' => $home]);
            $stack = $GLOBALS['_wp_switched_stack'];
            $siteUrl = $this->news()->get_request_url($atts);
            $this->assertIsArray($siteUrl);
            $request = $this->news()->build_initial_request($siteUrl, $atts);
            $this->assertSame('local', $request['scheme']);
            $this->assertSame(1, $request['site_id']);
            $this->assertNotSame($site, $request['site_id']);
            $this->assertSame($home.'wp-json/wp/v2/posts', $request['url']);

            // Dispatch against the actual subsite despite the informational ID.
            update_option('permalink_structure', '/%postname%/');
            self::factory()->post->create(['post_title' => 'Current subsite fixture']);
            $user = get_current_user_id();
            $output = do_shortcode('[uamswp_news site="'.$home.'"]');
            $this->assertStringContainsString('Current subsite fixture', $output);
            $this->assertSame($user, get_current_user_id());

            // Verify the plugin did not consume the caller's existing switch.
            $this->assertSame($site, get_current_blog_id());
            $this->assertSame($stack, $GLOBALS['_wp_switched_stack']);
        } finally {
            restore_current_blog();
        }

        // Verify the fixture's own explicit switch was fully restored.
        $this->assertSame($origin, get_current_blog_id());
        $this->assertFalse(ms_is_switched());
    }

    /**
     * Pin sibling discovery using actual network tables and HTTP dispatch.
     */
    public function test_sibling_site_uses_http_and_restores_blog_user_and_post(): void
    {
        // Create a sibling and establish the origin's post, user and switch stack.
        if (! is_multisite()) {
            $this->markTestSkipped('Requires WP_MULTISITE=1.');
        }

        $origin = get_current_blog_id();
        $site = self::factory()->blog->create(['path' => '/synd-sibling/']);
        $this->assertIsInt($site);
        $home = get_home_url($site, '/');
        $post = self::factory()->post->create();
        $this->assertIsInt($post);
        $user = self::factory()->user->create();
        $this->assertIsInt($user);
        wp_set_current_user($user);
        $permalink = get_permalink($post);
        $this->assertIsString($permalink);
        $this->go_to($permalink);
        $originalPost = $GLOBALS['post'];
        $stack = $GLOBALS['_wp_switched_stack'];
        $switches = [];
        $requests = [];
        add_action('switch_blog', static function (int $blog) use (&$switches): void {
            $switches[] = $blog;
        });
        add_filter('pre_http_request', static function (
            mixed $preempt,
            array $args,
            string $url
        ) use (&$requests): array {
            $requests[] = $url;

            return ['headers' => [], 'body' => '[]', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
        }, 10, 3);

        // Verify sibling discovery does not choose the internal REST branch.
        $atts = $this->news()->process_attributes(['site' => $home]);
        $siteUrl = $this->news()->get_request_url($atts);
        $this->assertIsArray($siteUrl);
        $request = $this->news()->build_initial_request($siteUrl, $atts);
        $this->assertSame($site, (int) $request['site_id']);
        $this->assertSame('http', $request['scheme']);
        $this->assertSame($home.'wp-json/wp/v2/posts', $request['url']);
        $this->assertStringContainsString(' = [];', do_shortcode('[uamswp_news site="'.$home.'"]'));
        $this->assertSame([$home.'wp-json/wp/v2/posts?_embed'], $requests);

        // Pin core's paired home-URL switches for the build and dispatch calls.
        $this->assertSame([$site, $origin, $site, $origin], $switches);

        // Verify each core switch restored the caller's global state.
        $this->assertSame($origin, get_current_blog_id());
        $this->assertSame($stack, $GLOBALS['_wp_switched_stack']);
        $this->assertSame($user, get_current_user_id());
        $this->assertSame($originalPost, $GLOBALS['post']);
        $this->assertFalse(ms_is_switched());
    }
}
