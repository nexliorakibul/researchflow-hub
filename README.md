# ResearchFlow Hub

An integrated research workspace for organizing projects, academic resources, papers, datasets, literature reviews, research gaps, experiments, tasks, notes, and citations.

Repository: <https://github.com/nexliorakibul/researchflow-hub>

## Current status

Version 1 and Version 2 are complete and deployed. Version 3 adds scholarly impact insights, open-access discovery, direct code links, DataCite dataset import, and optional authenticated ORCID connection/sign-in.

Live site: <https://researchflow-hub.onrender.com>

## Planned technology stack

PHP 8+, MySQL 8+, HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js, and PDO with prepared statements.

## Version 1 scope

The only main role is **Researcher / User**; users manage only their own records.

- Registration, login, logout, protected sessions, user profile, and password change.
- Dashboard and research project workspaces.
- General research resources, papers, and dataset metadata.
- Literature reviews and the literature review matrix.
- Research gaps and gap statistics.
- Experiments and experiment comparison.
- Research tasks and notes.
- IEEE, APA, and Harvard citation generation.
- Global search, filters, sorting, and pagination.
- Desktop, tablet, and mobile layouts.
- Security, validation, error pages, flash messages, empty states, and confirmation dialogs.

## Version 2 additions

- Crossref DOI metadata lookup, preview, and import.
- OpenAlex scholarly paper search and selected-result import.
- Per-user duplicate detection using normalized DOI or title and publication year.
- Single-paper and full-library BibTeX/RIS downloads.
- Allowlisted HTTPS API access, timeouts, response limits, caching, and safe error messages.

See [Version 2 implementation and configuration](docs/version-2.md).

## Version 3 additions

- Semantic Scholar citation counts, references, citing papers, and related-paper recommendations.
- Open-access PDF discovery through Unpaywall with Semantic Scholar fallback.
- Saved code-repository links and GitHub code discovery for papers.
- DataCite public dataset search, duplicate prevention, and metadata import.
- Optional authenticated ORCID connection and sign-in using OAuth 2.0.
- A documented one-time database upgrade path for existing installations.

See [Version 3 implementation, migration, and configuration](docs/version-3.md).

See [the complete requirements](docs/requirements.md) for scope and acceptance criteria.

Database references:

- [Database design and relationships](docs/database-design.md)
- [MySQL 8+ schema](database/schema.sql)
- [Database import and verification](docs/database-setup.md)
- [PDO connection setup](docs/database-connection.md)
- [Application and security foundation](docs/application-foundation.md)
- [Authentication implementation](docs/authentication.md)
- [Dashboard and application layout](docs/dashboard-layout.md)
- [Research Projects CRUD and workspace](docs/research-projects.md)
- [General Research Resources](docs/research-resources.md)
- [Research Papers](docs/research-papers.md)
- [Dataset Manager](docs/dataset-manager.md)
- [Literature Reviews](docs/literature-reviews.md)
- [Literature Review Matrix](docs/literature-review-matrix.md)
- [Research Gaps](docs/research-gaps.md)
- [Research Gap Statistics](docs/research-gap-statistics.md)
- [Experiments](docs/experiments.md)
- [Experiment Comparison](docs/experiment-comparison.md)
- [Research Tasks](docs/research-tasks.md)
- [Research Notes](docs/research-notes.md)
- [Citation Generator](docs/citation-generator.md)
- [Global Search](docs/global-search.md)
- [Filters, Sorting, and Pagination](docs/filters-sorting-pagination.md)
- [User Profile](docs/user-profile.md)
- [Password Change](docs/password-change.md)
- [Responsive Design](docs/responsive-design.md)
- [Security Audit and Hardening](docs/security-audit.md)
- [Error Handling and User Feedback](docs/error-handling.md)
- [End-to-End Testing and QA](docs/testing-qa.md)
- [Project Report and Screenshot Evidence](docs/project-report.md)
- [Git Delivery Status](docs/git-delivery.md)
- [Live Deployment](docs/deployment.md)

## Repository structure

```text
assets/
  css/
    app.css
  js/
    app.js
  images/
config/
  app.php
  database.example.php
database/
docs/
  requirements.md
includes/
  app-layout.php
  auth.php
  bootstrap.php
  csrf.php
  database.php
  error-handler.php
  functions.php
  security.php
  validation.php
errors/
  403.php
  404.php
  500.php
modules/
  projects/
  resources/
  papers/
  datasets/
  literature-reviews/
  research-gaps/
  experiments/
  tasks/
  notes/
screenshots/
.gitignore
README.md
```

Empty scaffold folders contain `.gitkeep` files so Git preserves the structure.

## Installation

Database import and PDO connection details are documented in `docs/database-setup.md` and `docs/database-connection.md`. Feature setup will be completed in later steps.

## Security

Never commit real credentials, database passwords, API keys, tokens, or other secrets. Private environment and database configuration files are ignored; use the tracked credential-free `config/database.example.php` as the template.
