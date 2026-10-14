# Developer Guide

This guide is intended for developers who want to extend or customize the CP-Sync plugin. It covers hooks, filters, the API, and cron.

## Plugin Architecture

CP-Sync follows an object-oriented architecture with clear separation of concerns:

- **Core**: Base functionality and framework
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

// Force debug logging on (same as Log → Enable Debug Mode). Add this from a plugin or
// mu-plugin, not a theme: it is read once when CP Sync loads. Defining the
// CP_SYNC_DEBUG constant as true also works.
apply_filters('cp_sync_debug_mode', $debug_mode);
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

For more advanced development information, consult the inline code documentation or contact our developer support team.
