# Troubleshooting

Written against v1.4.5.4.

- [The page source shows an empty host error](#the-page-source-shows-an-empty-host-error)
- [Nothing appears where the shortcode is](#nothing-appears-where-the-shortcode-is)
- [The list is empty](#the-list-is-empty)
- [A list with offset shows fewer posts than expected](#a-list-with-offset-shows-fewer-posts-than-expected)
- [The list ignores the category or tag](#the-list-ignores-the-category-or-tag)
- [The Post ID field has no effect](#the-post-id-field-has-no-effect)
- [A new article does not appear](#a-new-article-does-not-appear)
- [The list is unstyled](#the-list-is-unstyled)
- [More than 100 posts are asked for and 100 appear](#more-than-100-posts-are-asked-for-and-100-appear)

## The page source shows an empty host error

```html
<!-- uamswp_news ERROR - an empty host was supplied -->
```

**Cause:** `site` is set to a value with no host name (then `host` is not used), or `site` is empty and `host` has no host name.

**Fix:** set `site` to the full address of the source, such as `site="https://news.example.org/"`.

## Nothing appears where the shortcode is

**Cause:** `output` is not set, so the default `json` format prints only a `<script>` tag, or `output` has a value other than `json`, `headlines`, `excerpts`, `cards` or `full`, which prints nothing.

**Fix:** set `output="headlines"` (or another format) and check the spelling: the values are plural except `full`. See [Output formats](Output-formats.md).

## The list is empty

**Cause:** for a list read from this site, plain permalinks: the internal request needs pretty permalinks, and without them the list is empty. Otherwise, the source answered with no posts, or with something that is not a list of posts. An error (a refused connection, a timeout) and a `404` print an empty list and are tried again on the next view; any other non-list answer, such as a `401` or a `500` error object, is cached as empty for 10 minutes.

**Fix:** open `https://<source>/wp-json/wp/v2/posts` in a browser (with the same `query`, if one is set). A JSON list there means the plugin should read it; anything else is the source's problem to fix. Once it answers, change `cache_bust` to skip the cached empty result ([Getting fresh content](Getting-fresh-content.md)).

## A list with offset shows fewer posts than expected

```text
[uamswp_news site="https://news.example.org/" output="headlines" offset="1"]
```

**Cause:** with `offset` set and `count` not set, the plugin asks for the source's default page of posts and skips the first `offset` of them when it prints. The default page is whatever the source's REST API returns without `per_page` (10 posts on a stock WordPress site), so the list holds that many less `offset`.

**Fix:** set `count` for an exact length: `count="4" offset="1"` shows four posts after the first. See [Splitting a feed across two lists](Splitting-a-feed-across-two-lists.md).

## The list ignores the category or tag

**Cause:** `site_category_slug`, `advanced_cat`, `tag` and `id` are sent as `filter[...]` parameters, which WordPress core has not supported since 4.7. A source site that does not restore them returns its newest posts unfiltered.

**Fix:** have the source site restore the `filter` parameter, or use `query` with the core parameters instead, for example `query="posts?categories=12"` (IDs, not slugs). See [Filtering by category, tag or post](Filtering-by-category-tag-or-post.md).

## The Post ID field has no effect

**Cause:** the [Editor form](Editor-form.md)'s Post ID field writes `postid="1234"`, and the shortcode's attribute is `id`. The unknown attribute is dropped.

**Fix:** edit the shortcode by hand to `id="1234"`.

## A new article does not appear

**Cause:** the list is served from the cache: up to 10 minutes for another site, or until a post is saved for this site. A full-page cache can also be serving the whole page.

**Fix:** see [Getting fresh content](Getting-fresh-content.md).

## The list is unstyled

**Cause:** the plugin loads its stylesheet only when the current post's content contains `[uamswp_news]`. A shortcode in a widget or a template gets no stylesheet.

**Fix:** put the shortcode in the post content, or have the theme load `css/uamswp-syndication-news.css` from the plugin, or style the classes in [Output formats](Output-formats.md) itself.

## More than 100 posts are asked for and 100 appear

**Cause:** one request asks for at most 100 posts, the REST API's own limit for `per_page`.

**Fix:** `offset` cannot reach past the first 100, because the skipped posts count toward the same request. Ask the source for the next page through `query` instead: a second shortcode with `count="100" query="posts?page=2"` shows posts 101 to 200.
