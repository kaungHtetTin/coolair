# Cool Air Solution — Detailed Project Specification

Version: 1.2
Date: 22 November 2026  
Status: Implemented first-release baseline. Owner-confirmed scope is recorded below; implementation defaults and operating limits are documented in [implementation notes](implementation-notes.md).
Business: Cool Air Solution Co., Ltd.

## 1. Purpose and interpretation

Build a system for managing air-conditioning products and services, customer jobs, quotations, preliminary invoices, final invoices, payment collections, and income and expenses.

Provide two interfaces sharing the same business data:

1. An office application with a responsive desktop-oriented interface.
2. A staff application designed primarily for mobile screens.

Both interfaces require authentication. The staff application has no public registration. The application must support paper printing and downloadable PDF documents.

This specification distinguishes the following:

- **Confirmed:** Explicitly requested or confirmed by the owner.
- **Proposed:** Recommended behavior for discussion; not an accepted requirement yet.
- **Open:** A business decision is needed before the affected feature can be finalized.

Detailed fields, permissions, correction rules, and technical designs below are proposed unless identified as confirmed. Their inclusion makes the intended implementation reviewable; it does not silently resolve open decisions.

Implementation update: the owner subsequently requested the complete build. Practical defaults were selected for unresolved details and recorded in [implementation notes](implementation-notes.md). Those notes describe the delivered behavior where this original discussion specification offers alternatives. They do not imply that the owner explicitly chose each default.

## 2. Source documents

| Source | Purpose and observations |
| --- | --- |
| [Original requirements](requirement.md) | Two applications; three document types; catalogs; accounts; authentication; document history. |
| [Five-page installation quotation](No_3320,Air_Con_Installation_Quotation_Update_c0956d25_ec1b_4e31.pdf) | Customer/site details, seven room/equipment sections, summary totals, detailed materials and labor, bilingual remarks, and a separate air-conditioner price table. |
| [Non-inverter service prices](photo_2026-11-22_15-46-53.jpg) | Prices by equipment type, horsepower, and service; gas and transportation charges. |
| [Inverter service prices](photo_2026-11-22_15-47-03.jpg) | A separate service price matrix for inverter equipment. |
| [Material prices](photo_2026-11-22_15-47-07.jpg) | 48 material/part entries with specifications, units, quantities, and prices. |
| [Handwritten invoice](photo_2026-11-22_15-47-11.jpg) | Branding, customer details, serial/date/assignment fields, equipment information, item lines, discount, net total, amount in words, and signatures. |
| [Income and expense categories](photo_2026-11-22_15-47-15.jpg) | Suggested category structure; some Myanmar labels need clearer transcription. |
| Owner's discussion decisions | No office approval; optional quotations; multiple invoices per job; multiple payments; collections and income/expenses included; stock tracking excluded; online-only use; paper and PDF output required. |

The PDF's seven section totals add to 7,319,400 Kyats. The visible handwritten invoice line amounts add to 740,700 Kyats. These are useful calculation examples, not default catalog values.

Prices differ between the reference sources. Do not import them as authoritative prices until the owner selects the current price list. Do not infer unclear sizes or Myanmar labels from low-resolution images.

## 3. Confirmed scope

### 3.1 Included

- Office and staff applications.
- Office-managed staff accounts and authenticated access.
- Product/material and service catalog management.
- Quotations, preliminary invoices, final invoices, and their history.
- Jobs with optional quotations and multiple invoices.
- Multiple payments against an invoice.
- Payment collection and income/expense management.
- Paper printing and PDF download.
- Internet-based operation without offline synchronization.
- SQLite as the server-side application database.
- No office approval requirement for the document workflow.

### 3.2 Excluded or not established

**Confirmed exclusions:**

- Stock quantities, stock movements, inventory deduction, and stock reconciliation.
- Offline business operation, offline drafts, and offline synchronization.
- Public staff registration.
- Office approval queues or approval gates for document issuance.

**Not established as first-release requirements:**

- Supplier purchasing, purchase orders, and procurement.
- Payroll, attendance, and staff commissions.
- A customer self-service portal.
- SMS, email, messaging integrations, and payment gateway integrations.
- Full accounting, general ledger, tax filing, and statutory financial statements.
- Multi-company or multi-branch operation.
- Legally certified electronic signatures.

These additional areas require a scope decision before implementation.

## 4. Users, access, and account management

### 4.1 Office users

Confirmed capabilities: authentication, catalog management, staff account management, quotation/invoice management, and history access.

