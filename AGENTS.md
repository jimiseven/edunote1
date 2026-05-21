# AGENTS.md

## Project shape (do not assume framework conventions)
- This is a plain PHP app (no Laravel/Symfony): route entrypoints are direct files like `index.php`, `login.php`, `admin/*.php`, `profesor/*.php`, and `direc/*.php`.
- Shared DB bootstrap is `config/database.php`; most pages `require_once` it directly and open PDO connections inline.
- Composer is only used for libraries (`phpoffice/phpspreadsheet`, `tecnickcom/tcpdf`), not for app scripts/tests.

## Local runtime facts
- DB connection defaults are hardcoded in `config/database.php`: host `localhost`, db `wiredcom_uni3t`, user `root`, empty password, timezone `America/La_Paz`.
- Login flow is in `login.php`; role redirects are: role `1` -> `admin/dash_iniciales.php`, role `2` -> `profesor/dashboard.php`, role `3` -> `direc/iniv.php`.

## Database/migrations quirks
- SQL patches live in `bds/*.sql`; historical full dumps also live under `bds/` subfolders (usually reference/backups, not migration sources of truth).
- `config/ejecutar_migraciones_bds.php` defines `edunote_aplicar_migraciones_bds(PDO $conn)`, but this function is not called anywhere in the repo. Do not assume migrations run automatically.
- Attendance table migration `bds/asistencia.sql` enforces `UNIQUE (id_estudiante, fecha)`, so current schema allows only one attendance record per student per day unless schema/business logic is changed together.

## Editing guidance specific to this repo
- Reuse existing helper `includes/asistencia_auth.php` for attendance permissions; avoid adding new duplicate auth helpers inside feature pages.
- Large admin pages (for example `admin/asistencia.php`) combine request handling, business logic, and HTML in one file; keep changes minimal and scoped to avoid regressions.

## Verification expectations
- There is no repo-level automated test/lint/typecheck config in this project.
- Minimum safe verification after changes is targeted manual validation in browser for affected role/page plus SQL-level sanity checks when changing attendance or grading logic.
