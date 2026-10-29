# Teacher Personnel Requests

The personnel workflow now distinguishes `employee_type=staff` (employees) and `employee_type=pps` (teachers).
The admin sidebar links to separate lists using this parameter. Existing URLs without a category default
to the staff list in the admin interface. API clients may omit the filter to retrieve all authorized records.

Existing rows are classified as staff by the database default. No existing employee data, approval decisions,
commissions or documents are migrated to a different owner or changed. Requests share the existing lifecycle,
action-based commission defaults, document storage, archive rules and financial access controls.

Teacher requests require a faculty, chair and PPS position. The server validates that the chair belongs to
the faculty and that the position belongs to that chair (not an administrative department). Transfers require
a second faculty/chair/position selection for the destination. Faculty changes clear dependent selections in
the form. An existing request cannot switch category while editing.

The migration makes `department_id` nullable and adds four academic reference columns plus the category.
Staff requests still require a department; teacher requests prohibit administrative department fields.
Do not roll back this migration while teacher requests exist; rollback explicitly refuses to discard them.
Referenced academic units cannot be deleted through the directory API.

Public status, the assigned approvers' list and details show the corresponding academic structure.
Public responses still exclude salary, private details, documents and approval comments; approvers still
only see their assigned requests and financial data remains restricted to admins/economists.

Back up the database and preserve APP_KEY before the normal additive production migration.
Rebuild application/frontend Docker images. Never run `migrate:fresh` or user seeders for this release.

Tests: `php artisan test --filter=TeacherPersonnelRequestTest` and the existing personnel workflow suites.
