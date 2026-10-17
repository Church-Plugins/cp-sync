# Data Mapping

CP Sync copies fields from your church management system into WordPress on a fixed map. What you can change is which records are included, on the **Groups**, **Events**, and **Sermons** tabs.

## Groups

Groups are created as CP Groups. Turn on **Sync Groups** on the **Connect** tab, with CP Groups active. See [CP Groups](../integrations/cp-groups.md).

### Planning Center

| Planning Center | CP Groups |
|-----------------|-----------|
| Name | Title |
| Description | Content |
| Header image | Featured image |
| Schedule | **Meeting Time Desc** |
| Location address | **Meeting Location** |
| Group type | Type (default label **Type**) |
| Tag groups selected under **Group Tags to Sync** | Extra filters on the groups page |
| Church Center web address | **Group Details** |

A closed enrollment is stored as **Group is Full**.

### Church Community Builder

CP Sync imports groups that are not inactive and that are listed in public search.

| Church Community Builder | CP Groups |
|--------------------------|-----------|
| Name | Title |
| Description | Content |
| Image | Featured image |
| Main leader name | **Group Leader** |
| Main leader email | **Group Leader Email** |
| Meeting day and time | **Meeting Time Desc** |
| Group type | Type (default label **Type**) |
| Department | Category (default label **Category**) |
| Childcare provided | **Kid Friendly** |
| Group is full | **Group is Full** |
| Public signup form URL | **Registration Action** |
| Group page on CCB | **Group Details** |

When CP Locations is active, campus or area is assigned as the group's location term. Without CP Locations it is not stored.

## Events

Events are created in The Events Calendar. Turn on **Sync Events** on the **Connect** tab, with The Events Calendar active. See [The Events Calendar](../integrations/the-events-calendar.md).

### Planning Center Calendar

On the **Events** tab, set **Event source** to **Pull from Calendar** or **Calendar AND Registrations**.

| Planning Center | The Events Calendar |
|-----------------|---------------------|
| Name | Event title |
| Description | Event page body |
| Summary | Excerpt, used by listings that show a summary |
| Start and end | Event start and end |
| Location | Venue |
| Image | Featured image |
| Tag groups selected under **Tag groups** | Taxonomies on the event |

**Summary** and **Description** are described in [Planning Center Online](../chms/planning-center.md#how-event-text-maps-from-planning-center) and [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#planning-center-how-event-text-maps).

Calendar sync imports future event instances.

### Planning Center Registrations

Set **Event source** to **Pull from Registrations** or **Calendar AND Registrations**.

| Planning Center | The Events Calendar |
|-----------------|---------------------|
| Name | Event title |
| Description | Event page body |
| Signup start and end | Event start and end |
| Signup location | Venue |
| Logo | Featured image |
| Categories | Event categories |

When **Event source** is **Calendar AND Registrations**, the **Events** tab shows **Events are not deduplicated across Calendar and Registrations — an event published in both will import twice.**

### Church Community Builder

| Church Community Builder | The Events Calendar |
|--------------------------|---------------------|
| Name | Event title |
| Description | Event page body |
| Start and end | Event start and end |
| Image | Featured image |

Each occurrence is imported as its own event. After import, events with a numeric CCB id get a venue (name, street, city, state, and zip) and an image from the event profile. See [Church Community Builder](../chms/church-community-builder.md#event-enrichment).

## Sermons

Planning Center Publishing episodes that are published to the Church Center library are created as CP Sermons. See [CP Sermons](../integrations/cp-library.md).

## Filters

Open the feed tab and use its filter:

- Planning Center **Groups**: **Groups**, plus **Visibility** (**Only Visible in Church Center** or **Show All**)
- Planning Center **Events**: **Calendar Filters** and **Visibility** under **Calendar Settings**, and **Registration Filters** under **Registration Settings**
- Planning Center **Sermons**: **Sermons**
- Church Community Builder **Groups**: **Groups**
- Church Community Builder **Events**: **Date Range**, then **Events** under **Event Filters**

The filter line reads **Pull Groups where**, **Pull Events where**, **Pull Sermons where**, **Pull Calendar Filters where**, or **Pull Registration Filters where**, then **All** or **Any**, then **of the following match**. Add a rule with **Add Condition**.

For Planning Center Calendar events, **Visibility** under **Calendar Settings** is separate from the **Calendar Filters** builder. An empty **Calendar Filters** builder does not turn **Visibility** off.
