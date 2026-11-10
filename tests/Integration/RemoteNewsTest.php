<?php

declare(strict_types=1);

namespace UAMS\ContentSyndication\Tests\Integration;

use UAMS\ContentSyndication\Tests\Support\IntegrationTestCase;
use WP_Error;

/**
 * Exercise the real HTTP API boundary without contacting external sites.
 *
 * @command  tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml tests/Integration/RemoteNewsTest.php
 */
final class RemoteNewsTest extends IntegrationTestCase
{
    /**
     * Pin remote response caching, cache-bust partitioning, and local-save scope.
     */
    public function test_remote_cache_survives_local_save_and_changes_with_attributes(): void
    {
        // Answer only this test's external hostname through the real HTTP API.
        $requests = [];
        add_filter('pre_http_request', function (
            mixed $preempt,
            array $args,
            string $url
        ) use (&$requests): mixed {
            if (! str_starts_with($url, 'https://remote.example/')) {
                return $preempt;
            }

            $requests[] = $url;

            return $this->response($this->postPayload());
        }, 10, 3);
        $shortcode = '[uamswp_news site="https://remote.example/" count="1"]';

        // Render twice and prove the second pass reads the real object cache.
        $first = do_shortcode($shortcode);
        $this->assertStringContainsString('Remote fixture', $first);
        $this->assertSame($first, do_shortcode($shortcode));
        $this->assertCount(1, $requests);
        $this->assertStringContainsString('/wp-json/wp/v2/posts?per_page=1', $requests[0]);
        $cached = $this->news()->get_content_cache(
            $this->news()->process_attributes(['site' => 'https://remote.example/', 'count' => '1']),
            'uamswp_news'
        );
        $this->assertIsArray($cached);
        $this->assertCount(1, $cached);

        // Save a local post and prove remote data is not invalidated with it.
        self::factory()->post->create();
        $this->assertSame($first, do_shortcode($shortcode));
        $this->assertCount(1, $requests);

        // Change a documented cache-busting attribute to force a new HTTP call.
        do_shortcode('[uamswp_news site="https://remote.example/" count="1" cache_bust="new-generation"]');
        $this->assertCount(2, $requests);
    }

    /**
     * Pin empty remote collections as cacheable responses.
     */
    public function test_empty_remote_response_is_cached(): void
    {
        // Count intercepted external calls while providing an empty collection.
        $calls = 0;
        add_filter('pre_http_request', function () use (&$calls): array {
            $calls++;

            return $this->response('[]');
        });
        $shortcode = '[uamswp_news site="https://remote.example/empty/"]';

        // Verify both empty output and a true cache hit.
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertSame(1, $calls);
    }

    /**
     * Pin real shortcode and output-filter dispatch with attributes intact.
     */
    public function test_output_filters_receive_processed_remote_data(): void
    {
        // Supply a deterministic remote collection through WordPress HTTP hooks.
        add_filter('pre_http_request', fn (): array => $this->response($this->postPayload()));
        $observed = [];
        add_filter('uamswp_content_syndicate_news_output', static function (
            mixed $content,
            array $data,
            array $atts
        ) use (&$observed): string {
            $first = array_values($data)[0];
            $observed = [$content, $first instanceof \stdClass ? $first->title : null, $atts['count']];

            return 'custom-output';
        }, 10, 3);
        add_filter('uamswp_content_syndicate_news', static fn (string $content): string => $content.'-filtered');

        // Verify the override bypasses HTML generation and the final filter runs.
        $this->assertSame('custom-output-filtered', do_shortcode('[uamswp_news site="https://remote.example/" count="1"]'));
        $this->assertSame([false, 'Remote fixture', '1'], $observed);
    }

    /**
     * Pin remote-headlines fields in the actual generated request.
     */
    public function test_headlines_requests_title_date_and_link(): void
    {
        // Observe the request while providing a complete remote fixture.
        $query = [];
        add_filter('pre_http_request', function (
            mixed $preempt,
            array $args,
            string $url
        ) use (&$query): array {
            wp_parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

            return $this->response($this->postPayload());
        }, 10, 3);

        // Pin both valid rendering and accumulation of repeated field parameters.
        $output = do_shortcode('[uamswp_news site="https://remote.example/" output="headlines"]');
        $this->assertStringContainsString('Remote fixture</a>', $output);
        $this->assertSame(['title', 'date', 'link'], $query['_fields']);
    }

    /**
     * Pin empty output and retries after a transport failure.
     */
    public function test_transport_error_is_not_cached(): void
    {
        // Return a real WordPress transport error from the HTTP boundary.
        $calls = 0;
        add_filter('pre_http_request', static function () use (&$calls): WP_Error {
            $calls++;

            return new WP_Error('fixture_transport', 'Offline fixture');
        });

        // Verify failures render empty output and retry rather than caching it.
        $shortcode = '[uamswp_news site="https://remote.example/offline/"]';
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertSame(2, $calls);
    }

    /**
     * Pin handling of HTTP status codes and malformed response bodies.
     *
     * @param  int  $status  HTTP response status.
     * @param  string  $body  Upstream body.
     * @param  int  $expectedCalls  Expected HTTP calls across two renders.
     *
     * @dataProvider responseFailures
     */
    public function test_http_status_and_invalid_json_cache_behavior(
        int $status,
        string $body,
        int $expectedCalls
    ): void {
        // Supply the failing response through the actual WordPress HTTP API.
        $calls = 0;
        add_filter('pre_http_request', function () use (
            &$calls,
            $status,
            $body
        ): array {
            $calls++;

            return ['headers' => [], 'body' => $body, 'response' => ['code' => $status, 'message' => 'OK']];
        });

        // Pin which failures are retried and which become cached empty arrays.
        $shortcode = '[uamswp_news site="https://remote.example/failure/"]';
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertStringContainsString(' = [];', do_shortcode($shortcode));
        $this->assertSame($expectedCalls, $calls);
    }

    /**
     * Enumerate existing response behavior, including cacheable server errors.
     *
     * @return array<string, array{int, string, int}> Response fixtures.
     */
    public static function responseFailures(): array
    {
        return [
            '404 retries' => [404, '[]', 2],
            '500 currently caches empty data' => [500, 'not-json', 1],
            'malformed success currently caches empty data' => [200, 'not-json', 1],
        ];
    }

    /**
     * Return an HTTP API response with a deterministic status and JSON body.
     *
     * @param  string  $body  Encoded remote collection.
     * @return array<string, mixed> WordPress HTTP response.
     */
    private function response(string $body): array
    {
        return [
            'headers' => [],
            'body' => $body,
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
        ];
    }

    /**
     * Return a minimal remote JSON collection that the non-headline branch uses.
     *
     * @return string Encoded remote fixture.
     */
    private function postPayload(): string
    {
        return (string) wp_json_encode([[
            'id' => 701,
            'date' => '2026-01-02T03:04:05',
            'modified' => '2026-01-03T03:04:05',
            'link' => 'https://remote.example/fixture/',
            'title' => ['rendered' => 'Remote fixture'],
            'content' => ['rendered' => '<p>Remote body</p>'],
            'excerpt' => ['rendered' => '<p>Remote excerpt</p>'],
            'featured_media' => 0,
        ]]);
    }
}
