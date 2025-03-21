# Developer Guide

This guide is intended for developers who want to extend or customize the CP-Sync plugin. It covers hooks, filters, the API, and custom integration development.

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
// Fires before a sync operation begins
do_action('cp_sync_before_sync', $chms_type, $data_type);

// Fires after a sync operation completes
do_action('cp_sync_after_sync', $chms_type, $data_type, $results);

// Fires when a group is imported/updated
do_action('cp_sync_group_imported', $group_id, $chms_data, $chms_type);

// Fires when an event is imported/updated
do_action('cp_sync_event_imported', $event_id, $chms_data, $chms_type);
```

### Filter Hooks

```php
// Filter ChMS data before it's processed
apply_filters('cp_sync_pre_process_data', $data, $chms_type, $data_type);

// Filter group data before import
apply_filters('cp_sync_pre_import_group', $group_data, $chms_type);

// Filter event data before import
apply_filters('cp_sync_pre_import_event', $event_data, $chms_type);

// Filter data mapping configuration
apply_filters('cp_sync_field_mapping', $mapping, $chms_type, $data_type);
```

## Creating Custom Data Filters

You can create custom data filters by hooking into the pre-processing filters:

```php
// Only import groups with "Youth" in the title
function my_custom_group_filter($group_data, $chms_type) {
    // Skip groups that don't contain "Youth" in the title
    if (strpos($group_data['title'], 'Youth') === false) {
        return false; // Returning false skips this item
    }
    return $group_data;
}
add_filter('cp_sync_pre_import_group', 'my_custom_group_filter', 10, 2);
```

## Extending Field Mappings

You can add custom field mappings for third-party plugins:

```php
// Add support for a custom field
function add_custom_group_field_mapping($mapping, $chms_type, $data_type) {
    if ($data_type === 'group' && $chms_type === 'pco') {
        $mapping['custom_field'] = [
            'source' => 'attributes.my_custom_field',
            'destination' => '_my_custom_field',
            'type' => 'meta'
        ];
    }
    return $mapping;
}
add_filter('cp_sync_field_mapping', 'add_custom_group_field_mapping', 10, 3);
```

## Creating Custom ChMS Integrations

To add support for another church management system:

1. Create a class that extends `CP_Sync\ChMS\ChMS`
2. Implement the required methods:
   - `connect()`
   - `get_groups()`
   - `get_events()`
   - `format_group()`
   - `format_event()`
3. Register your ChMS provider

Example skeleton:

```php
namespace My_Plugin\ChMS;

class My_ChMS extends \CP_Sync\ChMS\ChMS {
    public function connect() {
        // Implementation for connecting to your ChMS
    }
    
    public function get_groups() {
        // Implementation for retrieving groups
    }
    
    // Other required methods...
}

// Register your ChMS provider
function register_my_chms($providers) {
    $providers['my_chms'] = 'My_Plugin\ChMS\My_ChMS';
    return $providers;
}
add_filter('cp_sync_chms_providers', 'register_my_chms');
```

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

## Debugging Tools

For debugging, you can enable verbose logging:

```php
// Enable detailed logging
add_filter('cp_sync_debug_mode', '__return_true');

// Log all API requests and responses
add_filter('cp_sync_log_api_calls', '__return_true');
```

## Performance Optimization

For large datasets, consider these optimizations:

1. Implement batched processing:
   ```php
   add_filter('cp_sync_batch_size', function() { return 50; });
   ```

2. Increase memory limit for sync operations:
   ```php
   add_action('cp_sync_before_sync', function() {
       wp_raise_memory_limit('sync');
   });
   ```

3. Disable unnecessary processing:
   ```php
   // Disable thumbnail generation during import
   add_filter('cp_sync_process_thumbnails', '__return_false');
   ```

For more advanced development information, consult the inline code documentation or contact our developer support team.