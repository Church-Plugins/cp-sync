# Agent Instructions

Project conventions live in `CLAUDE.md` — read it first. This file covers how to
*finish* a unit of work cleanly.

## Verifying your work

There is no CI. Before claiming a change is done, run the local gates yourself:

```bash
composer test        # PHPUnit (once the harness lands — see ai/1.0-release-plan.md)
composer lint        # PHPCS (WordPress standard)
npm run build:wp     # compiles the React settings app — the ONLY command that does
```

Do not report work as complete on "it looks right." If a gate does not exist yet,
say so explicitly rather than implying it passed.

## Landing the plane (session completion)

When wrapping up a work session:

1. **File follow-ups** — capture anything left undone in `ai/` (see the existing
   `ai/follow-ups-*.md` pattern) so the next session has context.
2. **Run the gates above** if code changed.
3. **Report honestly** — what was verified at runtime vs. only traced through code.
4. **Commit/push only when asked.** Don't push to `master`; branch first.
