# Planning Center Online Integration

CP Sync provides deep integration with Planning Center Online (PCO), allowing you to synchronize groups, events, and other data with your WordPress website.

## Setting Up the PCO Integration

### Prerequisites

- An active Planning Center Online account you can sign in to
- Administrator access to your WordPress website
- CP Sync plugin installed and activated

### Connect to Planning Center

CP Sync connects to Planning Center through Church Plugins. On the **Connect** tab there is no Client ID, Client Secret, or callback URL field.

1. In your WordPress admin, go to **Church Plugins → CP Sync**.
2. Open the **Connect** tab.
3. Set **Church Management System** to **Planning Center Online**.
4. Under **PCO API Configuration**, click **Connect**. The line above the button reads **Click the button below to initiate the OAuth flow and connect to Planning Center Online.**
5. A new window opens at churchplugins.com, which sends you to Planning Center. Sign in there and authorize the connection. The window returns to WordPress and closes.
6. The **Connect** tab shows a success notice that reads **Connected**.

Reload the CP Sync page to see which account is connected. The notice then reads **Connected to {organization} as {person}** when Planning Center returns both names, or **Connected to {organization}** when only the organization name is available. `{organization}` and `{person}` are those names, not text you type.

To disconnect, click **Disconnect**. The **Connect** button comes back. Click **Connect** to sign in again. If the **Connect** button does not come back, reload the page and click **Disconnect** again. To disconnect Planning Center while another system is selected, switch **Church Management System** back to **Planning Center Online** first, then click **Disconnect**.

Changing **Church Management System** while you are connected leaves the Planning Center connection in place. CP Sync only syncs from the system currently selected. The tab says **Switching platforms does not disconnect Planning Center Online — its connection and settings are preserved.**

After you are connected, the same tab shows a **Sync** section. Choose what to sync, then click **Save all Settings**:

- **Sync Groups** — When enabled, groups are synced from your ChMS. With CP Groups active, they go to CP Groups. Without it, CP Sync shows them on your site.
- **Sync Events** — When enabled, events are synced from your ChMS to The Events Calendar.
- **Sync Sermons** — When enabled, sermons are synced from your ChMS to CP Sermons. This toggle is off until you turn it on.

**Sync Events** is disabled until The Events Calendar is active. Its help text is **Requires The Events Calendar plugin, which is not active on this site.** **Sync Sermons** is disabled until CP Sermons is active. Its help text is **Requires the CP Sermons plugin, which is not active on this site.** **Sync Groups** stays available without CP Groups. It is disabled only when **Display groups with CP Sync** is off and CP Groups is not active. The help text then reads **Turn on Display groups with CP Sync, or activate the CP Groups plugin.**

## Configuring Data Synchronization

### Groups Synchronization

Group sync does not need CP Groups. Without it, CP Sync shows the groups. See [Show Groups on Your Site](../integrations/show-groups.md). If **Display groups with CP Sync** is off and CP Groups is not active, the **Sync Groups** toggle is disabled and the **Groups** tab doesn't appear.

Group sync settings are on the **Groups** tab of **Church Plugins → CP Sync**. The tab appears after you connect to Planning Center, while **Sync Groups** is on.

1. Open the **Groups** tab.
2. Under **Group Tags to Sync**, choose the Planning Center tag groups you want on your site. Each selected tag group is added to your groups as its own filter, named after the tag group, with its tags as the options. For example, a "Life Stage" tag group becomes a Life Stage filter. Tag groups you don't select are not added.
3. Choose **Visibility**: **Only Visible in Church Center** (default) syncs only groups published on Church Center. A group Planning Center marks as not listed, or whose visibility is not public, is skipped on that setting. Groups that do not send those fields are unchanged. **Show All** does not apply this check.
4. Optionally, limit which groups sync. On screen the filter reads **Pull Groups where** **All** or **Any** **of the following match**. There is no separate Groups heading. Click **Add Condition** to add a condition on **Name**, **Description**, **Group Type**, **Location**, **Enrollment Status**, **Enrollment Strategy**, or **Visibility**. For **Group Type**, the comparisons are **Is**, **Is Not**, **Is Empty**, **Is Not Empty**, **Is in**, and **Is Not in**. With no conditions, every group allowed by Visibility syncs.
5. Click **Save all Settings**. The button stays disabled until you change a setting.

Groups that stop matching your filter are deleted from your site, and come back as new posts if they match again later. Switching **Visibility** back to **Only Visible in Church Center** removes unlisted groups that were already synced.

#### What Syncs for Each Group

There is no field mapping to set up. CP Sync fills in each group automatically. Where the [Data Mapping](../configuration/data-mapping.md) page already names a CP Groups field, the table uses that name.

