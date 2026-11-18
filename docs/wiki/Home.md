# UAMSWP Content Syndication

UAMSWP Content Syndication adds the `[uamswp_news]` shortcode to WordPress. The shortcode reads posts from another WordPress site's REST API, or from the site it runs on, and shows them on a page as a list of headlines, a list of excerpts, a set of cards, full articles, or a JSON object for a script. These pages explain what the plugin does, how to place a news list, every shortcode attribute, and what to check when a list comes out empty. The repository's [README](https://github.com/UAMS-Web/UAMSWP-Content-Syndication/blob/master/README.md) keeps the requirements, installation and credits.

**Getting started**

- [What the plugin does](What-the-plugin-does.md): where the posts come from, how a request is routed, and how results are cached.
- [Your first news list](Your-first-news-list.md): placing a list of headlines from another site on a page.

**Guides**

- [Filtering by category, tag or post](Filtering-by-category-tag-or-post.md): narrowing the list, and what the source site must support.
- [Splitting a feed across two lists](Splitting-a-feed-across-two-lists.md): one featured item, then the rest as headlines, with `offset`.
- [Getting fresh content](Getting-fresh-content.md): how long results are kept, and forcing a new request with `cache_bust`.

**Reference**

- [Shortcode attributes](Shortcode-attributes.md): every attribute of `[uamswp_news]`, its default and its effect.
- [Output formats](Output-formats.md): the markup and the fields of each `output` value.
- [Editor form](Editor-form.md): the News Syndication form in the post editor, where it appears.
- [Filters and actions](Filters-and-actions.md): the hooks a theme or another plugin can use.

**Troubleshooting**

- [Troubleshooting](Troubleshooting.md): one entry per symptom.

Records about particular sites (which sites run the plugin, and which sources they read) are not kept in these pages. They belong in the operator's private records.
