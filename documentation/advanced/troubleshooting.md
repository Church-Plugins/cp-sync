# Troubleshooting

This guide provides solutions for common issues you might encounter when using CP-Sync.

## Connection Issues

### Unable to Connect to Planning Center Online

1. **Check OAuth Redirect URI**:
   - Verify the redirect URI is correct: `https://your-domain.com/wp-json/cp-sync/v1/pco/oauth`
   - Ensure there are no typos in the domain

2. **API Credentials**:
   - Confirm your Client ID and Secret are entered correctly
   - Try reconnecting through the OAuth process

3. **SSL Issues**:
   - PCO requires a secure connection
   - Verify your site has a valid SSL certificate installed
   - Check that WordPress Site URL is using https://

### Unable to Connect to Church Community Builder

1. **API Credentials**:
   - Verify your API Username and Password
   - Check that your CCB subdomain is correct

2. **API Access**:
   - Ensure your CCB account has API access enabled
   - Contact your CCB administrator if necessary

3. **Incorrect URL Format**:
   - Only enter the subdomain part, not the full URL
   - Example: enter "churchname" not "churchname.ccbchurch.com"

## Sync Issues

### No Data Being Imported

1. **Check Connection Status**:
   - Verify your ChMS connection is active
   - Test the connection using the "Test Connection" button

2. **Review Filters**:
   - Check if you have filters that might be excluding all data
   - Try disabling filters temporarily to test

3. **API Permissions**:
   - Ensure your ChMS account has permission to access the data you're trying to import

### Only Partial Data Imported

1. **Filter Settings**:
   - Review your filter configurations
   - Check for unintended exclusions

2. **Rate Limiting**:
   - API rate limits may be interrupting your imports
   - Try reducing the batch size in advanced settings

3. **PHP Timeout**:
   - Check your PHP max_execution_time setting
   - Consider increasing it for large imports

### Duplicate Items Being Created

1. **Unique Identifier Settings**:
   - Verify the unique identifier field is configured correctly
   - This is usually an ID field that matches between systems

2. **Clear Cache**:
   - Try clearing the plugin cache in Advanced settings
   - This rebuilds the relationship maps between systems

## Integration Issues

### CP Groups Integration Problems

1. **Plugin Activation**:
   - Verify CP Groups plugin is installed and activated
   - Check compatible versions

2. **Mapping Configuration**:
   - Review field mappings between ChMS and CP Groups
   - Ensure required fields are mapped correctly

### The Events Calendar Integration Issues

1. **Plugin Compatibility**:
   - Verify you're using a compatible version of The Events Calendar
   - Update both plugins to the latest versions

2. **Venue and Organizer Settings**:
   - Check how venues and organizers are being mapped
   - Configure default venue if locations aren't importing correctly

## Error Messages

### "API Rate Limit Exceeded"

1. **Reduce Sync Frequency**:
   - Space out your sync operations
   - Consider incremental syncs instead of full syncs

2. **Batch Processing**:
   - Reduce the number of items processed per batch
   - Enable incremental processing in advanced settings

### "PHP Memory Limit Exceeded"

1. **Increase Memory Limit**:
   - Modify your PHP memory_limit setting
   - Contact your hosting provider if necessary

2. **Reduce Batch Size**:
   - Process fewer items per sync operation

## Logging and Debugging

### Enabling Debug Logs

1. Navigate to **Settings → CP Sync → Advanced**
2. Set "Log Level" to "Debug"
3. Enable "Detailed API Logging" if needed
4. Save settings
5. Perform the operation that's having issues
6. Check logs at **Settings → CP Sync → Logs**

### Common Log Error Messages

- **API Authentication Failed**: Check your API credentials
- **API Request Timeout**: The ChMS server took too long to respond
- **Invalid Response Format**: The ChMS returned unexpected data
- **Rate Limit Exceeded**: You've hit API request limits

## Getting Additional Help

If you're still experiencing issues after trying these troubleshooting steps:

1. **Check Documentation**:
   - Review the [Developer Guide](developer-guide.md) for advanced solutions

2. **Support Channels**:
   - Visit our support forum at [Church Plugins Support](https://churchplugins.com/support)
   - Submit a support ticket with your error logs attached
   - Email support@churchplugins.com with details about your issue