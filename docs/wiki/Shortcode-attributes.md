# Shortcode attributes

Written against v1.4.5.4.

Every attribute `[uamswp_news]` accepts, grouped by what it controls. All are optional. An attribute not listed here is dropped. The defaults are declared in `$defaults_atts` in [`includes/class-uams-syndicate-shortcode-base.php`](https://github.com/UAMS-Web/UAMSWP-Content-Syndication/blob/master/includes/class-uams-syndicate-shortcode-base.php), and the `uamswp_content_syndicate_default_atts` filter can change them site-wide ([Filters and actions](Filters-and-actions.md)).

Example:

```text
[uamswp_news site="https://news.example.org/research/" output="excerpts" count="3" site_category_slug="awards" date_format="M j"]
```

## Source

| Attribute | Default | Effect |
| --- | --- | --- |
| `site` | empty | Host name and path of the source site, such as `https://news.example.org/research/`. Overrides `host` when set. |
| `host` | the plugin's built-in news host | Host name of the source site, used when `site` is empty. A host with no usable host name prints the error comment described in [Troubleshooting](Troubleshooting.md#the-page-source-shows-an-empty-host-error). |
| `query` | `posts` | The REST route after `wp-json/wp/v2/`, such as `pages`. Query arguments can be included (`posts?orderby=title`). |
| `scheme` | `https` | Accepted for compatibility; it does not change the request. The scheme comes from the routing in [What the plugin does](What-the-plugin-does.md#where-the-posts-come-from). |

## Filtering

| Attribute | Default | Effect |
| --- | --- | --- |
| `site_category_slug` | empty | Comma-separated category slugs, sent as `filter[category_name]`. |
| `advanced_cat` | empty | Comma-separated category IDs, a negative ID excluding that category, sent as `filter[cat]`. |
| `tag` | empty | Comma-separated tag slugs, sent as `filter[tag]`. |
| `id` | empty | Comma-separated post IDs, sent as `filter[p]`. |

The source site must support the `filter` parameter for these to work: [Filtering by category, tag or post](Filtering-by-category-tag-or-post.md).

## How many posts

| Attribute | Default | Effect |
| --- | --- | --- |
| `count` | not set | How many posts to show. Not set, the source's own page size applies (10 on a default WordPress site). One request asks for at most 100. |
| `offset` | `0` | How many of the newest posts to skip in the printed formats. Set `count` with it: [Splitting a feed across two lists](Splitting-a-feed-across-two-lists.md). |
| `local_count` | `0` | How many posts to merge in through this site's REST API: [What the plugin does](What-the-plugin-does.md#merging-local-posts-into-a-remote-list). |

## Output

| Attribute | Default | Effect |
| --- | --- | --- |
| `output` | `json` | `json`, `headlines`, `excerpts`, `cards` or `full`: [Output formats](Output-formats.md). Any other value prints nothing. |
| `object` | `json_data` | Name of the JavaScript variable that `output="json"` assigns. |
| `date_format` | `F j, Y` | PHP date format for the date shown by `excerpts`, the only format that shows one. |
| `style` | empty | Accepted, but no format uses it. |

## Caching

| Attribute | Default | Effect |
| --- | --- | --- |
| `cache_bust` | empty | Never read. Changing it changes the cache key of a remote list, forcing a new request: [Getting fresh content](Getting-fresh-content.md). |

## Reference

- [Output formats](Output-formats.md)
- [Filters and actions](Filters-and-actions.md)
