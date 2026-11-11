# Events on your site

CP Sync can show events from Planning Center or Church Community Builder on your own site. You do not need The Events Calendar for this.

If The Events Calendar is active, CP Sync keeps sending events there and does not create these pages. See [The Events Calendar](the-events-calendar.md).

## Turn it on

1. Go to **Church Plugins → CP Sync**.
2. On the **Connect** tab, connect Planning Center or Church Community Builder. See [API Connections](../configuration/api-connections.md).
3. Under **Sync**, turn on **Sync Events**. Without The Events Calendar, it is off until you turn it on. On a site updated from before 1.2.0, it starts off too, even if it was on before.
4. Click **Save all Settings**.
5. Open the **Events** tab. Visibility, date range, event source, and registration settings on that tab are what the sync uses.
6. Click **Pull now** on the **Advanced** tab, or wait for the next scheduled sync.

The first visit after you turn this on refreshes the event links. If `/events/` shows a "page not found" message, go to **Settings → Permalinks** and click **Save Changes**.

Events do not appear in the WordPress admin menu. The sync owns their titles, descriptions, dates, and images, so change an event in your church management system. To change how events look, see [Theme templates](#theme-templates).

## If you already have a page at /events/

Leave **Sync Events** off. CP Sync will not take that address, and your page stays.

If you turn on **Sync Events**, CP Sync's event list uses `/events/`. Your old page is still in Pages, but the address shows the event list instead.

To put your page back:

1. Open the **Connect** tab.
2. Turn off **Sync Events**.
3. Click **Save all Settings**.
4. If the page is still missing, go to **Settings → Permalinks** and click **Save Changes**.

## Turn it off

Open the **Connect** tab, turn off **Sync Events**, and click **Save all Settings**. The event list and series pages stop being part of the site, and events stop syncing. Posts that were already imported stay in the database. Turn the switch back on later and they are available again.

## If you turn The Events Calendar off later

While The Events Calendar is active, these pages are not shown. CP Sync sends events to The Events Calendar instead.

If you deactivate The Events Calendar later and **Sync Events** is on, events imported earlier can show again at `/events/`. To keep that address, turn off **Sync Events**.

To clear out old events, use the **Content** level of the [Reset Tool](../advanced/reset-tool.md), then click **Pull now** on the **Advanced** tab. A content reset removes all imported content, including groups and sermons, and the pull brings back only what the turned-on feeds return. Take a database backup first.

If you activate The Events Calendar later and **Sync Events** is off, turn it on to start sending events there.

## What you get

Each date from your church management system becomes its own event.

- `/events/` opens on a month calendar. Each date shows its time and title and links to that date's page. On a phone, or in a column under 768 pixels wide, days with events show a dot. Tap a day to list its events below the calendar. You can also move between days with the arrow keys. The calendar opens on today, or on the next day with events if today has none.
- **List**, next to **Calendar**, shows upcoming dates in order, soonest first. The address for this view is `/events/?view=list`. Each date has a date badge, a linked title, the time, location, categories, a short description, and its image when it has one. The image is on the right, and above the details on a phone. One button says **Register** when your church management system sent a link, and **Learn more** when it did not. **Learn more** opens that date's page. A date that is part of a series also links to that series. Without JavaScript, `/events/` shows this list.
- **Category** above the calendar or list shows one event category. It is hidden when your site has no event categories. On these pages, a Church Community Builder event type is saved as a category when the listing includes one, so a site that only syncs Church Community Builder shows this filter when its events have a type.
- An event page shows the date and time in the timezone stored for that event. For Planning Center that is your WordPress timezone (**Settings → General → Timezone**) at the time of the sync. The page does not switch the clock to the visitor's browser. It shows the location, a map search link for that public location text, the image, the description, and a **Register** button when the church management system sent a link. A Planning Center registration that costs money shows its price near the top of the page, next to **Register**. One option shows that price. Several options show a range from the lowest to the highest, such as $75 – $200. A free event, or one with no price, shows no price. Lists and the calendar do not show prices. The button goes to that link. It does not use a form on your site. **Add to calendar** downloads a calendar file for that one date. A date that has already ended keeps its description, time, and price, says that it has ended, and does not show **Register**. A date with no end after its start counts as ended once its start date is over.
- A password-protected event or series asks for the password before showing its page, and it is left out of the lists at `/events/`, `/event-series/`, series pages, and the `[cp-sync-events]` shortcode. A password-protected event does not offer a calendar file.
- A series page shows the description, the image, the next upcoming dates in order, and a **Register** button when that series has a signup link. It does not download one calendar file for the whole series, and it does not guess dates that are not already published.

A series is created only when the church management system already groups the dates:

- In Planning Center, a Calendar event with two or more dates is a series. Each date is one of those instances.
- In Church Community Builder, an event id with two or more dates on the public calendar is a series.
- A single date does not get a series page.
- A Planning Center registration is one date. It does not get a series page.

Series pages use `/event-series/` so they do not take the `/series/` address CP Sermons uses. The list of all series is at `/event-series/`. Each series card has the series title, a link to that series, and one button. It does not show an event date. A series whose dates are all in the past is still listed.

## What is copied

| From your ChMS | On the site |
| --- | --- |
| Name | Event title. A series uses the same name. |
| Description | Event page, and the series page |
| Start and end | Stored in UTC and in the event timezone. The page shows that stored timezone. |
| All-day | The page says **All day** and does not show a clock time. |
| Location | Location line |
| Image | Featured image. If the download fails, the page uses the image address from your ChMS. |
| Registration link, or the Church Community Builder event page | **Register** button |
| Planning Center registration price | Price next to **Register** on the event page, when the event is not free. One option shows that price. Several options show a range, such as $75 – $200. |
| Planning Center tags or registration categories | Event category, when that feed includes one |
| Church Community Builder event type | Event category on these pages, when the listing includes one |

Events that the sync imports are published pages. CP Sync does not save a separate private copy of an event the visibility setting left out.

### Planning Center visibility

On the **Events** tab, **Visibility** applies to the calendar. It does not apply to registrations.

- **Only Visible in Church Center** (the usual setting). Calendar events that are hidden in Church Center, or that have an explicit not-approved status, are not imported. Events that are visible and not explicitly unapproved are published, including Link Only events.
- **Show All**. The visibility and approval checks are not applied. Calendar events the feed returns are published, including ones hidden in Church Center.

**Event source** chooses the feed:

- **Pull from Calendar**. Calendar events only.
- **Pull from Registrations**. Registration signups only. Archived signups are left out. The calendar visibility setting is not used.
- **Calendar AND Registrations**. Both. An event that exists in both is imported twice.

### Church Community Builder

The public calendar listing is already limited to events CCB shows publicly. If CCB marks an event as not approved, it is skipped. Everything that is imported is published.

**Date Range** on the **Events** tab is the window that is fetched:

- **Current and upcoming events**. Today through one year ahead.
- **Include past 30 days**. Thirty days ago through one year ahead.
- **All future events**. Today through ten years ahead.
- **Custom date range**. The start and end you enter. An empty start uses today. An empty end uses one year ahead.

A date that is still in the future and then disappears from the feed is moved to the trash. A date that has already ended is kept, because the feed usually stops sending past dates.

## Shortcode

Put this on any page or post:

```
[cp-sync-events]
```

It lists the next 5 upcoming dates.

The shortcode works only while **Sync Events** is on and The Events Calendar is not active.

| Attribute | What it does |
| --- | --- |
| `limit` | How many dates to show. The default is 5. Use `limit="0"` to show every upcoming date. |
| `category` | Only dates in that event category. Use the category slug, such as `worship`. |
| `series` | Only dates in one series. Use the series page ID, or the last part of its address. |

Examples:

```
[cp-sync-events limit="10" category="worship"]
```

```
[cp-sync-events series="sunday-service"]
```

## Theme templates

A theme can replace a template by copying it into `cp-sync/events/` in the theme. The files are `archive.php`, `toolbar.php`, `calendar.php`, `single.php`, `series.php`, `series-archive.php`, `card.php`, `series-card.php`, `list.php`, `detail-header.php`, `details.php`, `calendar-links.php`, `series-dates.php`, `shell-open.php`, and `shell-close.php`. Colors, spacing, and buttons use the same ChurchPlugins CSS variables and `.cp-button` as other CP products, such as `--cp-color-brand-primary`. Set those variables in your theme's CSS to change the look. The calendar's colors follow them too.
