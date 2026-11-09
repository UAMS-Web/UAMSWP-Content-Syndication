<?php

declare(strict_types=1);

namespace UAMS\ContentSyndication\Tests\Integration;

use ErrorException;
use UAMS\ContentSyndication\Tests\Support\IntegrationTestCase;
use WP_REST_Request;

/**
 * Characterize dispatch through the native REST server and real object cache.
 *
 * @command  tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml tests/Integration/NewsRestTest.php
 */
final class NewsRestTest extends IntegrationTestCase
{
    /**
     * Pin anonymous published-post results, author lookup, cache hits and saves.
     */
    public function test_local_rest_results_are_cached_until_a_post_is_saved(): void
    {
        // Seed public and private content for an anonymous REST collection.
        $author = self::factory()->user->create(['display_name' => 'Fixture Author']);
        $post = self::factory()->post->create([
            'post_title' => 'Published fixture',
            'post_status' => 'publish',
            'post_author' => $author,
        ]);
        self::factory()->post->create(['post_title' => 'Hidden draft', 'post_status' => 'draft']);
        wp_set_current_user(0);
        $originalBlog = get_current_blog_id();
        $originalUser = get_current_user_id();
        $requests = [];
        $observer = static function (
            mixed $response,
            mixed $server,
            WP_REST_Request $request
        ) use (&$requests): mixed {
            $requests[] = $request->get_route();

            return $response;
        };
        add_filter('rest_pre_dispatch', $observer, 10, 3);

        // Dispatch locally and verify public data, blog and user context.
        $shortcode = '[uamswp_news site="'.home_url('/').'" count="10"]';
        $first = do_shortcode($shortcode);
        $this->assertStringContainsString('Published fixture', $first);
        $this->assertStringContainsString('Fixture Author', $first);
        $this->assertStringNotContainsString('Hidden draft', $first);
        $this->assertContains('/wp/v2/posts', $requests);
        $this->assertContains('/wp/v2/users/'.$author, $requests);
        $this->assertSame($originalBlog, get_current_blog_id());
        $this->assertSame($originalUser, get_current_user_id());

        // Render again and prove no additional REST dispatch occurred.
        $firstRequests = $requests;
        $this->assertSame($first, do_shortcode($shortcode));
        $this->assertSame($firstRequests, $requests);

        // Save through WordPress and prove both data and generation are refreshed.
        wp_cache_set('last_changed', 'before-save', 'uamswp-content');
        wp_update_post(['ID' => $post, 'post_title' => 'Updated fixture']);
        $this->assertNotSame('before-save', wp_cache_get('last_changed', 'uamswp-content'));
        $updated = do_shortcode($shortcode);
        $this->assertStringContainsString('Updated fixture', $updated);
        $this->assertStringNotContainsString('Published fixture', $updated);
        $this->assertGreaterThan(\count($firstRequests), \count($requests));
    }

    /**
     * Pin anonymous dispatch: a logged-in viewer sees no more than a visitor would.
     */
    public function test_local_rest_dispatches_anonymously_for_a_logged_in_viewer(): void
    {
        // View as an administrator, who could read the draft through REST directly.
        self::factory()->post->create(['post_title' => 'Public fixture', 'post_status' => 'publish']);
        self::factory()->post->create(['post_title' => 'Private draft', 'post_status' => 'draft']);
        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $this->assertTrue(current_user_can('edit_posts'));

        // A draft-status query is refused for a visitor, so it renders nothing for the
        // administrator too, while a public collection still renders.
        $drafts = do_shortcode('[uamswp_news site="'.home_url('/').'" query="posts?status=publish,draft"]');
        $this->assertStringContainsString(' = [];', $drafts);
        $this->assertStringNotContainsString('Private draft', $drafts);
        $public = do_shortcode('[uamswp_news site="'.home_url('/').'"]');
        $this->assertStringContainsString('Public fixture', $public);
        $this->assertStringNotContainsString('Private draft', $public);
        $this->assertSame($admin, get_current_user_id());
    }

    /**
     * Pin the global-post leak caused by dispatching a local REST collection.
     */
    public function test_local_rest_currently_replaces_the_callers_global_post(): void
    {
        // Visit a distinct caller page before rendering the post collection.
        $post = self::factory()->post->create(['post_title' => 'REST result']);
        $page = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Caller page']);
        $this->go_to(get_permalink($page));
        $originalPost = $GLOBALS['post'];

        // Assert the known leak, then restore the fixture state explicitly.
        try {
            $this->assertSame($page, $originalPost->ID);
            $this->assertStringContainsString('REST result', do_shortcode('[uamswp_news site="'.home_url('/').'"]'));
            $this->assertSame($post, $GLOBALS['post']->ID);
            $this->assertNotSame($originalPost, $GLOBALS['post']);
        } finally {
            $GLOBALS['post'] = $originalPost;
        }
    }

