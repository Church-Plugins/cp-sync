# CP Library (Sermons) Integration

CP-Sync can import sermons from Planning Center Online's Publishing app directly into the CP Library plugin, keeping your WordPress sermon library in sync with the episodes you publish to Church Center.

> **Note:** Sermon syncing is currently available for Planning Center Online only. Church Community Builder does not provide a sermon/episode feed.

## Prerequisites

Before using the CP Library integration:

- CP Library plugin must be installed, activated, and up to date (the integration relies on CP Library's `SermonSync` facade)
- CP-Sync plugin must be installed and activated
- Planning Center Online must be connected, and your PCO account must use the Publishing app

## How the Integration Works

When enabled, CP-Sync will:

1. Fetch episodes from PCO Publishing — **only episodes published to your Church Center library are synced**
2. Create or update a corresponding CP Library sermon for each episode
3. Keep the sermons up to date on every scheduled or manual sync

For each episode, the following data is imported:

- **Title and description** → sermon title and content
- **Published date** → sermon date
- **Series** → CP Library series
- **Speakers** → CP Library speakers (resolved from the episode's speakership records)
- **Video and audio** → sermon media (the Church Center library URLs are preferred, falling back to the raw video URL)
- **Scripture, topics, and season** → the matching CP Library taxonomies

Imported sermons are matched by their PCO episode ID, so re-running a sync updates existing sermons in place rather than creating duplicates.

## Enabling Sermon Sync

1. Navigate to the CP Sync settings page (under the **Church Plugins** admin menu)
2. On the **Connect** tab, make sure Planning Center Online is connected
3. Turn on the **Sync Sermons** toggle (it is only available when CP Library is active)
4. Save your settings

## Filtering Which Sermons Are Imported

Once sermon sync is enabled, a **Sermons** tab appears in the PCO settings:

1. Open the **Sermons** tab
2. Use the filter builder to limit which episodes are imported (for example, by series or title)
3. Save your settings

Remember that regardless of filters, only episodes published to the Church Center library are ever pulled.

## Manual and Scheduled Synchronization

Sermons participate in the same sync runs as groups and events:

- Trigger a manual pull from the settings page, or
- Rely on the configured sync schedule (see [Sync Scheduling](../advanced/sync-scheduling.md))

## Preserving Manual Edits

If you customize an imported sermon in WordPress and don't want future syncs to overwrite it, use the per-post sync lock — see [Preventing Sync Updates](../configuration/preventing-sync-updates.md).

## Troubleshooting

- **No sermons importing**: Confirm the episodes are published to your Church Center library in PCO Publishing, and check any filters on the Sermons tab
- **"SermonSync facade is not available" in the log**: Update CP Library to the latest version
- **Missing speakers or series**: Verify the episode's series and speaker assignments in PCO Publishing

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.
