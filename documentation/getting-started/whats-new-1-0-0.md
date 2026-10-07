# What's New in CP Sync 1.0.0

This page covers what changed in CP Sync 1.0.0, how a few settings behave, and known issues to watch for after upgrading.

## Settings Have Moved

CP Sync settings now live under **Church Plugins > CP Sync** in your WordPress admin (`admin.php?page=cps_settings`). The old Settings link no longer works, so update any bookmarks.

## Other Changes in 1.0.0

- Sermon sync is off by default. Groups and Events stay on.
- Events keep their real times, and past events are kept by default.

## Planning Center: Calendar Event Visibility

When your Planning Center **Event source** is **Pull from Calendar** or **Calendar AND Registrations**, the **Calendar Settings** section includes a **Visibility** option:

- **Only Visible in Church Center** (default) -- Keeps events that Planning Center marks as visible in Church Center. Events that are hidden from Church Center are not synced.
- **Show All** -- Does not filter on Church Center visibility.

A few things to know:

- Events set to **Link Only** in Church Center still sync when **Only Visible in Church Center** is selected.
- If the Calendar Filters builder is left empty, no extra filter rules run (Church Center Visibility still applies).
- When the **Event source** is **Pull from Registrations**, there is no Visibility setting.

## Planning Center: How Event Text Maps

- The event's **Summary** in Planning Center becomes the short text shown in calendar listings that display a summary (for example, The Events Calendar list view and the Events Calendar Shortcode & Block listing).
- The event's **Description** becomes the text on the event's own page.
- If you edit only the Description, listings that show a summary keep the old Summary text. Update the Summary in Planning Center too.
- If you clear the Summary in Planning Center, the old short text currently stays on your site.

## Church Community Builder (CCB) Settings

### Which Groups Sync

Only groups that are not marked Inactive and have **Public Search** checked in CCB are synced. In CCB, you'll find this under **Group Actions > Edit Group Settings > Options**.

### How Far Ahead Events Sync

Use the **Date Range** setting on the CCB **Events** tab:

| Option | What syncs |
|--------|------------|
| Current and upcoming events (Recommended) | Today through one year ahead. This is the default. |
| Include past 30 days | From 30 days ago through one year ahead. |
| All future events | Up to 10 years ahead. |
| Custom date range | The Start and End Dates you choose. |

If you use **Custom date range**, the Start and End Dates do not move on their own. Check that the End Date is far enough out, or events after it won't sync.

## Known Issues

### Sync Interval Doesn't Take Effect After Upgrading

If you changed the sync interval before upgrading to 1.0.0, set it to a different interval once after upgrading and save, then set it back to the interval you want and save again. Saving the same value again does not fix it. Interval changes you make on 1.0.0 work normally.

### CCB Settings Won't Save on Some Hosts

On servers without PHP's sodium extension, saving any Church Community Builder setting (including Date Range) fails with an error, and Disconnect doesn't work. CCB credentials saved before upgrading keep syncing. If you run into this, please contact support.

### Hand Edits on CCB-Synced Events and Groups Are Overwritten

Changes you make by hand in WordPress to CCB-synced content are replaced on the next sync. For example, a category you add by hand to an imported event is cleared, and a group title or description you edit by hand is set back to the CCB value.

To keep your changes on CP Sync 1.0.0 or later:

1. Open the event or group in WordPress.
2. In the **CP Sync** box, check **Prevent sync from updating this post**.
3. Save the post.

Your WordPress changes will stay, and later changes in CCB will not update that post while the box is checked. Version 0.3.1 does not have this checkbox; upgrade to 1.0.0 or later to use it.