    /**
     * Pin page-save invalidation and its negative control with the hook removed.
     */
    public function test_page_save_invalidation_depends_on_registered_hook(): void
    {
        // Verify a real page write invalidates the plugin cache generation.
        wp_cache_set('last_changed', 'initial-page', 'uamswp-content');
        $page = self::factory()->post->create(['post_type' => 'page']);
        $this->assertNotSame('initial-page', wp_cache_get('last_changed', 'uamswp-content'));

        // Remove only the plugin hook and prove saving no longer invalidates it.
        remove_action('save_post_page', 'UAMS\Content_Syndicate\clear_local_content_cache');
        try {
            wp_cache_set('last_changed', 'without-hook', 'uamswp-content');
            wp_update_post(['ID' => $page, 'post_title' => 'Page update']);
            $this->assertSame('without-hook', wp_cache_get('last_changed', 'uamswp-content'));
        } finally {
            add_action('save_post_page', 'UAMS\Content_Syndicate\clear_local_content_cache');
        }
    }

    /**
     * Pin lack of invalidation for custom post types.
     */
    public function test_custom_post_save_currently_does_not_invalidate(): void
    {
        // Register a real REST-enabled post type and save through the factory.
        register_post_type('synd_fixture', ['public' => true, 'show_in_rest' => true]);
        try {
            wp_cache_set('last_changed', 'custom-post', 'uamswp-content');
            self::factory()->post->create(['post_type' => 'synd_fixture']);
            $this->assertSame('custom-post', wp_cache_get('last_changed', 'uamswp-content'));
        } finally {
            unregister_post_type('synd_fixture');
        }
    }

    /**
     * Pin cached empty collections without issuing a second REST request.
     */
    public function test_empty_local_results_are_cached(): void
    {
        // Count real REST dispatches for an empty collection.
        $calls = 0;
        add_filter('rest_pre_dispatch', static function (mixed $response) use (&$calls): mixed {
            $calls++;

            return $response;
        });
        $shortcode = '[uamswp_news site="'.home_url('/').'"]';
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertSame(1, $calls);

        // Verify the empty array is a cache hit, not a falsy cache miss.
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertSame(1, $calls);
    }

    /**
     * Pin missing REST routes as uncached empty results.
     */
    public function test_local_rest_error_is_retried(): void
    {
        // Observe a nonexistent route without replacing the REST server response.
        $calls = 0;
        add_filter('rest_pre_dispatch', static function (mixed $response) use (&$calls): mixed {
            $calls++;

            return $response;
        });
        $shortcode = '[uamswp_news site="'.home_url('/').'" query="posts/999999999"]';

        // Verify a real 404 produces empty output but never a successful cache hit.
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertSame(2, $calls);
    }

    /**
     * Pin the current dependency on pretty permalinks for local REST requests.
     */
    public function test_plain_permalinks_currently_return_no_local_posts(): void
    {
        // Disable pretty permalinks and prove native REST still has the fixture.
        $this->set_permalink_structure('');
        $post = self::factory()->post->create(['post_title' => 'Plain permalink fixture']);
        $response = rest_do_request(new WP_REST_Request('GET', '/wp/v2/posts'));
        $this->assertSame(200, $response->get_status());
        $this->assertSame($post, $response->get_data()[0]['id']);

        // Pin rejection of the hard-coded pretty URL and the shortcode's result.
        $this->assertFalse(WP_REST_Request::from_url(home_url('/wp-json/wp/v2/posts')));
        $output = do_shortcode('[uamswp_news site="'.home_url('/').'"]');
        $this->assertStringContainsString(' = [];', $output);
        $this->assertStringNotContainsString('Plain permalink fixture', $output);
    }

    /**
     * Pin the legacy filter query parameters being ignored by native WP REST.
     */
    public function test_legacy_category_filter_currently_does_not_filter_local_posts(): void
    {
        // Create a post outside the nonexistent requested category.
        self::factory()->post->create(['post_title' => 'Unfiltered local fixture']);

        // Exercise the plugin URL builder through the native collection endpoint.
        $output = do_shortcode('[uamswp_news site="'.home_url('/').'" site_category_slug="missing-fixture-category"]');
        $this->assertStringContainsString('Unfiltered local fixture', $output);
    }

    /**
     * Pin the current local-headlines array/object mismatch as a known failure.
     */
    public function test_local_headlines_currently_reads_array_response_as_object(): void
    {
        // Seed a nonempty native REST collection to reach the broken branch.
        self::factory()->post->create();

        // Turn the native warning into an asserted exception, not hidden noise.
        set_error_handler(static function (
            int $severity,
            string $message
        ): never {
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING);

        // Dispatch the real request and restore the framework error handler.
        try {
            $this->expectException(ErrorException::class);
            $this->expectExceptionMessage('Attempt to read property');
            do_shortcode('[uamswp_news site="'.home_url('/').'" output="headlines"]');
        } finally {
            restore_error_handler();
        }
    }
}
