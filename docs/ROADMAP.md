# Roadmap

How the app gets built, in order. Each phase is a complete slice: code, tests, Czech UI
strings and doc updates. A phase is done when its "Done when" list holds and `php artisan test`
passes. Product rules live in [PROJECT.md](PROJECT.md); this file only orders the work.

The milestones are arranged so the app becomes **useful early**: after Milestone 1 you can
already track money by hand, before import or AI exist.

Status legend: ✅ done · 🔜 next · ⏳ planned

---

## Milestone 0 — Foundation ✅

- Laravel 13, Livewire 4, Tailwind 4, SQLite; no Docker.
- Single-account auth: first-run setup page, login with lockout, password change in the app,
  `app:reset-password` command.
- Czech UI through translation keys; timezone Europe/Prague.
- Served locally at `http://expense-tracker.local` (WAMP Apache vhost).
- Agent guide (`AGENTS.md`) and project docs.

---

## Milestone 1 — Manual tracking

Goal: record money by hand and see a basic monthly total.

### 1.1 Accounts ✅
- Bank accounts page: create, edit, archive, restore and delete. Each account has a name, an
  owner and a Czech account number (`prefix-number/bank code`).
- Account numbers are checked with the official checksum and stored normalized
  (`CzechAccountNumber`), so statement counterparties can be matched later.
- Household members (two people) are named on the Settings page; an account belongs to one of
  them or is shared.
- Decided: an account with transactions is archived, not deleted. The delete guard is added in
  1.3, when transactions exist.

### 1.2 Categories ✅
- Categories page with three groups: expenses, income and transfers; flat list, no
  subcategories. One "add category" button by the title; the group is chosen in the form.
- A group with no categories offers a default Czech set (`DefaultCategories`); groups that
  already have categories are never touched.
- Add, rename, archive, restore and delete. Names are unique per type, ignoring letter case.
  The type is fixed once created.

### 1.3 Transactions ✅
- Data model: account, date, amount (haléře, signed: negative = money out), type
  (income / expense / transfer), category, counterparty name and account, variable symbol,
  message, note, source (manual / import).
- The form uses a "kind" (expense, income, outgoing transfer, incoming transfer) that sets type
  and sign. Amounts are entered Czech-style (`1 234,50`) and parsed by `App\Money\Amount`.
- A category must match the kind: expense, income or transfer category; for transfers it is
  optional. Changing the kind clears the chosen category. Archived accounts and categories
  cannot be used for new transactions but stay valid on existing ones.
- Month view with previous / next month and filters (account, owner, kind, category,
  uncategorized), kept in the URL.
- Accounts and categories that have transactions can only be archived, not deleted.

### 1.4 Monthly summary dashboard ✅
- Přehled page with month switcher and owner / account filters.
- Tiles: income, expenses, balance, saved, invested, account movement, each compared with the
  previous month (`App\Reports\MonthlySummary`, `Totals`).
- Saved = transfers to savings minus transfers from savings; invested = outgoing transfers to
  investments. Both rely on the transfer category's purpose, not its name, and count each
  movement once (deposits by their outgoing side, withdrawals by their incoming side).
- Charts with Apache ECharts: expenses by category (donut), last 12 months (bars + lines),
  where the money went (sankey). Category lists and chart slices link to filtered transactions.
- Per-owner table when no filter is active.
- Verified in a real browser (headless Edge) on desktop and phone widths.

---

## Milestone 2 — Statement import

Goal: transactions come from bank statements instead of typing.

Banks in priority order: **ČSOB** (personal accounts) and **Air Bank** (shared account). PDF only.

### 2.0 PDF spike ✅
- `smalot/pdfparser` reading the plain text in reading order works for ČSOB (positional reading
  jumbles page 1). Layout inspected with a masking script, so no real data left the computer.

### 2.1 ČSOB PDF import ✅
- "Import výpisu" page: upload, preview, confirm; the uploaded file is deleted afterwards.
- `CsobStatementParser`: header (period, account, opening / closing balance) and rows spanning
  several lines (type, counterparty, account, VS, message, card merchant, original currency).
