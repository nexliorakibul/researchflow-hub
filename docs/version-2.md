# ResearchFlow Hub — Version 2

Version 2 extends the existing private paper library with scholarly metadata discovery and portable reference exports. It does not add collaboration, AI, ORCID, citation graphs, file uploads, or Version 3+ functionality.

## Delivery order

1. Shared scholarly API client and environment configuration.
2. Crossref DOI lookup, preview, and import.
3. OpenAlex search, result preview, and import.
4. Duplicate detection for manual and API-created papers.
5. Single-paper and full-library BibTeX/RIS export.
6. Security, responsive layout, syntax, and regression verification.

## Pages

- `modules/papers/import-doi.php` — authenticated Crossref DOI lookup and import preview.
- `modules/papers/discover.php` — authenticated OpenAlex search and results.
- `modules/papers/import-openalex.php` — CSRF-protected, POST-only OpenAlex import.
- `modules/papers/export.php` — authenticated BibTeX or RIS download for one owned paper or the full owned library.

## Configuration

The APIs work without committed credentials. The following optional environment variables improve identification, capacity, and operational control:

```text
RFH_API_CONTACT_EMAIL=maintainer@example.com
RFH_OPENALEX_API_KEY=optional-key
RFH_API_TIMEOUT=8
```

Do not add real API keys to Git. Configure them in the deployment provider's secret environment settings.

## Import behavior

- DOI values are normalized before validation, matching, and storage.
- Crossref and OpenAlex metadata is fetched by the server from fixed HTTPS API hosts.
- Imported metadata is mapped into existing `papers` fields; no Version 2 database migration is required.
- A Crossref record is previewed before the user confirms import.
- An OpenAlex result is fetched again by its validated work ID before it is saved.
- Project ownership and reading-status validation are reused from the existing Papers module.

## Duplicate rules

An import or manual save is blocked when the current user already owns:

- a paper with the same normalized DOI; or
- a paper with the same normalized title and publication year.

Users are isolated by `user_id`, and editing a paper ignores that paper's own ID during duplicate checks.

## API safeguards

- HTTPS and hostname allowlists prevent arbitrary server-side URL requests.
- Requests have a bounded timeout and response size.
- Per-session request limits reduce third-party API abuse.
- Short-lived filesystem caching reduces unnecessary third-party requests.
- API errors are converted to safe user messages.
- Optional API identifiers and keys remain server-side.
- Imported values still pass normal server-side paper validation.

## Export behavior

- BibTeX downloads use `.bib` and `application/x-bibtex`.
- RIS downloads use `.ris` and `application/x-research-info-systems`.
- Exports are generated only from records owned by the authenticated user.
- Empty libraries redirect safely with a flash message.

## Acceptance checklist

- [x] A valid Crossref DOI can be looked up, previewed, and imported.
- [x] OpenAlex can be searched by title, topic, author, or keyword.
- [x] A selected OpenAlex result can be imported through a POST-only, CSRF-protected action.
- [x] Duplicate DOI and title/year records are rejected.
- [x] Existing manual create and edit flows use the duplicate rules.
- [x] One paper can be exported as BibTeX and RIS.
- [x] The complete owned paper library can be exported as BibTeX and RIS.
- [x] No API credential is stored in the repository.
- [x] Version 1 paper CRUD, ownership checks, and citation generation remain available.
