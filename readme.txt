=== CP Sync ===
Contributors: churchplugins, tabormoushey
Tags: church, ccb, planning-center, sync, events
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
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
* **Events Sync** — Import events from your church management system. While The Events Calendar is active, they go there. Without it, turn on Sync Events to show them on your site.
* **Scheduled Imports** — Automatic background syncs on a configurable cadence
* **Data Filters** — Include or exclude items by tag, group type, date range, and more
* **WP-CLI Commands** — Run and debug imports from the command line
* **Detailed Logging** — Built-in log viewer for troubleshooting sync issues
* **Developer Hooks** — Action and filter hooks for custom integrations

= Integrations =

* **The Events Calendar** — Optional. While it is active, events from your church management system are created there.
* **CP Groups** — Optional. CP Sync can show the same groups without it. CP Groups adds filters and a contact form when you activate it.

= CCB Event Enrichment =

The calendar listing imports the event. On CP Sync's own event pages, that import saves the location name. When The Events Calendar is active and the event has a numeric Church Community Builder id, the event profile can then add a venue with the name, street, city, state, and zip, and an image. Later syncs do that again only when Church Community Builder reports a change.

== Installation ==

1. Upload the `cp-sync` folder to the `/wp-content/plugins/` directory, or install through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Church Plugins → CP Sync** and open the **Connect** tab.
4. Set **Church Management System**, then connect:
   * **PCO**: Choose Planning Center Online, then click **Connect** under **PCO API Configuration** and complete the OAuth flow.
   * **CCB**: Choose Church Community Builder, enter **Subdomain**, **API Username**, and **API Password**, then click **Connect to CCB**.
5. On **Connect**, turn on **Sync Groups** and/or **Sync Events**, then click **Save all Settings**. Configure each enabled feed on its tab.
6. Save settings and run an initial sync, or wait for the scheduled background sync to run.

= Quick Start =

1. **Connect** your ChMS at **Church Plugins → CP Sync → Connect**
2. **Choose** which groups and event calendars to import
3. **Set a schedule** under **Advanced** (**Update Interval**) or click **Pull now**
4. If anything looks off, open **Log**, set **Enable Debug Mode** to **Enable**, click **Save all Settings**, run the sync again, and check **Log File Content**

== Frequently Asked Questions ==

= Which church management systems are supported? =

CP Sync currently supports Planning Center Online (PCO) and Church Community Builder (CCB). PCO uses OAuth for authentication; CCB uses Basic Authentication with an API username and password.

= Do I need The Events Calendar plugin? =

No. Groups can show on your site without another plugin. Events can show on your site without The Events Calendar: each date gets a page, and a real series of dates gets a series page. Turn on Sync Events (Connect). Leave it off if you already have a page at /events/. The /events/ page opens on a month calendar. Calendar and List switch between that calendar and a list of upcoming dates. Without JavaScript, the page shows the list. The Category filter appears only when the site has event categories. On these pages, a Church Community Builder event type is used as a category when the listing includes one.

Install The Events Calendar only if you want events in that calendar. While it is active, CP Sync keeps using it and does not create the separate event pages.

= Why are my CCB venues missing addresses? =

When The Events Calendar is active, and the event has a numeric Church Community Builder id, the event profile can add a venue with the name, street, city, state, and zip, and an image. The calendar listing itself does not save that address. If addresses are still missing, open **Church Plugins → CP Sync → Log**, set **Enable Debug Mode** to **Enable**, click **Save all Settings**, run the sync again, and look for `Skipping enrichment` or `Failed to fetch event_profile` messages. An event without a numeric Church Community Builder id cannot use that profile, so it does not get a venue from this step.

= How often does it sync? =

You can configure scheduled syncs at **Church Plugins → CP Sync → Advanced** (**Update Interval**), or run a manual sync with **Pull now** at any time. Background sync uses WordPress Cron — for sites with unreliable cron, see the Developer Guide for setting up a server-level cron job.

= Will syncing create duplicates? =

CP Sync uses a unique ChMS ID for each item to prevent duplicates. Re-running a sync updates existing items rather than creating new ones.

= Where can I find logs? =

