# Show Groups on Your Site with CP Sync

CP Sync can show your groups on your website. You do not need the CP Groups plugin for this.

Each group shows its name, photo, description, meeting time, location, and leader names. When your church management system has a signup link, the group has a **Register** button. When it does not, and the group has a public page, the button says **Learn more**. Leader email addresses are not shown.

CP Groups adds filters, a map, and a contact form. If you activate CP Groups later, it uses the same groups. You do not import them again.

## Turn It On

1. Go to **Church Plugins → CP Sync**.
2. Open the **Advanced** tab.
3. Leave **Display groups with CP Sync** on. It is on until you turn it off.
4. On the **Connect** tab, turn on **Sync Groups**.
5. Click **Save all Settings**.
6. After a sync, open `yoursite.org/groups/`.

Each group also has its own page. The title on the groups page links to that page.

If you turn **Display groups with CP Sync** off and CP Groups is not active, **Sync Groups** is disabled. Its help text reads **Turn on Display groups with CP Sync, or activate the CP Groups plugin.**

## Put Groups on Another Page

Add this shortcode to any page or post:

```
[cp-sync-groups]
```

That shows 12 groups. To show a different number:

```
[cp-sync-groups count="6"]
```

`count="-1"` shows every group.

To show one group type, use the type's slug (the short name in its web address), such as `small-groups`:

```
[cp-sync-groups count="6" type="small-groups"]
```

## Change How Groups Look

Copy a template from the plugin into your theme and edit the copy:

- `wp-content/plugins/cp-sync/templates/groups/archive.php` to `wp-content/themes/your-theme/cp-sync/groups/archive.php`
- `wp-content/plugins/cp-sync/templates/groups/single.php` to `wp-content/themes/your-theme/cp-sync/groups/single.php`

The groups page is the archive file. A single group is the single file. This works with classic themes and block themes.

## If You Use CP Groups

Install and activate CP Groups when you want filters, a map, or a contact form. CP Groups takes over the groups CP Sync already created. **Display groups with CP Sync** no longer draws the groups page while CP Groups is active.

See [CP Groups](cp-groups.md) for the sync settings.
