# Splitting a feed across two lists

Written against v1.4.5.4.

A news page often features the newest article as a card and lists the next few as headlines. Two shortcodes on the same page do it, the second skipping what the first showed with `offset`.

## Steps

1. Add the featured item:

   ```text
   [uamswp_news site="https://news.example.org/" output="cards" count="1"]
   ```

2. Below it, add the rest of the list, skipping the first item:

   ```text
   [uamswp_news site="https://news.example.org/" output="headlines" count="4" offset="1"]
   ```

3. Preview the page. The card shows the newest article and the headlines show the four after it, with no repeat.

## How offset and count combine

With `offset` set, the plugin requests `count` plus `offset` posts, then skips the first `offset` of them when it prints. So `count="4" offset="1"` requests five posts and shows four. Use the same filters on both shortcodes, or the second list skips posts the first one never showed.

**Note:** set `count` together with `offset` for a list of a known length. With `offset` alone, the plugin asks for the source's default page and skips the first `offset` posts of it, so the list holds the default page less `offset` ([Troubleshooting](Troubleshooting.md#a-list-with-offset-shows-fewer-posts-than-expected)).

**Note:** the JSON output (`output="json"` or no `output`) does not skip anything: it includes the offset posts.

## Reference

- [Shortcode attributes](Shortcode-attributes.md#how-many-posts)
- [Output formats](Output-formats.md)
