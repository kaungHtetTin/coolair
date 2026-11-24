# Cool Air Solution

An online operations application for an air-conditioning service business. PHP, SQLite, React, and Vite power separate office and mobile staff workspaces. No MySQL server is required.

## Included features

- Office-managed accounts, login, disabled-account enforcement, and role/record permissions.
- Shared read-only customers, creator-owned customer editing, creator usernames on records, and scoped operation history with immutable actor attribution.
- Customers, service-site jobs, assignment, and job history.
- Products/materials, services, equipment-dependent service rates, gas, and transport catalogs. No stock tracking.
- Multi-room quotations, preliminary invoices, final invoices, revisions, conversion, and source billing checks.
- Historical description/price snapshots, percentage discounts, and fixed-point calculations.
- Multiple payments, job deposits, invoice allocations, receipts, and office-controlled full reversals.
- Other income, expenses, supporting PDF/image attachments, cash-movement reports, receivables, and CSV export.
- A4 paper printing and server-generated PDF downloads with bundled Myanmar Unicode font support.
- Light/dark themes, compact/comfortable density, and mobile navigation.
- Searchable, paged selectors for customers, jobs, catalog items, staff, invoice allocations, categories, and payment methods.
- Optional PWA installation with internet required. No offline business mode or background sync.

## Requirements

- PHP 8.2+ with PDO SQLite, BCMath, mbstring, GD, DOM/XML, fileinfo, and the standard session/JSON extensions.
- Composer for mPDF and its dependencies.
- Node.js 22.12+ and npm for building frontend assets.
- A writable persistent local filesystem for the SQLite database and private attachments.

## Install and run

From the project root:

```sh
composer install
npm ci
php bin/console migrate
php bin/console db:seed
npm run build
php -S localhost:8000 -t public public/router.php
```

Use the existing `.env`, or copy `.env.example` to `.env` on a new installation. Configuration defaults to `storage/database/app.sqlite`, the `Asia/Yangon` timezone, and a unique `cool_air_session` cookie.

To reset a development database, run `php bin/console migrate:fresh`. This deletes all tables and their data in the configured database and reruns every migration. Add `--seed` to also restore the initial settings and catalog: `php bin/console migrate:fresh --seed`.

Open http://localhost:8000. On an empty database, the first page creates the initial office administrator. There are no default passwords or seeded customer transactions. After setup, public account creation is unavailable; office users create staff accounts inside **Staff accounts**.

Alternatively, create the first office account before exposing the application:

```sh
php bin/console user:create administrator
```

Enter a password of at least 10 characters when prompted. A password argument is supported for automation, but an interactive prompt avoids storing it in shell history.

With XAMPP Apache, open http://localhost/cool-air-solution/public/. Enable `mod_rewrite`. A production virtual host should point directly to `public/`. Both `/office` and `/staff` open the shared application; the authenticated role determines available screens and data.

Use `npm run dev` to watch/rebuild assets while editing, then refresh the PHP page. No separate Vite server is needed.

Growing-list form fields support typing to search and loading additional results without stretching the input. They match ordinary input dimensions in compact, comfortable, and mobile layouts. See [_docs/form-selectors.md](_docs/form-selectors.md) for the reusable component and updated fields.

## First-use workflow

1. Review company contact details and document terms in **Settings**.
2. Review the editable payment methods and income/expense categories.
3. Create staff accounts and review the seeded catalog and service rates. The seed imports 66 readable items and 152 service rates from `_docs`, skips unclear entries, and preserves existing office edits. See [_docs/catalog-seed-notes.md](_docs/catalog-seed-notes.md) for source priority and entries without fixed prices.
4. Add a customer and a job, selecting the responsible staff member and service address.
5. Optionally prepare a quotation. Enter each equipment unit or room under a distinct section name, with its service/material charges.
6. Save and issue a preliminary or final invoice. A quotation is not required, and no office approval is required.
7. Collect installments from a final invoice, or record a job deposit from **Collections**. Allocate deposits to issued invoices of the same job later.
8. Print customer documents/receipts or download their PDFs.

Issued documents are locked. For an invoice correction, reverse its collections as necessary, void the original invoice with an office account, and issue a replacement. Voiding preserves the record. Reversal records a full correction/refund in this application; it does not send money through a bank or wallet.

## Finance rules

