# Preventing Sync Updates on Individual Posts

Every post CP-Sync imports (groups, events, sermons) is updated on each sync run. If a group, sermon, or upcoming event disappears from your ChMS, the next sync removes that post. Events whose end date has already passed stay on the site. If a sync gets nothing back at all from your ChMS, CP-Sync removes nothing; use the Reset tool if you really want to clear everything. If you want to customize a specific imported post in WordPress without your changes being overwritten, you can lock it.

## Locking a Post

1. Edit the imported post (any post CP-Sync created shows a **CP Sync** box in the editor sidebar)
2. Check **Prevent sync from updating this post**
3. Save/update the post

While locked:

- Sync runs will **not overwrite** the post's content, meta, or taxonomies
- Sync runs will **not remove** the post, even if the source item no longer exists in your ChMS

Uncheck the box and save to resume normal syncing. The next sync run will bring the post back in line with the ChMS source (overwriting any manual changes).

## Notes

- The lock box only appears on posts that CP-Sync imported.
- If you already made a CP Sermons series, speaker or service type by hand with exactly the same name as one in Planning Center, sermon sync links to it and keeps it in step with Planning Center. Resetting synced content can remove it.
- The lock applies per post; all other imported posts continue syncing normally.
- The sync process itself can never set or clear a lock — only a user saving the post from the edit screen can change it.
