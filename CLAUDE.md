# CP-Sync Development Guide

## Build Commands

There are **two** build systems, each compiling different, non-overlapping assets.
Know which one you need:

- `npm run build:wp` — **wp-scripts** → `build/src/`. Compiles the **React settings SPA**
  (`src/admin/settings/`). Enqueued via `enqueue_asset()` in `Admin/Settings.php`.
  **If you touch the settings UI, this is the command.**
- `npm run build` — **wpackio** → `dist/`. Compiles the **general plugin assets**
  (`assets/js/{main,admin}.js`, `assets/scss/{main,admin}.scss` — the jQuery
  multi-select / field-mapping widgets for the non-React admin). Enqueued in `_Init.php`.
- `npm run build:all` — runs both. **Use this for a full/production build.**
- `npm run start:wp` / `npm run start` — dev watch for wp-scripts / wpackio respectively.
- `npm run lint:wp` — ESLint over JS (`wp-scripts lint-js`).
- `npm run format:wp` — Prettier.

> The two-build split is known tech debt; consolidation onto wp-scripts is planned for
> the settings refactor (see `ai/1.0-release-plan.md`). Until then, `build:all` is safest.

## Verification (run before claiming work is done)

No CI exists — the gates are local:

- `composer test` — **fast, WP-free PHPUnit unit tests** (Brain Monkey for WP stubs).
  This is the green pre-commit gate. Sub-second; run it liberally.
- `composer lint` — PHPCS: security, prepared-SQL, i18n, PHP 7.4 compat. Currently
  reports a known baseline of pre-existing security findings tracked for the 1.0 plan,
  so it is not yet green — do not *add* new violations.
- `composer lint:fix` — PHPCBF auto-fix for the fixable subset.
- `composer verify` — runs test then lint (will be red until the lint baseline clears).

New PHP logic should ship with a unit test in `tests/Unit/`. See `ConvenienceTest` and
`DataFilterTest` for the pure-logic and Brain-Monkey-stubbed patterns respectively.

## Submodule note (read before touching `includes/ChurchPlugins`)

`includes/ChurchPlugins` is a **git submodule** (its own repo). The gitlink commit it
points at must reference a commit that exists on that repo's `master` (or a merged SHA) —
never a feature branch that may be deleted. When a ChurchPlugins change is needed, land
it there first, then bump the gitlink here to the merged SHA. `build:wp` output
(`build/`) is gitignored, so reviewers must rebuild locally.

## Code Style
- Follow WordPress coding standards for PHP and JavaScript
- Use PSR-4 autoloading for PHP classes with CP_Sync namespace
- React components are functional with hooks
- Organize SCSS with variables and component-based structure
- Use namespaced exceptions for error handling
- Maintain consistent naming: CamelCase for classes, snake_case for functions
- Include comprehensive docblocks for functions and classes
- Keep code DRY, especially with ChMS integrations

## Project Structure
- `includes/` - Main PHP classes and core functionality
  - `Admin/` - Admin-side settings and functionality
  - `ChMS/` - Church Management System integrations (PCO, CCB)
  - `ChurchPlugins/` - Shared libraries and utilities
  - `Integrations/` - Integration with other plugins (CP_Groups, TEC)
  - `Setup/` - Plugin initialization and setup
- `src/` - JavaScript/React source files
  - `admin/settings/` - Admin settings UI components
- `assets/` - Static assets (images, compiled JS/CSS)
- `dist/` - Build output directory
- `documentation/` - Customer documentation

## ChMS Integrations
- Planning Center Online (PCO) - REST API integration
- Church Community Builder (CCB) - XML API integration
- API credentials stored in WordPress options
- Data filters and rate limiters in place for API requests

## Documentation
- Customer documentation in `/documentation/` organized by topic
- Main sections: Getting Started, Configuration, ChMS, Integrations, Advanced, Support
- Reference `/documentation/README.md` for documentation structure overview
- When adding features, update relevant documentation files

## Git Workflow
- Make descriptive commit messages
- Reference issue numbers in commits when applicable
- Test your code before committing changes