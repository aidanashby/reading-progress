# Changelog

All notable changes to Reading Progress are documented here.

## 0.1.0

Initial release.

- `[reading_time]` shortcode: reading-time span (`reading-progress-time`) counted from post content only, configurable words per minute (default 250), configurable label / postfix / singular postfix.
- `[reading_progress_bar]` shortcode: scroll-driven progress bar (`reading-progress-bar` / `reading-progress-bar__fill`) tracking a configurable element, with orientation, thickness and colour options. Progress starts when the tracked element's top is 30vh below the viewport top.
- Both shortcodes accept attributes that override the saved settings.
- Settings page under Settings → Reading Progress, with a shortcode reference and a Settings link on the Plugins screen.
- Front-end script loads only on pages that use the progress bar shortcode.
- Self-updates from GitHub releases via the bundled Plugin Update Checker.
