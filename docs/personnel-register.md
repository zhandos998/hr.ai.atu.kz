# Personnel Register

The admin sidebar includes a separate personnel register group, independent of approval requests:

- `/admin/sick-leaves`: employee name, position, certificate number, date interval, optional diagnosis.
- `/admin/leaves`: employee name, position, order number, date interval and leave type.

Both registers have list, create, details and edit pages. Search is submitted by button, dates filter
intersecting absence periods, and lists paginate on the server (25 by default, at most 100).
Entering a name never creates a user account. Existing legacy sites/data are not imported.

API: `/api/admin/personnel-register/{type}`, with `sick_leave` and `leave` as allowed types.
All operations require Sanctum authentication and the admin role. No public or commission access
is provided. Type-scoped lookups prevent reading or changing a record through the other register.

The `personnel_register_entries` table is additive and separate from applications and personnel requests.
Diagnosis is encrypted with the application key. Preserve the existing `APP_KEY` and keep a secure backup;
changing it without an encryption-key rotation plan makes stored diagnoses unreadable.
Requests for these pages/APIs are excluded from Telescope, and API responses use private/no-store caching.
Deletion uses soft deletes, preserving the record in the database; a restore interface is not included yet.
Creator/editor IDs and timestamps are retained internally.

Run the additive migration through the existing release process after taking a database backup.
Do not use `migrate:fresh`, seed users or import a legacy database over the current database.
Application and frontend images must be rebuilt when deploying to Docker. Decide staff access and
legacy-data mapping separately before enabling the module for additional roles or importing real records.

Verification: `php artisan test --filter=PersonnelRegisterTest`, frontend tests and `npm.cmd run build`.
