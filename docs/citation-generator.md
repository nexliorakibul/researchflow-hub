# ResearchFlow Hub - Citation Generator

## Step 22 status

Step 22 generates citations directly from an authenticated user's saved paper metadata. Citations are derived output and are not stored in a duplicate database table.

## Included styles

- IEEE.
- APA.
- Harvard.

The generator appears on each owned paper's detail page and provides style selection, a read-only preview, and a copy button.

## Metadata handling

The generator uses only saved authors, title, publication year, venue or publisher, volume, issue, pages, DOI, and URL values. Optional fields are omitted when blank. It never inserts an unknown author, guessed date, fabricated venue, or fake identifier. A warning identifies missing core fields: authors, publication year, and venue or publisher.

DOIs are displayed directly in IEEE and Harvard output. APA output normalizes a saved DOI into a `https://doi.org/` link. The paper URL is used in APA only when no DOI is saved; IEEE and Harvard may include both saved DOI and URL values.

## Security and usability

- Authentication and paper ownership are enforced by the existing paper detail route.
- Stored metadata and generated citations are HTML-escaped.
- Citation generation is read-only and requires no database mutation or CSRF token.
- Copy feedback uses an accessible live region and includes a manual-copy fallback.
- The generator is responsive for desktop, tablet, and mobile layouts.

## Step 22 checkpoint

- [x] IEEE, APA, and Harvard citations are generated from saved paper metadata.
- [x] Style selection updates the citation preview.
- [x] The selected citation can be copied.
- [x] Missing metadata is omitted and clearly identified.
- [x] Other users' papers remain inaccessible.
- [x] Global Search and later modules were not implemented.
