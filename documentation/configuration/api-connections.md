# API Connections

Setting up API connections is a crucial step in configuring CP Sync. This guide will walk you through connecting your WordPress site to your church management system's API.

## Planning Center Online (PCO) Connection

### Prerequisites

Before connecting to Planning Center Online:

- Have an active Planning Center Online account you can sign in to
- Have administrator access to your WordPress site
- Have your Planning Center login ready

### Connection Steps

CP Sync connects to Planning Center through Church Plugins. The **Connect** tab has no Client ID, Client Secret, or callback URL field. More detail is in [Planning Center Online](../chms/planning-center.md#connect-to-planning-center).

1. In your WordPress admin, go to **Church Plugins → CP Sync**.
2. Open the **Connect** tab.
3. Set **Church Management System** to **Planning Center Online**.
4. Under **PCO API Configuration**, click **Connect**. The line above the button reads **Click the button below to initiate the OAuth flow and connect to Planning Center Online.**
5. A new window opens at churchplugins.com, which sends you to Planning Center. Sign in there and authorize the connection. The window returns to WordPress and closes.
6. The **Connect** tab shows a success notice that reads **Connected**.

Reload the CP Sync page to see which account is connected. The notice then reads **Connected to {organization} as {person}** when Planning Center returns both names, or **Connected to {organization}** when only the organization name is available.

To disconnect, click **Disconnect**, then click **Connect** to sign in again. If the **Connect** button does not come back, reload the page and click **Disconnect** again. To disconnect Planning Center while another system is selected, switch **Church Management System** back to **Planning Center Online** first, then click **Disconnect**.

Changing **Church Management System** while you are connected leaves the Planning Center connection in place. CP Sync only syncs from the system currently selected. The tab says **Switching platforms does not disconnect Planning Center Online — its connection and settings are preserved.**

After you are connected, the same tab shows a **Sync** section (**Sync Groups**, **Sync Events**, and **Sync Sermons**). Click **Save all Settings** after you change those toggles.

## Church Community Builder (CCB) Connection

### Prerequisites

Before connecting to Church Community Builder:

- Ensure you have an active CCB account with API access
- Obtain your API Username and API Password from CCB
- Your CCB admin may need to grant you API access

### Connection Steps

1. In your WordPress admin, go to **Church Plugins → CP Sync**.
2. Open the **Connect** tab.
3. Set **Church Management System** to **Church Community Builder**.
4. Under **Connect to Church Community Builder**, enter **Subdomain** (the part before `.ccbchurch.com`), **API Username**, and **API Password**.
5. Click **Connect to CCB**. It saves the fields and checks them. When the check succeeds, the button changes to **Disconnect** and the fields lock. If it fails, the tab shows **Connection failed** or the error message.

> **Note (CP Sync 1.0.0):** On some hosts without PHP's sodium extension, saving CCB settings can fail after upgrading. Credentials saved before upgrading may keep syncing. See [What's New in CP Sync 1.0.0](../getting-started/whats-new-1-0-0.md#ccb-settings-wont-save-on-some-hosts).

## Testing Connections

### Planning Center Online

There is no separate test button. After you authorize, the **Connect** tab shows **Connected**. Reload the page and the notice names the account when Planning Center returns it: **Connected to {organization} as {person}**, or **Connected to {organization}**.

### Church Community Builder

**Connect to CCB** checks the credentials. There is no separate test button. A working connection replaces that button with **Disconnect**.

## Troubleshooting Connection Issues

If you encounter connection problems:

### Planning Center Online

- On the **Connect** tab, click **Connect** again. If the browser blocks the window, the tab shows **Failed to open authentication window. Make sure your browser allows popups.** Allow popups for your site and click **Connect** again.
- If you close the window before finishing, the tab shows **Authentication window was closed**. Click **Connect** to start again.
- To sign in again, click **Disconnect**, then **Connect**. If the **Connect** button does not come back, reload the page and click **Disconnect** again.

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

For further assistance with connection issues, please see the [Troubleshooting](https://docs.churchplugins.com/knowledge-base/advanced-troubleshooting-cp-sync/) guide.
