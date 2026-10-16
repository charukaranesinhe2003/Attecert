# Scoped admin accessibility review

15 October 2026. This is a code, rendered-markup and color-pair review of the changed admin surfaces, with earlier browser observations clearly separated. It is not a full WCAG conformance evaluation.

## Criteria and scope

The review uses the W3C guidance for [text contrast](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), [keyboard operation](https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html), [reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html) and [labels/instructions](https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html). Normal text requires at least 4.5:1 contrast under the referenced text criterion; the values below are calculations from source colors rather than rendered pixel measurements.

Changed surfaces include the staff layout/navigation/search, case section tabs/progress, document filters/release controls, reports, payment/refund forms, settings and operational lists. Dynamic public-site copy and every historical template are outside this review's claimed coverage.

## Implemented and checked

| Area | Evidence | Status |
| --- | --- | --- |
| Case section relationships | AdminAccessibilityTest renders a real case view and verifies tablist, selected tab, unique IDs, controls/labelledby pairs, panel hidden state and one initial keyboard entry point. | Automated rendered DOM checks pass. |
| Tab keyboard implementation | admin.js handles Left/Right with wrap, Home/End, activation, focus and selected state. It also handles section links and URL hashes. | Source reviewed and syntax checked; fresh browser key execution blocked. |
| Mobile menu and search | Existing skip link, expanded state, Escape/focus restoration, open-menu Tab containment and asynchronous search status/error handling are retained. | Earlier phase browser checks passed; follow-up source reviewed. |
| Documents | Named filters, labelled confirmations, table caption/column headers, unique IDs and permission-dependent actions are asserted against real rendered views. | Automated rendered DOM checks pass. |
| Reports | Date-range 422 responses, labels, linked alert errors and outstanding-table caption/headers are asserted. | Automated rendered DOM checks pass. |
| Payment/refund forms | Failed fields and aria-invalid/error links apply only to the matching form/invoice. Notes are escaped; unrelated invoice input is ignored. | Automated rendered DOM checks pass. |
| Settings/lists | Grouped fieldsets, label relationships, retained errors, responsive data labels, counts, empty states and bounded pagination are present. Malformed input is regression-tested. | Source and integration checks pass; fresh visual inspection pending. |
| Focus and motion | 3px focus-visible outline, white sidebar focus outline and reduced-motion overrides are defined in admin.css. | Source reviewed. |
| Responsive rules | Main content uses min-width:0; layout collapses at 1100/1000/900/640px, forms become single-column and tables expose per-cell labels. | Source reviewed; current viewport/zoom matrix pending. |

AdminAccessibilityTest passed **5 tests, 104 assertions** as part of the complete 112-test run. These assertions inspect structural evidence; they do not replace assistive-technology testing.

## Calculated color pairs

A standalone Node calculation used sRGB relative luminance and the contrast formula on the literal CSS colors. All listed text pairs exceed 4.5:1.

| Pair | Foreground / background | Ratio |
| --- | --- | ---: |
| Muted text on card | #52677c / #ffffff | 5.85:1 |
| Muted text on page | #52677c / #f5f7f6 | 5.44:1 |
| Secondary text | #4d6278 / #ffffff | 6.30:1 |
| Success badge | #145c3d / #e5f6ef | 7.14:1 |
| Warning badge | #754a08 / #fff4df | 7.03:1 |
| Danger badge | #8b2222 / #fdecec | 7.82:1 |
| Navigation headings | #afc8dc / #061f35 | 9.65:1 |
| Primary button | #ffffff / #0f776e | 5.41:1 |
| Focus outline against white | #0c655e / #ffffff | 6.91:1 |

Inherited backgrounds, hover/disabled states, translucent surfaces, all status combinations, borders and rendered font sizes were not exhaustively measured. These results only support the named pairs.

## Browser evidence and limitation

During the first implementation phase, a disposable preview was checked at desktop and narrow viewports for login, navigation, live search under CSP, document queue/release confirmation and reports; no console errors were observed. The later UI additions were not all present at that time.

The follow-up preview started successfully on an isolated database. The browser tool then refused the navigation because automatic approval review could not complete after an account usage limit. No alternate browser or indirect automation was used. The preview was stopped and its database/storage cleaned up. There are no new screenshots or fresh rendered visual claims from that blocked attempt.

Before claiming conformance, exercise the updated screens at desktop/tablet/320px, 200%/400% zoom, keyboard-only and with a screen reader. Check overflow and focus visibility on every panel, mobile menu focus order, search announcements, error discovery, native date inputs, Sinhala/Tamil line wrapping and actual computed contrast for all states.
