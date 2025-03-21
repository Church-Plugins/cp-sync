# CP Groups Integration

CP-Sync integrates seamlessly with the CP Groups plugin, allowing you to import groups from your church management system directly into your WordPress site.

## Prerequisites

Before using the CP Groups integration:

- CP Groups plugin must be installed and activated
- CP-Sync plugin must be installed and activated
- Your church management system (PCO or CCB) must be connected

## How the Integration Works

When enabled, CP-Sync will:

1. Import groups from your ChMS
2. Create or update corresponding CP Groups in WordPress
3. Maintain synchronization between your ChMS and WordPress
4. Map group attributes properly between systems

## Configuring the CP Groups Integration

### Enable Integration

1. Navigate to **Settings → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the **Groups** tab
4. Check the box to "Enable CP Groups Integration"
5. Save your settings

### Group Type Mapping

You can map group types from your ChMS to CP Groups taxonomies:

1. Navigate to the **Groups** tab of your ChMS settings
2. In the "Group Type Mapping" section, assign each ChMS group type to a CP Groups taxonomy term
3. This ensures your groups are properly categorized in WordPress

### Group Fields Mapping

Map fields from your ChMS to CP Groups fields:

1. In the "Group Fields Mapping" section
2. Configure how each field from your ChMS maps to CP Groups:
   - Group Name → Group Title
   - Description → Group Content
   - Location → Group Location
   - Schedule → Group Schedule
   - Leaders → Group Leaders

### Advanced Configuration

For advanced users, additional settings are available:

- **Group Status**: Configure which status groups should have when imported (Published, Draft, etc.)
- **Group Image**: Option to import group images as featured images
- **Group Filtering**: Filter which groups are imported based on criteria
- **Custom Taxonomies**: Map additional ChMS data to custom taxonomies in CP Groups

## Manual Synchronization

To manually synchronize groups:

1. Navigate to **Settings → CP Sync**
2. Go to your ChMS tab and then the **Groups** tab
3. Click the "Sync Groups Now" button
4. Wait for the synchronization to complete

## Scheduled Synchronization

Configure automatic synchronization:

1. Navigate to **Settings → CP Sync → Advanced**
2. In the "Sync Schedule" section, enable automatic synchronization
3. Select the frequency (daily, weekly, etc.)
4. Save your settings

## Troubleshooting

Common issues and solutions:

- **Groups not importing**: Check your group type filters in the settings
- **Missing information**: Review field mapping configuration
- **Duplicate groups**: Make sure you have properly set up the unique identifier settings
- **Leaders not showing**: Verify leader mapping configuration

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.