=== BWing ===
Contributors: themehunk
Tags: rankings, themes, plugins, statistics, theme detector
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Daily rankings tracker for WordPress.org themes, plugins and authors, with premium rankings, a theme & plugin detector, hosting checker and statistics.

== Description ==

BWing syncs the WordPress.org theme and plugin directories every day, stores a daily snapshot of each item's rank, active installs and downloads, and turns that history into rankings, trends and statistics.

Every front-end section has its own settings screen in the dashboard and a matching shortcode, so you can place it on any page. BWing is built to pair with the BlogWings theme but works with any theme.

= Features =

* **Daily sync** of WordPress.org themes and plugins via WP-Cron, run in time-boxed batches. An optional secret server-cron URL is available for hosts where WP-Cron is unreliable.
* **Rankings** – full theme and plugin ranking tables with filters, sorting, search and pagination.
* **Premium rankings** – manually managed premium themes and plugins.
* **Authors leaderboard** and single author profiles.
* **Biggest moves & notable moves** – trending up/down, bracket crossings, top-10 entries and anomalies.
* **Featured themes & plugins** – always show chosen companies' items, with the remaining slots filled by daily random picks.
* **Download stats** – today, 7-day and 30-day downloads in the top lists.
* **Brand logos & author photos** – premium items, theme authors and plugin authors get their logo or WordPress.org photo automatically.
* **SEO & AI search** – meta descriptions, canonical URLs, Open Graph, JSON-LD schema, an XML sitemap of every tracked page and `/llms.txt`.
* **Statistics** dashboards for themes, plugins and authors.
* **Fastest themes** Lighthouse table.
* **Theme & plugin detector** – scan any site, with "most detected" lists.
* **Hosting checker** tool.
* **Single item pages** with rank, active installs and downloads charts at pretty URLs: `/themes/{slug}/`, `/plugins/{slug}/`, `/premium-themes/{slug}/`, `/premium-plugins/{slug}/` and `/authors/{slug}/`. Plain permalinks are supported too.
* **Newsletter** signup form with a subscribers list in the admin.
* **FAQ, CTA, hero, about and feature-card** content blocks.
* **One-click setup** – on activation BWing creates its database tables, default content and all pages (Home, Themes, Plugins, Stats, Detector, Hosting Checker, About and more) with the shortcodes already in place.

= Shortcodes =

Every shortcode reads its defaults from its dashboard section, and any attribute overrides them, e.g. `[bwing_top type="plugin" count="10" title="Top Plugins"]`.

* `[bwing_hero]` – Hero with headline, live search, buttons and counters.
* `[bwing_search]` – Live search for themes, plugins and authors.
* `[bwing_counters]` – Stat counters.
* `[bwing_trending type="both|theme|plugin"]` – Biggest moves.
* `[bwing_featured type="both|theme|plugin"]` – Featured themes & plugins (pinned companies + daily random picks).
* `[bwing_tools]` – AI tools directory.
* `[bwing_submit_tool]` – Submit an AI tool form.
* `[bwing_listings]` – Listing directory (themes, plugins, blocks, hosting, software). cate="wp-theme" per_page="18"
* `[bwlisting cate="woocommerce" count="9"]` – Compact listing grid for one category (kept from the BlogWings theme).
* `[bwing_login]`, `[bwing_register]`, `[bwing_account]` – Front-end account pages.
* `[bwing_notable]` – Notable moves feed.
* `[bwing_top type="theme|plugin" count="20"]` – Top free items.
* `[bwing_top_premium type="theme|plugin"]` – Top premium items.
* `[bwing_top_authors type="theme|plugin|all"]` – Top authors.
* `[bwing_mini_top type="theme|plugin|author"]` – Compact top-5 list.
* `[bwing_text section="about|ecosystem"]` – Text block.
* `[bwing_beyond]` – Feature cards grid.
* `[bwing_faq section="home|theme|plugin|detector|hosting"]` – FAQ accordion.
* `[bwing_cta]` – Closing call-to-action band.
* `[bwing_newsletter style="card|inline"]` – Newsletter signup form.
* `[bwing_subnav current="..."]` – Section tabs.
* `[bwing_ranking type="theme|plugin"]` – Full ranking table.
* `[bwing_premium_ranking type="theme|plugin"]` – Full premium ranking table.
* `[bwing_authors]` – Authors leaderboard.
* `[bwing_stats type="theme|plugin|author"]` – Statistics dashboard.
* `[bwing_fastest]` – Fastest themes table.
* `[bwing_detector]` – Theme & plugin detector.
* `[bwing_most_detected type="theme|plugin"]` – Most detected themes or plugins.
* `[bwing_hosting_checker]` – Hosting checker.
* `[bwing_item type="theme|plugin" slug="astra"]` – Single item page.
* `[bwing_author slug="automattic"]` – Single author profile.

