<?php

/**
 * UAMS_Syndicate_Shortcode_Base request building and input sanitizing, with
 * WordPress mocked.
 *
 * @command  vendor/bin/pest tests/Unit/ShortcodeBaseTest.php
 *
 * @see includes/class-uams-syndicate-shortcode-base.php
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\when;

covers(UAMS_Syndicate_Shortcode_Base::class);

/**
 * The base shortcode under test, set by `beforeEach` and read by each test.
 *
 * Pest binds test state to `$this`, which static analysis types as `mixed`;
 * a typed accessor keeps every call on the real class.
 *
 * @param  UAMS_Syndicate_Shortcode_Base|null  $set  A new instance to keep, or null to read.
 * @return UAMS_Syndicate_Shortcode_Base The instance for the running test.
 */
function baseShortcode(?UAMS_Syndicate_Shortcode_Base $set = null): UAMS_Syndicate_Shortcode_Base
{
    static $current = null;

    if ($set instanceof UAMS_Syndicate_Shortcode_Base) {
        $current = $set;
    }

    if (! $current instanceof UAMS_Syndicate_Shortcode_Base) {
        throw new LogicException('No base shortcode was created for this test.');
    }

    return $current;
}

beforeEach(function (): void {
    stubEscapeFunctions();

    // The three WordPress URL helpers the class leans on, as WordPress defines
    // them for the inputs below.
    when('trailingslashit')->alias(fn (string $value): string => rtrim($value, '/\\').'/');
    when('wp_parse_url')->alias(fn (string $url, int $component = -1): mixed => parse_url($url, $component));
    when('is_multisite')->justReturn(false);

    baseShortcode(new UAMS_Syndicate_Shortcode_Base);
});

it('keeps only integers from a list of ids', function (string $input, string $expected): void {
    expect(baseShortcode()->sanitized_ids($input))->toBe($expected);
})->with([
    'plain list' => ['1,2,3', '1,2,3'],
    'text becomes zero' => ['12,abc,7', '12,0,7'],
    'decimals are truncated' => ['4.9', '4'],
    'empty input is a single zero' => ['', '0'],
]);

it('passes each comma-separated term to sanitize_key and joins the results', function (): void {
    // Each term reaches sanitize_key untrimmed, in order; the class adds no
    // cleaning of its own.
    Functions\expect('sanitize_key')
        ->times(3)
        ->andReturnUsing(fn (string $key): string => 'k('.$key.')');

    expect(baseShortcode()->sanitized_terms('news, press,x'))
        ->toBe('k(news),k( press),k(x)');
});

it('takes the host from the site attribute ahead of the host attribute', function (): void {
    $url = baseShortcode()->get_request_url([
        'site' => 'https://example.org/blog',
        'host' => 'news.uams.edu',
    ]);

    expect(is_array($url) ? $url['host'] : null)->toBe('example.org')
        ->and(is_array($url) ? $url['path'] : null)->toBe('/blog/');
});

it('falls back to the host attribute', function (): void {
    $url = baseShortcode()->get_request_url(['site' => '', 'host' => 'https://news.uams.edu']);

    expect(is_array($url) ? $url['host'] : null)->toBe('news.uams.edu');
});

it('returns false when no host can be read from the attributes', function (): void {
    expect(baseShortcode()->get_request_url(['site' => '', 'host' => '']))->toBeFalse();
});

it('requests a remote host over https', function (): void {
    when('get_home_url')->justReturn('https://www.uams.edu');

    $request = baseShortcode()->build_initial_request(
        ['host' => 'news.uams.edu', 'path' => '/'],
        ['scheme' => 'https', 'query' => 'posts'],
    );

    expect($request)->toBe([
        'url' => 'https://news.uams.edu/wp-json/wp/v2/posts',
        'scheme' => 'https',
        'site_id' => false,
    ]);
});

