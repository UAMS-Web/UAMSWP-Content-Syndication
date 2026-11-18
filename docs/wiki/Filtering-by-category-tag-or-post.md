# Filtering by category, tag or post

Written against v1.4.5.4.

A news list usually needs only part of a site's posts: one category, one tag, or a few chosen articles. Four attributes narrow the request:

| To show | Use | Example |
| --- | --- | --- |
| Posts in categories, by slug | `site_category_slug` | `site_category_slug="research,awards"` |
| Posts in categories, by ID, with exclusions | `advanced_cat` | `advanced_cat="12,-40"` |
| Posts with tags, by slug | `tag` | `tag="covid-19"` |
| Particular posts, by ID | `id` | `id="1234,1240"` |

Each takes a comma-separated list. Slugs are passed through `sanitize_key()` and IDs through `intval()`, so a negative ID in `advanced_cat` stays negative.

## Steps

1. Find the slug or the ID on the source site: a category's slug is in its archive address (`/category/research/`), and an ID is in the address of its edit screen (`post=1234`, `tag_ID=40`).
2. Add the attribute to the shortcode:

   ```text
   [uamswp_news site="https://news.example.org/" output="excerpts" count="3" site_category_slug="research"]
   ```

3. Preview the page and compare the list with the category's archive on the source site.

**Warning:** these attributes are sent as `filter[category_name]`, `filter[cat]`, `filter[tag]` and `filter[p]` query parameters. WordPress core removed the `filter` parameter from its REST API in version 4.7, so the source site only honors them when it runs a plugin that restores it. A source that does not ignores the filter and returns its newest posts unfiltered, with no error. Step 3 is how to tell the difference.

## Reference

- [Shortcode attributes](Shortcode-attributes.md#filtering)
- [Filters and actions](Filters-and-actions.md): `uamswp_content_syndicate_taxonomy_filters` changes the request URL before these are added.
- [Troubleshooting](Troubleshooting.md#the-list-ignores-the-category-or-tag)
