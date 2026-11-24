# Editor form

Written against v1.4.5.4.

The plugin can register a form for `[uamswp_news]` with Shortcake (Shortcode UI), so an editor fills in fields instead of typing attributes. The form appears only when a class named `UAMS_Shortcakes` exists, which another UAMS plugin provides; without it, type the shortcode by hand.

## Where it appears

On the post and page edit screens, as **News Syndication** with an RSS icon in the Shortcake inserter. The plugin also adds its admin stylesheet to the editor and a script that shows or hides fields as the format changes.

## Fields

| Field | Attribute | Type | Shown for |
| --- | --- | --- | --- |
| Format | `output` | Headline, Excerpt, Card or Full | always |
| Category | `site_category_slug` | text | Headline and Excerpt |
| Count | `count` | number, at least 1 | Headline and Excerpt |
| Offset | `offset` | number, at least 0 | Headline and Excerpt |
| Post ID | `id` | number | Full |

With Card selected the script hides all four fields below Format. Until a format is chosen, all fields are shown.

**Note:** the Post ID field writes `id`, the attribute the shortcode reads. A shortcode saved before this was fixed carries `postid="1234"`, which the shortcode ignores: change it to `id="1234"` ([Troubleshooting](Troubleshooting.md#a-saved-post-id-has-no-effect)).

The form has no field for `site` or `host`, so a shortcode inserted with it reads the default host until the attribute is added by hand.

## Reference

- [Shortcode attributes](Shortcode-attributes.md)
