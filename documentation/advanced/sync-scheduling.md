# Sync Scheduling

CP Sync runs one schedule for every feed that is turned on. Groups, events, and sermons share that schedule.

## Set the Interval

1. Go to **Church Plugins → CP Sync**.
2. Open the **Advanced** tab.
3. Set **Update Interval** to **Hourly**, **Daily**, or **Weekly**. **Hourly** is the default.
4. Click **Save all Settings**.

The first run is scheduled about an hour after the plugin loads. The choices are **Hourly**, **Daily**, and **Weekly** only.

Which feeds run is set on the **Connect** tab:

- **Sync Groups** and **Sync Events** are on until you turn them off.
- **Sync Sermons** is shown for Planning Center, and it is off until you turn it on.
- A toggle is disabled when its plugin is missing: CP Groups, The Events Calendar, or CP Sermons.

A turned-off feed is skipped. See [API Connections](../configuration/api-connections.md).

### After Upgrading to 1.0.0

If you changed the sync interval before upgrading to 1.0.0 and the new interval does not take effect, set it to a different interval once after upgrading and save, then set it back to the interval you want and save again. Saving the same value again does not fix it. Interval changes you make on 1.0.0 work normally. See [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#sync-interval-doesnt-take-effect-after-upgrading).

## Start a Sync

- On **Advanced**, click **Pull now**. This pulls every feed that is turned on. The tab shows **Hard pull started successfully**.
- On **Groups**, **Events**, or **Sermons**, click **Pull Now**. The tab shows **Import started**.

Those tabs appear after you are connected, the matching **Sync** toggle is on, and the companion plugin is active.

While a sync is running, the page shows **A sync is currently in progress** and **Cancel Sync**.

## Read the Log

1. Open the **Log** tab.
2. Set **Enable Debug Mode** to **Enable** when you need the debug log, then click **Save all Settings**.
3. Read **Log File Content**. **Clear Log File** empties it.

## If Scheduled Syncs Do Not Run

- Confirm WordPress cron is running on the server.
- Confirm the church management system is still connected on the **Connect** tab.
- On the **Log** tab, set **Enable Debug Mode** to **Enable**, click **Pull now** on **Advanced**, and read **Log File Content**.

### WordPress Cron on the Server

If WordPress cron does not run on your host:

1. Add `define('DISABLE_WP_CRON', true);` to wp-config.php.
2. Set a server cron job to call wp-cron.php.
3. The commands are in the [Developer Guide](developer-guide.md).
