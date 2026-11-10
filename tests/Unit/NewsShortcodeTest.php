<?php

/**
 * Characterize News normalization, rendering, and remote cache boundaries.
 *
 * @command  vendor/bin/pest tests/Unit/NewsShortcodeTest.php
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\Functions\when;

covers(UAMS_Syndicate_Shortcode_News::class);

/**
 * Build an asymmetric remote REST payload without loading WordPress.
 *
 * @return stdClass Remote post fixture.
 */
function newsRemotePost(): stdClass
{
    return json_decode(json_encode([
        'id' => 37,
        'date' => '2026-03-02T09:11:13',
        'modified' => '2026-04-05T12:14:16',
        'link' => 'https://remote.example/story',
        'title' => ['rendered' => 'Distinct title'],
        'content' => ['rendered' => '<p>Ordinary content</p>'],
        'excerpt' => ['rendered' => '<b>Brief excerpt</b>'],
        'fullcontent' => '<article>Expanded content</article>',
        'featured_media' => 91,
        '_embedded' => [
            'wp:featuredmedia' => [[
                'source_url' => 'https://images.example/original.jpg',
                'alt_text' => 'Image alternative',
                'caption' => ['rendered' => '<i>Image caption</i>'],
                'media_details' => ['sizes' => [
                    'post-thumbnail' => ['source_url' => 'https://images.example/post.jpg'],
                    'thumbnail' => ['source_url' => 'https://images.example/tiny.jpg'],
                    'uams_news' => ['source_url' => 'https://images.example/news.jpg'],
                ]],
            ]],
            'author' => [['name' => 'Distinct author']],
        ],
    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    // Stub only the WordPress boundary, leaving the News implementation real.
    stubEscapeFunctions();
    stubTranslationFunctions();
    when('add_shortcode')->justReturn();
    when('shortcode_atts')->alias(fn (array $defaults, array $atts): array => array_replace($defaults, array_intersect_key($atts, $defaults)));
    when('trailingslashit')->alias(fn (string $value): string => rtrim($value, '/').'/');
    when('wp_parse_url')->alias(fn (string $url, int $component = -1): mixed => parse_url($url, $component));
    when('get_home_url')->justReturn('https://local.example');
    when('is_multisite')->justReturn(false);
    when('absint')->alias(fn (mixed $value): int => abs((int) $value));
    when('wp_json_encode')->alias(fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR));
    when('wp_kses_post')->returnArg();
    when('do_shortcode')->alias(fn (string $value): string => 'expanded:'.$value);
    when('add_query_arg')->alias(function (array $args, string $url): string {
        // Preserve query replacement semantics for the repeated headline field.
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        return strtok($url, '?').'?'.http_build_query(array_replace($query, $args));
    });
    when('wp_cache_get')->justReturn([]);

    $this->newsEditorStyles = [];
    when('plugins_url')->returnArg();
    when('add_editor_style')->alias(function (string $stylesheet): void {
        $this->newsEditorStyles[] = $stylesheet;
    });
    $this->news = new UAMS_Syndicate_Shortcode_News;
    $this->newsAtts = ['host' => 'https://remote.example'];
});

it('normalizes every remote field and preserves the full-content distinction', function (string $output): void {
    // Normalize the payload through the public API.
    $post = newsRemotePost();
    $data = $this->news->process_remote_posts([$post], ['output' => $output]);
    $expected = [
        'ID' => 37, 'date' => $post->date, 'link' => $post->link,
        'modified' => $post->modified, 'title' => 'Distinct title',
        'content' => '<p>Ordinary content</p>', 'excerpt' => '<b>Brief excerpt</b>',
    ];
    if ($output === 'full') {
        $expected['fullcontent'] = '<article>Expanded content</article>';
    }

    $expected += [
        'thumbnail' => 'https://images.example/post.jpg',
        'thumbalt' => 'Image alternative', 'thumbcaption' => '<i>Image caption</i>',
        'image' => 'https://images.example/news.jpg', 'imagealt' => 'Image alternative',
        'imagecaption' => '<i>Image caption</i>', 'author_name' => 'Distinct author', 'terms' => [],
    ];

    // Assert normalized values, including properties absent outside full mode.
    expect(array_keys($data))->toBe([strtotime($post->date)])
        ->and((array) array_values($data)[0])->toBe($expected);
})->with(['json', 'excerpts', 'cards', 'full']);

it('uses remote thumbnail fallbacks and leaves missing medium metadata absent', function (string $remove, string $expected): void {
    // Remove progressively preferred image sizes.
    $post = newsRemotePost();
    $sizes = $post->_embedded->{'wp:featuredmedia'}[0]->media_details->sizes;
    unset($sizes->{'post-thumbnail'}, $sizes->uams_news);
    if ($remove === 'both') {
        unset($sizes->thumbnail);
    }

    // Observe the fallback image and the current partial-property shape.
    $result = array_values($this->news->process_remote_posts([$post], ['output' => 'cards']))[0];
    expect($result->thumbnail)->toBe($expected)
        ->and($result->thumbalt)->toBe('Image alternative')
        ->and($result->thumbcaption)->toBe('<i>Image caption</i>')
        ->and($result->image)->toBeFalse()
        ->and(property_exists($result, 'imagealt'))->toBeFalse()
        ->and(property_exists($result, 'imagecaption'))->toBeFalse();
})->with([
    ['preferred', 'https://images.example/tiny.jpg'],
    ['both', 'https://images.example/original.jpg'],
]);

it('currently leaves image properties absent when featured media is unavailable', function (string $condition): void {
    // Exercise each guard independently with otherwise usable media.
    $post = newsRemotePost();
    if ($condition === 'zero') {
        $post->featured_media = 0;
    } elseif ($condition === 'missing') {
        unset($post->_embedded->{'wp:featuredmedia'});
    } else {
        $post->_embedded->{'wp:featuredmedia'} = [];
    }

    unset($post->_embedded->author);

    // Pin the missing image fields rather than silently repairing production.
    $result = array_values($this->news->process_remote_posts([$post], ['output' => 'json']))[0];
    expect($result->thumbnail)->toBeFalse()
        ->and($result->author_name)->toBe('')
        ->and(property_exists($result, 'image'))->toBeFalse()
        ->and(property_exists($result, 'thumbalt'))->toBeFalse();
})->with(['zero', 'missing', 'empty']);

it('returns only headline fields and allocates consecutive duplicate-date keys', function (): void {
    // Supply three equal dates to exercise repeated collision increments.
    $post = newsRemotePost();
    $second = clone $post;
    $second->link = 'https://remote.example/second';

    $data = $this->news->process_remote_posts([$post, $second, $post], ['output' => 'headlines']);
    $timestamp = strtotime($post->date);

    // Assert both key allocation and intentionally reduced field selection.
    expect(array_keys($data))->toBe([$timestamp, $timestamp + 1, $timestamp + 2])
        ->and((array) $data[$timestamp])->toBe(['link' => $post->link, 'date' => $post->date, 'title' => 'Distinct title'])
        ->and($data[$timestamp + 1]->link)->toBe('https://remote.example/second');
});

it('normalizes remote author fallbacks for missing and empty embedding', function (string $condition): void {
    // Keep unrelated post fields intact while varying the author guards.
    $post = newsRemotePost();
    $post->featured_media = 0;
    if ($condition === 'embedding') {
        unset($post->_embedded);
    } else {
        $post->_embedded->author = [];
    }

    expect(array_values($this->news->process_remote_posts([$post], ['output' => 'json']))[0]->author_name)->toBe('');
})->with(['embedding', 'empty author']);

it('returns empty normalized data for empty inputs', function (): void {
    expect($this->news->process_remote_posts([], ['output' => 'full']))->toBe([])
        ->and($this->news->process_remote_posts(null, ['output' => 'json']))->toBe([])
        ->and($this->news->process_local_posts([], ['output' => 'cards']))->toBe([])
        ->and($this->news->process_local_posts(null, ['output' => 'cards']))->toBe([]);
});

it('reduces local REST array headlines to link, date and title', function (): void {
    // Promote any warning to an exception, so reading an array as an object cannot pass unnoticed.
    $post = ['date' => '2026-03-02T09:11:13', 'link' => 'https://local.example/story', 'title' => ['rendered' => 'Local title']];
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    // Restore the runner's handler even when an assertion fails.
    try {
        $data = $this->news->process_local_posts([$post], ['output' => 'headlines']);
    } finally {
        restore_error_handler();
    }

    expect(array_map(fn (object $item): array => (array) $item, array_values($data)))->toBe([
        ['link' => 'https://local.example/story', 'date' => '2026-03-02T09:11:13', 'title' => 'Local title'],
    ]);
});

it('currently raises an image-property warning when rendering remote cards without media', function (): void {
    // Keep the actual normalized missing-property shape through the dispatcher.
    $post = newsRemotePost();
    $post->featured_media = 0;

    when('wp_cache_get')->justReturn($this->news->process_remote_posts([$post], ['output' => 'cards']));
    $bufferLevel = ob_get_level();
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    // Assert the warning and clean up the renderer's interrupted output buffer.
    try {
        expect(fn (): string => $this->news->display_shortcode($this->newsAtts + ['output' => 'cards']))
            ->toThrow(ErrorException::class, 'Undefined property: stdClass::$image');
    } finally {
        restore_error_handler();
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
});

it('uses the current time for undated remote and local posts', function (): void {
    // Bound the real fallback clock without redefining a native function.
    $before = time();
    $post = newsRemotePost();
    $post->date = '';

    $local = json_decode(json_encode($post, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $local['featured_media'] = 0;

    // Assert both normalizers use the current clock and distinct collision keys.
    $remoteKeys = array_keys($this->news->process_remote_posts([$post, $post], ['output' => 'json']));
    $localKeys = array_keys($this->news->process_local_posts([$local, $local], ['output' => 'json']));
    expect($remoteKeys[0])->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time())
        ->and($localKeys[0])->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time())
        ->and($remoteKeys[1])->toBeGreaterThan($remoteKeys[0])
        ->and($localKeys[1])->toBeGreaterThan($localKeys[0]);
});

it('normalizes local array posts without inventing remote-only media fields', function (string $output): void {
    // Use the actual array shape returned by a local REST response.
    $post = json_decode(json_encode(newsRemotePost(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $post['featured_media'] = 0;
    $expected = [
        'ID' => 37, 'date' => $post['date'], 'link' => $post['link'],
        'modified' => $post['modified'], 'title' => 'Distinct title',
        'content' => '<p>Ordinary content</p>', 'excerpt' => '<b>Brief excerpt</b>',
    ];
    if ($output === 'full') {
        $expected['fullcontent'] = '<article>Expanded content</article>';
    }

    $expected += ['thumbnail' => false, 'author_name' => '', 'terms' => []];

    // Verify every field and duplicate-date key through the public method.
    $data = $this->news->process_local_posts([$post, $post], ['output' => $output]);
    expect(array_keys($data))->toBe([strtotime($post['date']), strtotime($post['date']) + 1])
        ->and((array) array_values($data)[0])->toBe($expected);
})->with(['json', 'full']);

it('normalizes local REST media fallbacks and author responses through mocked dispatch', function (): void {
    // Mock the core static factory, never replace WordPress with a behavioral class.
    $requests = Mockery::mock('alias:WP_REST_Request');
    $requests->shouldReceive('from_url')->andReturnUsing(fn (string $url): stdClass => (object) ['fixture_route' => $url]);
    $media = ['media_details' => ['sizes' => []], 'source_url' => 'original-local.jpg'];
    $author = ['name' => 'Local author'];
    when('rest_do_request')->alias(function (stdClass $request) use (&$media, &$author): object {
        return (object) ['data' => $request->fixture_route === 'author-route' ? $author : $media];
    });
    $post = json_decode(json_encode(newsRemotePost(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $post['author'] = 19;
    $post['_links'] = ['wp:featuredmedia' => [['href' => 'media-route']], 'author' => [['href' => 'author-route']]];

    // Exercise preferred, thumbnail, and original media with distinct image URLs.
    foreach ([
        [['post-thumbnail' => ['source_url' => 'preferred-local.jpg'], 'thumbnail' => ['source_url' => 'tiny-local.jpg']], 'preferred-local.jpg'],
        [['thumbnail' => ['source_url' => 'tiny-local.jpg']], 'tiny-local.jpg'],
        [[], 'original-local.jpg'],
    ] as [$sizes, $expected]) {
        $media['media_details']['sizes'] = $sizes;
        $result = array_values($this->news->process_local_posts([$post], ['output' => 'full']))[0];
        expect($result->thumbnail)->toBe($expected)
            ->and($result->author_name)->toBe('Local author')
            ->and($result->fullcontent)->toBe('<article>Expanded content</article>');
    }

    // Pin the author fallback when a dispatched response has no name field.
    $author = [];
    expect(array_values($this->news->process_local_posts([$post], ['output' => 'json']))[0]->author_name)->toBe('');

    // Exercise the first guard on each linked resource independently.
    $author = ['name' => 'Must not fetch this author'];
    $post['featured_media'] = 0;
    $post['author'] = 0;
    $result = array_values($this->news->process_local_posts([$post], ['output' => 'json']))[0];
    expect($result->thumbnail)->toBeFalse()->and($result->author_name)->toBe('');
});

it('allows the host-data filter to replace normalized remote and local results', function (): void {
    // Replace rather than merely decorate the normalized value.
    expectApplied('uams_content_syndication_host_data')->twice()->andReturnUsing(fn (object $subset, mixed $post, array $atts): object => (object) ['filtered' => $atts['output']]);
    $post = newsRemotePost();
    $local = json_decode(json_encode($post, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $local['featured_media'] = 0;

    // Observe the filter's result in both public return values.
    expect((array) array_values($this->news->process_remote_posts([$post], ['output' => 'json']))[0])->toBe(['filtered' => 'json'])
        ->and((array) array_values($this->news->process_local_posts([$local], ['output' => 'full']))[0])->toBe(['filtered' => 'full']);
});

it('skips linked local REST resources whose URL cannot become a request', function (): void {
    // WP_REST_Request::from_url() answers false for a URL it cannot parse.
    $requests = Mockery::mock('alias:WP_REST_Request');
    $requests->shouldReceive('from_url')->andReturn(false);
    Functions\expect('rest_do_request')->never();
    $post = json_decode(json_encode(newsRemotePost(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $post['author'] = 19;
    $post['_links'] = ['wp:featuredmedia' => [['href' => 'media-route']], 'author' => [['href' => 'author-route']]];

    $result = array_values($this->news->process_local_posts([$post], ['output' => 'json']))[0];

    expect($result->thumbnail)->toBeFalse()
        ->and($result->author_name)->toBe('');
});

it('renders each output format from cached normalized data with sorting and offset', function (string $output): void {
    // Cache deliberately unordered results with enough data for every renderer.
    $row = array_values($this->news->process_remote_posts([newsRemotePost()], ['output' => 'full']))[0];
    $older = (object) (array) $row;
    $older->title = 'Older entry';
    $older->date = '2026-01-01T00:00:00';

    when('wp_cache_get')->justReturn([10 => $older, 20 => $row]);

    // Render through the public shortcode dispatcher, not its private renderer.
    $html = $this->news->display_shortcode($this->newsAtts + ['output' => $output, 'count' => 1, 'offset' => 1, 'object' => 'newsFixture', 'date_format' => 'Y/m/d']);
    if ($output === 'json') {
        expect($html)->toBe('<!-- UAMSWP Output JSON --><script>var newsFixture = '.json_encode([$row, $older], JSON_THROW_ON_ERROR).';</script>');
    } elseif ($output === 'unknown') {
        expect($html)->toBe('');
    } else {
        expect($html)->toContain('UAMSWP Output '.ucfirst($output), 'Older entry')
            ->not()->toContain('Distinct title');
        if ($output === 'excerpts') {
            expect($html)->toContain('2026/01/01', 'Distinct author', '<b>Brief excerpt</b>', 'https://images.example/post.jpg', 'Image alternative');
        } elseif ($output === 'cards') {
            expect($html)->toContain('https://images.example/news.jpg', '&lt;i&gt;Image caption&lt;/i&gt;', '2026-04-05T12:14:16');
        } elseif ($output === 'full') {
            expect($html)->toContain('expanded:<article>Expanded content</article>', '<span class="wp-caption-text"><i>Image caption</i></span>', '<h2 itemprop="headline">Older entry</h2>', '<span itemprop="name">Distinct author</span>', '<span class="content-item-byline-date">2026/01/01</span>');
        }
    }
})->with(['json', 'headlines', 'excerpts', 'cards', 'full', 'unknown']);

it('renders explicit media and content fallbacks without omitted fixture properties', function (string $output): void {
    // Supply explicit absent values as a host-data filter can do in production.
    $row = array_values($this->news->process_remote_posts([newsRemotePost()], ['output' => 'full']))[0];
    $row->image = false;
    $row->imagecaption = '';
    $row->thumbnail = false;
    $row->author_name = '';
    $row->fullcontent = '';

    when('wp_cache_get')->justReturn([20 => $row]);

    // Assert fallback output and absent author/caption markup.
    $html = $this->news->display_shortcode($this->newsAtts + ['output' => $output]);
    if ($output === 'cards') {
        expect($html)->toContain('http://via.placeholder.com/560x350?text=Not%20Available');
    } elseif ($output === 'full') {
        expect($html)->toContain('http://www.uams.edu/_images/blank.gif', 'expanded:<p>Ordinary content</p>')
            ->not()->toContain('class="content-item-byline-author"')
            ->not()->toContain('wp-caption-text')
            ->toMatch('/blank\.gif"\/>\s*<\/div>/');
    } else {
        expect($html)->not->toContain('<img');
    }
})->with(['cards', 'full', 'excerpts']);

it('honors output overrides including empty strings and the final content filter', function (mixed $override, string $expected): void {
    // Install both filters so strict false and empty string remain distinct.
    expectApplied('uamswp_content_syndicate_news_output')->once()->andReturn($override);
    expectApplied('uamswp_content_syndicate_news')->once()->andReturnUsing(fn (string $content): string => '['.$content.']');

    expect($this->news->display_shortcode($this->newsAtts))->toBe($expected);
})->with([
    ['override', '[override]'],
    ['', '[]'],
    [false, '[<!-- UAMSWP Output JSON --><script>var json_data = [];</script>]'],
]);

it('returns an explicit error for an empty host', function (): void {
    expect($this->news->display_shortcode(['host' => '']))->toBe('<!-- uamswp_news ERROR - an empty host was supplied -->');
});

it('reads local normalized cache hits without dispatching REST requests', function (): void {
    // Use the local scheme selected by the real base request builder.
    when('wp_cache_get_last_changed')->justReturn('generation-7');
    $row = (object) ['title' => 'Cached local'];
    $cacheRead = null;
    when('wp_cache_get')->alias(function (string $key, string $group) use (&$cacheRead, $row): array {
        $cacheRead = [$key, $group];

        return [20 => $row];
    });
    Functions\expect('rest_do_request')->never();
    Functions\expect('wp_cache_set')->never();

    // Verify the generation-aware local cache namespace and preserved JSON keys.
    expect($this->news->display_shortcode(['host' => 'https://local.example']))
        ->toBe('<!-- UAMSWP Output JSON --><script>var json_data = {"20":{"title":"Cached local"}};</script>')
        ->and($cacheRead)->toBe([md5('https://local.example/wp-json/wp/v2/posts?_embed=').':generation-7', 'uamswp-content']);
});

it('caches local REST misses for both accepted and rejected statuses', function (): void {
    // Mock only the static core boundary and the REST response contract.
    $requests = Mockery::mock('alias:WP_REST_Request');
    $requests->shouldReceive('from_url')->andReturnUsing(fn (string $url): stdClass => (object) ['fixture_route' => $url]);
    when('wp_cache_get')->justReturn(false);
    when('wp_cache_get_last_changed')->justReturn('generation-rest');

    $post = json_decode(json_encode(newsRemotePost(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $post['featured_media'] = 0;
    $status = 200;
    $response = new class([$post], function () use (&$status): int {
        return $status;
    })
    {
        /**
         * @param  array<int, array<string, mixed>>  $data
         */
        public function __construct(public array $data, private Closure $statusReader) {}

        public function get_status(): int
        {
            return ($this->statusReader)();
        }
    };
    when('rest_do_request')->justReturn($response);
    $stored = [];
    when('wp_cache_set')->alias(function (string $key, mixed $data, string $group) use (&$stored): bool {
        $stored = [$key, $data, $group];

        return true;
    });

    // Assert successful normalization and the current false-cache write on failure.
    foreach ([200, 201, 404] as $code) {
        $status = $code;
        $html = $this->news->display_shortcode(['host' => 'https://local.example']);
        expect($stored[0])->toBe(md5('https://local.example/wp-json/wp/v2/posts?_embed=').':generation-rest')
            ->and($stored[2])->toBe('uamswp-content');
        if ($code === 200) {
            expect(array_values($stored[1])[0]->title)->toBe('Distinct title')
                ->and($html)->toContain('Distinct title');
        } else {
            expect($stored[1])->toBeFalse()
                ->and($html)->toBe('<!-- UAMSWP Output JSON --><script>var json_data = [];</script>');
        }
    }
});

/**
 * The host concatenation assigned to unused $local_shortcodes
 * has no observable effect. Equivalent mutants: 5ee7937765aabcb2,
 * 0b4ef9ce6bdbf432, and 29980a201afc4131. Only those concatenation operators
 * are ignored; no production statement is deleted.
 */
it('currently preserves the remote row on a local merge timestamp collision', function (): void {
    // Supply different remote and local cached rows sharing a timestamp.
    $remote = (object) ['title' => 'Remote wins'];
    $collision = (object) ['title' => 'Local collision lost'];
    $local = (object) ['title' => 'Local newest'];
    when('get_site')->justReturn((object) ['domain' => 'local.example', 'path' => '/']);
    when('wp_cache_get_last_changed')->justReturn('generation-8');
    when('wp_cache_get')->alias(fn (string $key, string $group): array => $group === 'uamswp_news' ? [20 => $remote] : [20 => $collision, 30 => $local]);

    Functions\expect('rest_do_request')->never();

    // Pin array-union precedence and the final reverse timestamp ordering.
    expect($this->news->display_shortcode($this->newsAtts + ['local_count' => 2]))
        ->toBe('<!-- UAMSWP Output JSON --><script>var json_data = {"30":{"title":"Local newest"},"20":{"title":"Remote wins"}};</script>');
});

/**
 * Local cap equivalents: 3b1584c92448af1f and 7a682d28550d370a. Changing >100
 * to >=100 or >99 only changes which expression supplies 100, and absint()
 * normalizes either result before the query is built.
 */
it('uses local attribute overrides and a bounded count in the merged cache key', function (int $count, int $requested): void {
    // Make prefixed overrides observable without replacing WordPress REST classes.
    $this->news->local_extended_atts = ['local_host' => 'https://local.example', 'local_query' => 'local-stories'];
    when('get_site')->justReturn((object) ['domain' => 'unused.example', 'path' => '/unused/']);
    when('wp_cache_get_last_changed')->justReturn('generation-9');

    $localRead = null;
    when('wp_cache_get')->alias(function (string $key, string $group) use (&$localRead): array {
        if ($group === 'uamswp-content') {
            $localRead = [$key, $group];
        }

        return [];
    });

    // Assert the full normalized local request identity and cache generation.
    expect($this->news->display_shortcode($this->newsAtts + ['local_count' => $count]))
        ->toBe('<!-- UAMSWP Output JSON --><script>var json_data = [];</script>')
        ->and($localRead)->toBe([md5('https://local.example/wp-json/wp/v2/local-stories?per_page='.$requested.'&_embed=').':generation-9', 'uamswp-content']);
})->with([[1, 1], [99, 99], [100, 100], [101, 100]]);

/**
 * Remote cap equivalents: 198839429625d515 and 40411582c937bcd9. Changing >100
 * to >=100 or >99 still sends the same absint-normalized per_page at the only
 * newly matched value, 100. Values on either side are exercised below.
 */
it('bounds remote request counts while retaining the current JSON offset behavior', function (mixed $count, int $offset, ?int $perPage): void {
    // Capture the network URL while returning an empty successful payload.
    $url = '';
    when('wp_cache_get')->justReturn(false);
    when('wp_remote_get')->alias(function (string $request) use (&$url): array {
        $url = $request;

        return [];
    });
    when('is_wp_error')->justReturn(false);
    when('wp_remote_retrieve_response_code')->justReturn(200);
    when('wp_remote_retrieve_body')->justReturn('[]');
    when('wp_cache_set')->justReturn(true);

    // Assert the request boundary rather than a mocked helper call sequence.
    expect($this->news->display_shortcode($this->newsAtts + ['count' => $count, 'offset' => $offset]))
        ->toBe('<!-- UAMSWP Output JSON --><script>var json_data = [];</script>');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect(isset($query['_embed']))->toBeTrue()
        ->and(isset($query['per_page']) ? (int) $query['per_page'] : null)->toBe($perPage);
})->with([[false, 0, null], [0, 0, null], [1, 0, 1], [99, 0, 99], [99, 1, 100], [100, 0, 100], [101, 0, 100], [-3, 0, 3], [1, 2, 3]]);

it('requests all three reduced headline fields and renders the normalized response', function (): void {
    // Capture a genuine News-built URL at the HTTP boundary.
    $url = '';
    when('wp_cache_get')->justReturn(false);
    when('wp_remote_get')->alias(function (string $request) use (&$url): array {
        $url = $request;

        return [];
    });
    when('is_wp_error')->justReturn(false);
    when('wp_remote_retrieve_response_code')->justReturn(200);
    when('wp_remote_retrieve_body')->justReturn(json_encode([newsRemotePost()], JSON_THROW_ON_ERROR));
    when('wp_cache_set')->justReturn(true);

    // Verify reduced field order along with observable headline link and title.
    $html = $this->news->display_shortcode($this->newsAtts + ['output' => 'headlines']);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['_fields'])->toBe(['title', 'date', 'link'])
        ->and($html)->toContain('<a href="https://remote.example/story">Distinct title</a>');
});

it('normalizes and caches merged local REST misses only on exact success', function (): void {
    // Keep the remote side cached while making the local side dispatch.
    $requests = Mockery::mock('alias:WP_REST_Request');
    $requests->shouldReceive('from_url')->andReturnUsing(fn (string $url): stdClass => (object) ['fixture_route' => $url]);
    when('get_site')->justReturn((object) ['domain' => 'local.example', 'path' => '/']);
    when('wp_cache_get_last_changed')->justReturn('generation-merge');
    when('wp_cache_get')->alias(fn (string $key, string $group): array|false => $group === 'uamswp_news' ? [] : false);

    $post = json_decode(json_encode(newsRemotePost(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $post['featured_media'] = 0;
    $status = 200;
    $response = new class([$post], function () use (&$status): int {
        return $status;
    })
    {
        /**
         * @param  array<int, array<string, mixed>>  $data
         */
        public function __construct(public array $data, private Closure $statusReader) {}

        public function get_status(): int
        {
            return ($this->statusReader)();
        }
    };
    when('rest_do_request')->justReturn($response);
    $stored = null;
    when('wp_cache_set')->alias(function (string $key, mixed $data, string $group) use (&$stored): bool {
        $stored = [$key, $data, $group];

        return true;
    });

    // Assert rejected merge responses cache an empty array, unlike local-only misses.
    foreach ([200, 201, 404] as $code) {
        $status = $code;
        $html = $this->news->display_shortcode($this->newsAtts + ['local_count' => 1]);
        expect($stored[0])->toBe(md5('https://remote.example/wp-json/wp/v2/posts?per_page=1&_embed=').':generation-merge')
            ->and($stored[2])->toBe('uamswp-content');
        if ($code === 200) {
            expect(array_values($stored[1])[0]->title)->toBe('Distinct title')
                ->and($html)->toContain('Distinct title');
        } else {
            expect($stored[1])->toBe([])
                ->and($html)->toBe('<!-- UAMSWP Output JSON --><script>var json_data = [];</script>');
        }
    }
});

it('fetches and caches successful remote normalization including invalid JSON', function (string $body, int $status, int $expectedCount): void {
    // Capture the actual normalized cache write and render that same data.
    $stored = null;
    when('wp_cache_get')->justReturn(false);
    when('wp_remote_get')->justReturn(['response' => 'fixture']);
    when('is_wp_error')->justReturn(false);
    when('wp_remote_retrieve_response_code')->justReturn($status);
    when('wp_remote_retrieve_body')->justReturn($body === 'post' ? json_encode([newsRemotePost()], JSON_THROW_ON_ERROR) : $body);
    when('wp_cache_set')->alias(function (string $key, mixed $value, string $group, int $ttl) use (&$stored): bool {
        $stored = [$key, $value, $group, $ttl];

        return true;
    });

    // Verify content, cache identity, normalized data, and the ten-minute lifetime.
    $html = $this->news->display_shortcode($this->newsAtts);
    expect($stored[0])->toBe(md5(serialize($this->news->process_attributes($this->newsAtts))))
        ->and($stored[1])->toHaveCount($expectedCount)
        ->and($stored[2])->toBe('uamswp_news')
        ->and($stored[3])->toBe(600)
        ->and($html)->toBe('<!-- UAMSWP Output JSON --><script>var json_data = '.json_encode($stored[1], JSON_THROW_ON_ERROR).';</script>');
})->with([['post', 200, 1], ['invalid{', 200, 0], ['null', 200, 0], ['[]', 500, 0]]);

it('does not cache remote errors or 404 responses', function (bool $error, int $status): void {
    // Keep body retrieval and writes forbidden on unsuccessful fetch branches.
    when('wp_cache_get')->justReturn(false);
    when('wp_remote_get')->justReturn(['response' => 'fixture']);
    when('is_wp_error')->justReturn($error);
    when('wp_remote_retrieve_response_code')->justReturn($status);

    Functions\expect('wp_remote_retrieve_body')->never();
    Functions\expect('wp_cache_set')->never();

    expect($this->news->display_shortcode($this->newsAtts))->toBe('<!-- UAMSWP Output JSON --><script>var json_data = [];</script>');
})->with([[true, 200], [false, 404]]);

it('registers the News shortcode and stylesheet hook on construction', function (): void {
    $registered = [];
    when('add_shortcode')->alias(function (string $tag, array $callback) use (&$registered): void {
        $registered[$tag] = $callback;
    });
    $news = new UAMS_Syndicate_Shortcode_News;
    expect($news->shortcode_name)->toBe('uamswp_news');
    expect($registered)->toBe(['uamswp_news' => [$news, 'display_shortcode']]);
    expect(has_action('wp_enqueue_scripts', [$news, 'enqueue_syndication_stylesheet']))->toBe(10);
});

it('enqueues News styles only when the current post contains its shortcode', function (bool $hasPost, bool $hasShortcode): void {
    // Model the independent missing-post and shortcode-presence guards.
    when('get_post')->justReturn($hasPost ? (object) ['post_content' => '[uamswp_news]'] : null);
    when('has_shortcode')->justReturn($hasShortcode);
    when('plugins_url')->returnArg();
    if ($hasPost && $hasShortcode) {
        Functions\expect('wp_enqueue_style')->once()->with('uamswp-syndication-news-style', '/css/uamswp-syndication-news.css', [], '');
    } else {
        Functions\expect('wp_enqueue_style')->never();
    }

    $this->news->enqueue_syndication_stylesheet();
    expect(true)->toBeTrue();
})->with([[false, false], [true, false], [true, true]]);

it('advertises the current Shortcake attribute names and numeric boundaries', function (): void {
    // Capture the UI's public registration data without loading Shortcake.
    $registered = null;
    when('shortcode_ui_register_for_shortcode')->alias(function (string $name, array $config) use (&$registered): void {
        $registered = [$name, $config];
    });
    $this->news->build_shortcake();

    // Pin the existing postid attribute mismatch as current configuration.
    expect($registered)->toBe(['uamswp_news', [
        'label' => 'News Syndication',
        'listItemImage' => 'dashicons-rss',
        'attrs' => [
            [
                'label' => 'Format', 'attr' => 'output', 'type' => 'radio',
                'options' => ['headlines' => 'Headline', 'excerpts' => 'Excerpt', 'cards' => 'Card', 'full' => 'Full'],
                'description' => 'Preferred output format',
            ],
            [
                'label' => 'Category', 'attr' => 'site_category_slug', 'type' => 'text',
                'description' => 'Please enter the filter / category',
            ],
            [
                'label' => 'Count', 'attr' => 'count', 'type' => 'number',
                'description' => 'Number of news articles to display',
                'meta' => ['placeholder' => '1', 'min' => '1', 'step' => '1'],
            ],
            [
                'label' => 'Offset', 'attr' => 'offset', 'type' => 'number',
                'description' => 'Number of news articles to skip',
                'meta' => ['placeholder' => '0', 'min' => '0', 'step' => '1'],
            ],
            [
                'label' => 'Post ID', 'attr' => 'postid', 'type' => 'number',
                'description' => 'Specific ID of article',
            ],
        ],
        'post_type' => ['post', 'page'],
    ]]);
});
