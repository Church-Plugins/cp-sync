# Show Groups on Your Site with CP Sync

CP Sync can show your groups on your website. You do not need the CP Groups plugin for this.

Each group shows its name, photo, description, meeting time, location, and leader names. When your church management system has a signup link, the group has a **Register** button. When it does not, and the group has a public page, the button says **Learn more**. Leader email addresses are not shown.

CP Groups adds filters, a map, and a contact form. If you activate CP Groups later, it uses the same groups. You do not import them again.

## Turn It On

1. Go to **Church Plugins → CP Sync**.
2. Open the **Advanced** tab.
3. Turn on **Display groups with CP Sync**. A new site has this on. A site that was already using CP Sync only for events has it off until you turn it on.
4. On the **Connect** tab, turn on **Sync Groups**. Both this and the display toggle need to be on. If **Sync Groups** is off, the groups page is not created.
5. Click **Save all Settings**.
6. After a sync, open `yoursite.org/groups/`.

When Groups sync is on, the groups list uses `/groups/`. If a page on your site already uses that address, rename that page's slug. In **Pages**, open the page, change the slug (the last part of its web address) to something else, such as `groups-info`, and update the page. The groups list stays at `/groups/`. If the old address still opens the page, go to **Settings → Permalinks** and click **Save Changes**.

With **Show All**, every group syncs and appears on your site, including groups unlisted in your ChMS.

Each group also has its own page. The title on the groups page links to that page. On the groups list, the description is shortened to about 40 words. The group's own page shows the full description. The web address of each group stays the same if you install CP Groups later, because CP Groups uses these same groups.

To stop showing groups, turn off **Display groups with CP Sync** on the **Advanced** tab.

If you turn **Display groups with CP Sync** off and CP Groups is not active, **Sync Groups** is disabled. Its help text reads **Turn on Display groups with CP Sync, or activate the CP Groups plugin.**

## Put Groups on Another Page

Add this shortcode to any page or post:

```
[cp-sync-groups]
```

That shows 12 groups. To show a different number:

```
[cp-sync-groups count="3"]
```

The count you set is the number of groups shown, including after you install CP Groups. CP Groups' own groups page uses its **Groups per page** setting instead of this count.

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