Proposed additional capabilities: customer/job management, payment recording, income/expense management, reporting, company settings, and document templates.

Distinguish an administrator from ordinary office users if different financial or account-management permissions are needed. A configurable role builder is not yet a requirement.

### 4.2 Staff users

Latest confirmed access update: customers are shared for reading, with staff editing restricted to customers they created. Jobs/documents remain scoped to related staff work. Staff operation history is self-only, while office users can inspect all actors. Creator and actor usernames are retained in snapshots. See [staff access and history](staff-access-and-history.md) for the implemented rules and legacy-history limits.

Confirmed owner update: the Finance section is office-only. Staff cannot access collections, deposit allocation, receipts, income/expenses, financial reports, or their supporting files. This supersedes the earlier proposed collection/expense permissions below. Staff retain quotation, preliminary invoice, and final invoice management, and a jobs/documents overview.

Confirmed capabilities: login, quotation management, preliminary invoice management, final invoice management, and history access. Office approval is not required.

Proposed additional capabilities: create customers and jobs during document entry, record collections, and print/download permitted documents.

Open: whether staff can see all records, only records they created, or jobs assigned to them; whether they can change prices, apply discounts, record expenses, view financial reports, or reverse transactions.

### 4.3 Proposed access matrix

| Capability | Office | Staff |
| --- | --- | --- |
| Manage staff accounts | Yes | No |
| Manage product/service prices | Yes | No by default |
| Create and issue customer documents | Yes | Yes; no office approval |
| View document history | Yes | Visibility boundary open |
| Record customer payments | Yes | Proposed; confirm scope |
| Record expenses | Yes | Open |
| View all company finances | Yes for authorized users | Open |
| Correct/void issued documents or payments | Restricted permission proposed | Open |
| Change company settings | Administrator proposed | No |

Permissions govern who may perform an action; they must not introduce an office approval step.

### 4.4 Account behavior

Proposed account fields: name, unique login identifier, role, optional phone, active/inactive status, password hash, and timestamps.

- Office creates and disables staff accounts.
- Disabled accounts cannot start or continue authenticated business activity.
- Password resets are office-managed unless another process is agreed.
- Disabling an account preserves its historical records and attribution.
- Authentication and authorization are enforced on the server for every protected action.

## 5. Customers, sites, jobs, and equipment

### 5.1 Customers and sites

Proposed customer fields: customer number, name, phone, optional alternate contact, address, and remarks.

A customer may have several service addresses. A job identifies the actual service site. Document headers retain a snapshot of the customer/site information used when issued.

Customer registration should be available during document creation without losing the user's entered lines. Existing customers should be searchable to reduce duplicates.

### 5.2 Jobs

A job groups the work for a customer at a particular site. It can contain:

- No quotation, one quotation, or multiple quotation revisions.
- Multiple preliminary invoices.
- Multiple final invoices for distinct work or stages.
- Related payments through its invoices.
- Room/equipment sections and assigned staff, if assignment is adopted.

Proposed job fields: unique job number, customer/site, title or description, date, responsible staff, optional assigned staff, remarks, and status.

Proposed job statuses: open, in progress, completed, cancelled. Job completion and invoice payment are separate states; completed work may still have an outstanding balance.

Open: whether the paper form's assignment number equals the job number or is a separate reference.

### 5.3 Equipment and room sections

The PDF requires grouped documents. Proposed section fields:

- Room/location name.
- Equipment/product description.
- Brand and model.
- Equipment type.
- Inverter/non-inverter classification.
- Horsepower and equipment quantity.
- Optional serial number and equipment notes.
- Service/material lines and section subtotal.

Brand, model, horsepower, and equipment type are separate fields even when handwritten examples combine them.

Simple jobs should allow one section with minimal entry. Larger jobs should support several rooms and equipment units without flattening their individual cost breakdowns.

## 6. Catalogs and pricing

### 6.1 Products, materials, and parts

Proposed fields: item code, name, category, description/specification, optional brand/model, unit, default selling price, active status, and price-change metadata.

Examples include copper pipe, cable, safeguards, frames, trunking, drainage pipe, remote controls, capacitors, sensors, and air-conditioning equipment.

No stock availability check, stock balance, or deduction occurs when an item is invoiced.

Open: whether equipment sale items and installation materials share one catalog with categories or have separate office screens. Both may use a shared underlying item model.

### 6.2 Services and service price matrix

Service prices vary by:

1. Service type.
2. Equipment type.
3. Inverter/non-inverter classification.
4. Horsepower.

