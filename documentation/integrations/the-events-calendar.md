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

1. Navigate to **Settings → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the **Events** tab
4. Check the box to "Enable The Events Calendar Integration"
5. Save your settings

### Calendar Mapping

You can choose which calendars from your ChMS to import:

1. Navigate to the **Events** tab of your ChMS settings
2. In the "Calendars to Import" section, select the calendars you want to import
3. This ensures only relevant events are imported into your WordPress site

### Event Fields Mapping

Map fields from your ChMS to The Events Calendar fields:

1. In the "Event Fields Mapping" section
2. Configure how each field from your ChMS maps to The Events Calendar:
   - Event Title → Event Title
   - Description → Event Content
   - Start Date/Time → Event Start
   - End Date/Time → Event End
   - Location → Event Venue
   - Categories → Event Categories

### Advanced Configuration

For advanced users, additional settings are available:

- **Event Status**: Configure which status events should have when imported (Published, Draft, etc.)
- **Event Image**: Option to import event images as featured images
- **Event Filtering**: Filter which events are imported based on date range or other criteria
- **Recurring Events**: Configure how recurring events are handled

## Manual Synchronization

To manually synchronize events:

1. Navigate to **Settings → CP Sync**
2. Go to your ChMS tab and then the **Events** tab
3. Click the "Sync Events Now" button
4. Wait for the synchronization to complete

## Scheduled Synchronization

Configure automatic synchronization:

1. Navigate to **Settings → CP Sync → Advanced**
2. In the "Sync Schedule" section, enable automatic synchronization
3. Select the frequency (daily, weekly, etc.)
4. Save your settings

## Troubleshooting

Common issues and solutions:

- **Events not importing**: Check your calendar selection and date range filters
- **Missing information**: Review field mapping configuration
- **Duplicate events**: Make sure you have properly set up the unique identifier settings
- **Location issues**: Verify venue mapping configuration

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.