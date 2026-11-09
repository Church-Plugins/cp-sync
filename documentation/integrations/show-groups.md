# Show Groups on Your Site with CP Sync

CP Sync can show your groups on your website. You do not need the CP Groups plugin for this.

The groups page uses the same layout as CP Groups, without a filter column. Each group shows its photo, category and type buttons, name, meeting time, location, and a short description. The buttons open a page of groups with that category or type. Labels such as **Full** or **Kid Friendly** appear when your church management system sets them. A group's own page adds its leader names, the full description, **View Details** when your church management system has a page for the group, and **Register Now** when it has a signup link. Leader email addresses are not shown. CP Sync does not add search or filters.

CP Groups is optional. CP Sync can show the same groups without it. CP Groups adds its own group screens when you activate it. If you activate it later, it uses the same groups. You do not import them again.

## Turn It On

1. Go to **Church Plugins → CP Sync**.
2. On the **Connect** tab, turn on **Sync Groups**. Without CP Groups, it is off until you turn it on. On a site updated from before 1.2.0, it starts off too, even if it was on before.
3. Click **Save all Settings**.
4. After a sync, open `yoursite.org/groups/`.

When Groups sync is on, the groups list uses `/groups/`. If a page on your site already uses that address, rename that page's slug. In **Pages**, open the page, change the slug (the last part of its web address) to something else, such as `groups-info`, and update the page. The groups list stays at `/groups/`. If the old address still opens the page, go to **Settings → Permalinks** and click **Save Changes**.

With **Show All**, every group syncs and appears on your site, including groups unlisted in your ChMS.

Each group also has its own page. The photo and title on the groups page link to it. **All groups** at the top of that page returns to the list. On the groups list, the description is shortened to about two lines, and a long group name stays on one line. The web address of each group stays the same if you install CP Groups later, because CP Groups uses these same groups.

To stop showing groups, turn off **Sync Groups** on the **Connect** tab and click **Save all Settings**. Groups also stop syncing. Groups already imported stay in the database.

Groups do not appear in the WordPress admin menu. The sync owns their names, descriptions, and photos, so change a group in your church management system. To change how groups look, see [Change How Groups Look](#change-how-groups-look).

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

Colors, spacing, and buttons use the same ChurchPlugins CSS variables and `.cp-button` as CP Groups, such as `--cp-color-brand-primary`. To change the look across CP products, set those variables in your theme's CSS.

## If You Use CP Groups

Install and activate CP Groups when you want its own group screens. It uses the groups CP Sync already created, and they appear in the WordPress admin menu. If **Sync Groups** is off, turn it on to keep syncing.

See [CP Groups](cp-groups.md) for the sync settings.
