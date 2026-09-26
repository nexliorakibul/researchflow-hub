# Live Deployment

Checked: 2026-09-26. **Step 34 status: PASS.**

ResearchFlow Hub is live at <https://researchflow-hub.onrender.com> using a Render Docker web service and a TiDB Cloud Starter database. TiDB is MySQL-compatible and supports the existing PDO application without changing the project data model.

## Deployment files

- `Dockerfile` provides PHP 8.3, Apache, PDO, and the MySQL driver.
- `.dockerignore` prevents local configuration, credentials, reports, screenshots, and development files from entering the image.
- `render.yaml` defines a free Singapore-region web service and prompts for database credentials instead of storing them in Git.
- `config/database.example.php` supports a verified TLS connection for the hosted database.

## Database

1. Create a free TiDB Cloud Starter instance with spending limit `0`.
2. Create or select the `researchflow_db` database.
3. Import `database/schema.sql`.
4. Keep the generated host, port, username, and password private.

## Render

Create a Blueprint from the public GitHub repository and enter these private values when prompted:

- `RFH_DB_HOST`
- `RFH_DB_USER`
- `RFH_DB_PASSWORD`

The remaining values are defined safely in `render.yaml`. Render builds the Docker image and serves the application through HTTPS. The current service was created from the public repository URL; future releases require a manual Blueprint sync unless GitHub repository access is connected for automatic deployments.

## Verification checklist

- [x] Home page returns HTTP 200 over HTTPS.
- [x] Registration, login, and protected sessions work on the live service.
- [x] A user can create and view a project on the live service.
- [x] Dashboard, global search, all main module pages, comparison/statistics pages, profile, and password change return HTTP 200.
- [x] CSS and JavaScript assets return HTTP 200.
- [x] Security headers are present and an unknown route returns HTTP 404.
- [x] No database credential is stored in tracked project files.

## Free-tier notes

The Render free web service can sleep after inactivity and take about one minute to wake. TiDB Cloud Starter remains free only within its published quota and with a spending limit of `0`.
