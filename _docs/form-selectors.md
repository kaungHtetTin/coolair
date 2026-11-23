# Searchable form selectors

Growing record lists use the shared `SearchSelect` control through `Select` in `resources/js/shared.jsx`. Small fixed enumerations retain native selects.

## Updated fields

| Form | Searchable field | Lookup source |
| --- | --- | --- |
| Job editor | Customer | Customers, including name/phone/address search |
| Job editor | Assigned to | Active office/staff accounts |
| Service-rate editor | Service | Active service catalog items |
| Quotation/preliminary/final editor | Job | Accessible jobs and customer details |
| Document charge line | Catalog item | Active items, specifications, categories, and units |
| Collection/deposit form | Job; receiving method/account | Accessible jobs; active methods |
| Deposit allocation | Final invoice | Issued unpaid invoices from the payment's job |
| Income/expense form | Category; method/account; optional job | Categories for the selected entry type; active methods; accessible jobs |
| Financial report filters | Payment method; recorded by | Methods and staff, including inactive records for historical filtering |

Status, role, active/inactive, item type, equipment type, entry type, and inverter classification are fixed lists and remain normal selects. Free-text units and catalog category fields retain their existing behavior.

## Behavior

- Search is performed on the server with a short debounce. The endpoint returns at most 25 options per page.
- Scroll or use **Load more** to retrieve subsequent pages; the dropdown does not render the full data set at once.
- Option descriptions distinguish customers with similar names and item variants such as different pipe sizes. The submitted value is the record ID or configured category/method name.
- Existing values are resolved by ID independently of the current results page. Opening, searching, cancelling, and refocusing do not discard a committed selection.
- Typing does not create a record or submit a label as an ID. Required controls need a selected value. Optional controls can be cleared.
- Arrow keys, Home/End, Enter, Escape, and Tab support keyboard operation. Escape closes the dropdown before closing a surrounding dialog.
- Loading, no matches, request failure, retry, disabled, selected, and focus states are provided.
- Menus render outside dialog scroll containers, retain the current theme, and fit the visible viewport.
- Customer searches allow staff to read all shared customers. Job searches and selected-ID resolution remain limited to accessible jobs; sharing a customer grants no access to another staff member's work. Staff cannot edit another creator's customer. Password hashes are excluded from user lookup payloads.
- Finance selectors (invoice allocation, payment methods, and income/expense categories) are office-only, matching the restricted Finance section. Staff continue using customer/job/catalog selectors for document work.
- Cancelled jobs and archived catalog items are excluded from new selections. Existing selected records can still be identified, and historical report filters may include inactive methods/users.

## Consistent dimensions

Searchable controls use the same `--control` token, borders, padding, text size, grid width, and focus treatment as ordinary inputs. The selected label cannot expand a form column.

| Mode | Control height |
| --- | ---: |
| Compact desktop | 34 px |
| Comfortable desktop | 43 px |
| Mobile | 42 px |

The dropdown has bounded height and scrolls independently. Long labels and descriptions wrap inside the option menu rather than widening the input.

## Reuse

```jsx
import { Select } from './shared';

<Select
  label="Customer"
  resource="customers"
  value={form.customer_id}
  required
  onChange={event => setForm({ ...form, customer_id: event.target.value })}
/>

<Select
  label="Service"
  resource="catalog"
  params={{ kind: 'service' }}
  value={form.catalog_id}
  onChange={(event, option) => {
    // option.record contains the selected source record, including service rates.
    setForm({ ...form, catalog_id: event.target.value });
  }}
/>
```

`Select searchable` also supports a finite list of ordinary `<option>` children for smaller reusable local controls. Use `resource` for potentially unbounded data. `SearchSelect` is exported directly for callers that supply structured `options`.

The authenticated GET endpoint is `api/lookup`. Parameters include `resource`, `q`, `page`, optional `id` for selected-value resolution, and resource-specific `kind`, `job_id`, or `active_only`. Responses contain `rows`, `total`, `page`, `per_page`, and `has_more`. Each row contains `value`, `label`, `description`, `selectedLabel`, `disabled`, and a permitted `record` payload.

Lookup data is not stored for offline use. Existing master-register/context data in bootstrap is unchanged; the new selectors query the paged endpoint independently of those arrays.

## Verification

`tests/lookups.php` covers pagination, phone/specification search, ID hydration, literal wildcard input, permissions, archive handling, private fields, and invoice pages beyond the first 25. `tests/search-select-checks.mjs`, called by the browser suite, uses 65 extra customers in an isolated test database to verify later pages, keyboard use, required selection, clear, retry, dark theme, modal/mobile bounds, and matching input dimensions.