- Every row is checked against the running balance plus opening / closing balance
  (`BalanceCheck`); a mismatch rejects the whole statement.
- Card payments are dated by purchase; the bank posting date is kept as `posted_on`.
- The statement is matched to a registered account by its number.
- Verified on a real statement (63 rows, balances match) and in a real browser.
- An import can be deleted from the history; it removes only the transactions that import
  created, so the statement can be imported again.

### 2.2 Duplicate protection ✅
- Each imported row gets an import key (bank, account, posting date, bank reference, amount,
  balance); rows already imported are skipped and shown as such in the preview.

### Hidden payments ✅
- Eye icon in the import preview and the transaction list, checkbox in the form; hidden details
  never reach the page and are kept when editing. Optional PIN to reveal: later, if wanted.

### 2.3 Air Bank PDF import 🔜 *(needs a sample Air Bank statement)*
- Second parser behind the same `StatementParser` interface; bank detected automatically.

### 2.4 Transfer detection ⏳
- ✅ On import: counterparty account matches an own account → transfer.
- ⏳ Without a counterparty account: an opposite-sign, same-amount transaction on another own
  account within a few days → transfer.
- **Done when:** a contribution from a personal account to the shared one is recognized from
  either side and is excluded from income and expense totals.

---

## Milestone 3 — Fixed items and smart categorization

Goal: most transactions are categorized automatically.

### 3.1 Recurring payments (fixed items)
- ✅ "Pravidelné platby" page: name, kind (expense or outgoing transfer), amount,
  frequency (monthly / quarterly / half-yearly / yearly) with a payment month for non-monthly
  ones, optional end month,
  optional due day (missing 3 days after it), account, category, optional recipient account and match
  text.
- ✅ Monthly status per payment: paid (with the actual amount), waiting, missing, not due
  (`App\Recurring\RecurringOverview`, `DueSchedule`). A payment up to 7 days early or late counts
  for the right month.
- ✅ Linked by hand: "record payment" button, or the recurring payment field on a transaction.
- ✅ Dashboard: fixed expenses, left after fixed costs, recurring payments of the month.
- ⏳ Automatic matching of imported payments (account, amount, date window,
  recipient account, match text) — built together with statement import.

### 3.2 Learned rules ⏳
- A manual category change creates a rule (payee → category) applied to future imports.
- Rules can be listed, edited and deleted.

### 3.3 AI spike ⏳ *(can run in parallel with earlier phases)*
- Test a local model (Ollama) on invented Czech merchant names: accuracy and speed on this
  computer. Compare with one free hosted provider.
- **Done when:** the default provider is chosen and recorded in PROJECT.md.

### 3.4 AI categorization ⏳
- Provider interface; provider chosen in configuration.
- Only transactions left after transfers, fixed items and rules are sent, grouped by unique
  payee, in batches, with the fixed items list as context. Account numbers and personal names
  are stripped before sending to a hosted provider.
- Runs in batches while the import page is open, with progress; no background worker.
- Results carry a confidence level: high is applied, low goes to a review queue.
- A fuzzy match to a fixed item is confirmed once, then stored as a rule.
- **Done when:** an import of a real month leaves only a short review queue, and the app still
  works (manual categorization) when no AI provider is reachable.

---

## Milestone 4 — Reporting

### 4.1 Dashboard additions ⏳
- The core dashboard with charts was built early in 1.4.
- Fixed expenses, left after fixed costs and missing payments were added with 3.1.
- Still to add: top merchants.
- Rearrange the dashboard after real-world testing.

### 4.2 Excel export ⏳
- Monthly `.xlsx` with summary, fixed and variable costs in separate sections, and all
  transactions.
- **Decide:** the PHP Excel library.

---

## Milestone 5 — Easy install for others

- One-click start script for Windows (`start.bat`) that starts the app and opens the browser.
- Install steps that need only PHP and Composer (no Node), including a `composer setup` without
  the npm steps.
- README: install, start, back up (copy the SQLite file), reset password.
- **Done when:** someone else can go from downloading the repo to a working app by following the
  README.

---

## Later (out of scope for v1)

Forecasting, budgets and goals, other banks, multi-currency, bank API sync.
