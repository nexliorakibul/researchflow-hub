# ResearchFlow Hub — Version 3

Version 3 turns the existing paper and dataset libraries into a connected research-discovery workspace. It preserves the single-user-owned-record security model: every saved paper and dataset remains isolated by `user_id`.

## Features

- **Scholarly insights:** Semantic Scholar citation totals, influential citation totals, references, citing papers, and related-paper recommendations, with an OpenAlex fallback when the anonymous Semantic Scholar service is rate-limited.
- **Open-access finder:** Unpaywall DOI lookup when `RFH_API_CONTACT_EMAIL` is configured, with Semantic Scholar public-PDF fallback.
- **Research code links:** a validated code-repository URL on each paper plus a title-based GitHub repository search.
- **DataCite datasets:** public dataset search, server-side result verification, duplicate prevention, and import into the existing Dataset Manager.
- **ORCID identity:** optional OAuth `/authenticate` connection, disconnect, and sign-in for accounts that have already linked an ORCID iD.

Version 3 does not scrape websites, download paper PDFs, store large datasets, generate AI summaries, or introduce collaboration.

## Pages

- `modules/papers/insights.php` — live scholarly impact, related research, open-access, and code discovery.
- `modules/datasets/discover.php` — DataCite dataset search and preview.
- `modules/datasets/import-datacite.php` — POST-only, CSRF-protected dataset import.
- `orcid-start.php` — creates a short-lived OAuth state and starts ORCID authorization.
- `orcid-callback.php` — validates state, exchanges the authorization code, and connects or signs in.
- `orcid-disconnect.php` — authenticated, POST-only ORCID disconnect.

## Existing-database migration

Run `database/version-3-migration.sql` once before deploying Version 3 over a Version 1/2 database. Fresh installations should use `database/schema.sql`, which already contains the Version 3 columns.

The migration was verified with local MySQL 8.0.46 and the live MySQL-compatible TiDB 8.5.3 database without deleting or replacing existing records.

The migration adds:

- `users.orcid_id`, with a unique constraint;
- `papers.code_url`.

## Optional environment variables

```text
RFH_API_CONTACT_EMAIL=maintainer@example.com
RFH_SEMANTIC_SCHOLAR_API_KEY=optional-key
RFH_ORCID_CLIENT_ID=APP-...
RFH_ORCID_CLIENT_SECRET=private-secret
RFH_ORCID_REDIRECT_URI=https://example.com/orcid-callback.php
```

Crossref, OpenAlex, DataCite, and unauthenticated Semantic Scholar access continue to work without committed credentials. Unpaywall requires a valid contact email. ORCID controls remain hidden or disabled until all three ORCID OAuth variables are configured. Secrets must be stored only in local or deployment environment settings.

## Security and reliability

- Every Version 3 page requires the same protected session and record-ownership checks as Version 1.
- Imports use POST, CSRF validation, server-side API refetching, validation, and prepared statements.
- External requests use fixed HTTPS host allowlists, bounded timeouts and response sizes, caching, and per-session rate limits.
- ORCID uses a random, short-lived state value and never stores the access token returned during authentication.
- External URLs are validated and output is escaped.
- API failures are converted to safe user-facing messages.

## Acceptance checklist

- [x] An owned paper can store and open a code-repository URL.
- [x] An owned paper can display Semantic Scholar impact metrics, citations, references, and recommendations.
- [x] A legal open-access location is shown when a supported provider returns one.
- [x] DataCite dataset metadata can be searched, previewed, re-fetched, validated, and imported.
- [x] Duplicate dataset name or URL records are rejected per user.
- [x] ORCID OAuth connection and sign-in are available when deployment credentials are configured.
- [x] ORCID disconnect is authenticated, CSRF-protected, and POST-only.
- [x] No API key, OAuth secret, database password, or access token is committed.
- [x] Version 1 and Version 2 workflows remain available.