The full list is also available under **BWing → Tools & Shortcodes**.

= For theme developers =

* Override any template by copying it from the plugin's `templates/` folder into a `bwing/` folder in your theme.
* Helper functions: `bwing_get_top( $type, $limit )`, `bwing_get_top_authors( $type, $limit )`, `bwing_url( $path )` and `bwing_enqueue()`.
* Front-end assets load only on pages that use a shortcode, unless "load CSS everywhere" is enabled in the General settings.

== Installation ==

1. Upload the `bwing` folder to `/wp-content/plugins/`, or install the zip from **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. Tables, default settings, sample content and pages are created automatically, and the daily sync is scheduled for 03:00.
3. Go to **BWing → Dashboard** to check sync health and run the first sync manually.
4. Optional: if WP-Cron is unreliable on your host, point a server cron job at the server-cron URL shown on the dashboard.

== Frequently Asked Questions ==

= How often is the data updated? =

Once a day. The sync runs on the `bwing_daily_sync` cron event and continues in batches until every page of the directory has been fetched.

= Single item URLs return 404. =

Go to **Settings → Permalinks** and click **Save Changes** to flush the rewrite rules.

= Can I use the shortcodes with any theme? =

Yes. BWing ships its own styles; the BlogWings theme simply adds tighter integration.

== Changelog ==

= 1.6.0 - 2026-11-03 =
* New: Listing directory at `/listing/` – search, category tabs with sub-category pills, Free/Pro filter, sorting (newest, most reviewed, A–Z) and pagination. Category and tag archives (`/listing-cate/{term}/`, `/listing-tag/{tag}/`) use the same layout.
* New: Single listing page at `/listing/{slug}/` – badges, author link, rating (YASR), Live demo / Download / Stats buttons, screenshot, overview, key features, tags, details sidebar, share buttons, related listings and reviews, with SoftwareApplication schema.
* New: Listing author page at `/listing/author/{name}/` – author logo, listing count by category, website link and all of the author's listings.
* New: `[bwing_listings]` shortcode; the theme's `[bwlisting]` shortcode keeps working.
* New: "Listing details" box (short description, demo, download, author name/URL, Free/Pro) and list columns in the admin.
* New: WordPress.org download links become direct .zip downloads and link to the item's stats page.
* New: "Listing" dropdown in the primary menu; starter categories are created when none exist.
* Changed: The listing post type, categories, tags and meta keys moved from the BlogWings theme into the plugin with the same names, so existing listings and URLs keep working.

= 1.5.1 - 2026-10-31 =
* Changed: Headings, text and backgrounds use the BlogWings theme palette again (navy ink, lavender backgrounds); the lighter 700/600/400 weight scale stays.
* Changed: Top-3 rank badges and logo backgrounds use theme colours (brand soft / surface) instead of fixed colours.
* Fixed: Log in and registration did not redirect after success when no return address was given.
* Fixed: The 1.5.0 update could run several times at once and create duplicate pages, menu items and tools.

= 1.5.0 - 2026-10-31 =
* New: AI tools directory (`[bwing_tools]`) – product-finder page with search, category pills, pricing filter, sorting and pagination; each tool has its own page at `/ai-tools/{slug}/` with SoftwareApplication schema and similar tools.
* New: Front-end tool submission (`[bwing_submit_tool]`) for logged-in members. Submissions are saved as Pending for review, with duplicate-URL check, honeypot, a 5-per-day limit and an admin email.
* New: Front-end log in (`[bwing_login]`), registration (`[bwing_register]`) and account (`[bwing_account]`) pages; the account page lists the member's submissions with their review status.
* New: AI Tools post type and categories under BWing in the admin, with a details box (website, tagline, pricing, logo, featured) and list columns.
* New: Starter set of 23 well-known AI tools in 10 categories.
* New: "AI Tools" dropdown in the primary menu and AI tools / Submit a tool links in the footer menu; pages are created automatically on update.
* New: Settings → Sections → AI Tools & Accounts (titles, tools per page, admin emails, open/closed registration).
* Changed: The admin bar is hidden for members without editing rights.

