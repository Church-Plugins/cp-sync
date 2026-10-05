# General Settings

The General Settings section of CP Sync allows you to configure basic plugin options and global behaviors. This page explains how to access and configure these settings.

## Accessing General Settings

1. Log in to your WordPress admin dashboard
2. Navigate to **Church Plugins → CP Sync** (`admin.php?page=cps_settings`)
3. Click on the **General** tab (this is typically the default tab)

> **Note (CP Sync 1.0.0):** Settings moved under **Church Plugins > CP Sync**. The old **Settings → CP Sync** link no longer works. For a full list of 1.0.0 changes and known issues, see [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md).

## Available Settings

### Plugin Activation

- **License Key**: Enter your license key to activate the plugin and receive updates
- **License Status**: View the current status of your license (Active, Inactive, or Expired)

### Sync Settings

- **Auto Sync**: Enable or disable automatic synchronization
- **Sync Frequency**: Choose how often the sync should run (Hourly, Twice Daily, Daily, Weekly)
- **Sync Time**: For daily and weekly syncs, choose what time the sync should run
- **Sync Day**: For weekly syncs, choose what day the sync should run

If you changed the sync interval before upgrading to 1.0.0 and the new interval does not take effect, set it to a different interval once, save, then set it back and save again. Interval changes you make on 1.0.0 work normally. See [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#sync-interval-doesnt-take-effect-after-upgrading).

### Logging

- **Enable Logs**: Turn logging on or off
- **Log Level**: Select the level of detail for logs (Error, Warning, Info, Debug)
- **Log Retention**: Choose how long to keep logs before they are automatically deleted

### Error Notifications

- **Admin Email**: The email address that will receive error notifications
- **Notification Frequency**: How often to send notification emails (Immediately, Daily Summary, Weekly Summary)
- **Error Threshold**: The minimum error level that triggers a notification

## Applying Settings

After adjusting your settings:

1. Click the **Save Changes** button at the bottom of the page
2. The page will refresh and display a success message if your settings were saved correctly

## Testing Your Configuration

After saving your settings, you can test your configuration by:

1. Navigating to the **Advanced** tab
2. Scrolling to the **Testing Tools** section
3. Clicking the **Test Configuration** button

This will verify that your settings are properly configured and that the plugin can function with the current settings.

## Troubleshooting

If you encounter issues with your general settings:

- Check your license key for accuracy
- Ensure your server can send emails if you've enabled error notifications
- Verify that your WordPress cron system is functioning properly for auto-sync features
- Check the logs (if enabled) for any error messages

For more detailed troubleshooting, see the [Troubleshooting](https://docs.churchplugins.com/knowledge-base/advanced-troubleshooting-cp-sync/) section.
