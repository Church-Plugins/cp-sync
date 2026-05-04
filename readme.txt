=== CP Sync ===
Contributors: churchplugins, tabormoushey
Tags: church, ccb, planning-center, sync, events
Requires at least: 6.0
Tested up to: 6.7.1
Requires PHP: 7.4
Stable tag: 0.3.1
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