Reference services include installation, servicing, removal, and removal with reinstallation. The income category reference also names testing and repairs, refrigerator repairs, washing-machine repairs, and air/water cooler repairs.

The selected combination should suggest the appropriate rate. Missing combinations must be shown explicitly rather than silently treated as free services.

Open: permission to override a suggested rate and whether a reason is mandatory.

### 6.3 Gas and transportation

- Refrigerant items support a refrigerant type and a unit such as pound.
- Transportation charges may depend on destination and use trip as the unit.
- Both appear as explicit customer-facing lines when charged.
- Destination names and current prices require owner confirmation.

### 6.4 Historical pricing

- A document line stores its description, specification, unit, quantity, rate, and amount as a snapshot.
- Later catalog changes do not change existing documents.
- Archived items remain visible in historical documents.
- Conversion preserves the source values unless the user explicitly changes the new draft.
- Drafts must not silently refresh prices from the catalog.

## 7. Shared document structure and calculations

### 7.1 Header fields

Proposed fields: document type, unique number, date, customer and site snapshot, job reference, optional assignment reference, staff attribution, remarks, and source-document references.

Optional fields depend on type: quotation validity date, invoice due date, work date, and customer reference.

### 7.2 Line fields

Section, ordering, line kind, optional catalog item reference, description/specification, unit, decimal quantity, unit rate, line amount, and optional notes.

Line kinds may distinguish equipment sales, materials, services, refrigerant, transportation, and custom charges.

Equipment quantity and material quantity are distinct. The system must not multiply a section total by the equipment count a second time without an explicit calculation rule.

### 7.3 Calculation rules

Proposed formulas:

- Line amount = quantity × unit rate, rounded using the agreed currency rule.
- Section subtotal = sum of its line amounts.
- Document subtotal = sum of section subtotals and any ungrouped charge lines.
- Net total = document subtotal − document discount, plus any explicitly configured tax or adjustment.
- Paid amount = sum of effective payment allocations after reversals/refunds.
- Outstanding balance = net total − paid amount.

Use exact decimal or fixed-point arithmetic on the server. Store monetary amounts as scaled integers in SQLite, using the agreed currency precision; do not rely on SQLite `REAL` or a `DECIMAL` column declaration for exact money storage. For example, if whole-Kyat precision is agreed, 740,700 Kyats is stored as the integer 740700. Store fractional quantities and percentage values with an explicitly defined fixed scale or validated decimal text. Server calculations are authoritative; browser totals provide immediate feedback but cannot determine the stored financial result. The precision decision in D10 must be resolved before the financial schema is finalized.

Quantities must support fractions. Confirm the number of decimal places for quantities, rates, stored money, and printed totals. MMK/Kyats is the working currency inferred from the references; currency settings and rounding remain open.

The handwritten form has a percentage discount field. Proposed support is one document-level discount. Flat discounts, line-level discounts, tax, and special adjustments require explicit decisions. Prevent negative totals and invalid percentages.

### 7.4 Numbering and persistence

Proposed independent sequences: quotation, preliminary invoice, final invoice, payment receipt, other income, and expense. Prefixes, reset periods, and draft-number behavior are open.

- Allocate numbers safely under concurrent requests.
- Do not reuse an issued document number after cancellation.
- Prevent duplicate issuance or duplicate payment collection from repeated taps/retried requests.
- Save a document and its sections/lines atomically.

## 8. Quotations

Confirmed: quotation creation, history, paper printing, and PDF download are needed in both applications. A quotation is optional before invoicing.

Proposed behavior:

- Create and save a draft quotation.
- Add grouped room/equipment estimates with services and materials.
- Preview and issue the quotation without office approval.
- Revise the quotation while preserving earlier issued versions.
- Convert all or selected work into a preliminary or final invoice draft.
- Retain source references without changing the quotation into an invoice in place.
- Record customer acceptance/rejection if this tracking is desired.

Proposed statuses: draft, issued, superseded, cancelled. Customer acceptance and expiry may be tracked separately if adopted.

The PDF's remarks explain estimated quantities and potential price changes. The application should support reusable, editable terms whose wording is snapshotted on each issued document. Confirm the final Myanmar/English wording before use.

The equipment price list appended to the PDF must remain distinct from billable lines unless the owner confirms that it should be included in totals.

## 9. Preliminary invoices

Confirmed: a preliminary invoice presents charges to a customer before the final invoice. It does not require office approval.

Proposed behavior:

