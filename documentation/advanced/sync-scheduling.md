# Sync Scheduling

CP-Sync allows you to configure automated synchronization schedules to keep your WordPress site up-to-date with your church management system. This guide explains how to set up and manage sync schedules.

## Understanding Sync Scheduling

Automated sync scheduling ensures that your WordPress site regularly pulls the latest data from your ChMS without manual intervention. This keeps your groups, events, and other information current.

## Types of Sync Operations

CP-Sync supports scheduling different types of sync operations:

- **Full Sync**: Imports all data from your ChMS, updating existing items and adding new ones
- **Incremental Sync**: Only imports data that has changed since the last sync
- **Groups Sync**: Only synchronizes group data
- **Events Sync**: Only synchronizes event data

## Accessing Sync Scheduling

1. Navigate to **Settings → CP Sync → Advanced**
2. Scroll to the "Sync Scheduling" section

## Setting Up a Basic Schedule

To create a basic sync schedule:

1. Enable "Auto Sync" by checking the box
2. Select the frequency:
   - Hourly
   - Twice Daily
   - Daily
   - Weekly
3. For daily or weekly syncs, select the time of day
4. For weekly syncs, select the day of the week
5. Save your settings

## Advanced Scheduling Options

For more control over your sync schedule, additional options are available:

### Per-Module Scheduling

You can set different schedules for different types of data:

1. In the "Advanced Scheduling" section
2. Configure separate schedules for:
   - Groups synchronization
   - Events synchronization
3. Each can have its own frequency and timing

### Limiting Sync Size

To manage server resources for large data sets:

1. Enable "Limit Sync Size"
2. Set the maximum number of items to process per sync operation
3. If there are more items than the limit, the remaining items will be processed in the next scheduled sync

## Custom WP-Cron Schedules

For technical users who need more specific scheduling:

1. Navigate to the "Custom Schedules" section
2. Create custom intervals not provided by default (e.g., every 4 hours)
3. Apply these custom schedules to your sync operations

## Manual Sync Controls

In addition to scheduled syncs, you can always run manual syncs:

1. Navigate to **Settings → CP Sync**
2. Select your ChMS tab
3. Go to the relevant data tab (Groups, Events)
4. Click "Sync Now" to run an immediate synchronization

## Monitoring Sync Status

To check on your sync operations:

1. Navigate to **Settings → CP Sync → Logs**
2. View the sync operation logs
3. Check when the last sync ran and what was processed
4. Look for any errors or warnings

## Troubleshooting Scheduled Syncs

If your scheduled syncs aren't running properly:

- Verify WordPress cron is working correctly on your server
- Check for PHP timeout issues during large syncs
- Ensure your ChMS API credentials remain valid
- Review server error logs for any related issues

### WordPress Cron Alternatives

If WordPress cron is unreliable on your hosting:

1. Disable WordPress cron by adding `define('DISABLE_WP_CRON', true);` to your wp-config.php
2. Set up a server cron job to call wp-cron.php directly
3. Detailed instructions for this setup can be found in the [Developer Guide](developer-guide.md)

For more detailed troubleshooting of sync issues, see the [Troubleshooting](troubleshooting.md) guide.