# Developer Guide

This guide is intended for developers who want to extend or customize the CP-Sync plugin. It covers hooks, filters, the API, and cron.

## Plugin Architecture

CP-Sync follows an object-oriented architecture with clear separation of concerns:

- **Root** (`includes/_Init.php`, `includes/Templates.php`): plugin bootstrap, logging, and templates, built on the bundled ChurchPlugins framework (`includes/ChurchPlugins`)
- **ChMS**: Church Management System integrations
- **Admin**: Administrative interfaces
- **Integrations**: WordPress plugin integrations
- **Setup**: Initialization and configuration

## Available Hooks

CP-Sync provides various action and filter hooks for customization.

### Action Hooks

```php
// Fires after any item of a given type is created or updated.
// $type matches the integration type (e.g. 'events', 'groups').
// Useful for type-specific post-processing such as fetching additional
// data from the source API. CCB uses this hook to enrich events with
// full venue addresses and images from the event_profile endpoint.
do_action("cp_sync_{$type}_update_item_after", $item, $post_id);
```

### Filter Hooks

```php
// Whether to delete past events during sync cleanup (default false)
apply_filters('cp_sync_remove_past_events', false, $chms_id, $post_id, $integration);

// Email stored as a Planning Center group's Group Leader Email. Defaults to the group's
// contact email, otherwise the first leader who has an email. On CP Groups 1.2 or newer
// the result is only used when no leader has an email: it is then put on the first
// Group Leaders row.
apply_filters('cp_sync_pco_group_leader_email', $email, $group, $leader_meta);

// Force debug logging on (same as Log → Enable Debug Mode). Add this from a plugin or
// mu-plugin, not a theme: it is read once when CP Sync loads. Defining the
// CP_SYNC_DEBUG constant as true also works.
apply_filters('cp_sync_debug_mode', $debug_mode);

// Public address for CP Sync event pages. Default "events" (/events/).
// Used only when The Events Calendar is not active.
apply_filters('cp_sync_event_rewrite_slug', 'events');

// Public address for CP Sync series pages. Default "event-series"
// so it does not take /series/ from CP Sermons. Filter it to "series"
// on a site that does not use that address.
apply_filters('cp_sync_event_series_rewrite_slug', 'event-series');
```

## Preserving Past Events

Every ChMS query is windowed — Planning Center's calendar crawl only asks for future
event instances, and CCB's is bounded by the configured date range. Once an event's end
date passes it simply stops appearing in the sync payload, which is indistinguishable
from the event having been deleted at the source.

By default the sync **keeps** those events. The rule is based on the event's own end
date, not on the query window: an event whose end date has passed is preserved when it
goes missing from the response, and an event that has not yet ended is removed — the
latter being the case that genuinely means "deleted in the ChMS."

One consequence worth knowing: if your date range extends into the past (CCB's
`include_past_30` or a custom range with an earlier start), an event you delete in the
ChMS *after* it has already happened will not be removed from WordPress, because the
sync cannot distinguish it from one that simply aged out of the window. Delete it in
WordPress too, or opt into the filter below.

If you want past events removed instead, opt in:

```php
add_filter('cp_sync_remove_past_events', '__return_true');
```

Removal is permanent — the event post is force-deleted, bypassing the trash, and its featured image is deleted too unless another post uses it — so leave this off unless you're certain.

You can also scope the decision per event:

```php
add_filter('cp_sync_remove_past_events', function($remove_past, $chms_id, $post_id) {
	// Only prune past events older than two years.
	$end = get_post_meta($post_id, '_EventEndDate', true);

	return $end && $end < date('Y-m-d H:i:s', strtotime('-2 years'));
}, 10, 3);
```

To keep an individual event untouched by sync entirely — past or future — use the lock
option on the event itself rather than this filter.

## Groups Lite templates

When CP Groups is not active, **Display groups with CP Sync** is on, and **Sync Groups** is on for the active church management system, CP Sync registers `cp_group` (archive slug `groups`) plus `cp_group_type`, `cp_group_category`, and `cp_group_life_stage`. An events-only site does not get that archive. On a site that was using CP Sync before 1.2.0, the display toggle defaults off until an admin turns it on. The 1.2.0 upgrade also saves the 1.1.0 Sync Groups state: unsaved, with CP Groups inactive, is stored off. Activating CP Groups registers those same names and takes over the posts. The life-stage rewrite slug stays `life stages` (with the space) until a follow-up in both plugins.

Theme overrides live in `cp-sync/` inside the theme:

- `cp-sync/groups/archive.php`
- `cp-sync/groups/single.php`

The lookup is `CP_Sync\get_template_hierarchy()`. The shortcode is `[cp-sync-groups count="12" type="small-groups"]`. Leader output reads the `leaders` meta rows first (name only), then the legacy `leader` value. Emails are not printed.

## REST API Endpoints

CP-Sync provides REST API endpoints for programmatic access:

- `POST /wp-json/cp-sync/v1/pull`: pull all content types
- `POST /wp-json/cp-sync/v1/pull/{type}`: pull one type (groups, events or sermons)
- `GET /wp-json/cp-sync/v1/get-log`: read the sync log
- `POST /wp-json/cp-sync/v1/clear-log`: clear the sync log
- `POST /wp-json/cp-sync/v1/reset`: reset or clear install data at level `queue`, `state`, `content`, `connection`, or `all` (`confirm` must match `level`)

Every route requires a logged-in user with the manage_options capability (an administrator), using WordPress REST API authentication.

## Custom Cron Implementation

For websites with unreliable WordPress cron:

1. Disable WP-Cron by adding to wp-config.php:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. Set up a server cron job to call WordPress cron:
   ```
   */15 * * * * wget -q -O /dev/null https://your-site.com/wp-cron.php?doing_wp_cron
   ```

For more advanced development information, consult the inline code documentation or [contact our developer support team](https://docs.churchplugins.com/contact-support/).
