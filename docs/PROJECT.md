# Household Expense Tracker — Project Description

Status: draft v1 (scope and technology agreed in discussion; details to be refined per feature).

## Purpose

A shared household finance app for two people. It ingests bank account statements,
categorizes every transaction with AI, and shows where money comes from, where it goes and
what is left, month by month.

## Users and accounts

- One profile (a single login) shared by both people. It sees all three accounts.
- The app is **behind a login**, even though it only runs locally. There is a single user
  account; no registration page. The password can be reset from the command line.
- Three separate bank accounts: one per person plus one **shared** account, all in that profile.
- Every account has an **owner flag**: person A, person B, or shared.
- Each account is registered with its account number, so the app can recognize its own accounts.
- Dashboards can be filtered by account or by owner, or show everything combined.

## Money model

Every transaction is exactly one of:

| Type | Meaning | Counted in income/expense totals |
|------|---------|----------------------------------|
| **Income** | Money entering the household from outside (salary, refunds, …) | Yes |
| **Expense** | Money leaving the household to outside | Yes |
| **Transfer** | Money moving between the household's own accounts | **No** |

### Transfers

Each person sends money to the shared account. This is a transfer, not an expense: counting
it would double the spending (once when it leaves a personal account, once when the shared
account pays the rent). Transfers are excluded from income and expense totals, and shown in
their own section.

Detection, in order:
1. The counterparty account number matches a registered account → transfer.
2. No counterparty number, but an opposite-sign transaction with the same amount exists on
   another registered account within a few days → transfer.
3. Otherwise the transaction is categorized as income or expense.

The user can always correct the type manually.

Because contributions land on the shared account, shared costs such as rent are not split per
transaction. Per-person totals come from each person's own accounts, and the shared account
is reported on its own.

## Core features (v1)

1. **Statement import.** Upload a statement file for an account; parse it into transactions;
   skip duplicates when statements overlap.
2. **AI categorization.** Each transaction gets a type and a category. Incomes have
   categories too (salary, refunds, …).
3. **Fixed items list.** The user defines recurring payments the app should expect (see below).
4. **Manual transactions.** Add, edit and delete transactions. A manual category change
   overrides the AI and is remembered as a rule.
5. **Monthly dashboard.** Total income, total expenses, balance, income by source, top spending
   categories and merchants, fixed vs variable spending, money left after fixed costs, transfers
   between accounts, and missing fixed payments.
6. **Excel export.** A monthly `.xlsx` report with fixed and variable costs in separate sections.

## Fixed items (rent, subscriptions, utilities, standing orders)

The user maintains a list of fixed items. Each item has:

- a name (Rent, Netflix, Electricity)
- an amount and a tolerance (exact for rent, a percentage for utilities)
- a frequency (monthly, quarterly, yearly) and an expected day or window
- a category
- an account or owner, with optional match text (payee, variable symbol)

Only **outgoing** payments (withdrawals) are recognized as fixed items. Incoming money is
categorized normally.

The list is the source of truth. It drives:
- cheaper and more consistent categorization (see below)
- the fixed vs variable split and the "left after fixed costs" figure
- warnings when an expected payment is missing or its amount changed notably

## Categorization pipeline

1. **Transfer detection** (deterministic, see above).
2. **Fixed item match** (deterministic): amount, date window and payee text against the list.
   A clear match is tagged with the item and its category; no AI call.
3. **Learned rules**: a payee already categorized before, or corrected by the user, reuses that
   result.
4. **AI**: everything left is sent in batches. The prompt includes the fixed items list so the AI
   can propose "this is probably the Electricity item" when payee text or amount differ slightly.
   The AI returns the category or fixed item with a confidence level.

Confidence handling:
- High confidence is applied automatically.
- Low confidence goes to a review queue.
- A fuzzy match to a fixed item is **confirmed by the user once**; the confirmation is stored as
  a rule and applies automatically afterwards.

## Out of scope for v1

- **Forecasting** ("next month you will have X left"). Planned for the future; the data model
  must keep recurring items and history in a form that makes it possible.
- Bank API synchronisation (open banking).
- Budgets and goals.
- Multi-currency.
- Native mobile app.

## Technology

| Area | Choice | Notes |
|------|--------|-------|
| Backend / frontend | **To be decided** (leaning Laravel) | Standalone project, independent of any existing codebase, no Docker. Leaning Laravel + Vue/Inertia; Python (FastAPI) + a JS frontend only if the bank exports are PDF only. |
| Database | **SQLite** | Decided. One file, no server, trivial backup. Amounts stored as integers (haléře). Can move to MySQL/PostgreSQL if the app is ever hosted. |
| App type | Responsive web app | One codebase for phone and desktop. |
| AI | Provider-agnostic, free option required | A small interface with the provider set in configuration (local model via Ollama, a free API tier, or OpenAI API). Batched classification returning structured JSON. |
| Background work | Job queue (framework-dependent) | Parsing and AI calls run as jobs. |
| Excel export | Library depends on the backend | Decide when building the export. |
| Charts | Chart.js or ApexCharts | Decide when building the dashboard. |
| Statement parsing | Depends on bank export format | CSV preferred; PDF is a significant risk. |

## Decisions

- **Currency and language:** CZK only, Czech UI.
- **Profiles:** one profile, one login, all three accounts visible. Login is required.
- **Network exposure:** the server listens on `127.0.0.1` only, so other devices on the
  network cannot reach it. The login protects the web interface; the SQLite file itself is not
  encrypted.
- **Hosting:** local only. The app runs on the user's own computer and is opened on
  `localhost` in the browser, via a browser shortcut or installed web app. No phone or remote
  access for now.
- **Simple usage is a priority:** starting the app must be one click or automatic. No Docker
  containers to start manually.

## Open decisions

1. **Statement format.** Not known yet; the banks' exports must be tested. Biggest technical risk.
2. **AI provider.** Must be free. Local model keeps data on the machine but is heavy on a
   personal computer; free hosted tiers receive transaction text and may use it for training.
   Optionally strip account numbers and names before sending. Performance of local models to
   be tested.
3. **Backend and frontend stack.** See the Technology table. May combine technologies if needed.
4. **Duplicate handling.** To be discussed. Proposed: detect and skip duplicates on overlapping
   statements.
