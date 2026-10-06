# CP Groups Integration

CP-Sync imports groups from Planning Center or Church Community Builder into the CP Groups plugin.

## Prerequisites

Before using the CP Groups integration:

- CP Groups must be installed and activated
- CP-Sync must be installed and activated
- Your church management system must be connected
- You need an account that can manage options (an Administrator)

## Turn On Group Sync

1. Go to **Church Plugins → CP Sync**.
2. On the **Connect** tab, connect Planning Center or Church Community Builder. See [API Connections](../configuration/api-connections.md).
3. Under **Sync**, turn on **Sync Groups**.
4. Click **Save all Settings**.

If CP Groups is not active, **Sync Groups** is disabled and the help text reads **Requires the CP Groups plugin, which is not active on this site.**

**Sync Groups** is on until you turn it off. The **Groups** tab appears after you are connected and the toggle is on.

## Planning Center

On the **Groups** tab:

- **Group Tags to Sync**: each selected tag group is added as a category and shown as a filter on the groups page.
- **Visibility**: **Only Visible in Church Center** (the default) or **Show All**.
- **Groups**: include or exclude groups. The line reads **Pull Groups where**, then **All** or **Any**, then **of the following match**. Add a rule with **Add Condition**.
- **Generate Preview**: shows a sample of the groups that match.
- **Pull Now**: imports now. The tab shows **Import started**.

## Church Community Builder

On the **Groups** tab, the heading reads **Select data to pull from Church Community Builder**.

- **Groups**: the same **Pull Groups where** filter, **All** or **Any**, and **Add Condition**.
- **Generate Preview** and **Pull Now** work the same way. **Pull Now** shows **Import started**.

CP Sync imports groups that are not inactive and that are listed in public search. Your filter runs after that.

## What Is Copied

See [Data Mapping](../configuration/data-mapping.md#groups).

## Schedule

Set **Update Interval** on the **Advanced** tab (**Hourly**, **Daily**, or **Weekly**). See [Sync Scheduling](../advanced/sync-scheduling.md).

## Troubleshooting

- **Groups not importing**: Confirm CP Groups is active and **Sync Groups** is on. On Planning Center, check **Visibility** and clear the **Groups** filter. On Church Community Builder, the group must be active and listed in public search.
- **A field is missing on the group**: Compare the group with [Data Mapping](../configuration/data-mapping.md#groups).

To keep hand edits on one imported group, see [Preventing Sync Updates](../configuration/preventing-sync-updates.md).
