# Faculties API

Public, read-only JSON directory. No authorization is required. Limit: 60 requests per minute per IP.

Data source: `academic_units`. Faculties have `kind=faculty`; chairs have `kind=chair`
and reference the faculty through `parent_id`. Administrative departments in the
`departments` table are not included. IDs are the original `academic_units.id` values.

## All Faculties

`GET /api/faculties`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Faculty name",
      "departments": [
        { "id": 2, "name": "Chair name", "faculty_id": 1 }
      ]
    }
  ]
}
```

`departments` in this API means academic chairs, not administrative departments.
Faculty and chair names are sorted alphabetically; the "Другое" group is last.
Empty directories and faculties without chairs return empty arrays.

## Chairs In One Faculty

`GET /api/faculties/{faculty_id}/departments`

```json
{
  "faculty": { "id": 1, "name": "Faculty name" },
  "data": [
    { "id": 2, "name": "Chair name", "faculty_id": 1 }
  ]
}
```

IDs above are illustrative. Use the IDs returned by `/api/faculties`.
An unknown faculty, a chair ID, or a nonnumeric ID returns HTTP 404.
The API does not expose positions, staff, applications, or internal descriptions.
Editing remains restricted to the existing administrator API.