- Create directly for a job or from a quotation.
- Update quantities to actual measured materials and performed services.
- Preview, print, and download with a prominent preliminary label.
- Convert to a final invoice draft while retaining the original record and references.
- Prevent accidental repeated conversion of the same work.

Proposed statuses: draft, issued, converted, cancelled.

A preliminary invoice is not counted as finalized sales or an outstanding final-invoice balance under the proposed reporting model. Whether it can receive a deposit is open and must use an explicit payment-allocation rule.

## 10. Final invoices and multiple billing stages

Confirmed: final invoices can be created without office approval; one job may have multiple invoices.

Proposed behavior:

- Create directly, from a quotation, or from a preliminary invoice.
- Review and issue immediately according to the user's permissions.
- Preserve the issued version for printing and historical reference.
- Record collections and show outstanding balance.
- Track document status separately from payment status.

Proposed document statuses: draft, issued, void. Payment statuses: unpaid, partially paid, paid.

Multiple invoices must identify their work/stage. A quotation or preliminary-invoice line may be billed partly or fully; record the billed quantity or amount so later invoices cannot unintentionally duplicate it. Explicit additional work should be entered as new lines.

Open: whether partial conversion is required in the first release and whether progress billing is by line quantity, fixed stage amount, or both.

Issued invoices should not be silently overwritten. Proposed correction options are a controlled revision when unpaid or void-and-replace. Handling paid invoices requires explicit payment reallocation/refund rules. The final correction policy is open.

## 11. Payment collection and receipts

### 11.1 Core requirements

Confirmed: payment collection and multiple payments are included.

Proposed first-release baseline: each payment is allocated to one final invoice; an invoice accepts any number of valid partial payments. A job aggregates invoice totals and collections without adding them as separate charges.

Example:

| Event | Amount (Kyats) | Remaining balance (Kyats) |
| --- | ---: | ---: |
| Final invoice issued | 740,700 | 740,700 |
| Payment 1 | 300,000 | 440,700 |
| Payment 2 | 200,000 | 240,700 |
| Payment 3 | 240,700 | 0 |

### 11.2 Payment fields

- Unique payment/receipt number.
- Customer, invoice, and job references.
- Collection date/time and recorded-at timestamp.
- Positive amount and currency.
- Payment method and receiving account, if accounts are adopted.
- Collecting staff and recording user where these differ.
- Optional bank/mobile transfer reference.
- Notes and optional proof attachment.
- Effective, reversed, or refunded state and correction references.

### 11.3 Validation and integrity

- Recalculate the current balance when saving a payment.
- Save payment, allocation, and any generated income record in one transaction.
- Handle concurrent collections so two staff members cannot both collect an outdated remaining balance.
- A failed save must not leave a receipt or income entry without its payment.
- Use a request identifier or equivalent protection against repeated submissions.
- Do not hard-delete posted collections.
- Preserve the original amount and attribution when correcting an entry.

Proposed default: reject an amount exceeding the outstanding balance until a customer-credit policy is agreed.

### 11.4 Receipts and history

Proposed receipt output: company header, receipt number/date, customer, invoice/job reference, collected amount, method/reference, collector, and resulting invoice balance.

Each invoice should display its chronological collections and corrections. Reprinting a receipt must not create another payment.

Open: deposits before invoicing, one payment covering multiple invoices, overpayments/customer credit, refunds, reversal permissions, and payment reallocation.

## 12. Income and expenses

### 12.1 Income

Confirmed: income management is included.

Proposed operational model:

- Effective invoice collections generate linked income entries automatically.
- Issuing an invoice does not generate a cash-income entry.
- Other income can be entered manually with a category and description.
- A payment appears once in income, even when viewed through multiple reports.
- Payment reversals/refunds adjust their linked entries while preserving history.
- Invoice totals remain available as sales/billing metrics separate from cash collections.

Prevent manual editing of a payment-generated income amount; correction should originate from the payment workflow.

If a collection is categorized by invoice line type, allocations across mixed service/material invoices need an agreed rule. Do not assign the full collection to every line category. A simpler first release may use an invoice-collection category and report billed service categories separately.

The income recognition/reporting basis is a proposal requiring confirmation; this module is not specified as a full accounting system.

### 12.2 Expenses

Proposed expense fields: number, category, date, amount, currency, description, optional payee, method/account, recording user, optional responsible staff/job reference, optional attachment, and correction status.

