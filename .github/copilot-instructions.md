# FlexReal Copilot Instructions

## Project shape
- This is a PHP web application for student, parent, teacher, staff, and officer workflows.
- The frontend is mostly plain HTML, CSS, and JavaScript paired with PHP pages and JSON endpoints.
- PostgreSQL access is centralized through `db_connect.php` using PDO.
- Thai-language UI text is present; preserve UTF-8 handling and existing Thai copy when changing behavior.

## Database
- Runtime code currently targets the legacy schema and table names.
- `docs/database-redesign.md` and `docs/database-v2.sql` describe a destructive rebuild target. Do not migrate queries to the new schema unless the task explicitly requests it.
- Use parameterized PDO queries for all user-controlled values. Preserve transaction boundaries and authorization checks.
- Never expose or duplicate database credentials, passwords, reset tokens, or other secrets.

## Editing and validation
- Keep changes focused and follow the existing page/API naming patterns.
- Check related callers and role/session behavior before changing shared PHP helpers or endpoints.
- Run `php -l` on changed PHP files when PHP is available; for frontend changes, use the narrowest available browser or syntax check.
- Do not change database structure or destructive migration scripts as part of unrelated feature work.
