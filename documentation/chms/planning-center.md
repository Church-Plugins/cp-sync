# Planning Center Online Integration

CP Sync provides deep integration with Planning Center Online (PCO), allowing you to synchronize groups, events, and other data with your WordPress website.

## Setting Up the PCO Integration

### Prerequisites

- An active Planning Center Online account with API access
- Administrator access to your WordPress website
- CP Sync plugin installed and activated

### Connect to Planning Center

1. Navigate to **Church Plugins → CP Sync** in your WordPress admin dashboard
2. Select the **PCO** tab
3. Click the **Connect to Planning Center** button
4. Follow the OAuth authentication process
5. Grant necessary permissions when prompted

### API Application Settings

For advanced users who want to create their own PCO API application:

1. Go to [Planning Center Developer Dashboard](https://api.planningcenteronline.com/oauth/applications)
2. Create a new application
3. Set the redirect URI to: `https://your-domain.com/wp-json/cp-sync/v1/pco/oauth`
4. Copy the Client ID and Secret to the PCO settings in CP Sync

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
2. Choose your **Event source** (Calendar, Registrations, or Both)
3. Select which calendars to import (when using Calendar or Both)
4. Configure event field mapping
5. Save your settings

In CP Sync 1.0.0 and later, events keep their real times, and past events are kept by default.

### Calendar Settings: Visibility

When your Planning Center **Event source** is **Calendar** or **Both**, the **Calendar Settings** section includes a **Visibility** option:

- **Only Visible in Church Center** (default) — Keeps events that Planning Center marks as visible in Church Center. Events that are hidden from Church Center are not synced.
- **Show All** — Does not filter on Church Center visibility.

A few things to know:

- Events set to **Link Only** in Church Center still sync when **Only Visible in Church Center** is selected.
- If the Calendar Filters builder is left empty, no extra filter rules run (Church Center Visibility still applies).
- When the Event source is **Registrations** only, there is no Visibility setting.

For a short release summary, see [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#planning-center-calendar-event-visibility).

### How Event Text Maps from Planning Center

- The event's **Summary** in Planning Center becomes the short text shown in calendar listings that display a summary (for example, The Events Calendar list view and the Events Calendar Shortcode & Block listing).
- The event's **Description** becomes the text on the event's own page.
- If you edit only the Description, listings that show a summary keep the old Summary text. Update the Summary in Planning Center too.
- If you clear the Summary in Planning Center, the old short text currently stays on your site.

## Advanced Settings

- **Filter Data**: Apply Calendar Filters and other filters to control which data is imported
- **Import Schedule**: Configure automatic sync schedules
- **Logging**: Enable detailed logging for troubleshooting

## Troubleshooting PCO Integration

- **Authentication Errors**: Check your API credentials and permissions
- **Missing Data**: Verify that your PCO account has the necessary modules, and review Visibility and Calendar Filters if Calendar events are missing
- **Rate Limiting**: PCO limits API requests; adjust your sync frequency if needed

For more help, see the [Troubleshooting](https://docs.churchplugins.com/knowledge-base/advanced-troubleshooting-cp-sync/) guide.