- Support category management and filtering.
- Allow standalone company expenses and optional job-related expenses.
- Record corrections with actor, reason, and timestamp.
- Confirm the Myanmar category labels before seeding them.
- No expense approval workflow is specified.

### 12.3 Accounts and summaries

Open payment methods may include cash, bank transfer, and mobile wallets. Confirm the actual methods and named accounts before seeding.

An account balance feature requires opening balances, transfers, and correction rules; a payment-method summary alone does not establish a reliable cash/bank balance.

Reports should distinguish collections, other income, expenses, and outstanding receivables. Collections plus other income minus expenses may be shown as net recorded cash movement, not automatically labeled business profit.

## 13. Paper printing and PDF download

### 13.1 Required document outputs

Confirmed: paper printing and PDF download for quotations, preliminary invoices, and final invoices.

Proposed additional outputs: payment receipts and printable income/expense summaries.

Provide separate, clearly named actions for **Print** and **Download PDF**. Opening the browser's Save as PDF option alone does not replace the downloadable PDF requirement.

Both outputs must use the same document data, calculations, and template rules. They must exclude application navigation, editing controls, and action buttons.

### 13.2 Document presentation

Use the supplied references to establish:

- Company logo, legal/display name, address, telephone, and email.
- Document type, unique number, date, and relevant job/assignment reference.
- Customer and site details.
- Equipment and room information where applicable.
- Ordered line tables with description, unit, quantity, rate, and amount.
- Subtotals, discount, net total, and appropriate payment information.
- Remarks, terms, amount in words where required, and signature spaces.

Preliminary invoices must be clearly identifiable. Draft and void outputs should be visibly marked if their printing is permitted.

### 13.3 Quotation layout

- Support a room/equipment summary and detailed section breakdowns as demonstrated by the five-page reference.
- Repeat relevant identifiers on continuation pages.
- Show the grand total once as the payable estimate; avoid counting summary and detail rows twice.
- Keep optional equipment price-list appendices clearly separate from included charges.

### 13.4 Pagination, language, and delivery

- Handle long descriptions and multi-page tables.
- Repeat table headers and include page numbering.
- Keep totals and signature blocks readable without overlap or clipping.
- Bundle/embed appropriate Myanmar and Latin fonts for reliable PDF rendering.
- Preserve selectable text where practical.
- Use meaningful download filenames containing document type and number.
- Reprinting issued documents must preserve historical line prices and amounts.
- Downloads require the same authorization as viewing the source document.
- PDF generation errors must be visible and must not alter document/payment state.

Proposed default paper size: A4. Actual invoice paper size, portrait/landscape orientation, receipt size, margins, logo asset, signature handling, and amount-in-words language are open.

For invoices printed after collection, clearly identify paid-to-date and balance as current information. Open: whether historical original invoice output must also be downloadable alongside the current balance version.

## 14. Office application screens

Proposed navigation and screens:

1. Login and account/password management.
2. Dashboard: billed totals, collections, expenses, and outstanding balances shown separately.
3. Customers and service sites.
4. Jobs and room/equipment details.
5. Product/material catalog, units, and categories.
6. Service catalog and price matrix.
7. Quotations: list, editor, preview, history, conversion, print/PDF.
8. Preliminary invoices: list, editor, preview, history, conversion, print/PDF.
9. Final invoices: list, editor, detail, payment history, print/PDF.
10. Collections and receipt detail.
11. Other income and expense management.
12. Reports.
13. Staff/account management.
14. Company and document settings.

Lists should support relevant search, dates, status, customer, job, and staff filters, with pagination. Returning from a record should preserve filters and the current list position where practical.

## 15. Staff application screens and mobile behavior

Proposed screens:

- Login.
- Home with permitted recent work and document shortcuts.
- Customer/job lookup and creation where permitted.
- Quotations, preliminary invoices, and final invoices.
- Mobile document editor with room/equipment sections.
- Document preview, history, print, and PDF download.
- Collection form and receipts if staff collection is enabled.
- Personal collection/expense history according to the final permission policy.
- Profile, password change, and logout.

Requirements for the mobile experience:

- Avoid relying on desktop tables for entry.
- Use readable labels and touch-friendly controls.
- Provide product/service search and clear quantity/rate inputs.
- Keep totals and primary actions easy to reach.
- Preserve unsaved screen state during normal navigation where practical; warn before discarding changes.
- Show pending saves and prevent repeated taps from creating duplicate records.
- Display useful validation messages beside the affected fields.

Temporary in-memory form state is acceptable. Persistent offline drafts and an offline outbox are excluded.

