# Events on your site

CP Sync can show events from Planning Center or Church Community Builder on your own site. You do not need The Events Calendar for this.

If The Events Calendar is active, CP Sync keeps sending events there and does not create these pages. See [The Events Calendar](the-events-calendar.md).

## Turn it on

Both of these have to be on. If either is off, CP Sync does not create the `/events/` or `/event-series/` addresses, and a page you already have at `/events/` stays in place.

1. Go to **Church Plugins → CP Sync**.
2. Open the **Advanced** tab.
3. Turn on **Display events with CP Sync**. On an upgrade this is off until you turn it on, unless event sync was already on.
4. On the **Connect** tab, **Sync Events** is on until you turn it off. With the display toggle on, that switch is available even when The Events Calendar is not installed.
5. Click **Save all Settings**.
6. Open the **Events** tab. Visibility, date range, event source, and registration settings on that tab are what the sync uses.
7. Click **Pull now** on the **Advanced** tab, or wait for the next scheduled sync.

The first visit after you turn this on refreshes the event links. If `/events/` shows a "page not found" message, go to **Settings → Permalinks** and click **Save Changes**.

## If you already have a page at /events/

Leave **Display events with CP Sync** off. CP Sync will not take that address, and your page stays.

If you turn display on while **Sync Events** is also on, CP Sync's event list uses `/events/`. Your old page is still in Pages, but the address shows the event list instead.

To put your page back:

1. Open the **Advanced** tab.
2. Turn off **Display events with CP Sync**.
3. Click **Save all Settings**.
4. If the page is still missing, go to **Settings → Permalinks** and click **Save Changes**.

Turning off **Sync Events** on the **Connect** tab does the same thing to the addresses: the event list and series pages are not registered. Either switch being off is enough.

## Turn it off

Open the **Advanced** tab, turn off **Display events with CP Sync**, and click **Save all Settings**. The event list and series pages stop being part of the site. Posts that were already imported stay in the database. Turn the switch back on later and they are available again, as long as **Sync Events** is also on.

## What you get

Each date from your church management system becomes its own event.

- The list of upcoming dates is at `/events/`. Dates are in order, soonest first. Each date has a linked title and one button. The button says **Register** when your church management system sent a link, and **View details** when it did not. **View details** opens that date's page. A date that is part of a series also links to that series.
- An event page shows the date and time in your WordPress timezone (**Settings → General → Timezone**), the location, the image, the description, and a **Register** button when the church management system sent a link. The button goes to that link. It does not use a form on your site.
- A series page shows the description, the image, the next upcoming dates, and the same **Register** button.

A series is created only when the church management system already groups the dates:

- In Planning Center, a Calendar event with two or more dates is a series. Each date is one of those instances.
- In Church Community Builder, an event id with two or more dates on the public calendar is a series.
- A single date does not get a series page.
- A Planning Center registration is one date. It does not get a series page.

Series pages use `/event-series/` so they do not take the `/series/` address CP Sermons uses.

## What is copied

| From your ChMS | On the site |
| --- | --- |
| Name | Event title. A series uses the same name. |
| Description | Event page, and the series page |
| Start and end | Stored in UTC and in the WordPress timezone. The page shows the WordPress timezone. |
| All-day | The page says **All day** and does not show a clock time. |
| Location | Location line |
| Image | Featured image. If the download fails, the page uses the image address from your ChMS. |
| Registration link, or the Church Community Builder event page | **Register** button |
| Planning Center tags, registration categories, or a Church Community Builder event type | Event category, when the feed already includes one |

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

A theme can replace a template by copying it into `cp-sync/events/` in the theme. The files are `archive.php`, `single.php`, `series.php`, `card.php`, and `list.php`.
