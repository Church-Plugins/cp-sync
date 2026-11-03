# Events on your site

CP Sync can show events from Planning Center or Church Community Builder on your own site. You do not need The Events Calendar for this.

If The Events Calendar is active, CP Sync keeps sending events there and does not create these pages. See [The Events Calendar](the-events-calendar.md).

## Turn it on

1. Go to **Church Plugins → CP Sync**.
2. Open the **Advanced** tab.
3. **Display events with CP Sync** is on until you turn it off.
4. Click **Save all Settings**.
5. Open the **Advanced** tab again and click **Pull now**, or wait for the next scheduled sync.

The first visit after you turn this on refreshes the event links. If `/events/` shows a "page not found" message, go to **Settings → Permalinks** and click **Save Changes**.

## What you get

Each date from your church management system becomes its own event.

- The list of upcoming dates is at `/events/`. Dates are in order, soonest first. A date that is part of a series links to that series.
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

Hidden or unapproved Planning Center calendar events are skipped when **Visibility** is **Only Visible in Church Center**. That is the usual setting. Church Community Builder rows that are not approved are skipped.

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

## Defaults when The Events Calendar is not installed

The **Events** tab (event source, visibility, date range, and filters) stays hidden until The Events Calendar is active. **Sync Events** on the **Connect** tab stays disabled for the same reason. **Display events with CP Sync** is the switch for these pages.

Until that tab is available, CP Sync uses the usual defaults:

- Planning Center imports the calendar, and only events that are visible in Church Center.
- Church Community Builder imports current and upcoming events, from today through one year ahead.

Registrations are not imported unless The Events Calendar is active and you choose them on the **Events** tab.

## Theme templates

A theme can replace a template by copying it into `cp-sync/events/` in the theme. The files are `archive.php`, `single.php`, `series.php`, `card.php`, and `list.php`.
