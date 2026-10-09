# Household Expense Tracker

A local, single-profile web app for categorizing and summarizing household expenses from bank
account statements.

## What it will do

- Import bank statements for three accounts (two personal, one shared).
- Categorize income and expenses with AI, using a list of fixed items (rent, subscriptions,
  utilities) to make matching easier.
- Treat money moved between the household's own accounts as transfers, not income or expenses.
- Show a dashboard per month: income, expenses, balance, top categories, fixed vs variable
  costs, missing payments.
- Allow manual add and edit of transactions.
- Export a monthly report to Excel.

## Principles

- **Local first.** Runs on your own computer and opens on `localhost`. Your financial data stays
  on your machine.
- **Simple to start.** One click or automatic. No containers to manage.
- **Free to run.** AI categorization works with a local model or a free tier.

## Documentation

- [Project description](docs/PROJECT.md): scope, money model, categorization pipeline,
  technology choices and open decisions.
- [Roadmap](docs/ROADMAP.md): build order and current status.

## Privacy

Bank statements and the database are never committed. See `.gitignore`.

## License

[MIT](LICENSE)
