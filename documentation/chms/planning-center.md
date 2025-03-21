# Planning Center Online Integration

CP-Sync provides deep integration with Planning Center Online (PCO), allowing you to synchronize groups, events, and other data with your WordPress website.

## Setting Up the PCO Integration

### Prerequisites

- An active Planning Center Online account with API access
- Administrator access to your WordPress website
- CP-Sync plugin installed and activated

### Connect to Planning Center

1. Navigate to **Settings → CP Sync** in your WordPress admin dashboard
2. Select the **PCO** tab
3. Click the **Connect to Planning Center** button
4. Follow the OAuth authentication process
5. Grant necessary permissions when prompted

### API Application Settings

For advanced users who want to create their own PCO API application:

1. Go to [Planning Center Developer Dashboard](https://api.planningcenteronline.com/oauth/applications)
2. Create a new application
3. Set the redirect URI to: `https://your-domain.com/wp-json/cp-sync/v1/pco/oauth`
4. Copy the Client ID and Secret to the PCO settings in CP-Sync

## Configuring Data Synchronization

### Groups Synchronization

1. Go to the **Groups** tab in the PCO settings
2. Configure which group types to import
3. Set up field mapping for:
   - Group name
   - Description
   - Location
   - Meeting time
   - Leaders
4. Configure group taxonomy assignments
5. Save your settings

### Events Synchronization

1. Go to the **Events** tab in the PCO settings
2. Select which calendars to import
3. Configure event fields mapping
4. Set the sync frequency
5. Save your settings

## Advanced Settings

- **Filter Data**: Apply advanced filters to control which data is imported
- **Import Schedule**: Configure automatic sync schedules
- **Logging**: Enable detailed logging for troubleshooting

## Troubleshooting PCO Integration

- **Authentication Errors**: Check your API credentials and permissions
- **Missing Data**: Verify that your PCO account has the necessary modules
- **Rate Limiting**: PCO limits API requests; adjust your sync frequency if needed

For more help, see the [Troubleshooting](../advanced/troubleshooting.md) guide.