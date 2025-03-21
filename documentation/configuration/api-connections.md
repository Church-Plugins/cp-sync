# API Connections

Setting up API connections is a crucial step in configuring CP-Sync. This guide will walk you through connecting your WordPress site to your church management system's API.

## Planning Center Online (PCO) Connection

### Prerequisites

Before connecting to Planning Center Online:

- Ensure you have an active Planning Center Online account
- Make sure you have administrative access to your account
- Have your PCO login credentials ready

### Connection Steps

1. Navigate to **Settings → CP Sync** in your WordPress admin dashboard
2. Click on the **PCO** tab
3. In the **Connect** sub-tab, click the **Connect to Planning Center** button
4. You'll be redirected to the Planning Center Online login page
5. Enter your Planning Center credentials and authorize the connection
6. Upon successful authorization, you'll be redirected back to your WordPress admin
7. Verify that the connection status shows as "Connected"

### Advanced: Manual API Setup

For those who prefer to set up their own API application in Planning Center:

1. Go to the [Planning Center Developer Portal](https://api.planningcenteronline.com/oauth/applications)
2. Create a new application
3. Set the Redirect URI to: `https://your-site.com/wp-json/cp-sync/v1/pco/oauth`
4. Copy the Client ID and Client Secret
5. In your WordPress admin, navigate to **Settings → CP Sync → PCO → Connect**
6. Enter the Client ID and Client Secret in the corresponding fields
7. Click "Save API Settings" and then "Connect to Planning Center"

## Church Community Builder (CCB) Connection

### Prerequisites

Before connecting to Church Community Builder:

- Ensure you have an active CCB account with API access
- Obtain your API Username and API Password from CCB
- Your CCB admin may need to grant you API access

### Connection Steps

1. Navigate to **Settings → CP Sync** in your WordPress admin dashboard
2. Click on the **CCB** tab
3. In the **Connect** sub-tab, enter the following information:
   - CCB Church Subdomain (the part before `.ccbchurch.com`)
   - API Username
   - API Password
4. Click "Save API Settings"
5. Click "Test Connection" to verify your credentials
6. If successful, you'll see a success message

## Testing Connections

After setting up your connection, it's important to test it:

1. Navigate to your ChMS tab (PCO or CCB)
2. Find the "Test Connection" button
3. Click to test the connection
4. The system will attempt to retrieve data and report success or failure

## Troubleshooting Connection Issues

If you encounter connection problems:

### Planning Center Online

- Ensure your OAuth redirect URI is correct
- Check that you have the necessary permissions in Planning Center
- Verify that your WordPress site is accessible from the internet
- Clear your browser cache and try reconnecting

### Church Community Builder

- Double-check your API username and password
- Ensure your CCB subdomain is correct
- Verify with your CCB administrator that API access is enabled for your account
- Check that the API services you need are enabled in CCB

## Security Considerations

Your API connection credentials grant access to potentially sensitive information. Please follow these security practices:

- Keep your WordPress site updated
- Use HTTPS for your WordPress site
- Limit admin access to trusted individuals
- Regularly review who has access to your ChMS accounts

For further assistance with connection issues, please see the [Troubleshooting](../advanced/troubleshooting.md) guide.