<?php

/**
 * UAMS_Syndicate_Shortcode_Base request building and input sanitizing, with
 * WordPress mocked.
 *
 * @see includes/class-uams-syndicate-shortcode-base.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\when;

beforeEach(function (): void {
    stubEscapeFunctions();

    // The three WordPress URL helpers the class leans on, as WordPress defines
    // them for the inputs below.
    when('trailingslashit')->alias(fn (string $value): string => rtrim($value, '/\\').'/');
    when('wp_parse_url')->alias(fn (string $url, int $component = -1): mixed => parse_url($url, $component));
    when('is_multisite')->justReturn(false);

    $this->shortcode = new UAMS_Syndicate_Shortcode_Base;
});

it('keeps only integers from a list of ids', function (string $input, string $expected): void {
    expect($this->shortcode->sanitized_ids($input))->toBe($expected);
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

    expect($this->shortcode->sanitized_terms('news, press,x'))
        ->toBe('k(news),k( press),k(x)');
});

it('takes the host from the site attribute ahead of the host attribute', function (): void {
    $url = $this->shortcode->get_request_url([
        'site' => 'https://example.org/blog',
        'host' => 'news.uams.edu',
    ]);

    expect($url['host'])->toBe('example.org')
        ->and($url['path'])->toBe('/blog/');
});

it('falls back to the host attribute', function (): void {
    $url = $this->shortcode->get_request_url(['site' => '', 'host' => 'https://news.uams.edu']);

    expect($url['host'])->toBe('news.uams.edu');
});

it('returns false when no host can be read from the attributes', function (): void {
    expect($this->shortcode->get_request_url(['site' => '', 'host' => '']))->toBeFalse();
});

it('requests a remote host over https', function (): void {
    when('get_home_url')->justReturn('https://www.uams.edu');

    $request = $this->shortcode->build_initial_request(
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

    $request = $this->shortcode->build_initial_request(
        ['host' => 'dev.example.org', 'path' => '/'],
        ['scheme' => 'https', 'query' => 'posts'],
    );

    expect($request)->toBe([
        'url' => 'http://dev.example.org/wp-json/wp/v2/posts',
        'scheme' => 'local',
        'site_id' => 1,
    ]);
});
