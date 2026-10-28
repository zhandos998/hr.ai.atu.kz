# Worker Import

## Pages

- `/admin/users/import`: upload, review, and confirm an XLSX import.
- `/admin/users/technical`: accounts whose email ends in `@hr-ai.invalid`.
- Both pages require administrator API access. Normal user listing excludes technical accounts.

## Workbook

One worksheet, up to 200 employees and 2 MB uploaded size. Columns:

1. Employee name.
2. Legacy identifier, including Cyrillic or Kazakh letters. It is not assigned as an application login.
3. Password for new accounts only.
4. Legacy position and contact information.
5. Optional legacy date, ignored during import.

A header row beginning with `ФИО` / `Логин` or `name` / `login` is optional.
The first employee is retained when the workbook has no headers. Numeric passwords
use the cell's display format, including formatted leading zeroes. Formulas are rejected.

## Review And Apply

- Only an unambiguous exact name match is preselected for updating. Similar names,
  duplicates, and placeholder names such as `user44` require a manual choice.
- Each row can update a selected account, create a new account, or be skipped.
- Existing passwords, email addresses, names, roles, verification state, and existing
  position assignments are preserved. Historical usernames are neither assigned nor overwritten.
- Legacy position/contact text fills `contact_info` only when it is empty.
- Structured positions are chosen explicitly from the directory. Assignment adds the
  selected position without detaching existing ones; it does not grant a role or commission membership.
- New accounts receive role `user`, a unique technical email, and a hashed password
  from the workbook. They are administrator-provisioned and can sign in only by email.
  Passwords outside the existing 4-64 character policy cannot create new accounts.
- The legacy identifier is used only to recognize previous imports and derive a stable technical email.
- The complete plan is validated before updates, and applied in a database transaction.
  Changes to an existing account since preview invalidate that selection.
- Previews expire after 30 minutes and belong to their creating administrator.
  Confirmation is idempotent: repeated confirmation does not create duplicate accounts.

## Privacy And Deployment

- Passwords and password hashes are never included in preview responses or import results.
- Raw passwords are hashed before creating a preview. Its remaining payload is encrypted
  in `worker_import_batches` and cleared after completion. Starting a new preview also
  clears expired payloads. The batch retains result IDs and a source-file fingerprint for audit.
- Workbook uploads are not published to storage. Credential workbooks must remain outside
  Git and Docker images; `.dockerignore` excludes XLSX files.
- Deploy with `composer install` and `php artisan migrate --force`; never use `migrate:fresh`
  or reseed production for this feature. Rebuild frontend and app images as usual.
- Do not apply a local preview to production: upload and review against the target database.

The XLSX reader uses [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/)
with bounded rows, archive-size checks, and no formula evaluation.