| Planning Center | CP Groups |
|-----------------|-----------|
| Name | Title |
| Description | Content |
| Header image | Featured image |
| Location full address | **Meeting Location** |
| Schedule | **Meeting Time Desc** |
| All leader names, comma-separated | **Group Leader** |
| Group contact email when set, otherwise the email of the first leader who has one (filterable via `cp_sync_pco_group_leader_email`) | **Group Leader Email** |
| Group type | Type (default label **Type**) |
| Tags in the tag groups you selected | The matching option in that tag group's filter (for example, Life Stage: Adults) |
| Church Center URL | **Group Details** (the View Details link) |
| Enrollment closed, or enrollment auto-closed | **Group is Full** |

On CP Groups 1.2 or newer, leaders are saved as **Group Leaders** rows instead of **Group Leader** and **Group Leader Email**. Each row keeps that leader's own name and email. If no leader has an email, the group's contact email is put on the first row. A failed leader fetch leaves the leader fields unset and preserves the values already stored.

### Events Synchronization

Sending events to The Events Calendar needs that plugin to be active. Without it, turn on **Display events with CP Sync** on the **Advanced** tab. **Sync Events** and the **Events** tab then work the same way: visibility, event source, and filters on that tab are what get imported. See [Events on your site](../integrations/events-on-your-site.md).

1. Go to the **Events** tab in the PCO settings
2. Choose your **Event source**: **Pull from Calendar**, **Pull from Registrations**, or **Calendar AND Registrations**
3. Set **Tag groups**, **Visibility**, and **Calendar Filters** (**Pull from Calendar** or **Calendar AND Registrations**), or **Registration Filters** (**Pull from Registrations** or **Calendar AND Registrations**)
4. Click **Save all Settings**

**Caution:** If you switch **Event Source** to **Pull from Calendar** from **Pull from Registrations** or **Calendar AND Registrations**, the next sync permanently deletes upcoming Registrations-sourced events — including events that are still running today. Past events (already ended) are kept. Events with **Prevent sync from updating this post** checked are kept, so lock any Registrations events you want to keep before you switch. Coming from **Pull from Registrations**, Calendar events then import as new posts. Coming from **Calendar AND Registrations**, the Calendar copies stay and the duplicate Registrations copies are removed, leaving one Calendar copy of events that were in both. Switching to **Calendar AND Registrations** keeps existing Registrations posts; an event present in both apps imports twice.

In CP Sync 1.0.0 and later, events keep their real times, and past events are kept by default.

### Calendar Settings: Visibility

When your Planning Center **Event source** is **Calendar** or **Both**, the **Calendar Settings** section includes a **Visibility** option:

- **Only Visible in Church Center** (default) — Keeps events that Planning Center marks as visible in Church Center and approved. Hidden events, and events whose approval status is an explicit non-approved value such as pending or rejected, are not synced. An empty or missing approval status is treated as approved.
- **Show All** — Does not filter on Church Center visibility or approval.

A few things to know:

- **Link Only** events still sync when **Only Visible in Church Center** is selected, as long as they are visible and not explicitly unapproved.
- If the Calendar Filters builder is left empty, no extra filter rules run. With **Only Visible in Church Center**, the visibility and approval checks still apply.
- When the Event source is **Registrations** only, there is no Visibility setting, and these calendar checks are not used.
- With **Only Visible in Church Center**, upcoming events already on the site that become hidden or explicitly unapproved are removed on the next sync. CP Sync shows an admin notice with how many were skipped and how many upcoming events were removed. The notice appears only when events were removed, and it goes away after a sync that removes nothing. Events whose end date has passed stay. Locked posts stay. **Show All** does not remove them for this reason.

Event times on your site use your WordPress timezone (Settings → General). Make sure it matches the timezone set in your Planning Center account, or event times may look shifted.

For how this used to work in 1.0.0, see [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#planning-center-calendar-event-visibility).

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

- **Connection window**: On the **Connect** tab, click **Connect**. If the browser blocks the window, the tab shows **Failed to open authentication window. Make sure your browser allows popups.** Allow popups for your site and click **Connect** again. If you close the window before finishing, the tab shows **Authentication window was closed**. Click **Connect** to start again. To sign in with a different Planning Center account, click **Disconnect**, then **Connect**.
- **Missing Data**: Verify that your PCO account has the necessary modules, and review Visibility and Calendar Filters if Calendar events are missing
- **Rate Limiting**: PCO limits API requests; adjust your sync frequency if needed

For more help, see the [Troubleshooting](https://docs.churchplugins.com/knowledge-base/advanced-troubleshooting-cp-sync/) guide.
