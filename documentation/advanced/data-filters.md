# Customizing Data Filters

Data filters in CP-Sync allow you to control exactly which data is imported from your church management system into WordPress. This guide explains how to use and customize these filters.

## Understanding Data Filters

Data filters act as rules that determine whether a specific group, event, or other item from your ChMS should be imported. For example, you might want to:

- Only import active groups
- Only import events happening in the future
- Only import groups with certain types or categories
- Exclude groups with specific words in their names

## Accessing Data Filters

The filter tabs appear only after you connect your ChMS. The **Groups** tab shows as soon as you connect when CP Groups is active, because **Sync Groups** is on by default. The **Events** tab shows as soon as you connect when The Events Calendar is active, because **Sync Events** is on by default. Turning either of those toggles off hides that tab. The **Sermons** tab stays hidden until you turn **Sync Sermons** on, and it appears only when CP Sermons is active. **Calendar Filters** and **Registration Filters** are Planning Center only and follow the event source you choose; **Sermons** is Planning Center only; the **Events** filter is CCB.

1. Navigate to **Church Plugins → CP Sync**
2. Open the **Groups**, **Events**, or **Sermons** tab
3. Use the filter there (**Groups**, **Calendar Filters**, **Registration Filters**, **Events**, or **Sermons**)

## Setting a Filter

Each filter named above — **Groups**, **Calendar Filters**, **Registration Filters**, **Events**, and **Sermons** — is one list of conditions. The only button that adds to that list is **Add Condition**.

1. Open the **Groups**, **Events**, or **Sermons** tab, as in [Accessing Data Filters](#accessing-data-filters).
2. Click **Add Condition**.
3. Set **Selector**, **Compare**, and **Value**.
4. Click **Add Condition** again for another condition. Each condition has a trash-can **Remove** button.
5. The line reads **Pull Groups where**, **Pull Calendar Filters where**, **Pull Registration Filters where**, **Pull Events where**, or **Pull Sermons where**, then **All** or **Any**, then **of the following match**.
6. Click **Save all Settings**.

**All** pulls an item only when every condition matches. **Any** pulls it when one condition matches.

## Common Filter Examples

### Groups Filters

- Only import groups whose **Enrollment Status** is Open (Planning Center) or whose **Inactive** is No (CCB)
- Exclude groups with "Staff" or "Internal" in the name
- Only import Small Groups and Life Groups
- Only import groups that have a meeting location (Planning Center: **Location** **Is Not Empty**)

### Events Filters

- Only import events that start before a date you pick (**Start Date** **Is Less Than**)
- Exclude events with "Private" or "Staff Only" in the title
- Only import events of a certain **Event Type** or **Ministry Area** (CCB)

## Troubleshooting Filters

If your filters aren't working as expected:

- Check whether the line says **All** or **Any**
- Confirm **Selector**, **Compare**, and **Value** on each condition
- Test with one condition, then add more
