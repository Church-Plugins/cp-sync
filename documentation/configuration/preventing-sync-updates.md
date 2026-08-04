# Preventing Sync Updates on Individual Posts

Every post CP-Sync imports (groups, events, sermons) is normally kept in sync with your ChMS — each sync run updates it, and if the source item disappears from the ChMS, the post is removed. If you want to customize a specific imported post in WordPress without your changes being overwritten, you can lock it.

## Locking a Post

1. Edit the imported post (any post CP-Sync created shows a **CP Sync** box in the editor sidebar)
2. Check **"Prevent sync from updating this post"**
3. Save/update the post

While locked:

- Sync runs will **not overwrite** the post's content, meta, or taxonomies
- Sync runs will **not remove** the post, even if the source item no longer exists in your ChMS

Uncheck the box and save to resume normal syncing. The next sync run will bring the post back in line with the ChMS source (overwriting any manual changes).

## Notes

- The lock box only appears on posts that CP-Sync imported — regular posts you created yourself are never touched by sync and need no lock.
- The lock applies per post; all other imported posts continue syncing normally.
- The sync process itself can never set or clear a lock — only a user saving the post from the edit screen can change it.