it('uses the home URL scheme and marks the scheme local when the requested site is the requesting site', function (): void {
    when('get_home_url')->justReturn('http://dev.example.org');

    $request = baseShortcode()->build_initial_request(
        ['host' => 'dev.example.org', 'path' => '/'],
        ['scheme' => 'https', 'query' => 'posts'],
    );

    expect($request)->toBe([
        'url' => 'http://dev.example.org/wp-json/wp/v2/posts',
        'scheme' => 'local',
        'site_id' => 1,
    ]);
});

it('keeps the complete base defaults and empty extension behavior', function (): void {
    expect(baseShortcode()->defaults_atts)->toBe([
        'object' => 'json_data', 'output' => 'json', 'host' => 'news.uams.edu',
        'scheme' => 'https', 'site' => '', 'site_category_slug' => '',
        'advanced_cat' => '', 'tag' => '', 'style' => '', 'query' => 'posts',
        'local_count' => 0, 'count' => false, 'date_format' => 'F j, Y',
        'offset' => 0, 'cache_bust' => '', 'id' => '',
    ])->and(baseShortcode()->local_default_atts)->toBe([])
        ->and(baseShortcode()->local_extended_atts)->toBe([])
        ->and(baseShortcode()->shortcode_name)->toBe('')
        ->and(baseShortcode()->default_path)->toBe('wp-json/wp/v2/')
        ->and(baseShortcode()->display_shortcode([]))->toBe('');

    // Exercise the base no-op registration without requiring a subclass.
    baseShortcode()->construct();
});

it('lets a concrete shortcode register through the base constructor', function (): void {
    Functions\expect('add_shortcode')->once()->with('uamswp_news', Mockery::on(
        fn (mixed $callback): bool => \is_array($callback)
            && $callback[0] instanceof UAMS_Syndicate_Shortcode_News
            && $callback[1] === 'display_shortcode',
    ));

    new UAMS_Syndicate_Shortcode_News;
});

it('merges filtered defaults and extension-only local attributes without accepting unknown keys', function (): void {
    // Model WordPress attribute selection, including its final named filter.
    when('shortcode_atts')->alias(function (array $defaults, array $atts, string $shortcode = ''): array {
        $result = array_replace($defaults, array_intersect_key($atts, $defaults));

        return $shortcode === 'probe' ? array_replace($result, ['style' => 'filtered-style']) : $result;
    });

    // Give each precedence layer a distinct value and one extension key.
    expectApplied('uamswp_content_syndicate_default_atts')->once()
        ->andReturnUsing(fn (array $defaults): array => array_replace($defaults, ['count' => 7, 'extra' => 'filtered']));
    baseShortcode()->shortcode_name = 'probe';
    baseShortcode()->local_default_atts = ['count' => 9, 'unknown' => 'ignored'];
    baseShortcode()->local_extended_atts = ['extra' => 'extended', 'color' => 'red'];

    // Assert precedence, local extension generation, and final filter output.
    $result = baseShortcode()->process_attributes(['count' => 3, 'local_color' => 'blue', 'unknown' => 'ignored']);
    expect($result)->toBe([
        'object' => 'json_data', 'output' => 'json', 'host' => 'news.uams.edu',
        'scheme' => 'https', 'site' => '', 'site_category_slug' => '',
        'advanced_cat' => '', 'tag' => '', 'style' => 'filtered-style', 'query' => 'posts',
        'local_count' => 0, 'count' => 3, 'date_format' => 'F j, Y',
        'offset' => 0, 'cache_bust' => '', 'id' => '', 'extra' => 'extended',
        'color' => 'red', 'local_extra' => 'extended', 'local_color' => 'blue',
    ]);
});

