# ResearchFlow Hub - Database Setup

## Step 5 status

**PASS** - the Version 1 schema was imported into Oracle MySQL 8.0.46 and verified on 2026-09-19.

## Imported database

- Database: `researchflow_db`
- Source: `database/schema.sql`
- Server: Oracle MySQL Community Server 8.0.46
- Engine: InnoDB
- Character set: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`

## Verified tables

1. `users`
2. `projects`
3. `resources`
4. `papers`
5. `datasets`
6. `literature_reviews`
7. `research_gaps`
8. `experiments`
9. `tasks`
10. `notes`

## Verification results

- Base tables: 10
- Foreign-key constraints: 20
- Check constraints: 21
- Every table uses InnoDB and `utf8mb4_unicode_ci`.
- The schema contains no sample records.
- XAMPP MariaDB was stopped to prevent a port 3306 conflict with Oracle MySQL.
- The database password was not written to any project file or documentation.

## Repeatable import command

Run this only against the intended MySQL 8+ server:

```sh
/usr/local/mysql/bin/mysql -u root -p < database/schema.sql
```

Enter the password only at the prompt. Do not place it in the command, repository, README, or schema file.

## Step 5 checkpoint

- [x] Oracle MySQL 8+ is running.
- [x] Root authentication was verified without storing credentials in the repository.
- [x] `researchflow_db` was created from the tracked schema.
- [x] All 10 required tables were created.
- [x] Foreign keys, checks, engine, character set, and collation were verified.
- [x] No sample data, PHP connection code, authentication code, or CRUD code was added.
