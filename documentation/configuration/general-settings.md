# General Settings

License, logging, and the sync schedule live under **Church Plugins → CP Sync** (`admin.php?page=cps_settings`). You need an account that can manage options (an Administrator).

The screen opens on the **Connect** tab. The other tabs on every visit are **Log**, **License**, and **Advanced**. After you connect a church management system, **Groups**, **Events**, and (Planning Center only) **Sermons** appear when that sync is turned on. See [API Connections](api-connections.md).

> **Note (CP Sync 1.0.0):** Settings moved under **Church Plugins > CP Sync**. The old **Settings → CP Sync** link no longer works. For a full list of 1.0.0 changes and known issues, see [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md).

## License

Open the **License** tab.

- **License Key**: paste your key.
- **Activate**: checks the key. After it succeeds, the key field locks and the button reads **Deactivate**.
- **Enable beta updates**: receive beta releases. Off until you turn it on.

**Activate** and **Deactivate** save on their own. Click **Save all Settings** after you change **Enable beta updates**.

## Log

Open the **Log** tab.

- **Enable Debug Mode**: **Enable** or **Disable**. **Disable** is the default.
- **Log File Content**: the sync log. CP Sync writes to it only while **Enable Debug Mode** is set to **Enable**.
- **Clear Log File**: empties the log.

Click **Save all Settings** after you change **Enable Debug Mode**.

## Sync Schedule

Open the **Advanced** tab.

- **Display groups with CP Sync**: shows a groups page and the `[cp-sync-groups]` shortcode when CP Groups is not active and **Sync Groups** is on. A new site has this on. On a site that was using CP Sync before 1.2.0, this is off until you turn it on. CP Groups adds filters, a map, and a contact form. See [Show Groups on Your Site](../integrations/show-groups.md).
- **Update Interval**: **Hourly** (the default), **Daily**, or **Weekly**.
- **Display events with CP Sync**: shown when The Events Calendar is not active. Off until you turn it on, unless event sync was already on. With **Sync Events** also on, CP Sync lists events at `/events/`. Leave it off if a page already uses that address. See [Events on your site](../integrations/events-on-your-site.md).
- **Pull now**: starts a sync of every feed that is turned on. The tab shows **Hard pull started successfully**.
- **Delete all data on uninstall**: off until you turn it on. See [Reset Tool](../advanced/reset-tool.md).
- **Danger Zone**: reset install data. See [Reset Tool](../advanced/reset-tool.md).

Click **Save all Settings** after you change **Update Interval**, **Display events with CP Sync**, or **Delete all data on uninstall**.

If you changed the sync interval before upgrading to 1.0.0 and the new interval does not take effect, set it to a different interval once, save, then set it back and save again. Interval changes you make on 1.0.0 work normally. See [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#sync-interval-doesnt-take-effect-after-upgrading).

While a sync is running, the top of the page shows **A sync is currently in progress** and **Cancel Sync**.

## Saving

**Save all Settings** sits at the bottom of every tab. It stays disabled until you change something, and the label changes to **Saving...** while the save runs. A failed save shows an error under the tabs. A successful save leaves you on the same page.
