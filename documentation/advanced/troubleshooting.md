# Troubleshooting

This guide provides solutions for common issues you might encounter when using CP-Sync.

## Connection Issues

### Unable to Connect to Planning Center Online

Connection uses the **Connect** button. The **Connect** tab has no Client ID, Client Secret, or callback URL field. See [Planning Center Online](../chms/planning-center.md#connect-to-planning-center).

1. **Open the sign-in window**:
   - Go to **Church Plugins → CP Sync** and open the **Connect** tab.
   - Set **Church Management System** to **Planning Center Online**.
   - Under **PCO API Configuration**, click **Connect**.
   - If the browser blocks the window, the tab shows **Failed to open authentication window. Make sure your browser allows popups.** Allow popups for your site and click **Connect** again.
   - If you close the window before finishing, the tab shows **Authentication window was closed**. Click **Connect** to start again.

2. **Reconnect**:
   - Click **Disconnect**, then **Connect**, and sign in to Planning Center again in the window that opens.
   - If the **Connect** button does not come back, reload the page and click **Disconnect** again.
   - After a successful sign-in, the tab shows **Connected**. Reload the page to see **Connected to {organization} as {person}** or **Connected to {organization}** when Planning Center returns those names.

### Unable to Connect to Church Community Builder

1. **API Credentials**:
   - Verify your API Username and Password
   - Check that your CCB subdomain is correct

2. **API Access**:
   - Ensure your CCB account has API access enabled
   - Contact your CCB administrator if necessary

3. **Incorrect URL Format**:
   - Only enter the subdomain part, not the full URL
   - Example: enter "churchname" not "churchname.ccbchurch.com"

## Sync Issues

### No Data Being Imported

1. **Check Connection Status**:
   - Verify your ChMS connection is active

2. **Review Filters**:
   - Check if you have filters that might be excluding all data
   - Try disabling filters temporarily to test

3. **API Permissions**:
   - Ensure your ChMS account has permission to access the data you're trying to import

4. **Database Character Set**:
   - If the preview shows your data but the sync imports nothing, check the logs for a line like `Queue column wp_options.option_value charset: latin1`
   - Anything other than `utf8mb4` means the database cannot store some of the characters in your ChMS data — see [System Requirements](../getting-started/requirements.md#database-character-set)

### Sync Reports Success but Nothing Is Imported

This usually means the sync is queueing items correctly but the queue cannot be read back. Preview will look completely normal, because it reads from the ChMS directly and never touches the queue.

1. **Check the Logs** at **Church Plugins → CP Sync → Log** for either of these:
   - `Queue column ... charset: latin1` (or `utf8`) — the database cannot represent characters in your data
   - `Batch ... is unreadable and will be discarded without importing` — the queue was corrupted after it was stored

2. **Confirm Items Are Being Processed**:
   - A healthy sync logs a line per item, such as `Processing group 123: Summer Series` followed by `Group 123 created with ID: 456`

3. **Check Background Processing**:
   - Imports run in a background request after the sync is triggered, so the site must be able to make loopback requests to itself
   - Confirm loopback requests are working under **Tools → Site Health**
   - Verify WP-Cron is running, as it retries the queue if the background request does not complete

### Content Imported with `?` Characters

Titles or descriptions arriving as `Summer Series ? Week 1` mean the database cannot represent the original character — usually a bullet (`•`) or curly quote (`’`). The data in your ChMS is fine; it is being altered as it is stored.

Converting the database to `utf8mb4` resolves this. See [System Requirements](../getting-started/requirements.md#database-character-set).

### Only Partial Data Imported

1. **Filter Settings**:
   - Review your filter configurations
   - Check for unintended exclusions

2. **PHP Timeout**:
   - Check your PHP max_execution_time setting
   - Consider increasing it for large imports

## Integration Issues

### CP Groups Integration Problems

1. **Plugin Activation**:
   - Verify CP Groups plugin is installed and activated
   - Check compatible versions

### The Events Calendar Integration Issues

1. **Plugin Compatibility**:
   - Verify you're using a compatible version of The Events Calendar
   - Update both plugins to the latest versions

2. **Venue and Organizer Settings**:
   - For CCB: venue addresses and event images are populated via an enrichment step that runs after each event imports. If venues are missing, look in the log for `Skipping enrichment` or `Failed to fetch event_profile`. Recurring event occurrences without a numeric event ID are not enriched.

## Error Messages

### "PHP Memory Limit Exceeded"

1. **Increase Memory Limit**:
   - Modify your PHP memory_limit setting
   - Contact your hosting provider if necessary

## Logging and Debugging

### Enabling Debug Logs

1. Navigate to **Church Plugins → CP Sync → Log**
2. Perform the operation that's having issues
3. Check **Log File Content**

## Getting Additional Help

If you're still experiencing issues after trying these troubleshooting steps:

1. **Check Documentation**:
   - Review the [Developer Guide](developer-guide.md) for advanced solutions

2. **Support Channels**:
   - Visit our support forum at [Church Plugins Support](https://churchplugins.com/support)
   - Submit a support ticket with your error logs attached
   - Email support@churchplugins.com with details about your issue