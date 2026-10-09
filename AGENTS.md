# Expense Tracker — Agent Guide

Instructions for AI coding agents (and humans) working on this repository. Read this before
changing code.

## What this project is

A local, single-profile web app that imports bank statements, categorizes transactions with AI
and shows monthly summaries of income, expenses and balance for a household with three accounts
(two personal, one shared).

**[docs/PROJECT.md](docs/PROJECT.md) is the source of truth** for scope and product decisions:
money model (income / expense / transfer), fixed items, categorization pipeline, what is out of
scope. Read it before building a feature. When a decision changes, update that file in the same
change.

**[docs/ROADMAP.md](docs/ROADMAP.md) is the build order.** Work on the phase marked 🔜 unless
asked otherwise, and update the status markers when a phase is finished.

The project is in early development. Prefer small, complete slices (code + tests + docs) over
broad scaffolding.

## Stack

- **Laravel 13** on **PHP 8.3+**
- **Livewire 4** (class-based components) + Blade + **Tailwind CSS 4** built with Vite
- **SQLite** (`database/database.sqlite`); sessions, cache and queue use the database driver
- **PHPUnit** for tests, **Pint** for code style
- No Docker, no Redis, no separate database server

## Commands

```sh
php artisan test                  # run the test suite
vendor/bin/pint                   # fix code style
npm run build                     # rebuild CSS/JS after changing views or classes
php artisan migrate               # apply migrations
php artisan app:reset-password    # set a new password for the single account
```

## Architecture and conventions

### Structure
- **Pages are full-page Livewire components** in `app/Livewire/`, views in
  `resources/views/livewire/`. Routes live in `routes/web.php`. New components are class-based
  (`config/livewire.php` → `make_command.type = class`).
- **Keep components thin.** They hold UI state, validate input and call domain code. Put real
  logic (statement parsing, categorization, matching, reporting) in plain PHP classes grouped by
  area, e.g. `app/Statements/` for statement parsers.
- **Shared UI lives in Blade components** under `resources/views/components/ui/`
  (`x-ui.field`, `x-ui.select`, `x-ui.button` with variants and `href`, `x-ui.card`,
  `x-ui.alert`, `x-ui.nav-link`, `x-ui.icon-button` for row actions with an accessible label). Reuse them; add a new one instead of copying utility classes
  between views.
- Format money only through `App\Money\Amount` (parse input, format for display) so amounts look
  the same everywhere.
- Layouts: `resources/views/layouts/app.blade.php` (signed in) and `guest.blade.php`
  (setup and login), selected with Livewire's `#[Layout]` attribute.

### PHP style
- `declare(strict_types=1);` in every new PHP file.
- Type all parameters, return types and properties.
- **No descriptive prose comments.** Name things so the code explains itself. Only structured
  annotations used by tooling are allowed (`@param`, `@return`, `@var`, `@use`, …).
- No magic values: use enums, config or class constants.
- Run Pint before finishing.

### Money and data
- **Amounts are integers in haléře** (1 CZK = 100). Never use floats for money.
- Single currency: CZK.
- Every schema change is a new migration; never edit a migration that was already committed.

### UI language
- The UI is **Czech**; the code is English.
- Write user-facing strings in English through `__()` and add the Czech translation to
  `lang/cs.json`. Validation messages and attribute names go in `lang/cs/validation.php`.

### Auth
- One account, no public registration. While no user exists, `/setup` creates it; afterwards
  `/login`. The password is changed on the account page or with `app:reset-password`.
- Do not add registration, multi-user features or email-based password reset.

## Testing

- PHPUnit, class-based. Feature tests in `tests/Feature/<Area>/`, extending `Tests\TestCase`
  with `RefreshDatabase`. Unit tests in `tests/Unit/` only for pure logic with no framework or
  database.
- Name tests for behavior: `test_it_rejects_a_wrong_password`.
- Cover the happy path **and** the important failure paths for every feature.
- Use factories for data. Livewire components are tested with `Livewire::test(...)`.
- Tests do not need built assets (`withoutVite()` is set in `Tests\TestCase`).
- A feature is not done until `php artisan test` passes.

## Frontend assets

- Tailwind classes are compiled by Vite. After changing views or classes, run `npm run build`.
- **`public/build` is committed** so people can run the app without Node. Commit rebuilt assets
  together with the views that need them.
- No external CDNs, web fonts or remote scripts: the app must work fully offline.

## Privacy and security

- The app runs locally and is reached only from the same computer.
- **Never commit real financial data**: statements, exports, SQLite files, `.env`. Test fixtures
  must use invented data.
- The only outbound network traffic allowed at runtime is to the configured AI provider.
- Rely on Eloquent and query bindings; never interpolate input into raw SQL.

## Git

- The maintainer commits and pushes. Agents do not commit or push unless explicitly asked.
