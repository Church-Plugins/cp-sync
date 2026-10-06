# The Events Calendar Integration

CP-Sync imports events from Planning Center or Church Community Builder into The Events Calendar.

## Prerequisites

Before using The Events Calendar integration:

- The Events Calendar must be installed and activated
- CP-Sync must be installed and activated
- Your church management system must be connected
- You need an account that can manage options (an Administrator)

## Turn On Event Sync

1. Go to **Church Plugins → CP Sync**.
2. On the **Connect** tab, connect Planning Center or Church Community Builder. See [API Connections](../configuration/api-connections.md).
3. Under **Sync**, turn on **Sync Events**.
4. Click **Save all Settings**.

If The Events Calendar is not active, **Sync Events** is disabled and the help text reads **Requires The Events Calendar plugin, which is not active on this site.**

**Sync Events** is on until you turn it off. The **Events** tab appears after you are connected and the toggle is on.

## Planning Center

On the **Events** tab:

- **Event source**: **Pull from Calendar** (the default), **Pull from Registrations**, or **Calendar AND Registrations**.
- **Show Register button on events**: on until you turn it off. Synced events that have a registration link get a **Register** button. When the signup is at capacity, the button reads **Sold Out**.
- **Calendar Settings** (shown for **Pull from Calendar** and **Calendar AND Registrations**):
  - **Tag groups**: selected tag groups become taxonomies on the event.
  - **Visibility**: **Only Visible in Church Center** (the default) or **Show All**.
  - **Calendar Filters**: **Pull Calendar Filters where**, then **All** or **Any**, and **Add Condition**.
- **Registration Settings** (shown for **Pull from Registrations** and **Calendar AND Registrations**):
  - **Registration Filters**: **Pull Registration Filters where**, then **All** or **Any**, and **Add Condition**.
- **Generate Preview** and **Pull Now**. **Pull Now** shows **Import started**.

When **Event source** is **Calendar AND Registrations**, the tab shows **Events are not deduplicated across Calendar and Registrations — an event published in both will import twice.**

Calendar sync imports future event instances. **Visibility** is separate from **Calendar Filters**. An empty **Calendar Filters** builder does not turn **Visibility** off.

## Church Community Builder

On the **Events** tab, the heading reads **Select data to pull from Church Community Builder**.

- **Date Range**: **Current and upcoming events (Recommended)** (the default; today through one year ahead), **Include past 30 days** (30 days ago through one year ahead), **All future events** (today through 10 years ahead), or **Custom date range**. **Custom date range** shows **Start Date** and **End Date**.
- **Show Register button on events**: on until you turn it off.
- **Events**, under **Event Filters**: **Pull Events where**, then **All** or **Any**, and **Add Condition**.
- **Generate Preview** and **Pull Now**. **Pull Now** shows **Import started**.

Each occurrence is imported as its own event. After import, events with a numeric CCB id get a venue (name, street, city, state, and zip) and an image from the event profile. See [Church Community Builder](../chms/church-community-builder.md#event-enrichment).

## What Is Copied

See [Data Mapping](../configuration/data-mapping.md#events).

## Schedule

Set **Update Interval** on the **Advanced** tab (**Hourly**, **Daily**, or **Weekly**). See [Sync Scheduling](../advanced/sync-scheduling.md).

## Troubleshooting

- **Events not importing**: Confirm The Events Calendar is active and **Sync Events** is on. On Planning Center, check **Event source**, **Visibility**, and the filter for that source. On Church Community Builder, widen **Date Range**.
- **A field is missing on the event**: Compare the event with [Data Mapping](../configuration/data-mapping.md#events).
- **CCB venues have no address**: Events without a numeric CCB id are not enriched. On the **Log** tab, set **Enable Debug Mode** to **Enable** and look for `Skipping enrichment` or `Failed to fetch event_profile`.

To keep hand edits on one imported event, see [Preventing Sync Updates](../configuration/preventing-sync-updates.md).
