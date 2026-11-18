# Filters and actions

Written against v1.4.5.4.

The hooks a theme or another plugin can use to change what `[uamswp_news]` requests, keeps and prints. They run in the order listed for each shortcode.

## Actions

| Action | When | Arguments |
| --- | --- | --- |
| `uamswp_content_syndicate_shortcodes` | On `init`, after `[uamswp_news]` is registered | none |

Use it to register a shortcode built on `UAMS_Syndicate_Shortcode_Base`, which is loaded by then.

## Filters

| Filter | Receives | Returns |
| --- | --- | --- |
| `uamswp_content_syndicate_default_atts` | the default attributes array | the defaults to use; anything other than an array is ignored |
| `uamswp_content_syndicate_taxonomy_filters` | the request URL, the attributes, the request URL again | the request URL, before the category, tag and ID parameters are added |
| `uams_content_syndication_host_data` | the kept fields (`stdClass`), the post as received, the attributes | the fields to keep for that post |
| `uamswp_content_syndicate_news_output` | `false`, the posts, the attributes | `false` to print the built-in format, or the markup to print instead |
| `uamswp_content_syndicate_news` | the markup, the attributes | the final markup |

The post that `uams_content_syndication_host_data` receives is an object for a remote post and an array for a local one.

`uams_content_syndication_host_data` runs only when a result is fetched, not when it is served from the cache, so a change to that filter shows once the cached result expires (or after `cache_bust` changes, for a remote list). With `local_count` set, `uamswp_content_syndicate_taxonomy_filters` runs a second time, for the merged request.

## Examples

Change the default source for every shortcode on the site:

```php
add_filter('uamswp_content_syndicate_default_atts', function (array $defaults): array {
    $defaults['host'] = 'news.example.org';

    return $defaults;
});
```

Keep one more field from each remote post:

```php
add_filter('uams_content_syndication_host_data', function (stdClass $subset, $post, array $atts): stdClass {
    $subset->slug = is_object($post) ? ($post->slug ?? '') : ($post['slug'] ?? '');

    return $subset;
}, 10, 3);
```

Replace the markup of one format:

```php
add_filter('uamswp_content_syndicate_news_output', function ($content, array $posts, array $atts) {
    if ($atts['output'] !== 'headlines') {
        return $content;
    }

    $items = array_map(fn (stdClass $post): string => '<li>'.esc_html((string) $post->title).'</li>', $posts);

    return '<ol>'.implode('', $items).'</ol>';
}, 10, 3);
```

A replacement does not skip `offset` posts for you; the built-in formats do that while printing.

## Reference

- [Output formats](Output-formats.md#fields-kept-for-each-post)
- [Shortcode attributes](Shortcode-attributes.md)
