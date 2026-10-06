# Developer Guide

This guide is intended for developers who want to extend or customize the CP-Sync plugin. It covers the hooks the plugin runs, the REST API, and cron.

## Plugin Architecture

CP-Sync follows an object-oriented architecture with clear separation of concerns:

- **Core**: Base functionality and framework
- **ChMS**: Church Management System integrations
- **Admin**: Administrative interfaces
- **Integrations**: WordPress plugin integrations
- **Setup**: Initialization and configuration

## Available Hooks

Names and arguments below are the ones the plugin passes.

### Actions

- `do_action( "cp_sync_{$this->type}_update_item_after", $item, $id )` — `$this->type` is `groups`, `events`, or `sermons`. `$item` is the item array. `$id` is the post ID. CCB hooks `cp_sync_events_update_item_after`.
- `do_action( 'cp_update_item_after', $item, $id, $this )` — `$this` is the integration instance. `$id` is the post ID. Runs at the same point as the type action above.
- `do_action( 'cp_' . $this->id . '_update_item_after', $item, $id )` — `$this->id` is the integration id (`tec`, `cp_groups`, or `cp_library`). `$id` is the post ID.
- `do_action( 'cp_sync_global_settings_updated', $settings, $old_settings )` — `$settings` is the global settings array just saved. `$old_settings` is that array before the save.
- `do_action( "cp_sync_load_taxonomy_{$this->id}", $taxonomy, $args )` — `$this->id` is the integration id. `$taxonomy` is the taxonomy slug. `$args` is the array passed to `register_taxonomy()`.

### Filters

- `apply_filters( 'cp_sync_remove_past_events', false, $chms_id, $post_id, $this )` — `$this` is the events integration. See [Preserving Past Events](#preserving-past-events).
- `apply_filters( "cp_sync_{$this->type}_should_remove_item", true, $chms_id, $this )` — `$chms_id` is the ChMS id. `$this` is the integration instance.
- `apply_filters( "cp_sync_{$this->type}_item", $item, $this )` — `$item` is the item array about to be queued.
- `apply_filters( 'cp_sync_process_items', $items, $this )` — `$items` is the array of items about to be processed.
- `apply_filters( 'cp_sync_process_hard_refresh', true, $items, $this )` and `apply_filters( 'cp_sync_process_hard_refresh', true, $taxonomies, $this )` — the second argument is the items array in one call and the taxonomies array in the other.
- `apply_filters( 'cp_sync_pull_items', $posts, $this )` — `$posts` is the posts array from the formatted ChMS payload.
- `apply_filters( 'cp_sync_pull_taxonomies', $taxonomies, $this )` — `$taxonomies` is the taxonomies array from that payload.
- `apply_filters( "cp_sync_pull_{$integration_type}", null, $integration_type )` — `$integration_type` is `groups`, `events`, or `sermons`.
- `apply_filters( 'cp_sync_item_is_locked', $locked, $post_id, $chms_id, $this )` — `$locked` is whether the post has the lock meta.
- `apply_filters( 'cp_sync_show_event_registration_button', $show, $post_id )` — `$show` is the current register-button flag. `$post_id` is the event post ID.
- `apply_filters( 'cp_sync_debug_mode', $debug_mode )` — `$debug_mode` is true when **Enable Debug Mode** is **Enable**, or when the `CP_SYNC_DEBUG` constant is true.
- `apply_filters( 'cp_sync_active_chms', $chms )` — `$chms` is the active ChMS slug stored in settings. The default passed in is `pco`.
- `apply_filters( 'cp_sync_global_settings', $settings )` — `$settings` is the global settings array passed into the settings page.
- `apply_filters( 'cp_sync_oauth_token', $token, $active_chms )` — `$token` is the token from the OAuth redirect. `$active_chms` is the active ChMS object.
- `apply_filters( 'cp_sync_oauth_refresh_token', $refresh_token, $active_chms )` — `$refresh_token` is the refresh token from that redirect.
- `apply_filters( 'cp_sync_congregation_map', array() )` — a map from a ChMS congregation id to a location.
- `apply_filters( 'cp_sync_cron_args', $args )` — `$args` has `timestamp` and `recurrence`.
- `apply_filters( 'cp_sync_image_cache_dir', 'cp-sync' )` — directory name under uploads. Default `cp-sync`. The import integration appends `/` and its type.
- `apply_filters( 'cp_sync_image_mime_types', $types )` — `$types` maps a MIME type to an extension: `image/jpeg`, `image/jpg`, and `image/jpe` to `jpg`; `image/png` to `png`; `image/gif` to `gif`; `image/webp` to `webp`.
- `apply_filters( 'cp_sync_normalize_thumbnail_url', explode( '?', $url )[0], $url, $this )` — `$url` is the original thumbnail URL. A Planning Center URL that contains a `key` query parameter returns before this filter runs.
- `apply_filters( 'cp_sync_template_paths', $paths )` — `$paths` is a list of base directories. The default is the plugin path.
- `apply_filters( 'cp_sync_template', $file, $template )` — `$file` is a candidate template path. `$template` is the requested template.
- `apply_filters( 'cp_sync_template_' . $template, $file )` — `$file` is the resolved template path, or false. `$template` has `.php` appended when the request did not already end in `.php` and did not contain `.json`.
- `apply_filters( 'churchplugins_fallback_mime_type', $types )` — `$types` defaults to `application/xml` and `application/octet-stream`.
- `apply_filters( 'cps_settings_get', $value, $key, $group )` — `$value` is the stored option or the default. `$key` is the option key. `$group` is the option group.

## Preserving Past Events

Every ChMS query is windowed — Planning Center's calendar crawl only asks for future
event instances, and CCB's is bounded by the configured date range. Once an event's end
date passes it simply stops appearing in the sync payload, which is indistinguishable
from the event having been deleted at the source.

By default the sync **keeps** those events. The rule is based on the event's own end
date, not on the query window: an event whose end date has passed is preserved when it
goes missing from the response, and an event that has not ended is removed — the
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

- `GET /wp-json/cp-sync/v1/status` - Get sync status
- `POST /wp-json/cp-sync/v1/sync` - Trigger a sync operation
- `GET /wp-json/cp-sync/v1/logs` - Retrieve sync logs

Authentication is required using WordPress REST API authentication.

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

3. For more granular control, you can directly trigger specific CP-Sync operations:
   ```
   0 0 * * * wget -q -O /dev/null "https://your-site.com/wp-json/cp-sync/v1/sync?type=pco&data=groups"
   ```

For more advanced development information, consult the inline code documentation or contact our developer support team.