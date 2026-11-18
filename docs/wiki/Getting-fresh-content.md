# Getting fresh content

Written against v1.4.5.4.

A list that does not show an article just published on the source site is usually being served from the cache. How long a result is kept depends on where it came from.

| Source | Kept for | Cleared when |
| --- | --- | --- |
| Another site, including another site on the network | 10 minutes | The 10 minutes pass, or any attribute of the shortcode changes |
| This site | Until a post or page on this site is saved | Any post or page is saved |

Both use the WordPress object cache. Without a persistent object cache (such as Redis or Memcached through an object-cache drop-in) nothing is kept between page loads, so every view makes a fresh request and this page does not apply.

## Steps to force a fresh request

1. Change or add `cache_bust` on the shortcode. Its value is never read; it only changes the cache key, because the key is made from every attribute:

   ```text
   [uamswp_news site="https://news.example.org/" output="headlines" count="5" cache_bust="2"]
   ```

2. Save the page and view it. The plugin makes a new request because no result is cached under the new key.

The old result stays in the cache until it expires, so changing `cache_bust` back within 10 minutes shows the old result again.

`cache_bust` does nothing for a list read from this site: that cache is keyed on the request address rather than the attributes. Save any post or page on this site instead.

**Note:** a full-page cache (a caching plugin, or a proxy in front of the site) is separate from the plugin's cache. Clear it too if the page itself is cached.

## Reference

- [What the plugin does](What-the-plugin-does.md#caching)
- [Shortcode attributes](Shortcode-attributes.md#caching)
