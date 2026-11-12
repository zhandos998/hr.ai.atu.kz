# Teacher Personnel Requests

The personnel workflow distinguishes `employee_type=staff` (employees), `employee_type=pps` (teachers)
and `employee_type=uvp` (educational support staff).
The admin sidebar has one list entry. Its category tabs show all records, teachers, UVP or staff.
Existing category URLs remain supported. Without an explicit category, the admin list restores the last
selected category from browser storage, or shows all categories on first use. `employee_type=all` explicitly
selects all in the browser URL; the API receives an empty category filter for this view.
API clients may omit the filter to retrieve all authorized records. Archive and applied filters are retained
when switching category tabs; pagination resets to the first page.
The create URL without a category shows a category picker before loading the corresponding form.

Existing rows are classified as staff by the database default. No existing employee data, approval decisions,
commissions or documents are migrated to a different owner or changed. Requests share the existing lifecycle,
action-based commission defaults, document storage, archive rules and financial access controls.

Teacher requests require a faculty, chair and PPS position. The server validates that the chair belongs to
the faculty and that the position belongs to that chair (not an administrative department). Transfers require
a second faculty/chair/position selection for the destination. Faculty changes clear dependent selections in
the form. An existing request cannot switch category while editing.

UVP has its own list tab and uses the same faculty/chair ownership checks, with the field
labelled as a UVP job rather than a PPS position. Job choices come from the separate global `uvp_positions`
catalog, not the selected chair's PPS positions. The seven default jobs are populated by `UvpPositionSeeder`.
Requests use `uvp_position_id` and `target_uvp_position_id`, with restrictive foreign keys, while ordinary
`position_id` fields are prohibited in UVP submissions. PPS/staff submissions prohibit the UVP fields.
No positions or user accounts are created automatically. Legacy UVP rows retain their old position references
for display; editing requires explicitly choosing from the UVP catalog rather than guessing a replacement.
Apply the additive UVP reference migration after creating/seeding the catalog. Rollback refuses to discard used references.
Action-based default commissions and the approval/document/archive rules are shared with the other categories.

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
