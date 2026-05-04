# Church Community Builder Integration

CP-Sync provides integration with Church Community Builder (CCB), allowing you to synchronize groups, events, and other data with your WordPress website.

## Setting Up the CCB Integration

### Prerequisites

- An active Church Community Builder account with API access
- Administrator access to your WordPress website
- CP-Sync plugin installed and activated
- API credentials (username and password) from your CCB administrator

### Connect to Church Community Builder

1. Navigate to **Settings → CP Sync** in your WordPress admin dashboard
2. Select the **CCB** tab
3. Enter your CCB subdomain (the part before `.ccbchurch.com`)
4. Enter your API Username and API Password
5. Click "Save API Settings"
6. Click "Test Connection" to verify your credentials work

## Configuring Data Synchronization

### Groups Synchronization

1. Go to the **Groups** tab in the CCB settings
2. Configure which group types to import:
   - Small Groups
   - Ministries
   - Departments
   - Other group types available in your CCB setup
3. Set up field mapping for:
   - Group name
   - Description
   - Location
   - Meeting time
   - Leaders
4. Configure group taxonomy assignments
5. Save your settings

### Events Synchronization

1. Go to the **Events** tab in the CCB settings
2. Select which calendars to import
3. Configure event fields mapping
4. Set the sync frequency
5. Save your settings

### Event Enrichment

CCB exposes event data through two endpoints with different levels of detail. CP-Sync uses both: the calendar listing for the initial import, then the event profile to fill in details that the listing omits.

After each event is imported or updated, CP-Sync automatically fetches the event profile to populate:

- **Full venue address** — street, city, state, and zip (the calendar listing only provides a venue name)
- **Event image** — the featured image from the event's profile

Enrichment runs once per event on first import, then re-runs only when CCB reports the event has been modified, so subsequent syncs stay fast. Venues are deduplicated within a sync so events sharing a location don't trigger redundant updates.

**Limitation:** Recurring event occurrences from the calendar listing don't include a numeric event ID and can't be enriched. These events will import with the venue name only. Master events with a numeric ID enrich normally.

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
- **Missing Data**: Verify that your CCB account has the necessary modules and permissions
- **Rate Limiting**: CCB limits API requests; adjust your sync frequency if needed
- **XML Parsing Errors**: These can occur if the CCB API response format changes

## CCB API Resources

- [CCB API Documentation](https://designccb.s3.amazonaws.com/helpdesk/files/official_api_specifications.pdf) (PDF)
- Contact your CCB administrator for specific questions about your CCB implementation

For more help, see the [Troubleshooting](../advanced/troubleshooting.md) guide.