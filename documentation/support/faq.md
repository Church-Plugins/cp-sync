# Frequently Asked Questions

## General Questions

### What is CP-Sync?
CP-Sync is a WordPress plugin that synchronizes data between your church management system (ChMS) and your WordPress website, allowing you to display groups, events, and other information from your ChMS directly on your website.

### Which church management systems does CP-Sync support?
CP-Sync currently supports Planning Center Online (PCO) and Church Community Builder (CCB). We plan to add more integrations in future updates.

### Do I need technical expertise to use CP-Sync?
No, CP-Sync is designed to be user-friendly. The setup process is guided, and most configurations can be done through the WordPress admin interface without coding knowledge.

### How often does CP-Sync update data from my ChMS?
You can configure the sync frequency (hourly, twice daily, daily, or weekly) in the plugin settings. You can also manually trigger a sync at any time.

## Installation and Setup

### What are the system requirements for CP-Sync?
CP-Sync requires WordPress 5.3 or higher, PHP 7.2 or higher, and MySQL 5.6 or higher. You also need an active account with one of the supported church management systems.

### How do I install CP-Sync?
You can install CP-Sync through the WordPress plugin directory or by uploading the plugin ZIP file through your WordPress admin dashboard. Detailed instructions are in the [Installation Guide](../getting-started/installation.md).

### Why can't I connect to my ChMS?
Connection issues usually stem from incorrect API credentials or permission issues. Make sure you've entered the correct information and that your ChMS account has API access. See the [Troubleshooting Guide](../advanced/troubleshooting.md) for more help.

### Is CP-Sync secure?
Yes, CP-Sync uses secure API connections and follows WordPress security best practices. Your API credentials are stored securely in the WordPress database using standard encryption methods.

## Features and Usage

### Can I control which groups are imported?
Yes, CP-Sync provides extensive filtering options. You can filter by group type, status, name, and more. See the [Data Filters Guide](../advanced/data-filters.md) for details.

### Can I map custom fields from my ChMS?
The Ministry Platform integration supports custom field mapping. For PCO and CCB, you can map standard fields to WordPress, but custom field mapping capabilities are limited to the fields specifically supported by the plugin.

### How does CP-Sync handle recurring events?
For The Events Calendar integration, CP-Sync can import recurring events according to the patterns defined in your ChMS. The exact handling depends on the capabilities of your church management system and the event plugin used.

### Will CP-Sync overwrite my existing content?
CP-Sync is designed to update only the content that comes from your ChMS. It will not affect manually created WordPress content unless it shares a unique identifier with imported content.

## Integrations

### Does CP-Sync work with The Events Calendar?
Yes, CP-Sync integrates with The Events Calendar plugin, allowing you to import events from your ChMS into your WordPress calendar. See the [The Events Calendar Integration Guide](../integrations/the-events-calendar.md) for setup instructions.

### How does CP-Sync integrate with CP Groups?
CP-Sync imports group data from your ChMS and creates or updates groups in the CP Groups plugin, maintaining synchronized data between the systems. See the [CP Groups Integration Guide](../integrations/cp-groups.md) for details.

### Can I use CP-Sync with other plugins not listed?
While CP-Sync is specifically designed to work with The Events Calendar and CP Groups, developers can extend it to work with other plugins using hooks and filters. See the [Developer Guide](../advanced/developer-guide.md) for more information.

## Troubleshooting

### What should I do if the sync process fails?
Check the logs in CP-Sync settings to identify the issue. Common problems include API rate limiting, connection issues, or PHP timeout errors. The [Troubleshooting Guide](../advanced/troubleshooting.md) provides solutions for these issues.

### How can I optimize CP-Sync for a large number of groups/events?
Use incremental syncing, adjust batch sizes, and schedule syncs during low-traffic periods. For detailed optimization strategies, see the [Developer Guide](../advanced/developer-guide.md).

### Why are some fields not being imported correctly?
Field mapping issues usually occur due to inconsistent data formats or missing fields in the source data. Review your field mapping configuration and check the data format in your ChMS.

## Support and Updates

### How do I get support for CP-Sync?
Support is available through our [support portal](https://churchplugins.com/support). Premium license holders receive priority support. See the [Getting Help Guide](getting-help.md) for more information.

### How often is CP-Sync updated?
We release updates regularly to improve functionality, fix bugs, and add new features. You can update the plugin through your WordPress dashboard when new versions are available.

### Is there a premium version of CP-Sync?
Yes, CP-Sync offers both free and premium versions. The premium version includes additional features such as advanced filtering, priority support, and additional ChMS integrations.