# General Settings

The General Settings section of CP-Sync allows you to configure basic plugin options and global behaviors. This page explains how to access and configure these settings.

## Accessing General Settings

1. Log in to your WordPress admin dashboard
2. Navigate to **Settings → CP Sync**
3. Click on the **General** tab (this is typically the default tab)

## Available Settings

### Plugin Activation

- **License Key**: Enter your license key to activate the plugin and receive updates
- **License Status**: View the current status of your license (Active, Inactive, or Expired)

### Sync Settings

- **Auto Sync**: Enable or disable automatic synchronization
- **Sync Frequency**: Choose how often the sync should run (Hourly, Twice Daily, Daily, Weekly)
- **Sync Time**: For daily and weekly syncs, choose what time the sync should run
- **Sync Day**: For weekly syncs, choose what day the sync should run

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

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) section.