On the **Log** tab at **Church Plugins → CP Sync**. Set **Enable Debug Mode** to **Enable**, click **Save all Settings**, then run the sync again and check **Log File Content**. CP Sync writes to the log only while debug mode is on.

= Can I extend or customize CP Sync? =

Yes. CP Sync exposes action and filter hooks for developers — see the Developer Guide in the plugin's `/documentation/` directory. The new `cp_sync_{$type}_update_item_after` hook in 0.3.0 enables type-specific post-processing for custom integrations.

== Changelog ==

= 1.2.0 =
* Fix: A synced item you moved to the Trash stays in the Trash when a filter leaves it out of a sync. It is not permanently deleted. When a later sync includes that item again, the same item stays in the Trash instead of a new published one being added.
* Fix: Registration Category filters now follow the comparison you picked. "Is Not" and "Is Not in" leave a registration out when any of its categories is one you excluded, and they include a registration that has no category. "Is Empty" matches a registration with no category, and "Is Not Empty" matches one that has a category. If you already filter registrations by Category, the next sync can include or leave out different registrations than before.
* Fix: With The Events Calendar, an event that is hidden or not approved is moved to the Trash instead of being deleted. When it is shown again, the same event comes back, including its image. An event that was removed in Planning Center or Church Community Builder is still deleted.
* Fix: A published synced item that no longer matches a filter is deleted, and a later sync that includes it adds a new item. An item you permanently deleted in WordPress is added again as a new item once it matches. An item that is still published on the site is updated in place.
* Fix: A Planning Center registration with several signup times is imported as one event, at the next upcoming time. If every time is in the past, it uses the most recent past time. It does not add one event per time.
* Fix: A Start Date or End Date filter no longer stops the events sync when a registration has no signup time. That registration is left out, and the other events still sync.
* New: Events synced from Planning Center Registrations store the public registration price: one price, a price range when there are several options, or Free. On sites that do not use The Events Calendar, an event's page shows the price when the event is not free. On sites that use The Events Calendar, the price also fills the event's cost, unless you entered a cost yourself.
* New: Sites that do not use The Events Calendar can show events from Planning Center or Church Community Builder. Each date is a page under Events. A Planning Center event with more than one date, or a Church Community Builder event with more than one date, also gets a series page under Event Series. A single date does not. Turn on Sync Events (Connect). It starts off without The Events Calendar, including on sites updated from an earlier version, so a page you already have at /events/ is left alone. /events/ opens on a month calendar with a switch to a list view and a category filter. On a phone, days with events show a dot, and tapping a day lists its events. Add `[cp-sync-events]` to a page to show the next few dates. An event page shows the stored timezone, location, description, and a price when the event is not free, and can download a calendar file for that one published date. A series page lists its upcoming dates and does not download a file for the whole series. Events have no screens in the WordPress admin, because the sync owns their content. Sites that use The Events Calendar are unchanged.
* New: Groups can be shown on the site without CP Groups: a groups page at /groups/, a page for each group, and the [cp-sync-groups] shortcode. The layout matches CP Groups, without its filters. A group page shows the photo, description, meeting details, leader names, and View Details and Register Now buttons when the church management system sent those links. Leader email addresses are not shown. CP Groups adds filters and a contact form, and it takes over the same groups when you activate it. Turn on Sync Groups (Connect). It starts off without CP Groups, including on sites updated from an earlier version. Groups have no screens in the WordPress admin until CP Groups is active, because the sync owns their content.
* New: When a sync removes hidden or unapproved events, administrators see a notice with the counts. It stays until each administrator dismisses it, and a newer removal replaces it.
* Church Community Builder events: The first sync after updating may adjust each event's saved timezone once. The time shown on the event does not change.
* Fix: A Church Community Builder event whose start or end is not a date, such as TBD, is skipped instead of stopping the sync.
* The plugin download includes the templates and styles for the event and group pages. They use the same ChurchPlugins CSS variables and buttons as other CP products.
* Docs: Planning Center and Registration Filters notes match this release.

