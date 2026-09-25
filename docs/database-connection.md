# ResearchFlow Hub - PDO Database Connection

## Step 6 status

**PASS** - the application-level PDO connection foundation was created and verified against the local Oracle MySQL 8.0.46 server.

## Files

- `config/database.example.php` - tracked, credential-free configuration template.
- `config/database.php` - ignored local configuration loader.
- `includes/database.php` - reusable PDO connection function.

## Configuration variables

The local configuration reads these environment variables:

- `RFH_DB_HOST` - defaults to `127.0.0.1`.
- `RFH_DB_PORT` - defaults to `3306`.
- `RFH_DB_NAME` - defaults to `researchflow_db`.
- `RFH_DB_USER` - defaults to `root` for the current local environment only.
- `RFH_DB_PASSWORD` - has no stored default and must be supplied securely.

Do not place a real password in the example file, README, Git history, shell command arguments, screenshots, or documentation. A fresh clone can create the ignored local loader with:

```sh
cp config/database.example.php config/database.php
```

## PDO security settings

- Exceptions are enabled for database errors.
- Associative arrays are the default fetch mode.
- Native prepared statements are required; emulated prepares are disabled.
- Persistent connections and stringified numeric fetches are disabled.
- The connection uses `utf8mb4` and validates its host, port, database name, and required configuration keys.
- Connection failures return a generic application exception; passwords are never included in errors.

## Verification result

- PDO driver: `mysql`.
- Server version: MySQL 8.0.46.
- Selected database: `researchflow_db`.
- Visible application tables: 10.
- Repeated calls return the same PDO connection during one PHP request.
- No credentials were added to tracked files.

## Step 6 checkpoint

- [x] Credential-free example configuration exists.
- [x] Local configuration is excluded by `.gitignore`.
- [x] Reusable PDO connection code exists.
- [x] Native prepared statements and safe PDO defaults are enabled.
- [x] A real connection to `researchflow_db` succeeds.
- [x] No authentication, session, CRUD, page, or Step 7 code was added.