= 1.4.1 - 2026-10-31 =
* Changed: New base text palette – deep teal headings and names (#10302e), grey-green secondary text, warm neutral backgrounds and borders; matching dark mode.
* Changed: Lighter type scale – 700 for titles and numbers (was 800), 400 for descriptions, 600 small-caps table and column labels on a tinted header row.
* Changed: Ranking numbers shown as round badges, top 3 highlighted in lime.

= 1.4.0 - 2026-10-31 =
* New: SEO for every theme, plugin, premium and author page – meta description, canonical URL, Open Graph / Twitter tags and JSON-LD (SoftwareApplication with ratings, ProfilePage, BreadcrumbList; WebSite search box on the home page).
* New: XML sitemap of every tracked theme, plugin, author and premium page at `/bwing-sitemap.xml`, added to robots.txt and to the Yoast SEO / Rank Math sitemap index.
* New: `/llms.txt` for AI search engines with key pages and today's top 25 themes and plugins.
* New: Answer-first summary paragraph at the top of each theme and plugin page.
* New: Featured themes & plugins section (`[bwing_featured]`) replaces Notable moves on the home page. Pin companies or single items from Sections → Featured (default: 2 theme and 3 plugin slots for ThemeHunk); the other slots are random picks that change daily. 10 items per column by default.
* New: The sync also fetches every theme and plugin by pinned companies, including items outside the ranked pages.
* New: Today, 7-day and 30-day downloads in the Top free themes / plugins lists, with an on/off setting.
* New: Automatic brand logos for premium items (free WordPress.org icon or the vendor's site icon).
* New: Theme authors show their company logo, plugin authors their WordPress.org profile photo; stored by the sync in two new database columns.
* Improved: Biggest moves redesigned – card header with "Full ranking" link, Climbing / Falling labels, old → new rank, and a layout that adapts to its container in any theme.
* Improved: Top free lists use a single-row table layout with column headers; paired home sections now stack full width.
* Improved: Every image and thumbnail gets a 6px radius and 4px padding; ranking names use the new `--bw-name` colour token (dark-mode aware).
* Improved: Unranked items show "Not ranked" instead of "#0".
* Fixed: Plugin authors could show another contributor's photo (e.g. a team account) instead of their own.
* Fixed: Long names overflowing the Biggest moves and Featured cards.
* Fixed: Download counts are hidden instead of wrong when history comes from a different data source (demo vs live).

= 1.3.0 - 2026-10-30 =
* Initial public release.
* New: Daily WordPress.org theme and plugin sync with batched WP-Cron jobs and a secret server-cron URL.
* New: Ranking, premium ranking, authors leaderboard, statistics and fastest-themes sections.
* New: Biggest moves and notable moves feeds.
* New: Theme & plugin detector, most-detected lists and hosting checker tools (rate-limited AJAX).
* New: Single item and author pages with rank, installs and downloads charts at pretty URLs.
* New: Newsletter signup with subscribers admin screen.
* New: Admin dashboard with sync health checks, per-section settings, Themes & Plugins, Premium, Subscribers, Detections and Tools & Shortcodes screens.
* New: 25 shortcodes, each configurable from its dashboard section.
* New: Automatic creation of default pages, content and database tables on activation.
* New: Theme template overrides via a `bwing/` folder.

== Upgrade Notice ==

= 1.6.0 =
Adds the Listing directory, single listing and listing author pages. Existing listings from the BlogWings theme keep working; the Listing menu is added automatically. You can remove single-listing.php and the listing post type code from the theme.

= 1.5.0 =
Adds an AI tools directory with member submissions and front-end log in / registration. Pages and the AI Tools menu are created automatically.

= 1.4.0 =
Adds SEO meta, schema, a sitemap and llms.txt, the Featured section, download stats and author/brand images. After updating, run Sync now once so author photos and featured companies' items are fetched.

= 1.3.0 =
Initial public release.
