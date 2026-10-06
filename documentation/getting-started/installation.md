# Installation

This guide will walk you through the process of installing and activating the CP Sync plugin on your WordPress website.

## Requirements

Before installing CP Sync, ensure your website meets these requirements:

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Access to a supported Church Management System (PCO or CCB)
- Administrator access to your WordPress website

## Installation Process

### Manual Installation

1. Download the CP-Sync plugin ZIP file from the [Church Plugins website](https://churchplugins.com)
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins → Add New → Upload Plugin**
4. Click "Choose File" and select the ZIP file you downloaded
5. Click "Install Now"
6. After installation, click "Activate" to enable the plugin

## Post-Installation Setup

After activating the plugin:

1. Go to **Church Plugins → CP Sync** (`admin.php?page=cps_settings`). You need an account that can manage options (an Administrator).
2. On the **Connect** tab, set **Church Management System** to **Planning Center Online** or **Church Community Builder**.
3. Connect that system. See [API Connections](../configuration/api-connections.md).
4. Under **Sync**, turn on the feeds you want. **Sync Groups** needs CP Groups. **Sync Events** needs The Events Calendar. **Sync Sermons** (Planning Center only) needs CP Sermons and is off until you turn it on. **Sync Groups** and **Sync Events** are on until you turn them off.
5. Click **Save all Settings**.

> **Note (CP Sync 1.0.0):** Settings live under **Church Plugins > CP Sync**. The old Settings link no longer works. See [What's New in CP Sync 1.0.0](whats-new-1-0-0.md).

## Verifying Installation

1. Go to **Church Plugins → CP Sync** and confirm the **Connect** tab loads.
2. Connect your church management system.
3. On the **Advanced** tab, click **Pull now**. The tab shows **Hard pull started successfully** when the sync starts.
4. Or open **Groups**, **Events**, or **Sermons** (those tabs appear after you are connected, the matching **Sync** toggle is on, and the companion plugin is active) and click **Pull Now**. That button shows **Import started**.

## Troubleshooting Installation

If you encounter issues during installation:

- Ensure your server meets the requirements
- Check for plugin conflicts
- Verify you have the latest version of the plugin
- Check your PHP error logs for any related errors

For further assistance, see the [Getting Help](https://docs.churchplugins.com/knowledge-base/getting-help-cp-sync/) section.
