# Staff access, document creators, and operation history

Updated: 22 November 2026

## Access rules

| Resource | Office | Staff |
| --- | --- | --- |
| Customers | Read and manage all | Read all; create and edit customers they created |
| Jobs | All jobs; assignment control | Jobs they created or are currently assigned to |
| Customer documents | All documents | Documents on an accessible job that they created, or documents on a job currently assigned to them |
| Record history | All actors | Their own operations only |
| Activity register | Company-wide; filter/search by acting user | **My activity**, restricted to their user ID |
| Finance | Full permitted office functions | No access; the previous office-only Finance decision remains in force |

These restrictions apply on the server to lists, dashboard counts, lookups, direct record URLs, source/conversion access, related-document links, and print/PDF routes. Hiding a navigation item is not the permission check.

A shared customer does not grant access to that customer's other jobs or documents. A staff user may reference an existing read-only customer when creating their own job, without changing the customer record. Customer creation ownership is recorded by the server and does not change when someone edits a customer.

Staff cannot reassign a job by changing a request body. Office users control assignment. Editing an existing job as staff preserves its assignee. If staff created a job later assigned to someone else, they retain the job and their own documents, but cannot see documents created by the other staff member merely because they created the job.

Documents on office-created jobs reassigned away from a staff member are no longer in that member's accessible job scope. Their own historical activity remains visible in **My activity**; this does not grant access to another staff member's current operations.

## Visible attribution

- Customer and job lists show the creator's name and username.
- Customer details show creation ownership, read-only/edit permissions, and permitted history.
- Quotation, preliminary invoice, and final invoice lists show **Created by @username**. Office can filter documents by creator.
- Document details show the original creator and the history of permitted operations.
- Job details show creator, assigned staff, and permitted job history.
- Office activity can be searched by username, operation, record, or changes and filtered by acting user.
- Staff **My activity** cannot be changed into another user's activity feed by passing a different user ID.

## Stored history

Each new audit entry stores:

- Acting user ID.
- Username and display-name snapshots at the time of the operation.
- Operation, record type, record ID, and UTC timestamp.
- Relevant before/after values, reason, document version, or linked record references.

Customer, job, and document records also store immutable creator-name and creator-username snapshots. Renaming an account does not rewrite the displayed historical creator or previous audit actors. New actions after a rename use the new username.

Recorded operations include successful creation/updates, job reassignment, document conversion/revision/issuance/voiding, customer/job/document detail views, opening print views, serving PDFs, collections/allocations/reversals, ledger posting/reversal, attachment upload/download, settings/catalog/rate/category/method/account updates, password changes, sign-in/sign-out, failed sign-in attempts, and requested report exports. Document history also links deposit allocation and payment reversal events to the invoice.

Print events mean that a print view was opened, not proof that a physical printer completed printing. PDF/download/export events describe the application request, not proof that the recipient opened or retained the file.

Passwords, password hashes in user snapshots, CSRF tokens, and request-idempotency keys are removed from audit details. Background dropdown searches, bootstrap polling, and viewing the audit register itself are not logged as business operations; this avoids an activity entry per keystroke or a recursively growing history feed. Anonymous or console/system actions are identified as **System**, rather than falsely attributed to a staff user. An attempted login username is stored separately from the verified actor identity.

Record detail pages show the latest 100 permitted events, with a link to the paginated full activity register for that record. Audit records are retained; there is no application endpoint for staff to edit or delete them.

## Existing data and migration

Migration `004_actor_history.php` adds actor and creator snapshots and backfills existing entries from their linked user accounts. A backup is taken before applying it to the live database.

Older entries retain their original timestamps and recorded details. Usernames used before this migration cannot be reconstructed if they had already changed, and missing before/after data from earlier versions is not invented. After migration, new snapshots preserve the username/name used at the time of each operation.

## Verification

- `tests/staff-scope.php`: unrelated job/document denial, matching list/detail scopes, related-link filtering, staff/office history scope, immutable attribution after account renaming, and secret redaction.
- `tests/staff-permissions-checks.mjs`: two staff accounts, customer owner edits/shared reads, forged reassignment denial, direct URL/PDF restrictions, own activity, office actor filtering, creator labels, and mobile record/history layout.
- The existing business, lookup, concurrency, document/payment, and print/PDF suites remain in place.
