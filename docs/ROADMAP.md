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

### 1.4 Basic monthly summary 🔜
- Per month: total income, total expenses, balance; transfers listed separately and excluded
  from totals. Filter by account or owner.
- **Done when:** the numbers match a hand calculation over test data, including transfers.

---

## Milestone 2 — Statement import

Goal: transactions come from bank statements instead of typing.

### 2.0 PDF spike ⏳ *(needs a sample ČSOB statement with personal data blacked out)*
- Throwaway check that `smalot/pdfparser` extracts rows correctly; otherwise try `pdftotext`.
- List which fields the PDF contains (counterparty account, variable symbol, message).
- **Done when:** the library choice is made and recorded in PROJECT.md.

### 2.1 ČSOB PDF import ⏳
- Upload a statement for a chosen account; parse deterministically.
- **Checked against the statement's opening and closing balance**; a mismatch rejects the import
  with a clear message.
- Preview the parsed rows before saving.
- The uploaded file is not kept after import.
- **Done when:** a real statement imports with totals matching, and a tampered or unparsable
  file is rejected.

### 2.2 Duplicate protection ⏳
- The same file cannot be imported twice; overlapping statements skip rows already present
  (bank transaction ID when available, otherwise date + amount + counterparty + message).
- **Decide:** the exact duplicate rule once the PDF fields are known.

### 2.3 ČSOB CSV import ⏳
- Second parser behind the same interface; fallback when a PDF layout changes.

### 2.4 Transfer detection ⏳
- On import: counterparty account matches an own account → transfer; otherwise an
  opposite-sign, same-amount transaction on another own account within a few days → transfer.
- **Done when:** a contribution from a personal account to the shared one is recognized from
  either side and is excluded from income and expense totals.

---

## Milestone 3 — Fixed items and smart categorization

Goal: most transactions are categorized automatically.

### 3.1 Fixed items ⏳
- List of expected recurring payments: name, amount and tolerance, frequency, expected day
  window, category, account, optional match text.
- Deterministic matching of outgoing payments on import.
- **Done when:** rent and subscriptions are tagged on import without AI, and an expected payment
  that did not arrive is reported as missing.

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

### 4.1 Monthly dashboard ⏳
- Income, expenses, balance; income by source; top categories and merchants; fixed vs variable;
  left after fixed costs; transfers; missing fixed payments. Filters by account and owner.
- Charts with Chart.js.

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
