=== Newsletter Publisher ===
Contributors: newsletterpublisher
Tags: newsletter, events, multilingual, gutenberg, shortcode
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.5
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish multilingual newsletter issues on your website with configurable layouts, categories, events and appearance.

== Description ==

Newsletter Publisher turns newsletter issues into regular WordPress content. Create events, group them by category, and publish an issue through a shortcode or the Newsletter block. This version focuses on the published website side; email delivery is intentionally not included yet.

**Content**

* Newsletter issues and events are WordPress custom post types.
* Events have start and end dates, an optional attachment, and a category.
* Issues select events automatically from a date period or use a manual selection.
* Event content stays live: editing an event updates every issue that includes it.
* Saved issues are rendered through a shortcode or block, not a public issue URL.

**Languages**

* Configure any number of languages with their own label, short code, locale and direction.
* The default language uses the normal post title and content.
* Other languages are entered in a Translations panel on each issue and event.
* Category labels and interface strings can be translated per language.
* Choose site-language mode or show all languages back to back, globally and per content type.
* Optionally hide events that have no translation for the active language.

**Layout and appearance**

* Category ordering and visibility.
* Continuous or per-category event numbering.
* Native accessible accordion with optional single-open behaviour.
* Date formats, date ranges and attachment links.
* Color presets, custom colors, typography, spacing, logo and custom CSS.
* Shortcode and block overrides for issue, language and display mode.

== Installation ==

1. Upload the `newsletter-wp-plugin` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open Newsletter → Settings and configure languages, layout and appearance.
4. Create events under Newsletter → Events.
5. Create an issue under Newsletter → Newsletter Issues, then place `[nlwp_newsletter]` or the Newsletter block on a page.

== Usage ==

Basic shortcode:

`[nlwp_newsletter]`

Show a specific issue in a specific language:

`[nlwp_newsletter issue_id="123" language="fi" display_mode="site"]`

Available attributes:

* `issue_id` – Issue post ID. Defaults to the latest published issue.
* `issue` – Alias for `issue_id`.
* `language` – Language code. Defaults to the active site language.
* `display_mode` – `site` or `stacked`. Overrides the plugin settings.
* `show_title` – `true` or `false`.
* `show_intro` – `true` or `false`.
* `class` – Additional CSS classes.

The same options are available in the Newsletter block sidebar.

== Frequently Asked Questions ==

= Does this plugin send email? =

No. This version publishes newsletter issues on the website. Email delivery is planned separately.

= Are issue pages public? =

No. Issues and events are edited in the admin but are not publicly queryable. Output happens through the shortcode or block.

= Can I use translations without a multilingual plugin? =

Yes. Add languages in the plugin settings and fill in the translation fields. If Polylang or WPML is active, the plugin also reads the current front-end language from it.

= What happens when a translation is missing? =

The default language is used as a fallback by default. You can also hide events that lack a translation for the active language.

== Screenshots ==

1. Newsletter settings, languages tab.
2. Issue editor with event selection.
3. Published newsletter with categories and accordion events.

== Changelog ==

= 0.1.0 =
* Initial website-side release: issues, events, categories, multilingual content, shortcode and block.
