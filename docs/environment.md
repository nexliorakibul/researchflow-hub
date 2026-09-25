# Development Environment

Checked: 2026-09-19. **Step 3 status: PASS.** PHP, Apache/PHP serving, Oracle MySQL 8+, PDO, PDO MySQL, phpMyAdmin, and local project serving were verified without storing credentials.

## PHP

- Version: PHP 8.0.28 (XAMPP CLI).
- Requirement satisfied: Yes.
- Executable: `/Applications/XAMPP/xamppfiles/bin/php`.
- The executable is not currently on the shell `PATH`; its full path works.

## MySQL

- Version: Oracle MySQL Community Server 8.0.46.
- Requirement satisfied: Yes.
- Server: Running locally on port 3306 during the Step 5 import and verification.
- XAMPP MariaDB 10.4.28 was stopped to prevent a port conflict.
- No credentials were written to project files or documentation.

## Apache

- Available: Yes — XAMPP Apache 2.4.56.
- Running: Successfully started temporarily on `127.0.0.1:8080` for the HTTP checks, then stopped cleanly.
- PHP serving: Verified through Apache using a temporary minimal echo page.

## PDO

- Enabled: Yes.
- Available drivers reported by PHP: `mysql`, `pgsql`, and `sqlite`.

## PDO MySQL

- Enabled: Yes.
- No database connection was attempted.

## phpMyAdmin

- Available: Yes — installed at `/Applications/XAMPP/xamppfiles/phpmyadmin`.
- HTTP verification: Passed with status `200` at `http://127.0.0.1:8080/phpmyadmin/` during the controlled Apache test.

## Local project

- Project path: `/Users/rakib/Documents/Codex/2026-09-19/read-this-pdf-carefully-from-beginning`.
- Verified test URL: `http://127.0.0.1:8080/environment_test.php`.
- Accessible: Yes during the controlled Apache test; output was `ResearchFlow Hub PHP environment is working.`
- Intended standard XAMPP URL after a permanent web-root mapping: `http://localhost/researchflow-hub/`.
- The temporary test page and temporary Apache configuration were removed after verification.

## Current service note

- Keep XAMPP MariaDB stopped while Oracle MySQL uses port 3306.
- A permanent `http://localhost/researchflow-hub/` mapping may be added when the application entry point is created; the Step 3 Apache/PHP serving check already passed on the verified test URL above.

Steps 1 and 2 remain unchanged. No credentials were added during environment verification.
