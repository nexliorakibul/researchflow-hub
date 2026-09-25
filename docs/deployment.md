# Live Deployment

ResearchFlow Hub is prepared for a free demonstration deployment using a Render Docker web service and a TiDB Cloud Starter database. TiDB is MySQL-compatible and supports the existing PDO application without changing the project data model.

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

The remaining values are defined safely in `render.yaml`. Render builds the Docker image, serves the application through HTTPS, and redeploys when `main` changes.

## Verification checklist

- Home page returns HTTP 200 over HTTPS.
- Registration, login, logout, and session protection work.
- A user can create, view, edit, and delete a project.
- Dashboard and global search load without server errors.
- Another user cannot access the first user's records.
- No credential appears in Git history, source files, logs, or rendered pages.

## Free-tier notes

The Render free web service can sleep after inactivity and take about one minute to wake. TiDB Cloud Starter remains free only within its published quota and with a spending limit of `0`.
