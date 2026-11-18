# Output formats

Written against v1.4.5.4.

The `output` attribute picks how the posts are printed. Every printed format is wrapped in `<div class="uamswp-content-syndication-wrapper">`, skips the first `offset` posts, and escapes titles and links. Excerpts and content pass through `wp_kses_post()`. The `excerpts`, `cards` and `full` formats add schema.org `NewsArticle` microdata to each item.

| `output` | Shows | Item markup |
| --- | --- | --- |
| `json` (default) | Nothing visible | `<script>var json_data = {...};</script>` |
| `headlines` | A list of linked titles | `ul.uamswp-content-syndication-list > li.uamswp-content-syndication-item` |
| `excerpts` | Thumbnail, title, date, author and excerpt | `ul.uamswp-content-syndication-excerpts > li.uamswp-content-syndication-item` |
| `cards` | Image, title, excerpt and a Read more button | `div.uamswp-content-syndication-cards > div.default-card` |
| `full` | Image and caption, title, author and the whole article | `div.uamswp-content-syndication-container > div.uamswp-content-syndication-full` |

## json

Prints an HTML comment and a script that assigns the posts to a global variable, named by `object`:

```html
<!-- UAMSWP Output JSON --><script>var news_items = {"1767225600":{"ID":101,"title":"Example title", ...}};</script>
```

The object is keyed by each post's publication time as a Unix timestamp, newest first. It includes the posts `offset` would skip. A script on the page reads the variable.

## headlines

A bulleted list of titles, each linking to the post. Only the title, date and link of each post are requested, which keeps the response small.

## excerpts

For each post: the thumbnail (when the post has a featured image) linking to the post, the title, the date in `date_format`, the author's name, and the excerpt.

## cards

For each post: the image, the title, the excerpt and a `Read more` link with the classes `uams-btn btn-sm btn-red`, which the theme styles. A post without the `uams_news` image size on the source site shows a placeholder image from an external service instead.

## full

For each post: the image with its caption, the title as an `h2`, the author's name, and the content, run through `do_shortcode()`. When the source's response has a `fullcontent` field, it is used instead of `content`. Each article ends with a horizontal rule.

## Fields kept for each post

What the printed formats read, and what the JSON object holds:

| Field | Kept for | Source in the REST response |
| --- | --- | --- |
| `link`, `date`, `title` | every format | `link`, `date`, `title.rendered` |
| `ID`, `modified` | all but `headlines` | `id`, `modified` |
| `content`, `excerpt` | all but `headlines` | `content.rendered`, `excerpt.rendered` |
| `fullcontent` | `full` | `fullcontent` |
| `thumbnail` | all but `headlines` | the featured image's `post-thumbnail` size, else `thumbnail`, else its full size; `false` without one |
| `thumbalt`, `thumbcaption` | remote posts with a featured image | the image's `alt_text` and `caption.rendered` |
| `image`, `imagealt`, `imagecaption` | remote posts with a featured image | the image's `uams_news` size; `image` is `false` when the size is missing |
| `author_name` | all but `headlines` | the embedded author's `name`; empty without one |
| `terms` | all but `headlines` | always an empty list |

Posts read from this site (local results) get `thumbnail` and `author_name` through further internal requests, and no `image` fields.

## Reference

- [Shortcode attributes](Shortcode-attributes.md#output)
- [Filters and actions](Filters-and-actions.md): `uams_content_syndication_host_data` changes the fields, and `uamswp_content_syndicate_news_output` replaces the markup.