- Currency: MMK, whole-Kyat rates and stored money; quantity precision: three decimal places; discount precision: two decimal places.
- Each rounded line amount is summed, then the document percentage discount is rounded and subtracted. The server recalculates totals.
- An issued invoice supports any number of installments up to its remaining balance. Excess payments are rejected when collected directly against an invoice.
- A deposit is held against a job and may be allocated across its invoices. Allocation does not count as additional income.
- Effective payments are the collection-income source; they are not duplicated into manual income entries.
- Other income and expenses are separate records. Reversals remain visible in history but are excluded from effective totals.
- Net cash movement is collections + other income − expenses. It is not a profit statement or a reconciled bank balance.
- Staff see jobs they created or are assigned to. Within accessible jobs, they see documents they created or documents on jobs assigned to them. All customers are readable; staff can edit only customers they created. Staff can issue permitted documents and enter custom prices/discounts. Finance remains office-only. **My activity** shows only their operations; office users can inspect company-wide history and filter by username. See [_docs/staff-access-and-history.md](_docs/staff-access-and-history.md).

## Printing and PDF

Every quotation, preliminary invoice, and final invoice has **Print paper** and **Download PDF** actions. Receipts have the same actions. The same PHP template supplies both paper and PDF output.

Documents use A4, repeat table headers, show grouped room costs, and include customer/cashier/technician signature spaces. Amounts in words use English; customer descriptions, addresses, and remarks support Myanmar Unicode. PDFs use the bundled Tharlon Myanmar font; the interface uses Noto Sans Myanmar. Final invoices show current payment balances at printing; receipts show current allocations. No network font service is needed at runtime.

## Configuration and storage

| Setting/path | Purpose |
| --- | --- |
| `DB_PATH` | SQLite file path; relative paths resolve from the project root. |
| `APP_URL` | Optional external base URL including a subfolder; leave empty for local path detection. |
| `APP_TIMEZONE` | Business dates; default `Asia/Yangon`. Audit timestamps are stored/displayed as UTC. |
| `SESSION_NAME` | Session cookie name; choose a unique value per installation. |
| `storage/database/` | Live database; never serve publicly. |
| `storage/uploads/` | Private payment/expense attachments; downloads require authentication. |
| `storage/pdf/` | Regenerable mPDF temporary/font cache. |
| `storage/backups/` | Database backup snapshots. |

SQLite foreign keys and a five-second busy timeout are enabled on every connection. Financial writes acquire an immediate transaction before checking balances. Unique request references prevent duplicate document/payment submissions and repeated deposit allocations. The database belongs on server-local persistent storage, not a network-mounted shared file.

Installable does not mean offline: the previous service worker is retired, its app caches are removed, and authenticated responses use `Cache-Control: no-store`. This app does not persist business drafts or queued transactions in browser storage. When a network save fails, check whether the server saved it before retrying; the same form retains its request reference while open.

## Backup and production deployment

```sh
php bin/console db:backup
```

This creates a consistent live SQLite snapshot using `VACUUM INTO`. Back up `storage/uploads/` separately along with necessary configuration. To restore, stop application writes, replace the configured database with a verified backup, restore matching attachments, check file permissions, and reopen the application. Test restoration before relying on a backup schedule.

For production: install dependencies from lockfiles, run migrations, build assets, set `APP_DEBUG=false`, enable HTTPS, create the initial administrator before public access, and point the server document root to `public/`. Preserve the database/uploads across deployments. Do not replace an existing database with a development copy. The migration/seed commands do not drop existing business tables or overwrite current company settings.

## Verification

For `https://coolair.k2studioapp.com/`, use [.env.production.example](.env.production.example) as the live environment template and follow [_docs/production-deployment.md](_docs/production-deployment.md). The production profile enables secure sessions/HTTPS, disables debug output/public administrator signup, and supports `php bin/console deploy:prepare` and `php bin/console deploy:check`. Keep the local `.env` and test database off the live server.

```sh
npm test
npm run test:e2e
```

The first command runs financial/permission checks and a real concurrent-payment test. The browser suite starts an isolated PHP server and SQLite database, creates test accounts/data, verifies office/mobile flows, conversions, installments, deposits, reversals, authorization, uploads, PDF/printing, responsive layouts, themes/density, and online-only behavior. It uses installed Microsoft Edge on Windows; on other systems install the Playwright Chromium browser with `npx playwright install chromium`.

Test databases and screenshots are written under ignored `tests/artifacts/`, never into the live application database. PDF generation requires `composer install` to have completed.

See [_docs/project-specification.md](_docs/project-specification.md) for the requirements and [_docs/implementation-notes.md](_docs/implementation-notes.md) for the implemented defaults and operating limits.

Myanmar and English are available from the language selector on sign-in and in the workspace top bar. The choice persists and applies to printing/PDF and CSV headings. See [_docs/localization.md](_docs/localization.md) for translation coverage and maintenance.
