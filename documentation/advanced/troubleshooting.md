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

1. **Credentials**:
   - On the **Connect** tab, set **Church Management System** to **Church Community Builder**.
   - Under **Connect to Church Community Builder**, check **Subdomain**, **API Username**, and **API Password**.
   - Click **Connect to CCB**. The button stays disabled until all three fields have a value. A failed check shows **Connection failed** or the message returned by CCB.

2. **Subdomain**:
   - Enter the part before `.ccbchurch.com`, such as `churchname`.
   - Anything other than letters, numbers, and hyphens shows **Invalid subdomain. Subdomains may contain only letters, numbers, and hyphens.**

3. **API access**:
   - Ask your CCB administrator to confirm the API user can sign in.

## Sync Issues

### No Data Being Imported

1. **Check the connection**:
   - Planning Center: the **Connect** tab shows **Connected**, or **Connected to {organization} as {person}** after you reload.
   - Church Community Builder: the button reads **Disconnect** and **Subdomain**, **API Username**, and **API Password** are locked.
   - Under **Sync**, the feed you want is on. **Sync Groups** needs CP Groups. **Sync Events** needs The Events Calendar. **Sync Sermons** (Planning Center) needs CP Sermons and is off until you turn it on.

2. **Review filters**:
   - Planning Center groups: **Visibility** and the **Groups** filter on the **Groups** tab.
   - Planning Center events: **Event source**, **Visibility**, **Calendar Filters**, and **Registration Filters** on the **Events** tab.
   - Planning Center sermons: the **Sermons** filter. Only episodes published to the Church Center library are imported.
   - Church Community Builder groups: the **Groups** filter. Groups that are inactive or not listed in public search are skipped.
   - Church Community Builder events: **Date Range** and the **Events** filter.
   - Remove a condition with the trash icon on that row, then click **Generate Preview**.

3. **API permissions**:
   - The church management system account has to be allowed to read the groups, events, or sermons you expect.

4. **Database Character Set**:
   - If the preview shows your data but the sync imports nothing, check the logs for a line like `Queue column wp_options.option_value charset: latin1`
   - Anything other than `utf8mb4` means the database cannot store some of the characters in your ChMS data — see [System Requirements](../getting-started/requirements.md#database-character-set)

### Sync Reports Success but Nothing Is Imported

This usually means the sync is queueing items correctly but the queue cannot be read back. Preview will look completely normal, because it reads from the ChMS directly and never touches the queue.

1. **Check the log** at **Church Plugins → CP Sync → Log**. Set **Enable Debug Mode** to **Enable**, click **Save all Settings**, and run the sync again. Look for either of these:
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

1. **Filters**:
   - Open the **Groups**, **Events**, or **Sermons** tab and read the filter. Remove conditions that exclude the records you want.
   - On Church Community Builder events, widen **Date Range**. **Current and upcoming events (Recommended)** is today through one year ahead. **All future events** is today through 10 years ahead. **Custom date range** stops at **End Date**.

2. **PHP Timeout**:
   - Check your PHP max_execution_time setting
   - Consider increasing it for large imports

## Integration Issues

### CP Groups Integration Problems

1. **Plugin and toggle**:
   - CP Groups is installed and activated.
   - On the **Connect** tab, **Sync Groups** is on. If CP Groups is inactive, the toggle is disabled and the help text reads **Requires the CP Groups plugin, which is not active on this site.**

2. **Filters**:
   - On the **Groups** tab, check **Visibility** (Planning Center) and the **Groups** filter.
   - Church Community Builder skips groups that are inactive or not listed in public search.

### The Events Calendar Integration Issues

1. **Plugin and toggle**:
   - The Events Calendar is installed and activated.
   - On the **Connect** tab, **Sync Events** is on. If The Events Calendar is inactive, the toggle is disabled and the help text reads **Requires The Events Calendar plugin, which is not active on this site.**

2. **Church Community Builder venues**:
   - Venue name, street, city, state, zip, and the event image are filled in after import, for events that have a numeric CCB id.
   - On the **Log** tab, set **Enable Debug Mode** to **Enable** and look for `Skipping enrichment` or `Failed to fetch event_profile`. Occurrences without a numeric event id are not enriched.

## Error Messages

### PHP runs out of memory

1. **Increase the memory limit**:
   - Raise PHP's `memory_limit`.
   - Ask your host if you cannot change it.

### Planning Center rate limits

The log can include `PCO rate limited (429): waiting`. CP Sync waits and retries that request. If syncs overlap, set **Update Interval** on the **Advanced** tab to **Daily** or **Weekly**.

## Logging and Debugging

### Enabling Debug Logs

1. Go to **Church Plugins → CP Sync** and open the **Log** tab.
2. Set **Enable Debug Mode** to **Enable**.
3. Click **Save all Settings**.
4. Run the sync (**Pull now** on **Advanced**, or **Pull Now** on **Groups**, **Events**, or **Sermons**).
5. Read **Log File Content** on the **Log** tab. **Clear Log File** empties it.

## Getting Additional Help

If you're still experiencing issues after trying these troubleshooting steps:

1. **Check Documentation**:
   - Review the [Developer Guide](developer-guide.md) for advanced solutions

2. **Support Channels**:
   - Visit our support forum at [Church Plugins Support](https://churchplugins.com/support)
   - Submit a support ticket with your error logs attached
   - Email support@churchplugins.com with details about your issue