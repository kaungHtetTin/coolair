# Catalog imported from the reference documents

Date: 22 November 2026

The owner requested seeding the items described in `_docs`, with unclear text entries omitted. Run `php bin/console migrate` followed by `php bin/console db:seed` to install the catalog on another installation.

## Included data

| Group | Catalog items | Source |
| --- | ---: | --- |
| Materials and parts | 46 | Material price-sheet rows 1–24 and 27–48. |
| Paired flare nuts | 1 | Handwritten invoice, line 9: 1/4" × 3/8", 12,000 MMK per listed unit. |
| Core services | 4 | Installation, servicing, removal, and removal with reinstallation from both service sheets. |
| Other repair services | 4 | Readable English repair entries on the income/expense reference. |
| Wall hacking for PVC pipe | 1 | Quotation PDF, page 2, living-room line 8. |
| Refrigerants | 3 | R-22 at 42,000; R-410a at 45,000; R-32 at 48,000 MMK/Lb. |
| Transportation | 3 | Lewe Township at 100,000; Zeyarthiri Township at 75,000; Tatkon Township at 100,000 MMK/trip. Names use English transliterations. |
| Mitsubishi equipment | 4 | PDF page 5: MS-JS10 / MU-JS10; MS-JS13 / MU-JS13; MS-JS18 / MU-JS18; MS-JS24 / MU-JS24. |
| **Total catalog items** | **66** | |

There are **152 service rates**: two inverter classifications × 19 equipment/horsepower combinations × four services. The application labels are used exactly so its rate lookup can select the imported rows:

- Split (wall mounted): 1, 1.5, 2, 2.5, and 3 HP.
- Floor stand: 2, 3, 4, and 5 HP.
- Ceiling cassette: 2, 3, 4, 5, and 6 HP.
- Floor ceiling (suspended): 2, 3, 4, 5, and 6 HP.

The service catalog's four base prices use the non-inverter, wall-mounted, 1 HP row. Equipment-specific selections use the service-rate matrix instead.

Readable English income categories are also inserted if missing. Existing English expense categories and other settings remain unchanged.

## Source priority and interpretation

- Dedicated material and service price sheets supply the defaults where the sample quotation/invoice has a different rate for the same identifiable item. For example, paired 1/4" × 3/8" copper uses 25,500 MMK/Ft from the material sheet rather than the PDF's 26,000.
- The inverter sheet's 5/6 HP suspended-unit servicing price is retained as printed: 87,000 MMK, even though it exceeds the listed installation price.
- The wall-hacking rate is printed as `65,00` in the PDF. Its quantity is 30 Ft and amount is 195,000 MMK, so the exact derived unit rate is 6,500 MMK/Ft. This derivation is recorded with the source.
- Four other repair services have readable names but no source prices: check/test/repair, refrigerator repair, washing-machine repair, and air/water-cooler repair. They have a zero placeholder and an explicit specification instructing users to enter the job's quoted charge. `Job` is a practical billing unit for these entries, not a transcribed source unit.
- Printed spelling is normalized for recognizable standard part names, such as flare nuts, without changing sizes, units, or prices. Grouped capacitor ratings remain grouped entries rather than invented separate price variations.
- Quotation installation and material bundles are represented by the existing services/materials. Room names and repeated charges are not separate sale items. Insulation, packing tape, and sockets/elbows included in a priced pipe bundle are not invented as separately priced items.
- Unspecified safeguard types in the quotation are not imported as extra ambiguous variants; the four clearly identified Digital/Metronic variants are included from the material sheet.
- The CHIGO equipment description on the handwritten invoice identifies equipment being serviced and does not supply a sale price. It is not imported as a priced equipment product.

## Skipped unclear text

- Material-sheet rows **25 and 26**, where the Myanmar descriptions distinguish different 3-pin parts but are not clear enough to name confidently.
- Myanmar expense labels on the income/expense photo; existing editable English expense categories are retained.
- The final Myanmar income entry on that photo.
- No unclear customer handwriting, serial values, or document totals are turned into catalog entries.

## Safe reruns and traceability

The transcription is in `database/catalog-data.php`; import logic is in `database/catalog-seed.php`.

- Each source item and service-rate combination has a stable source key.
- An exact existing item identity (kind/name/specification/unit) is adopted without changing its category, price, or archived state.
- An existing service-rate combination is adopted without changing its price.
- Subsequent runs follow source mappings, preserving renamed items, edited rate dimensions/prices, and availability instead of recreating original entries.
- New source items/rates are inserted transactionally. A repeated run with no additions creates no duplicate catalog records or audit events.
- `catalog_sources` stores file/row provenance and interpretation notes. `service_rate_sources` stores the source sheet for each seeded rate.
- The import creates no staff accounts, customers, jobs, documents, payments, income transactions, or expenses.

The dedicated seed test checks counts, sample rates, source identities, omission of unclear rows, repeatability, and preservation of existing office edits.
