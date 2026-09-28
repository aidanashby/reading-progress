# Reading Progress

WordPress plugin. Two independent features, each added via its own shortcode. The plugin sets **no styles of its own** on the front end beyond the few inline styles the progress bar needs to be visible and animate. Everything else is targetable by class and styled in the theme.

## Feature 1: Reading time — `[reading_time]`

Outputs a `<span class="reading-progress-time">` containing: **label + number + postfix**, e.g. `11 minutes reading time`.

- Word count is taken from the **post content only** (title excluded): `strip_shortcodes` → `wp_strip_all_tags` → `str_word_count`.
- Minutes = `round(words / wpm)`, floored to 1.
- Default speed: **250 words per minute**.
- Postfix switches between singular and plural forms.

Attributes (all optional, fall back to settings):

| Attribute | Meaning |
|---|---|
| `wpm` | words per minute |
| `label` | text before the number (default: none) |
| `postfix` | text after the number, 2+ minutes |
| `postfix_singular` | text after the number, exactly 1 |

`[reading_time wpm="300" label="Reading time: " postfix="min read" postfix_singular="min read"]`

## Feature 2: Reading progress bar — `[reading_progress_bar]`

Outputs:

```html
<div class="reading-progress-bar" data-rp-selector="…" data-rp-orientation="…">
  <div class="reading-progress-bar__fill" style="…"></div>
</div>
```

Placed wherever the shortcode sits. A small script updates the fill's `transform: scale()` (0→1) as the viewport scrolls through the tracked element. The CSS transition on the fill does the animating.

Progress starts when the top of the tracked element is **30vh** from the top of the viewport (not when it hits the very top), since readers scroll as they go rather than reading the top line. It reaches 100% when the bottom of the element reaches the bottom of the viewport.

Inline styles on `__fill` are limited to: size on the relevant axis (thickness), `background` (colour), `transform`, `transform-origin`, and `transition`. Position, track background, radius etc. are left to the theme.

Default tracked element:
`#main-content .et_builder_inner_content .et_pb_section_0_tb_body`

Attributes (all optional):

| Attribute | Meaning |
|---|---|
| `orientation` | `horizontal` or `vertical` |
| `thickness` | pixels (height if horizontal, width if vertical) |
| `color` | hex, e.g. `#c2185b` |
| `selector` | CSS selector of the element to track |

`[reading_progress_bar orientation="vertical" thickness="6" color="#c2185b"]`

## Settings page

Settings → Reading Progress:

- Words per minute
- Reading time label
- Reading time postfix
- Reading time postfix (singular)
- Bar orientation, thickness, colour
- Tracked element selector
- Shortcode reference (listed at the bottom of the page)

Stored as one option array, `reading_progress_settings`, removed on uninstall. A **Settings** link appears under the plugin on the Plugins screen.

## Asset loading

- `progress-bar.js` is enqueued **only** by the `[reading_progress_bar]` shortcode, so it loads only on pages that use the bar.
- `[reading_time]` loads no assets at all.
- `admin.css` + colour picker load only on the plugin's settings page.

## Updates

Self-updates from GitHub releases via the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (v5.7), pointed at `github.com/aidanashby/reading-progress`. Updates appear on the Plugins screen like any other.

To release: bump the `Version:` header and `READING_PROGRESS_VERSION`, add a `## x.y.z` section to CHANGELOG.md, commit, then push a tag such as `v0.1.3`. The Release workflow checks the versions match the tag and attaches `reading-progress.zip` to the release.

## Status

The update flow was checked on a local WordPress site on 28 September 2026: the plugin activates, finds its GitHub release and zip, and shows its icon. Shortcode output, the Divi selector and settings save have no recorded functional test.
