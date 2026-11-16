=== Newsletter Publisher ===
Contributors: newsletterpublisher
Tags: newsletter, events, multilingual, gutenberg, shortcode
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.5
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish multilingual newsletter issues on your website with configurable layouts, categories, events and appearance.

== Description ==

Newsletter Publisher turns newsletter issues into regular WordPress content. Create events, group them by category, and publish an issue through a shortcode or the Newsletter block. You can also compose and send each issue as email, or export a plain-text or minimal HTML copy for manual sending.

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

**Email**

* Compose and send each issue from Newsletter → Email.
* Choose which configured languages are included in the email and which one is primary.
* Combine multilingual titles, category labels, event titles and descriptions with configurable separators and optional language labels.
* Plain text by default, with an optional minimal HTML format.
* Preview, copy and download the rendered message for manual sending.
* Sends through `wp_mail()`; install an SMTP or email delivery plugin for reliable transport. Newsletter Publisher does not configure mail transport.

== Installation ==

1. Upload the `newsletter-wp-plugin` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open Newsletter → Settings and configure languages, layout, appearance and email defaults.
4. Create events under Newsletter → Events.
5. Create an issue under Newsletter → Newsletter Issues, then place `[nlwp_newsletter]` or the Newsletter block on a page.
6. To send the issue, open Newsletter → Email, choose the issue, review the languages and formatting, then send or export a copy.

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

Yes. Newsletter → Email composes an issue and sends it with the WordPress `wp_mail()` function. Install and configure an SMTP or email delivery plugin for reliable sending; this plugin does not configure mail transport. You can also copy or download the rendered message and send it manually.

= Which languages are included in an email? =

Each issue can include one or more configured languages. The selected languages are combined into one email, and the primary language supplies the subject, header, footer and interface labels.

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
4. Email compose screen with language and formatting controls.

== Changelog ==

= 0.2.0 =
* Added per-issue email composition, preview, send, copy and download.
* Added configurable email languages, primary language and multilingual title/description formatting.
* Added email sender settings, per-issue recipients, test sends and send history.
* Refactored shared content building so the website and email renderers use the same data.
* Email is sent through `wp_mail()` and requires an external SMTP or email delivery plugin.

= 0.1.0 =
* Initial website-side release: issues, events, categories, multilingual content, shortcode and block.
