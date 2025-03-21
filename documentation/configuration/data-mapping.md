# Data Mapping

Data mapping allows you to control how information from your church management system is imported into WordPress. CP-Sync provides mapping options to ensure your data appears correctly on your website.

## Understanding Data Mapping

Data mapping creates relationships between fields in your ChMS and fields in WordPress. For example:

- A PCO group name maps to a WordPress post title
- A CCB event description maps to a WordPress post content
- Schedule information maps to meeting time meta data

## Default Mappings

CP-Sync includes sensible default mappings for common data types:

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
| Description | Event Content |
| Start Date/Time | Event Start |
| End Date/Time | Event End |
| Location | Event Venue |
| Image | Featured Image |
| Category | Event Category |

## Field Mapping Configuration

Each ChMS integration includes specific field mapping options:

1. Navigate to **Settings → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the related tab (Groups, Events, etc.)
4. Configure the available mapping options
5. Save your settings

## Data Filters

Data filters allow you to control which records are imported based on criteria:

1. Navigate to **Settings → CP Sync → Advanced**
2. Configure filters based on:
   - Field values (e.g., only active groups)
   - Date ranges (e.g., future events only)
   - Custom conditions

## Ministry Platform Custom Mapping

For Ministry Platform integration only:

1. Navigate to **Settings → CP Sync → MP → Configure**
2. Under the Custom Field Mapping section, you can map MP fields to standard WordPress fields
3. Field mappings are specific to the Ministry Platform integration

## Regenerating Mappings

If you need to reset mappings to defaults:

1. Go to **Settings → CP Sync → Advanced**
2. Click **Reset Mappings**
3. Select which mappings to reset (Groups, Events, or All)
4. Confirm the reset