# Reset Tool

CP-Sync includes a reset tool for returning an install to a known state without reinstalling the plugin — useful for unsticking a stalled sync, forcing a full re-import, or wiping everything before decommissioning.

## Reset Levels

There are five levels, ordered least to most destructive. Each level includes the ones below it where that makes sense:

| Level | What it clears | Typical use |
|---|---|---|
| `queue` | Background-process batches, status flags, process locks, and health-check crons | Unstick a stalled or stuck sync |
| `state` | Everything in `queue`, plus the sync-state options | Make the next pull re-import everything (existing posts update in place — no duplicates) |
| `content` | All imported posts, terms, and taxonomies, plus sideloaded images and the image cache directory | Remove imported content but keep your connection and settings |
| `connection` | The saved Planning Center and Church Community Builder settings (credentials and the other fields stored for each system), the connection-test message, and the active Church Management System selection | Disconnect and start the connection over |
| `all` | State + content + connection, plus the plugin settings, debug log, and every scheduled event | Full wipe — the uninstall-equivalent reset |

## Using the Danger Zone (Admin UI)

1. Go to **Church Plugins → CP Sync** (you need an account that can manage options).
2. Open the **Advanced** tab and find **Danger Zone**.
3. Under **Reset level**, choose **Queue**, **Sync state**, **Content**, **Connection**, or **Everything**. Each choice shows a short description under its name.
4. For **Content**, **Connection**, and **Everything**, type `content`, `connection`, or `all` in the box. The label reads **Type "content" to confirm this destructive reset** (or `connection` or `all`). The button stays disabled until the word matches.
5. Click **Reset: Queue**, **Reset: Sync state**, **Reset: Content**, **Reset: Connection**, or **Reset: Everything**. **Queue** and **Sync state** ask you to confirm in a browser dialog first.

When the reset finishes, the tab shows **Reset complete.** and a summary of what was removed. **Connection** and **Everything** show **Reset complete. Reloading…** and then reload the page.

## Using WP-CLI

The same levels are available on the command line:

```bash
# Unstick a stalled sync.
wp cp-sync reset --level=queue

# Forget what has synced so the next pull re-imports everything.
wp cp-sync reset --level=state

# Full uninstall-equivalent wipe (destructive levels prompt for confirmation; --yes skips it).
wp cp-sync reset --level=all --yes
```

If `--level` is omitted, it defaults to `state`.

## REST API

The Danger Zone UI posts to `POST /cp-sync/v1/reset`. The endpoint requires the `manage_options` capability, and every level requires a `confirm` value that matches the level.

## Reset vs. Uninstall

The reset tool and plugin uninstall are separate:

- **Reset (`all`)** wipes CP-Sync's data while the plugin stays installed.
- **Deleting the plugin** only removes CP-Sync's data if the **"Delete all data on uninstall"** toggle (Advanced tab) is enabled first. Without it, uninstalling leaves your imported content and settings in place.

## Notes

- Imported posts are matched by their ChMS ID, so a `state` reset followed by a sync updates existing posts in place rather than duplicating them.
- On multisite, queue data lives at the network level and is handled correctly by every reset level.
- Resets are permanent — there is no undo. Take a database backup before running `content`, `connection`, or `all` on a production site.
