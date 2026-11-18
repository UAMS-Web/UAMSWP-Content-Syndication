# What the plugin does

Written against v1.4.5.4.

The plugin registers one shortcode, `[uamswp_news]`. Where the shortcode appears, the plugin requests a list of posts from a WordPress REST API, keeps a subset of each post's data, and prints it in the format the `output` attribute names. It adds no settings screen and stores nothing in the database.

## When it loads

1. On `plugins_loaded` the plugin loads its base class and registers its other hooks.
2. On `init` it registers `[uamswp_news]`, then fires the `uamswp_content_syndicate_shortcodes` action so another plugin can register shortcodes built on the same base class.
3. When a post or a page is saved, it marks its own cache of local results as out of date (see *Caching* below).

The front-end stylesheet is loaded only when the current post (the one `get_post()` returns while styles are enqueued) contains `[uamswp_news]` in its content. A shortcode placed somewhere else, such as a widget or a template, gets no stylesheet from the plugin.

## Where the posts come from

The source is the `site` attribute when it is set, otherwise the `host` attribute. The plugin reads the host name and path from it and builds a request to `<scheme>://<host><path>wp-json/wp/v2/<query>`, where `query` defaults to `posts`. It then adds the filters, the count and `_embed` (which asks the source to include the featured image and the author in the response).

The request is routed in one of three ways:

| The source is | How it is read | Scheme |
| --- | --- | --- |
| This site (same host and path as its home URL) | Dispatched inside WordPress with `rest_do_request()`, with no HTTP request | This site's home URL scheme |
| Another site on the same multisite network | An HTTP request with `wp_remote_get()` | That site's home URL scheme |
| Any other site | An HTTP request with `wp_remote_get()` | `https` |

The `scheme` attribute does not change the scheme of the request; the table above decides it.

**Note:** the first route needs pretty permalinks on this site. Under plain permalinks the internal request cannot be built, and a list read from this site is empty.

## What is kept from each post

For each post the plugin keeps the title, link and date, and for every format except `headlines` also the ID, modified date, content, excerpt, author name and featured image. [Output formats](Output-formats.md#fields-kept-for-each-post) lists the fields. The `uams_content_syndication_host_data` filter can change what is kept ([Filters and actions](Filters-and-actions.md)).

The posts are then sorted newest first by date and cut to `count`.

## Caching

Results are cached with the WordPress object cache, in two different ways:

- **Remote results** (another site, including another site on the network) are cached for 10 minutes under a key made from all of the shortcode's attributes. A request that fails, or that answers `404`, is not cached; a response that is not a list of posts (an error page with another status, for example) is cached as an empty list for those 10 minutes.
- **Local results** (this site) are cached under a key made from the request URL and kept until any post or page on this site is saved.

Without a persistent object cache, WordPress keeps the object cache only for the length of one page load, so every page view makes a new request. [Getting fresh content](Getting-fresh-content.md) covers `cache_bust`.

## Merging local posts into a remote list

`local_count` asks for that many posts with `rest_do_request()`, which can only reach this site's REST API, and merges them into the list before it is sorted and cut. The stock `[uamswp_news]` declares no `local_` attributes, so the merged request is built from the same `host` or `site` as the main one:

- When that is another site, the merged request returns nothing and `local_count` has no effect.
- When it is this site, it reads the same posts again, and duplicates are dropped.

Only a shortcode built on the base class that declares `local_host` or `local_site` in its `local_extended_atts` property can merge posts from this site into a list from another one. When a local and a remote post share a timestamp, the remote one is kept.

## Reference

- [Shortcode attributes](Shortcode-attributes.md)
- [Output formats](Output-formats.md)
- [Filters and actions](Filters-and-actions.md)
