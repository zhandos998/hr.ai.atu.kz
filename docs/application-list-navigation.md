# Application List Navigation

The Vacancies sidebar has one Applications entry at `/admin/applications`.
Its tabs show all candidates, teachers (`type=pps`), educational support staff (`type=uvp`)
or administrative staff (`type=staff`).
`type=all` explicitly selects all categories. The existing API already supports this unfiltered view.
Without an explicit type, the browser restores its last selected category, defaulting to all.

Tabs preserve applied search, archive state, common status filters and page size, resetting the page to one.
Category-specific hiring filters are cleared only when incompatible with the destination category.
Counts come from the existing server response and reflect the applied filters and archive scope.
Each row uses its own vacancy type for academic fields, hiring choices and detail links.
Detail links retain the complete return URL, including filters and pagination.

Manual creation first offers a teacher/UVP/staff category choice. Creating a different category from a specific
tab switches to that category on success. Competition pages stay scoped to their own type and do not
display category tabs or allow category selection for a new entrant.

UVP uses the separate `/admin/uvp-positions` directory and `vacancies.uvp_position_id`.
Its applications reference `uvp_faculty_id` and `uvp_academic_unit_id`, not PPS profiles or administrative departments.
The faculty/chair pairing is validated on create, edit and candidate self-application. Catalog names are global
across chairs. Used jobs cannot be deleted, including jobs referenced by archived processes or soft-deleted vacancies.

UVP competitions have independent numbering and cannot contain PPS/staff applications. The main commission
has an explicit `is_uvp` flag, false for existing members until the admin assigns it. UVP voting has no PPS hiring terms.
The additive `2026_10_30_140000_add_uvp_vacancies` migration extends the vacancy type and adds nullable references.
Back up the database before applying it; rollback refuses to discard existing UVP records or memberships.
No production deployment was performed during local implementation.
Verification: `php artisan test --filter=AdminApplicationsPaginationTest`,
`node --test tests/Frontend/application-navigation.test.mjs`, and `npm run build`.
