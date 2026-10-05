# Data Mapping

Data mapping allows you to control how information from your church management system is imported into WordPress. CP Sync provides mapping options to ensure your data appears correctly on your website.

## Understanding Data Mapping

Data mapping creates relationships between fields in your ChMS and fields in WordPress. For example:

- A PCO group name maps to a WordPress post title
- A CCB event description maps to a WordPress post content
- Schedule information maps to meeting time meta data

## Default Mappings

CP Sync includes sensible default mappings for common data types:

### Groups Mapping

| ChMS Field | WordPress Field |
|------------|------------------|
| Name | Post Title |
| Description | Post Content |
| Image | Featured Image |
| Leader(s) | Group Leader(s) |
| Schedule | Meeting Time |
| Location | Meeting Location |
| Type/Category | Group Type Taxonomy |

### Events Mapping

| ChMS Field | WordPress Field |
|------------|------------------|
| Title | Event Title |
| Description | Event Content (event page body) |
| Summary (Planning Center) | Short text / excerpt used in some calendar listings |
| Start Date/Time | Event Start |
| End Date/Time | Event End |
| Location | Event Venue |
| Image | Featured Image |
| Category | Event Category |

For Planning Center calendar events, **Summary** and **Description** map differently. See [Planning Center Online Integration](../chms/planning-center.md#how-event-text-maps-from-planning-center) and [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#planning-center-how-event-text-maps).

## Field Mapping Configuration

Each ChMS integration includes specific field mapping options:

1. Navigate to **Church Plugins → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the related tab (Groups, Events, etc.)
4. Configure the available mapping options
5. Save your settings

## Data Filters

Data filters allow you to control which records are imported based on criteria:

1. Navigate to **Church Plugins → CP Sync → Advanced**
2. Configure filters based on:
   - Field values (e.g., only active groups)
   - Date ranges (e.g., future events only)
   - Custom conditions

For Planning Center Calendar events, Church Center **Visibility** under **Calendar Settings** is separate from the Calendar Filters builder. An empty Calendar Filters builder does not turn Visibility off.

## Ministry Platform Custom Mapping

For Ministry Platform integration only:

1. Navigate to **Church Plugins → CP Sync → MP → Configure**
2. Under the Custom Field Mapping section, you can map MP fields to standard WordPress fields
3. Field mappings are specific to the Ministry Platform integration

## Regenerating Mappings

If you need to reset mappings to defaults:

1. Go to **Church Plugins → CP Sync → Advanced**
2. Click **Reset Mappings**
3. Select which mappings to reset (Groups, Events, or All)
4. Confirm the reset
