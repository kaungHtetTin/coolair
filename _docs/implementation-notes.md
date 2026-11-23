# Implementation notes

Date: 22 November 2026

The owner authorized building the project after the detailed specification and confirmed SQLite, online-only use, no office approval, no stock tracking, multiple invoices/payments, income/expenses, and paper/PDF output.

Catalog update: the owner subsequently authorized importing readable items from `_docs`. See [catalog seed notes](catalog-seed-notes.md) for the 66 catalog items, 152 service rates, source priorities, and skipped unclear text.

The following defaults resolve the previously open design choices for this implementation. They remain configurable or revisable through normal development; they are not new statements that the owner explicitly selected each detail.

| Area | Implemented default |
| --- | --- |
| Application | One shared application and database, with office and mobile staff interfaces; `/office` and `/staff` entry URLs. |
| Login | Username/password; first-office setup only while no users exist; office-created staff accounts. |
| Permissions | Staff have no Finance access. Office manages Finance and all business records. Staff jobs/documents are restricted to related work; all customers are readable, but staff edit only their own created customers. Staff see only their own operation history; office sees all actors. Creator/actor username snapshots persist through account renaming. See [staff access and history](staff-access-and-history.md). |
| Document authority | Staff may issue customer documents directly. No approval workflow. |
| Pricing | Editable catalog, service matrix, custom rates, and percentage discounts. Historical snapshots are retained. The document seed imports 66 readable catalog items and 152 service rates; dedicated price sheets take priority over conflicting sample rates. Review source notes and enter job-specific prices for services without fixed rates. |
| Currency | Whole-Kyat MMK amounts/rates; integer storage; quantities scaled to three decimal places; percentage discounts to two places. Half-up line/discount rounding. No tax module. |
| Numbering | QT, PI, INV prefixes plus a six-digit shared document ID. RCPT uses a separate receipt ID. Gaps between document types are expected; no annual resets. |
| Jobs/sites | Each job stores its service-site address; customers may have multiple jobs/addresses. Use distinct room/equipment section names for clear document breakdowns. |
| Conversion | New drafts retain source links; selected source quantities can be invoiced. Previously invoiced source quantities cannot be issued twice. Additional work can be entered as custom/new lines. |
| Revisions | Issued records stay unchanged. New revisions/replacements are drafts linked to their sources. Office voids an old invoice before its replacement can be issued. |
| Corrections | Full payment reversals and manual-entry reversals, with reasons and history. An invoice with effective allocated payments cannot be voided. Linked issued documents must be voided first. |
| Deposits | Job deposits before final invoicing are supported. One deposit can be allocated incrementally across invoices of the same job. Allocation retries are idempotent. |
| Income | Effective collections including deposits are reported once as collection income. Manual other income is separate. Allocation does not create income. This is operational cash reporting, not full accounting. |
| Methods | Cash, Bank transfer, KBZPay, and WavePay are editable initial methods. No payment gateway or automatic money transfer. Method totals are not bank-account balances. |
| Expenses | Editable initial English categories; unclear Myanmar labels have not been guessed. Optional job links and private supporting files are supported. |
| Output | A4 paper and server-generated downloadable PDF for all three customer-document types and receipts; CSV for financial reports. |
| Language | English interface and amount-in-words; Unicode Myanmar business content, local web font, and embedded PDF font. |
| Balances on output | Invoice paid/balance and receipt allocations are current at printing. This version does not store a separate immutable PDF for every payment state. |
| Branding | Configurable company name/contact details and terms; text company header and snowflake interface mark. No uploaded-logo/signature editor. |
| Connectivity | No offline shell, local business database, queued saves, or background synchronization. Existing app service workers/caches are retired. |
| Database | SQLite with foreign keys, bounded busy timeout, immediate financial transactions, unique request identifiers, and live snapshot backup command. |

## Operating limits

- Overpayment of a specific invoice is rejected. Extra unallocated funds must be recorded explicitly as a job deposit.
- Full recorded reversals/refunds are supported. Partial refunds, customer credit notes, moving money between accounts, and reallocation of already-used payments require a later business-rule extension. A reversal does not execute an external refund.
- Master registers and record-detail context still use bootstrap catalog/customer/job data; document, collection, ledger, and audit histories are paginated. Form selectors now query permission-scoped remote search results in pages of 25 independently of those arrays. See [searchable form selectors](form-selectors.md).
- Statements, accounting profit, tax returns, stock purchasing, payroll, public customer access, and automated notifications are outside this release.
- Quotation acceptance/rejection tracking and automatic expiry are not implemented; the optional validity date prints on the quotation.
- There is no automatic production backup scheduler. The backup command is available for the hosting scheduler; attachments require their own backup.
- Company details, terms, categories, current prices, and the final printer settings should be reviewed by the office before real customer use.

## Verification records

Automated checks live under `tests/`. Browser tests use their own SQLite files and accounts; no test customers or default passwords are inserted into the live database. Generated PDFs/screenshots are saved under ignored `tests/artifacts/` for inspection.