## 16. Online-only PWA behavior

Confirmed: internet is required. Offline mode and synchronization are excluded.

The existing installable PWA foundation may be retained, but business operation must not depend on offline caching.

- Remove the current offline React shell/precache behavior during the relevant implementation phase.
- Provide a migration path for previously registered service workers and their app-owned caches.
- Do not cache authenticated HTML, API responses, financial data, or PDFs for offline use.
- Do not add IndexedDB/local-storage transaction queues, background sync, or persistent offline drafts.
- When disconnected, show a connection message and disable network-dependent saves.
- After an uncertain save, check the server result before retrying; never assume failure means nothing was saved.
- Keep normal online screen state available for an explicit retry where possible.
- Installation remains optional; users may use the application in a browser.

Open: one installable application with role-based navigation versus separate office/staff PWA installations. Separate installs would require distinct manifest identities, start URLs, and appropriate scope handling.

## 17. Reports and history

Proposed first-release reports:

| Report | Main content |
| --- | --- |
| Quotation history | Issued estimates, revisions, customer/job, dates, totals, and conversion references. |
| Preliminary invoice history | Status and relationship to final invoices. |
| Invoice register | Issued/void records, totals, discounts, customer/job, and payment status. |
| Outstanding balances | Invoice total, paid amount, balance, and due date if adopted. |
| Collections | Collection date, receipt, invoice/customer/job, amount, method, and collector. |
| Other income | Categories, dates, amounts, and recording users. |
| Expenses | Categories, dates, amounts, payees, and optional job/staff references. |
| Income/expense summary | Collections, other income, expenses, and net recorded movement. |

Support date ranges and relevant customer, staff, status, method/account, and category filters. State whether each report uses issue date, collection date, or expense date. Exclude void/reversed records from effective totals while retaining access to their history.

For multi-invoice jobs, aggregate each invoice once and each effective collection once. Do not add job-level summary totals to their underlying invoice totals.

Proposed audit events: account changes, price changes, document creation/issuance/revision/conversion/voiding, payment posting/reversal/refund, and income/expense corrections. Record actor, time, record reference, and relevant changes or reason.

## 18. Proposed data model

| Entity | Purpose |
| --- | --- |
| Users | Office/staff identity, role, status, and authentication. |
| Customers | Customer identity and contacts. |
| Customer sites | Service locations linked to customers. |
| Jobs | Work grouping, customer/site, responsible staff, and lifecycle. |
| Job equipment/sections | Room and equipment information. |
| Catalog categories and units | Shared classifications and measurement units. |
| Catalog items | Materials, parts, and sale equipment without stock balances. |
| Services and service rates | Services and equipment-dependent price combinations. |
| Documents | Type, number, version/status, job, header snapshots, and totals. |
| Document sections and lines | Grouped cost breakdowns with historical values. |
| Document links/billing allocations | Source conversion links and previously billed work. |
| Payments and allocations | Collected money and its application to invoices. |
| Payment corrections | Reversal/refund relationships and reasons. |
| Income entries/categories | Automatic collection entries and other income. |
| Expenses/categories | Expense entries and classifications. |
| Payment methods/accounts | Methods and optional named receiving/paying accounts. |
| Attachments | Authorized access to proofs and supporting files. |
| Audit events | Business action history and attribution. |
| Number sequences/settings | Unique numbering, branding, and template configuration. |

A shared document model is proposed because the three document types have similar structures. Separate models are acceptable if conversion references, snapshots, history, and calculations remain consistent.

Payment allocation design should allow later support for deposits or payments spanning invoices without requiring these unconfirmed features in the first release.

## 19. Technical baseline and quality requirements

### 19.1 Existing foundation

The application uses a small custom PHP framework, React mounted in a PHP view, and Vite build configuration. It is not a Laravel application.

The first-release business modules, SQLite migrations, seed settings, authenticated backend endpoints, record-level permissions, print/PDF output, and automated checks are implemented. Authentication is enforced by the application's API and document/file controllers; the unused generic starter authentication callbacks are not its account system.

Confirmed database: SQLite, shared by both applications on the server. This is not a browser-local database and does not introduce offline operation.

Proposed stack: retain PHP, React, and Vite, use SQLite through PDO SQLite, and provide authenticated backend endpoints for React. Decide route structure before feature work; suggested areas are `/office`, `/staff`, and a shared backend endpoint namespace.

The `Mini\Database` connection, configuration, migrations, and seed tooling now use SQLite. A MySQL server is not required. Money uses integer storage and financial writes use immediate transactions.

