# Customizing Data Filters

Data filters in CP-Sync allow you to control exactly which data is imported from your church management system into WordPress. This guide explains how to use and customize these filters.

## Understanding Data Filters

Data filters act as rules that determine whether a specific group, event, or other item from your ChMS should be imported. For example, you might want to:

- Only import active groups
- Only import events happening in the future
- Only import groups with certain types or categories
- Exclude groups with specific words in their names

## Accessing Data Filters

1. Navigate to **Settings → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the relevant tab (Groups or Events)
4. Scroll to the "Advanced Filters" section

## Creating Basic Filters

### Filter Types

CP-Sync supports several types of basic filters:

- **Include/Exclude by Name**: Filter based on the group or event name
- **Include/Exclude by Type**: Filter based on the group type or event category
- **Include/Exclude by Status**: Filter based on status (active, inactive, etc.)
- **Date Range Filter**: For events, filter based on date range

### Setting Up a Basic Filter

To create a basic filter:

1. In the "Advanced Filters" section, click "Add Filter"
2. Select the filter type from the dropdown
3. Set the condition (equals, contains, starts with, etc.)
4. Enter the value to match
5. Save your settings

## Creating Advanced Filters

For more complex filtering needs, CP-Sync provides a condition builder:

1. In the "Advanced Filters" section, click "Add Condition Group"
2. Click "Add Condition" within the group
3. Select the field to filter on
4. Choose the operator (equals, not equals, contains, etc.)
5. Enter the value to match
6. Add additional conditions as needed
7. Set the group logic to "AND" or "OR"
8. Save your settings

### Multiple Condition Groups

You can create multiple condition groups to build complex filters. For example:

- Group 1 (AND): "Type equals Small Group" AND "Status equals Active"
- Group 2 (AND): "Type equals Ministry Team" AND "Status equals Active"
- Overall logic between groups: OR

This would import all active small groups and active ministry teams.

## Filter Testing

Before applying filters to your actual imports, you can test them:

1. Create your filters
2. Click "Test Filters" at the bottom of the filter section
3. The system will show you a preview of which items would be imported with these filters
4. Adjust your filters as needed based on the results

## Common Filter Examples

### Groups Filters

- Only import groups with status "Active"
- Exclude groups with "Staff" or "Internal" in the name
- Only import Small Groups and Life Groups
- Only import groups that have a meeting location

### Events Filters

- Only import events happening in the next 60 days
- Exclude events with "Private" or "Staff Only" in the title
- Only import events from specific calendars
- Only import events with a public location

## Troubleshooting Filters

If your filters aren't working as expected:

- Check the logical operators (AND/OR) between conditions
- Verify field names match exactly what's in your ChMS
- Test with simpler filters first, then add complexity
- Use the "Test Filters" function to debug

For more complex filtering needs or issues, see the [Developer Guide](developer-guide.md) for information on creating custom filter functions.