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

1. Go to **Church Plugins → CP Sync**.
2. On the **Connect** tab, turn on **Sync Groups**. It is disabled until CP Groups is active.
3. Click **Save all Settings**.

### What Is Copied

CP Sync copies a fixed set of fields. There is no field-mapping setting.

### Advanced Configuration

For advanced users, additional settings are available:

- **Group Filtering**: Filter which groups are imported based on criteria

## Manual Synchronization

To manually synchronize groups:

1. Go to **Church Plugins → CP Sync** and open the **Groups** tab.
2. Click **Pull Now**. The tab shows **Import started**.

## Scheduled Synchronization

Configure automatic synchronization:

1. Navigate to **Church Plugins → CP Sync → Advanced**
2. Set **Update Interval** to **Hourly**, **Daily**, or **Weekly**.
3. Click **Save all Settings**.

## Troubleshooting

Common issues and solutions:

- **Groups not importing**: Check your group type filters in the settings
- **Missing information**: CP Sync copies a fixed set of fields. There is no field-mapping setting.
- **Duplicate groups**: Imported groups are matched by their ChMS ID. There is no unique-identifier setting.

For more detailed troubleshooting, see the [Troubleshooting](../advanced/troubleshooting.md) guide.