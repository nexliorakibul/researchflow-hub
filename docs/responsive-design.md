# ResearchFlow Hub - Responsive Design

## Step 27 status

Step 27 audits and strengthens the shared public and protected layouts for desktop, tablet, and mobile screens.

## Desktop

- Fixed sidebar navigation remains visible.
- Dashboard statistics use a four-column grid where space permits.
- Multi-column cards, forms, detail panels, filters, and charts use the available width.
- Wide tables stay inside their panels and retain readable column widths.

## Tablet

- At 62rem and below, the sidebar becomes an off-canvas menu controlled by the hamburger button.
- Opening the sidebar displays an overlay, prevents background scrolling, and updates `aria-expanded`.
- Statistics, filters, forms, module cards, and comparison charts reduce their column counts at intermediate breakpoints.

## Mobile

- At 48rem and below, cards, forms, filters, detail layouts, charts, and page headings stack vertically.
- At 32rem and below, the header uses a two-column grid with full-width search beneath it.
- Form and destructive-action controls expand to usable widths.
- Tables scroll horizontally inside their own containers without causing page-level horizontal overflow.
- Pagination wraps, long messages break safely, and chart heights are reduced for narrow screens.
- Public login and registration cards fit within the viewport.

## Shared behaviour

- Both page layouts include the mobile viewport meta tag.
- Tables use touch-friendly horizontal scrolling.
- Chart.js charts use responsive sizing with aspect-ratio control disabled.
- Confirmation dialogs remain available through the shared application script.
- The sidebar can be closed with its overlay or the Escape key.
- Reduced-motion preferences disable sidebar animation.
- A local `visually-hidden` fallback keeps screen-reader text hidden even if Bootstrap CDN styling is unavailable.

## Verification

Browser rendering and computed styles were checked at:

- Desktop: 1440 × 1000.
- Tablet: 900 × 1000, including opening and closing the sidebar.
- Mobile: 390 × 900, including the application header, stacked grids, wide-table scrolling, full-width actions, alerts, and authentication card.

All tested widths had no page-level horizontal overflow.

## Step 27 checkpoint

- [x] Desktop sidebar, tables, dashboard cards, forms, and charts are usable.
- [x] Tablet sidebar and grids adapt correctly.
- [x] Mobile navigation, stacked cards, controls, messages, and horizontal tables are usable.
- [x] Public authentication pages fit mobile screens.
- [x] Step 28 security was audited separately; later checkpoints were not started by this responsive-design task.
