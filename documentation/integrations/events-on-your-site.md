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

## If you turn The Events Calendar off later

While The Events Calendar is active, these pages are not shown. CP Sync sends events to The Events Calendar instead.

If you deactivate The Events Calendar later, and **Display events with CP Sync** and **Sync Events** are both on, events imported earlier can show again at `/events/`.

To clear them out:

1. In the WordPress admin, open **Events** and move the old events to Trash. Open **Event Series** in that menu and trash old series too.
2. Go to **Church Plugins → CP Sync**, open the **Advanced** tab, and click **Pull now**.

Dates that are still in the feed are published again. Future dates that are no longer in the feed stay in the trash. A date that has already ended stays as you left it. If that sync comes back with no events, nothing is removed and nothing is restored.

## What you get

Each date from your church management system becomes its own event.

- The list of upcoming dates is at `/events/`. Dates are in order, soonest first. Each date has a linked title and one button. The button says **Register** when your church management system sent a link, and **Learn more** when it did not. **Learn more** opens that date's page. A Planning Center registration shows its price next to **Register**. One option shows that price. Several options show a range from the lowest to the highest, such as $75 – $200. A free registration says **Free**. A date that is part of a series also links to that series.
- An event page shows the date and time in the timezone stored for that event. For Planning Center that is your WordPress timezone (**Settings → General → Timezone**) at the time of the sync. The page does not switch the clock to the visitor's browser. It shows the location, a map search link for that public location text, the image, the description, and a **Register** button when the church management system sent a link. The price, when there is one, is next to **Register**. A stored free or zero price says **Free**. A blank price is left blank. The button goes to that link. It does not use a form on your site. **Add to calendar** downloads a calendar file for that one date. A date that has already ended keeps its description, time, and price, says that it has ended, and does not show **Register**. A date with no end after its start counts as ended once its start date is over.
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
| Planning Center registration price | Price next to **Register**. One option shows that price. Several options show a range, such as $75 – $200. A free registration says **Free**. |
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

A theme can replace a template by copying it into `cp-sync/events/` in the theme. The files are `archive.php`, `single.php`, `series.php`, `card.php`, `list.php`, `detail-header.php`, `details.php`, `calendar-links.php`, and `series-dates.php`. The list at `/events/` is unchanged: it is not a month calendar, and it does not add search or a category filter on the page.