= 1.1.0 =
* New: Planning Center group leaders now sync to CP Groups. Group Leader gets every leader's name, and Group Leader Email gets the group's contact email, or the email of the first leader who has one (developers can change this with the `cp_sync_pco_group_leader_email` filter). On CP Groups 1.2 or later, leaders are saved as Group Leaders rows instead, each with that leader's own email; the contact email is only used when no leader has one. If the leaders request fails, leaders already stored are kept.
* Fix: A failed Planning Center groups or tag-group request now stops the groups sync with a logged error, instead of being read as "no data" and removing group types and tag filters. Events and Sermons still sync in the same run.
* Security: Sync and background-process actions can no longer be triggered through a site URL. They run only from the schedule (WP-Cron) or the Pull buttons in the settings screen. Updates the ChurchPlugins framework to 1.1.19, which ignores request actions that aren't registered and requires a capability and nonce for those that are.
* Fix: Church Community Builder groups are no longer all marked full. CCB sends "false" as text, which was read as true.
* Fix: Saving, disconnecting and loading Church Community Builder credentials no longer fails on hosts without the PHP sodium extension.
* Docs: Accuracy fixes across the introduction, What's New, Developer Guide, how-to and settings pages, Groups Synchronization steps, and Data Mapping.

= 1.0.0-beta2 =
* Fix: A filter group set to "any" with no conditions no longer filters out every item — previews and syncs affected by this returned zero events. The Church Center visibility restriction (events and groups) is also now applied correctly; it was previously skipped due to a mis-keyed internal condition.
* Fix: Synced events no longer land at 12:00am — event times now persist on both newly created and existing events, and all-day status is kept in sync (including clearing it when an event gains specific times at the source).
* Fix: A failed Planning Center API request could silently read as "the calendar is empty," which removed previously imported events. Failed fetches now abort the sync with a logged error, and a safety guard refuses to run the removal pass when a fetch unexpectedly returns zero items.
* Fix: Event previews no longer crawl the entire calendar — they fetch only the handful of items they display, so Generate Preview responds in seconds on large calendars.
* Fix: Past events are no longer deleted. Because ChMS queries are windowed (Planning Center returns only future events; CCB only the configured date range), an event that ended simply stopped appearing in the sync and was removed as though it had been deleted at the source — permanently, along with its featured image. Cleanup now only removes events that are still inside the query window. Developers who want the old behavior can opt back in with the new `cp_sync_remove_past_events` filter; see the Developer Guide.
* Removed: The CCB "Remove events outside the date range" setting. It never took effect (it checked a post meta value the plugin never wrote, so past events were deleted regardless) and is superseded by the fix above.
* Fix: Removing a synced item no longer blanks the image on another item that shares it. Imported images are reused rather than downloaded twice, so one image can belong to several items; deleting one of them took the shared image with it. The image is now kept until the last item using it is gone. The Reset tool's content level follows the same rule, so it no longer deletes images still in use by content it is leaving in place.
* New: A sermon's Planning Center channel now sets its Service Type in CP Sermons, including the channel artwork — which CP Sermons serves as podcast channel art. Channels are matched by their Planning Center id, so renaming one updates the existing Service Type rather than creating a duplicate. Requires CP Sermons 1.6.3 or later with Service Types enabled; sites without them are unaffected.
* Fix: Sermon series now import their graphic from Planning Center. The series artwork was fetched with each episode but discarded, so synced series had no featured image. Requires CP Sermons 1.6.3 or later. An image you set on a series by hand is never replaced.
* Fix: Sermon artwork now comes from Planning Center correctly. Artwork is returned as a file object with named renditions, which the plugin did not read, so sermons silently fell back to a still frame from their video. A sermon now uses the episode image you uploaded; where you haven't uploaded one, its image is left empty so your theme falls back to the series image and then the service type image. Planning Center's auto-generated placeholder artwork (the abstract gradient it assigns to episodes with no image) is ignored rather than imported, for sermons, series and service types alike. Placeholder images imported by an earlier sync are removed on the next run, unless you set the image by hand.
* Improved: Planning Center rate limits (HTTP 429) are retried automatically with a short back-off instead of failing the sync.
* Improved: The sync log now records crawl progress page by page, fetch counts, rate-limit waits, and the result of the background dispatch — making stalled imports diagnosable from the log alone.

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
