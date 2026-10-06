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

1. Navigate to **Church Plugins → CP Sync**
2. Select your ChMS tab (PCO or CCB)
3. Go to the **Groups** tab
4. Check the box to "Enable CP Groups Integration"
5. Save your settings

### What Is Copied

CP Sync copies a fixed set of fields. There is no field-mapping setting.

### Advanced Configuration

For advanced users, additional settings are available:

- **Group Status**: Configure which status groups should have when imported (Published, Draft, etc.)
- **Group Image**: Option to import group images as featured images
- **Group Filtering**: Filter which groups are imported based on criteria
- **Custom Taxonomies**: Map additional ChMS data to custom taxonomies in CP Groups

## Manual Synchronization

To manually synchronize groups:

1. Navigate to **Church Plugins → CP Sync**
2. Go to your ChMS tab and then the **Groups** tab
3. Click the "Sync Groups Now" button
4. Wait for the synchronization to complete

## Scheduled Synchronization

Configure automatic synchronization:

1. Navigate to **Church Plugins → CP Sync → Advanced**
2. In the "Sync Schedule" section, enable automatic synchronization
3. Select the frequency (daily, weekly, etc.)
4. Save your settings

## Troubleshooting

Common issues and solutions:

- **Groups not importing**: Check your group type filters in the settings
- **Missing information**: CP Sync copies a fixed set of fields. There is no field-mapping setting.
- **Duplicate groups**: Imported groups are matched by their ChMS ID. There is no unique-identifier setting.

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.