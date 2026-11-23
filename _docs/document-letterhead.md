# Document letterhead

Quotations, preliminary invoices, final invoices and payment receipts use the supplied A4 letterhead at `public/branding/letterhead.jpg`. This is an unchanged copy of `photo_2026-11-23_16-14-51.jpg`: its blue/red company heading, services line, faint logo watermark and green contact footer are part of the artwork. Document titles, charges, customer details, totals, signatures and payment information remain selectable text over the artwork and follow the selected English/Myanmar locale.

The content area reserves 43 mm above and 24 mm below, with 12 mm side margins. The existing company header is omitted to avoid duplicating the letterhead. The letterhead does not change stored company settings, issued document snapshots or financial records. Its printed contact details are those in the supplied image; replacing the letterhead image updates that visual information for future print/PDF requests.

The server PDF places the complete image behind every A4 page at 210 × 297 mm. Its watermark opacity stays at 100% because the source already contains the intended faint central logo. Page numbers sit above the contact footer. Normal table headers repeat when tables continue onto another page.

The browser view presents a continuous A4-width preview with the header above and contact footer below the document. During paper printing, a fixed image repeats the complete letterhead on each page. A presentation table with repeating empty header/footer areas reserves the same safe content space while zero page margins let the artwork cover the sheet. The image is a print element, so the letterhead also prints with browser background graphics disabled. Choose A4 and disable the browser's own headers/footers in the print dialog for the intended output. The PDF download provides the most consistent pagination across browsers and printers.

The browser suite verifies both the server-generated PDFs and Chromium paper-print PDFs, including a large quotation. English/Myanmar rendering and receipts remain covered by the existing suite. Local PDF/screenshot examples are written under `tests/artifacts/` using isolated test records.