### 19.2 Security and data integrity

- Require authentication and server-side record-level permissions.
- Hash passwords and protect state-changing requests against CSRF.
- Validate inputs server-side and escape output.
- Use prepared database statements and transactions.
- Store financial amounts precisely and enforce database constraints.
- Protect PDF and attachment access; do not rely on hidden interface buttons.
- Validate uploaded file type/size and store files outside unrestricted public access where appropriate.
- Keep credentials and private application files outside the served document root.
- Use HTTPS in production and disable detailed public error output.
- Protect financial history from accidental deletion.
- Establish database/file backup and restore procedures before production use.

### 19.3 Usability and reliability

- Support Myanmar Unicode input and readable bilingual content.
- Make keyboard focus and validation feedback accessible.
- Use pagination/search for growing catalogs and history.
- Make failed saves distinguishable from completed saves.
- Expired sessions must lead to a clear sign-in/retry path without duplicate transactions.
- Verify print layouts on long documents and mobile-originated downloads.

Confirm business timezone; Asia/Yangon is proposed. Dates shown on reports and documents must use the configured business timezone consistently.

### 19.4 SQLite storage and operations

- Keep one shared database on persistent server-local storage outside `public/`; `storage/database/app.sqlite` is a proposed location. Office and staff requests use the same database.
- Configure a database file path rather than MySQL host, port, username, and password settings. Require the PHP PDO SQLite extension.
- Exclude live database files, journal/WAL sidecars, and backups from source control and public downloads.
- Enable foreign-key enforcement on every connection. Use unique/check constraints and indexes for identifiers, document links, payment references, and common filters.
- Keep financial write transactions short. For payment posting and number allocation, acquire the write transaction before reading the balance or sequence, then validate and save atomically. Use an immediate transaction or an equivalent safe strategy.
- SQLite allows one writer at a time. Configure a bounded busy timeout and handle lock contention with clear retry behavior; retain duplicate-request protection.
- Use a supported SQLite snapshot backup mechanism, such as the backup API or `VACUUM INTO`, for a live database. Back up attachments separately and test restoration. Do not treat an unsynchronized copy of an active database file as a complete backup.
- Confirm deployment storage supports SQLite locking; keep all application instances using the same supported local database arrangement.

