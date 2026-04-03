=== Muri Link Tracker ===
Contributors: muri3, nicolasdiolez
Tags: link checker, broken links, affiliate links, redirect checker, seo
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A high-performance link checker for WordPress with affiliate detection, redirect tracking, and background processing.

== Description ==

**Muri Link Tracker** is a powerful WordPress plugin designed to help site owners maintain their SEO health and user experience by identifying broken links, analyzing redirects, and detecting affiliate patterns — all without slowing down your site.

= Key Features =

* **Background Processing** — Scans run silently in the background using Action Scheduler, so your site stays fast.
* **Affiliate Link Detection** — Automatically identifies affiliate links across 14+ networks (Amazon, Awin, CJ, ShareASale, Impact, and more), including cloaked internal redirects.
* **Redirect Chain Analysis** — Detects single redirects, redirect chains, and redirect loops with full chain visualization.
* **Modern React Dashboard** — Real-time statistics, filterable link table, inline editing, and bulk actions in a polished admin interface.
* **Smart Delta Scans** — Only re-scan posts modified since the last scan, saving time and server resources.
* **CSV Export** — Export your full link inventory with filters applied for reporting and auditing.
* **Configurable** — Adjustable batch sizes, timeouts, request delays, and recheck intervals to fit any hosting environment.
* **Scan Resume** — Interrupted scans can be resumed right where they left off.
* **Gutenberg-Aware** — Extracts links from Gutenberg blocks with block-level metadata.

= What It Detects =

* Broken links (4xx and 5xx HTTP status codes)
* Redirect chains and loops
* Affiliate links (domain patterns, URL paths, query parameters, rel="sponsored")
* Cloaked affiliate links (internal URLs that redirect to external affiliates)
* Missing rel attributes (nofollow, sponsored, ugc)
* Timeout and connection errors

= Privacy =

Muri Link Tracker makes outbound HTTP requests to verify the status of links found in your content. These requests use a clearly identified User-Agent string (`MuriLinkTracker/1.0.0`). No personal user data is collected, stored, or transmitted to third parties.

= Development =

The full source code, including React/JavaScript source files and build configuration, is available on GitHub:
[https://github.com/nicolasdiolez/muri-link-tracker](https://github.com/nicolasdiolez/muri-link-tracker)

== Installation ==

1. Upload the `muri-link-tracker` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access the dashboard via **Muri Link Tracker** in the admin sidebar.
4. Click **Full Scan** to analyze all your published content.

= Requirements =

* WordPress 6.9 or higher
* PHP 8.2 or higher

== Frequently Asked Questions ==

= How does the scanning work? =

Muri Link Tracker uses WordPress Action Scheduler to process scans in the background. Posts are scanned in configurable batches, and each link is verified via HTTP HEAD request with GET fallback. This ensures your site remains responsive during scans.

= Does it slow down my site? =

No. All scanning and link checking happens in background processes. The plugin also includes configurable request delays and batch sizes to prevent overloading your server.

= What affiliate networks does it detect? =

Amazon, ShareASale, Commission Junction, Impact, Rakuten, ClickBank, Awin, Tradedoubler, Webgains, Commission Factory, FlexOffers, Skimlinks, Sovrn/VigLink, and PartnerStack. You can extend detection patterns via the `flc/classifier/affiliate_patterns` filter.

= Can I scan only specific post types? =

Yes. Go to Settings and select which post types to include in scans (posts, pages, or any custom post type).

= What happens when I delete a link? =

When you delete a link through the plugin, it removes the `<a>` tag from your post content while preserving the anchor text. The post's `modified_date` is not updated, so your SEO timestamps remain intact.

= Can I resume an interrupted scan? =

Yes. If a scan is cancelled or interrupted, you can resume it from the dashboard. The plugin tracks scan progress and picks up right where it left off.

== Screenshots ==

1. **The Pulse of Your Content** — The Dashboard provides a high-level view of your site's link health with real-time stats and intuitive scan controls.
2. **Precision Link Management** — Explore every link on your site with the filterable link table. Spot broken URLs and affiliate patterns at a glance with status-coded labels.
3. **Instant Fixes** — Correct URLs or update SEO attributes (nofollow, sponsored) directly from the dashboard without ever leaving the page.
4. **Tailored to Your Hosting** — Fine-tune scan intensity, timeouts, and batch sizes in the Settings panel to ensure peak performance on any server.

== Changelog ==

= 1.0.0 =
* Initial release.
* Background link scanning via Action Scheduler.
* Parallel HTTP checking with HEAD/GET fallback.
* Affiliate link detection for 14+ networks.
* Redirect chain and loop detection.
* Modern React admin dashboard with real-time stats.
* Link table with filtering, pagination, and bulk actions.
* Inline link editing with URL and rel attribute updates.
* CSV export with active filters.
* Delta scan (only modified posts since last scan).
* Scan resume functionality.
* Configurable batch sizes, timeouts, and request delays.
* Full internationalization support (i18n ready).

== Upgrade Notice ==

= 1.0.0 =
Initial release of Muri Link Tracker.
