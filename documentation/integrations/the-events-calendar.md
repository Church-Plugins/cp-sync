# The Events Calendar Integration

CP-Sync integrates with The Events Calendar plugin by Modern Tribe, allowing you to import events from your church management system directly into your WordPress calendar.

## Prerequisites

Before using The Events Calendar integration:

- The Events Calendar plugin must be installed and activated
- CP-Sync plugin must be installed and activated
- Your church management system (PCO or CCB) must be connected

## How the Integration Works

When enabled, CP-Sync will:

1. Import events from your ChMS
2. Create or update corresponding events in The Events Calendar
3. Maintain synchronization between your ChMS and WordPress calendar
4. Map event attributes properly between systems

## Configuring The Events Calendar Integration

### Enable Integration

1. Go to **Church Plugins → CP Sync**.
2. On the **Connect** tab, turn on **Sync Events**. It is disabled until The Events Calendar is active.
3. Click **Save all Settings**.

### What Is Copied

CP Sync copies a fixed set of fields. There is no field-mapping setting.

For CCB, venue records are populated with the full address (street, city, state, zip) and event images via a follow-up call to the event profile after import. See the [CCB Event Enrichment](../chms/church-community-builder.md#event-enrichment) section for details and limitations.

### Advanced Configuration

For advanced users, additional settings are available:

- **Event Filtering**: Filter which events are imported based on date range or other criteria

## Manual Synchronization

To manually synchronize events:

1. Go to **Church Plugins → CP Sync** and open the **Events** tab.
2. Click **Pull Now**. The tab shows **Import started**.

## Scheduled Synchronization

Configure automatic synchronization:

1. Navigate to **Church Plugins → CP Sync → Advanced**
2. Set **Update Interval** to **Hourly**, **Daily**, or **Weekly**.
3. Click **Save all Settings**.

## Troubleshooting

Common issues and solutions:

- **Events not importing**: Check **Event source** and the filters on the **Events** tab (Planning Center), or **Date Range** (Church Community Builder)
- **Missing information**: CP Sync copies a fixed set of fields. There is no field-mapping setting.
- **Duplicate events**: Imported events are matched by their ChMS ID. There is no unique-identifier setting.

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.