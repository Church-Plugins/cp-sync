=== CP Sync ===
Contributors: churchplugins, tabormoushey
Tags: church, ccb, planning-center, sync, events
Requires at least: 6.0
Tested up to: 6.7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync groups and events from your church management system (Planning Center Online or Church Community Builder) to WordPress.

== Description ==

CP Sync connects your WordPress site to your church management system (ChMS), keeping groups, events, and related content in sync automatically. Configure once and let CP Sync run scheduled imports in the background — no copy-paste, no stale data.

= Supported Church Management Systems =

* **Planning Center Online (PCO)** — Sync Groups and Calendar events via OAuth
* **Church Community Builder (CCB)** — Sync groups and events via the CCB API

= Features =

* **Groups Sync** — Import small groups, classes, and ministries with leaders, schedules, and meeting details
* **Events Sync** — Push your ChMS calendar to The Events Calendar with full venue and image data
* **Scheduled Imports** — Automatic background syncs on a configurable cadence
* **Field Mapping** — Map ChMS fields to WordPress post fields, taxonomies, and custom meta
* **Data Filters** — Include or exclude items by tag, group type, date range, and more
* **WP-CLI Commands** — Run and debug imports from the command line
* **Detailed Logging** — Built-in log viewer for troubleshooting sync issues
* **Developer Hooks** — Action and filter hooks for custom integrations

= Integrations =

* **The Events Calendar** — ChMS events become Events Calendar entries with mapped venues, organizers, and categories
* **CP Groups** — Display synced groups using the Church Plugins Groups plugin

= CCB Event Enrichment =

For Church Community Builder, CP Sync uses a two-phase strategy to populate complete event data: the calendar listing for the initial import, then the event profile to fill in full venue addresses (street, city, state, zip) and event images. Subsequent syncs only re-enrich events that CCB reports as modified, keeping repeat syncs fast.

== Installation ==

1. Upload the `cp-sync` folder to the `/wp-content/plugins/` directory, or install through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → CP Sync** and select your ChMS tab (Planning Center or CCB).
4. Connect to your ChMS:
   * **PCO**: Complete the OAuth flow with your Planning Center credentials.
   * **CCB**: Enter your CCB subdomain, API username, and API password.
5. Click **Test Connection** to verify your credentials.
6. Configure which groups and/or events to sync under their respective tabs.
7. Save settings and run an initial sync, or wait for the scheduled background sync to run.

= Quick Start =

1. **Connect** your ChMS (PCO or CCB)
2. **Choose** which groups and event calendars to import
3. **Map fields** between your ChMS and WordPress (defaults work for most setups)
4. **Set a schedule** under Advanced settings or run a manual sync to verify
5. **Review logs** at Settings → CP Sync → Logs if anything looks off

== Frequently Asked Questions ==

= Which church management systems are supported? =

CP Sync currently supports Planning Center Online (PCO) and Church Community Builder (CCB). PCO uses OAuth for authentication; CCB uses Basic Authentication with an API username and password.

= Do I need The Events Calendar plugin? =

Only if you want to sync events. Groups syncing works independently. If you sync events, install and activate The Events Calendar — CP Sync creates events in that plugin's format.

= Why are my CCB venues missing addresses? =

In version 0.3.0 and later, CP Sync automatically enriches CCB events with full venue addresses via a follow-up call to the `event_profile` endpoint. If addresses are still missing, enable Debug logging at **Settings → CP Sync → Advanced** and look for `Skipping enrichment` or `Failed to fetch event_profile` messages. Recurring event occurrences without a numeric event ID cannot be enriched and will import with the venue name only.

= How often does it sync? =

You can configure scheduled syncs at **Settings → CP Sync → Advanced**, or run a manual sync at any time. Background sync uses WordPress Cron — for sites with unreliable cron, see the Developer Guide for setting up a server-level cron job.

= Will syncing create duplicates? =

CP Sync uses a unique ChMS ID for each item to prevent duplicates. Re-running a sync updates existing items rather than creating new ones.

= Where can I find logs? =

At **Settings → CP Sync → Logs**. Increase verbosity by setting Log Level to "Debug" under Advanced settings.

= Can I extend or customize CP Sync? =

Yes. CP Sync exposes action and filter hooks for developers — see the Developer Guide in the plugin's `/documentation/` directory. The new `cp_sync_{$type}_update_item_after` hook in 0.3.0 enables type-specific post-processing for custom integrations.

== Changelog ==

