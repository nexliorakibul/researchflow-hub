# ResearchFlow Hub

An integrated research workspace for organizing projects, academic resources, papers, datasets, literature reviews, research gaps, experiments, tasks, notes, and citations.

## Current status

Step 1 scope is documented. Step 2 provides the initial repository structure and Git configuration. Environment setup, application features, database tables, and deployment have not started.

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

See [the complete requirements](docs/requirements.md) for scope and acceptance criteria.

## Repository structure

```text
assets/
  css/
  js/
  images/
config/
database/
docs/
  requirements.md
includes/
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

Installation and database setup instructions will be completed in later steps.

## Security

Never commit real credentials, database passwords, API keys, tokens, or other secrets. Private environment and database configuration files are ignored. A credential-free `config/database.example.php` may be added later.