Technical references: [SQLite data types](https://sqlite.org/datatype3.html), [transaction behavior](https://sqlite.org/lang_transaction.html), [foreign-key enforcement](https://sqlite.org/foreignkeys.html), and [live database backups](https://sqlite.org/backup.html).

## 20. Acceptance criteria

The agreed release should be accepted against the confirmed scope and the decisions resolved from Section 22.

1. Office can create and disable staff accounts; public registration is unavailable.
2. Each interface enforces the agreed permissions on the server.
3. Office can manage catalog items, units, services, and service rates without inventory features.
4. A staff user can issue customer documents without office approval.
5. A job can be invoiced without a quotation and can contain multiple invoices.
6. A multi-room quotation produces correct section subtotals and a grand total without double counting.
7. Converting a document preserves its source and historical pricing.
8. Updating catalog prices does not alter existing issued documents.
9. Paper output and downloadable PDFs work for all three customer document types.
10. Long descriptions and Myanmar text render without clipping or missing glyphs.
11. An invoice accepts several payments and shows the correct cumulative paid amount and balance.
12. Repeated submissions and concurrent collections cannot produce duplicate or excessive payments under the agreed policy.
13. Collections appear once in income; issuing an invoice does not also create duplicate cash income under the proposed model.
14. Other income and expenses can be recorded and reported separately.
15. Corrections preserve an audit trail and effective totals.
16. Job/report totals exclude superseded drafts and void/reversed transactions according to the agreed reporting rules.
17. Network loss cannot create queued offline business transactions or misleading save-success messages.
18. Existing service-worker caches are retired according to the online-only migration plan.
19. Both applications persist business records in the same server-side SQLite database and operate without a MySQL server.
20. SQLite money storage, foreign-key enforcement, lock-contention handling, and database backup/restore are verified before release.

Use the reference totals of 7,319,400 and 740,700 Kyats as calculation fixtures. Verify zero/partial/full payment cases, changed source prices, document conversion, long print tables, unauthorized access, and retry behavior.

## 21. Proposed implementation sequence

The implementation followed these work stages. Delivered behavior and the selected defaults are recorded in the implementation notes.

1. **Resolve specification decisions:** permissions, deposits, corrections, methods/accounts, printing, rounding, language, and numbering.
2. **Foundation:** SQLite connection/configuration and migration tooling, database migrations, authentication, permissions, office/staff navigation, shared UI, and online-only PWA transition.
3. **Master data:** company settings, users, customers/sites, units/categories, catalog items, services, and price matrix.
4. **Jobs and quotations:** equipment sections, document calculations, revisions, previews, paper/PDF output.
5. **Preliminary and final invoices:** conversion, multiple invoices, billing references, history, and issued-document rules.
6. **Collections:** partial payments, receipts, balances, corrections, and concurrency protection.
7. **Income and expenses:** linked collection income, manual entries, categories, reports, and agreed account handling.
8. **Release verification:** complete mobile/office flows, permissions, financial reconciliation, document rendering, backups, and deployment.

Each phase should leave a reviewable working result. Features dependent on an unresolved decision should not silently choose a business policy.

## 22. Open decisions

The following IDs preserve the original business-review questions. Defaults have now been selected for the first release; see [implementation notes](implementation-notes.md). Items such as production hosting, current prices, business wording, and any expansion beyond the documented operating limits still need operational review.

| ID | Decision | Proposed starting point / effect |
| --- | --- | --- |
| D01 | Can customers pay before a final invoice exists? | Allow job deposits only if confirmed; define allocation before implementation. |
| D02 | Can one collection cover several invoices? | One invoice per payment initially; support allocations in the design. |
| D03 | Which payment methods and named accounts are required? | Confirm cash, bank, and mobile wallet options; do not seed guesses. |
| D04 | Are account balances and transfers required? | Separate requirement from method-based collection summaries. |
| D05 | How are overpayments, refunds, reversals, and reallocations handled? | Reject overpayments until a credit policy is agreed; preserve correction history. |
| D06 | What can staff see and do financially? | Decide record visibility, collection/expense rights, discounts, price overrides, and correction permissions. |
| D07 | What is the issued-invoice correction policy? | Preserve issued records; decide revision versus void/replacement and treatment of existing payments. |
| D08 | What paper sizes and layouts are required? | A4 proposed; confirm invoice/receipt size and whether forms must match existing stationery. |
| D09 | Which languages are needed? | Confirm interface language, document language, and amount-in-words language. |
| D10 | How are currency, decimals, rounding, discounts, and taxes handled? | MMK inferred; percentage discount evidenced; other rules require confirmation. |
| D11 | Which price source is authoritative? | Verify current service/material rates before importing. |
| D12 | What are the final income/expense categories and reporting basis? | Confirm image transcription and collection-based income behavior. |
| D13 | Is the appended equipment price table informational or billable? | Keep separate until confirmed. |
| D14 | How should multiple invoices divide job work? | Decide selected quantities, fixed stage charges, or both; prevent repeated billing. |
| D15 | Are quotation acceptance, validity, and invoice due dates required? | Useful proposed fields; exact behavior remains open. |
| D16 | Is the assignment number the job number? | Confirm before printing and numbering design. |
| D17 | What are the document/receipt numbering formats? | Separate sequences proposed; prefixes and reset behavior open. |
| D18 | One installable PWA or separate office/staff installations? | Two interfaces are confirmed; installation separation is not. |
| D19 | Are payment receipts, report PDFs, and expense attachments mandatory? | Proposed supporting features beyond explicitly requested customer-document output. |
| D20 | What branding, signatures, and historical output rules are needed? | Obtain final logo/contact details; confirm signature spaces and original/current-balance invoice versions. |
| D21 | What are the hosting, backup, retention, and timezone settings? | SQLite is confirmed; confirm persistent server-local storage, PHP SQLite support, backups, retention, and operational settings before release. |

## 23. Change control

Record answers to the open decisions in this file and update the affected sections before implementation. Keep the original requirement document and references as source evidence.

When scope changes, update the version/date and distinguish newly confirmed requirements from proposals. Do not reintroduce office approval, stock tracking, offline mode, or offline synchronization, or replace SQLite with another database, without a new owner instruction.

### Revision history

| Version | Date | Change |
| --- | --- | --- |
| 1.0 | 22 November 2026 | Initial detailed discussion specification. |
| 1.1 | 22 November 2026 | Confirm SQLite; update money storage, database setup, transaction handling, backups, and acceptance criteria. |
| 1.2 | 22 November 2026 | Record the completed first-release implementation and link selected defaults, operating limits, setup, and verification notes. |
