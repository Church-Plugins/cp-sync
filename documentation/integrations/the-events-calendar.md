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

1. Navigate to **Church Plugins → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the **Events** tab
4. Check the box to "Enable The Events Calendar Integration"
5. Save your settings

### What Is Copied

CP Sync copies a fixed set of fields. There is no field-mapping setting.

For CCB, venue records are populated with the full address (street, city, state, zip) and event images via a follow-up call to the event profile after import. See the [CCB Event Enrichment](../chms/church-community-builder.md#event-enrichment) section for details and limitations.

### Advanced Configuration

For advanced users, additional settings are available:

- **Event Status**: Configure which status events should have when imported (Published, Draft, etc.)
- **Event Image**: Option to import event images as featured images
- **Event Filtering**: Filter which events are imported based on date range or other criteria
- **Recurring Events**: Configure how recurring events are handled

## Manual Synchronization

To manually synchronize events:

1. Navigate to **Church Plugins → CP Sync**
2. Go to your ChMS tab and then the **Events** tab
3. Click the "Sync Events Now" button
4. Wait for the synchronization to complete

## Scheduled Synchronization

Configure automatic synchronization:

1. Navigate to **Church Plugins → CP Sync → Advanced**
2. In the "Sync Schedule" section, enable automatic synchronization
3. Select the frequency (daily, weekly, etc.)
4. Save your settings

## Troubleshooting

Common issues and solutions:

- **Events not importing**: Check your calendar selection and date range filters
- **Missing information**: CP Sync copies a fixed set of fields. There is no field-mapping setting.
- **Duplicate events**: Imported events are matched by their ChMS ID. There is no unique-identifier setting.

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.