= 1.0.0 =
* Security: CCB API credentials are now encrypted at rest (libsodium with OpenSSL fallback, keyed from your site's WordPress salts). Existing plain-text credentials keep working and are re-encrypted on the next save.
* Security: The CCB subdomain is validated everywhere it is used — malformed values are rejected with a clear error instead of being built into API URLs.
* Security: OAuth callback tokens and all settings saved over the REST API are now sanitized per field, driven by the new settings schema.
* Security: Added missing permission checks to the PCO option-lookup REST endpoints (they now require an administrator, matching every other settings route).
* Security: Raised minimum versions of bundled HTTP libraries past known advisories (Guzzle 7.15.1+).
* New: Reset tool under Settings → Advanced (and `wp cp-sync reset`) with five levels — clear a stuck sync queue, force a full re-import, delete imported content, remove the ChMS connection, or reset everything. Destructive levels require typed confirmation.
* New: Per-post sync lock — a "Prevent sync from updating this post" checkbox on every imported post's edit screen. Locked posts are never overwritten or removed by a sync, so manual customizations are preserved; unchecking resumes syncing on the next run.
* New: Sermon syncing from Planning Center Publishing to CP Sermons — episodes published to your Church Center library import as CP Sermons sermons with series, speakers, media URLs, and artwork. Includes a Sermons settings tab with filtering, and a Sync Sermons toggle on the Connect tab.
* New: Groups and Events sync can be enabled or disabled per ChMS with toggles on the Connect tab. When a required companion plugin (CP Groups or The Events Calendar) is not active, the toggle is disabled with an explanation, the corresponding settings tab is hidden, and scheduled syncs skip that feed.
* New: Optional "Delete all data on uninstall" toggle (Settings → Advanced). Off by default, so uninstalling the plugin keeps your data; turn it on to have WordPress permanently remove all CP Sync settings, the ChMS connection, sync state, and imported content when the plugin is deleted.
* Enhancement: The settings screen was rebuilt on the WordPress component library for a native wp-admin look and feel, dramatically smaller page weight, and reliable browser-tab URLs for each settings tab.
* Enhancement: Settings screens are now declared in PHP as a schema and rendered by a single form engine — new integrations and fields no longer require custom UI code.
* Enhancement: Sync filter conditions no longer crash when no comparison options are available, correctly reset their value when switching between comparison types, and use collision-proof identifiers.
* Bug Fix: Fixed a crash when reading a settings field from a group that had not been saved yet.
* Bug Fix: Fixed duplicate saves when switching the active ChMS.
* Bug Fix: Image-cache error messages were never translatable due to a placeholder text domain.
* Removed: The unfinished Ministry Platform settings UI has been removed while MP support is completed; PCO and CCB are unaffected.
* Developer: New fast unit-test suite (`composer test`, 83 tests) and security-focused PHPCS gate (`composer lint`); `composer verify` runs both.
* Developer: The legacy secondary build system (wpackio) was removed — `npm run build:wp` is now the only build.

= 0.3.2 =
* Bug Fix: Fixed syncs that completed successfully but imported nothing on databases that are not utf8mb4. The queue is now encoded before it is stored, so characters the database cannot represent can no longer corrupt it.
* Bug Fix: Sync failures are no longer silent — an unreadable queue is now reported in the logs instead of being discarded as though it had been processed.
* Bug Fix: The queue column charset is now reported in the logs, with a warning when the database is not utf8mb4 and content will be imported with `?` substitutions.
* Bug Fix: `wp cp-sync ccb process_queue` now clears processed batches correctly on multisite, counts items rather than batches, and no longer stops the run when a single item throws.
* Documentation: Documented the utf8mb4 database requirement and added troubleshooting steps for syncs that report success but import nothing.

= 0.3.1 =
* Bug Fix: Fixed incomplete venues created during initial import — venue creation now deferred to enrichment phase where full address data is available.
* Bug Fix: Fixed stale venue associations persisting when a location is removed in CCB.
* Enhancement: Two-phase enrichment strategy for CCB events — always enriches new events, then uses modified timestamps to detect changes on subsequent syncs.
* Enhancement: Eliminated duplicate API calls during CCB event re-enrichment (single `event_profile` call instead of two).
* Enhancement: Added session-based venue deduplication to prevent redundant updates when multiple events share a venue.
* Enhancement: Added shimmer loading skeleton to settings page while JS bundle loads.
* Documentation: Documented CCB event enrichment behavior, the new `cp_sync_{$type}_update_item_after` action hook, and updated venue/location guidance in the troubleshooting and TEC integration guides.

= 0.3.0 =
* New Feature: CCB event enrichment — automatically fetches full venue addresses and event images from the `event_profile` endpoint after initial import.
* Enhancement: CCB venue import now supports full location data (street address, city, state, zip).
* Enhancement: Custom XML parser preserves CCB API attributes for reliable event ID extraction.
* Enhancement: Graceful enrichment failure handling — errors don't block event import.

= 0.2.0 =
* Breaking Change: CCB now uses Basic Authentication (username/password) instead of OAuth. Existing users will need to reconnect with API credentials.
* New Feature: Added WP-CLI commands for CCB debugging (`wp cp-sync ccb test-connection`).
* New Feature: Added configurable date range for CCB event sync with multiple preset options.
* New Feature: Added option to remove events outside the configured date range.

== Upgrade Notice ==

= 0.3.1 =
CCB event imports now include full venue addresses and event images automatically via a new two-phase enrichment process. After updating, run a manual sync to backfill enrichment data for existing CCB events.

= 0.3.0 =
Adds CCB event enrichment with full venue addresses and event images. CCB venue import now uses complete location data instead of just venue names.

= 0.2.0 =
Breaking change: CCB integration switched from OAuth to Basic Authentication. After updating, reconnect CCB at Settings → CP Sync → CCB using your API username and password.
