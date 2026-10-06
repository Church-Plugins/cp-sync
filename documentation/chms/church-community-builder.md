# Church Community Builder Integration

CP Sync provides integration with Church Community Builder (CCB), allowing you to synchronize groups, events, and other data with your WordPress website.

## Setting Up the CCB Integration

### Prerequisites

- An active Church Community Builder account with API access
- Administrator access to your WordPress website
- CP Sync plugin installed and activated
- API credentials (username and password) from your CCB administrator

### Connect to Church Community Builder

1. In your WordPress admin, go to **Church Plugins → CP Sync**.
2. Open the **Connect** tab.
3. Set **Church Management System** to **Church Community Builder**.
4. Under **Connect to Church Community Builder**, enter **Subdomain** (the part before `.ccbchurch.com`), **API Username**, and **API Password**.
5. Click **Connect to CCB**. It saves the fields and checks them. When the check succeeds, the button changes to **Disconnect** and the fields lock. If it fails, the tab shows **Connection failed** or the error message.

## Configuring Data Synchronization

### Groups Synchronization

1. Go to the **Groups** tab in the CCB settings
2. Set the **Groups** filter
3. Click **Save all Settings**

#### Which Groups Sync

Only groups that are not marked Inactive and have **Public Search** checked in CCB are synced. In CCB, you'll find this under **Group Actions > Edit Group Settings > Options**.

### Events Synchronization

1. Go to the **Events** tab in the CCB settings
2. Choose how far ahead events sync with **Date Range** (see below)
3. Click **Save all Settings**

#### How Far Ahead Events Sync

Use the **Date Range** setting on the CCB **Events** tab:

| Option | What syncs |
|--------|------------|
| Current and upcoming events (Recommended) | Today through one year ahead. This is the default. |
| Include past 30 days | The past 30 days plus upcoming events. |
| All future events | Up to 10 years ahead. |
| Custom date range | The Start and End Dates you choose. |

If you use **Custom date range**, the Start and End Dates do not move on their own. Check that the End Date is far enough out, or events after it won't sync.

### Event Enrichment

CCB exposes event data through two endpoints with different levels of detail. CP Sync uses both: the calendar listing for the initial import, then the event profile to fill in details that the listing omits.

After each event is imported or updated, CP Sync automatically fetches the event profile to populate:

- **Full venue address** — street, city, state, and zip (the calendar listing only provides a venue name)
- **Event image** — the featured image from the event's profile

Enrichment runs once per event on first import, then re-runs only when CCB reports the event has been modified, so subsequent syncs stay fast. Venues are deduplicated within a sync so events sharing a location don't trigger redundant updates.

**Limitation:** Recurring event occurrences from the calendar listing don't include a numeric event ID and can't be enriched. These events will import with the venue name only. Master events with a numeric ID enrich normally.

### Keeping Hand Edits on Synced Posts

Changes you make by hand in WordPress to CCB-synced events and groups are replaced on the next sync unless you lock the post. On CP Sync 1.0.0 or later:

1. Open the event or group in WordPress.
2. In the **CP Sync** box, check **Prevent sync from updating this post**.
3. Save the post.

See [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#hand-edits-on-ccb-synced-events-and-groups-are-overwritten).

## Advanced Settings

- **Filter Data**: Apply advanced filters to control which data is imported
- **Import Schedule**: Configure automatic sync schedules
- **Logging**: Enable detailed logging for troubleshooting

## XML API Considerations

CCB uses an XML-based API which has some limitations:

- Rate limiting may occur for large data imports
- Some data may not be available through the API
- The API may be slower than modern REST APIs

## Troubleshooting CCB Integration

- **Authentication Errors**: Check your API credentials and permissions
- **Missing Data**: Verify that your CCB account has the necessary modules and permissions; for groups, confirm **Public Search** and that the group is not Inactive
- **Settings won't save on some hosts**: On servers without PHP's sodium extension, saving CCB settings can fail after upgrading to 1.0.0. See [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#ccb-settings-wont-save-on-some-hosts)
- **Rate Limiting**: CCB limits API requests; adjust your sync frequency if needed
- **XML Parsing Errors**: These can occur if the CCB API response format changes

## CCB API Resources

- [CCB API Documentation](https://designccb.s3.amazonaws.com/helpdesk/files/official_api_specifications.pdf) (PDF)
- Contact your CCB administrator for specific questions about your CCB implementation

For more help, see the [Troubleshooting](https://docs.churchplugins.com/knowledge-base/advanced-troubleshooting-cp-sync/) guide.