it('isolates cached content by shortcode and all attributes including cache bust', function (): void {
    // Keep an in-memory WordPress cache with the same group/key separation.
    $cache = [];
    when('wp_cache_set')->alias(function (string $key, mixed $value, string $group, int $expiry) use (&$cache): void {
        expect($expiry)->toBe(600);
        $cache[$group][$key] = $value;
    });
    when('wp_cache_get')->alias(function (string $key, string $group) use (&$cache): mixed {
        return $cache[$group][$key] ?? false;
    });

    // Read each value back through the public API rather than its hash helper.
    $atts = ['count' => 2, 'cache_bust' => 'a'];
    $other = ['count' => 3, 'cache_bust' => 'a'];
    baseShortcode()->set_content_cache($atts, 'news', ['first']);
    baseShortcode()->set_content_cache($other, 'news', ['second']);
    baseShortcode()->set_content_cache($atts, 'cards', ['third']);
    expect(baseShortcode()->get_content_cache($atts, 'news'))->toBe(['first'])
        ->and(baseShortcode()->get_content_cache($other, 'news'))->toBe(['second'])
        ->and(baseShortcode()->get_content_cache($atts, 'cards'))->toBe(['third'])
        ->and(baseShortcode()->get_content_cache(['count' => 2, 'cache_bust' => 'b'], 'news'))->toBeFalse();
});

it('adds only populated taxonomy filters after the extension filter', function (): void {
    // Expose query values without duplicating the plugin's decision logic.
    when('add_query_arg')->alias(function (array $args, string $url): string {
        $parts = (array) parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        return ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').($parts['path'] ?? '').'?'.http_build_query(array_replace($query, $args));
    });
    when('sanitize_key')->alias(fn (string $key): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)));
    expectApplied('uamswp_content_syndicate_taxonomy_filters')->twice()
        ->andReturnUsing(fn (string $url): string => $url.'?extension=kept');

    // Preserve the extension query when there are no built-in filters.
    $url = 'https://example.org/posts';
    expect(baseShortcode()->build_taxonomy_filters([], $url))->toBe($url.'?extension=kept');

    // Verify each independent attribute maps to its own WordPress query key.
    $filtered = baseShortcode()->build_taxonomy_filters([
        'advanced_cat' => '-4,9x', 'site_category_slug' => 'Press!,Health',
        'tag' => 'Topic_A,SCIENCE', 'id' => '15,bad',
    ], $url);
    parse_str((string) parse_url($filtered, PHP_URL_QUERY), $query);
    expect($query)->toBe([
        'extension' => 'kept', 'filter' => [
            'cat' => '-4,9', 'category_name' => 'press,health',
            'tag' => 'topic_a,science', 'p' => '15,0',
        ],
    ]);
});

it('normalizes the legacy local scheme without mistaking another path for home', function (): void {
    when('get_home_url')->justReturn('http://same.example/one');

    expect(baseShortcode()->build_initial_request(
        ['host' => 'same.example', 'path' => '/two/'],
        ['scheme' => 'local', 'query' => 'pages?status=publish'],
    ))->toBe([
        'url' => 'https://same.example/two/wp-json/wp/v2/pages?status=publish',
        'scheme' => 'http', 'site_id' => false,
    ]);
});

it('treats a home URL without a host as a different site', function (): void {
    // A relative home URL parses to a path only, so it cannot match the requested host.
    when('get_home_url')->justReturn('/relative');

    expect(baseShortcode()->build_initial_request(
        ['host' => 'same.example', 'path' => '/'],
        ['scheme' => 'https', 'query' => 'pages'],
    ))->toBe([
        'url' => 'https://same.example/wp-json/wp/v2/pages',
        'scheme' => 'https', 'site_id' => false,
    ]);
});

it('uses a network site home scheme but leaves unmatched hosts remote', function (bool $found): void {
    // Distinguish current home, matching subsite, and a non-network host. WordPress
    // hands the site id back as a numeric string, which the home URL lookup needs as an int.
    when('get_home_url')->alias(fn (mixed $id = 0): string => $id === 37 ? 'http://network.example/child/' : 'https://home.example/root/');
    when('is_multisite')->justReturn(true);
    Functions\expect('get_blog_details')->once()
        ->with(['domain' => 'network.example', 'path' => '/child/'], false)
        ->andReturn($found ? (object) ['blog_id' => '37'] : false);

    expect(baseShortcode()->build_initial_request(
        ['host' => 'network.example', 'path' => '/child/'],
        ['scheme' => 'https', 'query' => 'pages'],
    ))->toBe([
        'url' => ($found ? 'http' : 'https').'://network.example/child/wp-json/wp/v2/pages',
        'scheme' => $found ? 'http' : 'https', 'site_id' => $found ? '37' : false,
    ]);
})->with([true, false]);

