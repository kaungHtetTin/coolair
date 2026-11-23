# Myanmar and English localization

The two-button English/Myanmar language toggle is available on sign-in/setup and in the workspace top bar for both office and staff accounts. English is the default. Switching languages updates the current screen without reloading or discarding form edits. The selection is saved per installation in browser local storage and a one-year, path-scoped preference cookie. Tabs in the same browser follow preference changes.

## Coverage

- Navigation, page headings, forms, searchable selectors, actions, confirmation dialogs, status/type labels, empty/loading states, notifications and connection messages.
- Browser form validation and server validation, authentication, permission, upload and session-expiry errors.
- Actor-history action names, field labels and machine status/type values. Original usernames, reasons and before/after records remain intact.
- Finance reports and CSV column headings, system collection categories and type labels. CSV remains UTF-8 with a BOM.
- Quotation, preliminary/final invoice and receipt print views and PDFs, including signatures, currency wording and page footers.
- Installable app manifest description and language. The app remains online-only.

Customer names, addresses, job titles, entered descriptions, company details, document terms, catalog contents, configurable payment-method/category names and usernames are business records. They display as entered rather than being automatically translated. Identifiers, enum values, dates submitted to the API, prices, financial calculations, database records and audit snapshots are independent of interface language. A dropdown displays a translated label while retaining its canonical submitted value.

## Implementation

`resources/lang/my.json` contains interface translations keyed by English source text. `resources/lang/messages.my.json` adds server messages, print labels and audit/report field names. English uses the source text; placeholders such as `{name}` and `{count}` are interpolated in both languages. Keep identical placeholder names in each translation.

`resources/js/i18n.jsx` owns the preference, reactive subscriptions, `tr(source, params)`, enum labels, language picker and localized native-validation messages. Add new presentation text through `tr`; keep business values and route/status keys separate. Module-level menus and equipment option values remain canonical and are translated when rendered. Give every translated HTML option an explicit original `value`.

`App\Services\Locale` reads a supported `lang` query parameter, then `X-App-Locale`, then the preference cookie, defaulting to English. Invalid values fall back to English. PHP templates use `__(source, params)`. API/upload errors include both localized `error` and stable `error_source`; the browser renders the source in its current language. Existing interpolated validators are supported by explicit templates in both translators.

Print links include `lang=en` or `lang=my`, so open print tabs and PDF downloads retain their chosen language. The browser and PDF use the existing embedded Myanmar fonts. Myanmar PDFs use Myanmar amount-in-words; stored document snapshots are never rewritten by a language switch. `/manifest?lang=...` localizes installation metadata with the existing stable app identity and icon paths.

## Verification

`npm test` includes translation completeness for static domain errors, placeholder parity, preference precedence, interpolated validators and English/Myanmar amount-in-words. `npm run test:e2e` checks language selection before sign-in, persistence, switching a filled form, canonical equipment option values, consistent control heights, translated errors, responsive/dark layouts, staff navigation restrictions, and Myanmar paper/PDF output. Test accounts and records use isolated databases under `tests/artifacts/`.
