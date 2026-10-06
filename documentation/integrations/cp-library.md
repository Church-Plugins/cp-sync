# CP Sermons Integration

CP-Sync can import sermons from Planning Center Online's Publishing app directly into the CP Sermons plugin (formerly CP Library), keeping your WordPress sermon library in sync with the episodes you publish to Church Center.

> **Note:** Sermon syncing is for Planning Center Online. On the **Connect** tab, **Sync Sermons** is shown when **Church Management System** is **Planning Center Online**.

## Prerequisites

Before using the CP Sermons integration:

- CP Sermons plugin must be installed, activated, and up to date (the integration relies on CP Sermons' `SermonSync` facade)
- CP-Sync plugin must be installed and activated
- Planning Center Online must be connected, and your PCO account must use the Publishing app

## How the Integration Works

When enabled, CP-Sync will:

1. Fetch episodes from PCO Publishing — **only episodes published to your Church Center library are synced**
2. Create or update a corresponding CP Sermons sermon for each episode
3. Keep the sermons up to date on every scheduled or manual sync

For each episode, the following data is imported:

- **Title and description** → sermon title and content
- **Church Center library publish time** → sermon date
- **Series** → CP Sermons series
- **Channel** → CP Sermons service type, when service types are enabled in CP Sermons
- **Speakers** → CP Sermons speakers (resolved from the episode's speakership records)
- **Video** → the Church Center library video URL, then the raw video URL
- **Audio** → the Church Center library audio URL
- **Episode art** → the sermon featured image (Planning Center's generated placeholder art is skipped)

Imported sermons are matched by their PCO episode ID, so re-running a sync updates existing sermons in place rather than creating duplicates.

## Enabling Sermon Sync

1. Go to **Church Plugins → CP Sync**
2. On the **Connect** tab, set **Church Management System** to **Planning Center Online** and connect
3. Turn on **Sync Sermons**. It is off until you turn it on. If CP Sermons is not active, the toggle is disabled and the help text reads **Requires the CP Sermons plugin, which is not active on this site.**
4. Click **Save all Settings**

## Filtering Which Sermons Are Imported

Once **Sync Sermons** is on and you are connected, a **Sermons** tab appears:

1. Open the **Sermons** tab
2. Use the **Sermons** filter. The line reads **Pull Sermons where**, then **All** or **Any**, then **of the following match**. You can filter on **Title**, **Description**, **Channel**, and **Series**. Add a rule with **Add Condition**.
3. Click **Save all Settings**

Remember that regardless of filters, only episodes published to the Church Center library are ever pulled.

## Manual and Scheduled Synchronization

Sermons run in the same sync as groups and events:

- On the **Sermons** tab, click **Pull Now**. The tab shows **Import started**.
- On the **Advanced** tab, click **Pull now**. That pulls every feed that is turned on, and the tab shows **Hard pull started successfully**.
- Or wait for **Update Interval** (see [Sync Scheduling](../advanced/sync-scheduling.md))

## Preserving Manual Edits

If you customize an imported sermon in WordPress and don't want future syncs to overwrite it, use the per-post sync lock — see [Preventing Sync Updates](../configuration/preventing-sync-updates.md).

## Troubleshooting

- **No sermons importing**: Confirm the episodes are published to your Church Center library in PCO Publishing, **Sync Sermons** is on, and the **Sermons** filter is not excluding them
- **"CP Sermons SermonSync facade is not available; is CP Sermons up to date?" in the log**: Update CP Sermons to the latest version
- **Missing speakers or series**: Verify the episode's series and speaker assignments in PCO Publishing

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.
