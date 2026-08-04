# CP-Sync Development Guide

## Build Commands

There is **one** build system: **wp-scripts**. (The old wpackio build and the jQuery
widgets it compiled were removed in 1.0.)

- `npm run build:wp` — wp-scripts → `build/src/`. Compiles the **React settings SPA**
  (`src/admin/settings/`), the only compiled JS in the plugin. Enqueued via
  `enqueue_asset()` in `Admin/Settings.php`. **This is the only command that compiles JS.**
- `npm run build:all` — alias for `build:wp` (kept for muscle memory / older docs).
- `npm run start:wp` — dev watch.
- `npm run lint:wp` — ESLint over JS (`wp-scripts lint-js`).
- `npm run format:wp` — Prettier.
- `npm run plugin-zip` — wp-scripts plugin-zip for packaging.

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

## Settings Architecture (schema-driven, since 1.0)

- **PHP is the source of truth for per-ChMS settings screens.** `ChMS::get_settings_schema()`
  (overridden in `PCO.php`/`CCB.php`) declares screens → sections → fields as pure data.
  `get_formatted_settings_schema()` serializes a client-safe projection (strips the
  server-only `sanitize`/`validate`/`encrypt` attrs, converts callable `options` into
  `optionsFetcher` REST descriptors) served at `GET /cp-sync/v1/{chms}/schema`.
- **One React renderer.** `src/admin/settings/schema-form/` — a field-type registry
  (`registerFieldType`) + thin `<SchemaForm>`. New field type = one registry entry, never
  a bespoke tab. Action widgets (OAuth/connect buttons, pull, preview) are NOT schema
  fields — tabs compose them alongside `<SchemaForm>`.
- **One store**: `cp-sync/global-settings` (`store/globalStore.js`) owns all values
  (global + per-ChMS), connection, filters, schemas, options cache, and ui state.
  `contexts/settingsContext.js` is a convenience wrapper only — complex flows use the
  store directly.
- **Save enforcement is two-layer, both driven by the schema:** the REST walk
  (`ChMS::sanitize_settings_by_schema()`, per-field sanitize/validate with 400s) and
  auto-registered option filters (`register_schema_option_filters()`, at-rest encryption)
  — encryption lives at the option layer ON PURPOSE so non-REST writers (WP-CLI,
  `update_setting()`) can never store plaintext. Do not consolidate the layers.
- **Known asymmetry:** GLOBAL settings screens (license/advanced/log) declare their
  schemas JS-side (`schema-form/schemas/*`) and the global POST uses the generic
  sanitizer; per-ChMS screens are PHP-declared. Filter configs still ship separately
  (`/{chms}/filters`) from the schema route — merging them is planned post-1.0.

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