it('round trips signed integer lists across deterministic generated inputs', function (): void {
    // Generate asymmetric signed values without relying on global random state.
    $seed = 73;
    for ($sample = 0; $sample < 40; $sample++) {
        $expected = [];
        $input = [];
        for ($item = 0; $item < 5; $item++) {
            $seed = ($seed * 48271) % 2147483647;
            $number = ($seed % 20001) - 10000;
            $expected[] = (string) $number;
            $input[] = ' '.$number.'suffix';
        }

        // Preserve order, signs, zero, and list length through sanitizing.
        expect(baseShortcode()->sanitized_ids(implode(',', $input)))->toBe(implode(',', $expected));
    }
});

it('reads shortcode attributes as strings and non-negative integers', function (): void {
    when('absint')->alias(fn (mixed $value): int => abs(is_numeric($value) ? (int) $value : 0));
    $atts = ['name' => 'story', 'count' => '-7', 'flag' => true, 'list' => ['a']];

    expect(baseShortcode()->string_att($atts, 'name'))->toBe('story')
        ->and(baseShortcode()->string_att($atts, 'flag'))->toBe('1')
        ->and(baseShortcode()->string_att($atts, 'list'))->toBe('')
        ->and(baseShortcode()->string_att($atts, 'missing'))->toBe('')
        ->and(baseShortcode()->absint_att($atts, 'count'))->toBe(7)
        ->and(baseShortcode()->absint_att($atts, 'list'))->toBe(0)
        ->and(baseShortcode()->absint_att($atts, 'missing'))->toBe(0);
});

it('follows a path through decoded REST arrays and objects', function (): void {
    $source = ['a' => (object) ['b' => ['c' => 5]], 'z' => 'text'];

    expect(baseShortcode()->value_at($source, 'a', 'b', 'c'))->toBe(5)
        ->and(baseShortcode()->value_at($source))->toBe($source)
        ->and(baseShortcode()->value_at($source, 'a', 'missing', 'c'))->toBeNull()
        ->and(baseShortcode()->value_at($source, 'z', 'deeper'))->toBeNull()
        ->and(baseShortcode()->value_at([['k' => 'v']], 0, 'k'))->toBe('v');
});

it('converts only scalars to strings', function (): void {
    expect(baseShortcode()->to_string(12))->toBe('12')
        ->and(baseShortcode()->to_string(false))->toBe('')
        ->and(baseShortcode()->to_string(null))->toBe('')
        ->and(baseShortcode()->to_string(['x']))->toBe('');
});

it('reads dates as timestamps and treats anything unreadable as the epoch', function (): void {
    expect(baseShortcode()->timestamp('2026-03-02T09:11:13'))->toBe(strtotime('2026-03-02T09:11:13'))
        ->and(baseShortcode()->timestamp('not a date'))->toBe(0)
        ->and(baseShortcode()->timestamp(null))->toBe(0);
});

it('keeps the base defaults when the defaults filter returns something else', function (): void {
    when('shortcode_atts')->alias(fn (array $defaults, array $atts): array => array_replace($defaults, array_intersect_key($atts, $defaults)));
    expectApplied('uamswp_content_syndicate_default_atts')->once()->andReturn('not an array');

    expect(baseShortcode()->process_attributes([])['host'])->toBe('news.uams.edu');
});

it('treats a taxonomy filter result that is not a string as an empty URL', function (): void {
    expectApplied('uamswp_content_syndicate_taxonomy_filters')->once()->andReturn(['x']);

    expect(baseShortcode()->build_taxonomy_filters([], 'https://news.uams.edu/wp-json/wp/v2/posts'))->toBe('');
});

it('drops positional attributes from the processed list', function (): void {
    when('shortcode_atts')->alias(fn (array $defaults, array $atts): array => $defaults + [0 => 'positional']);

    $result = baseShortcode()->process_attributes([]);

    expect($result)->not->toHaveKey(0)
        ->and($result['host'])->toBe('news.uams.edu');
});
