# Your first news list

Written against v1.4.5.4.

At the end of this page, a page on your site shows the five newest headlines from another WordPress site, each linking to the article on that site.

You need the plugin active, and the address of a WordPress site whose REST API answers publicly. The examples use `news.example.org`; put the real address in its place.

1. **Check the source answers.** Open `https://news.example.org/wp-json/wp/v2/posts` in a browser. You should see a JSON list of posts. If you see an error or a login page instead, the plugin will not be able to read that site either.
2. **Edit the page** that should show the list, and add a Shortcode block (or type into the classic editor):

   ```text
   [uamswp_news site="https://news.example.org/" output="headlines" count="5"]
   ```

3. **Preview the page.** A bulleted list of five linked titles appears where the shortcode is, newest first.
4. **Publish the page.**

**Note:** without `output`, the shortcode prints a JSON object in a `<script>` tag and nothing visible. Always set `output` for a list people will read.

**Note:** the plugin's stylesheet is loaded only when the shortcode is in the page's own content, so a shortcode placed in a widget or a template looks unstyled.

## Going further

- Show excerpts or cards instead: [Output formats](Output-formats.md).
- Show one category only: [Filtering by category, tag or post](Filtering-by-category-tag-or-post.md).
- Feature the newest item and list the rest: [Splitting a feed across two lists](Splitting-a-feed-across-two-lists.md).
- If the list is empty: [Troubleshooting](Troubleshooting.md).
