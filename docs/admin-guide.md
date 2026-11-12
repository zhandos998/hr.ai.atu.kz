# Administrator Guide

The administrator can start a contextual tour with the sidebar's instruction
button. On mobile the question-mark button is in the header. It does not open
automatically and is not shown to other roles.

The current page determines the steps: dashboard, competitions, application
lists/details, personnel processes/forms, users, directories and commissions.
Missing optional elements are skipped. Russian and Kazakh copy follows the
selected interface language.

The tour only highlights existing elements. It does not navigate, submit forms
or mutate records. Active controls are blocked during the tour. Next/Previous,
Skip, the close button and Escape are supported; the tour can be restarted.
Navigation, locale changes and component unmount cancel the current tour.

## Maintenance

- Step copy and route mapping: `resources/js/react/guides/admin.js`.
- Lifecycle and lazy Driver.js loading: `resources/js/react/guides/useAdminGuide.js`.
- Stable targets: `data-admin-guide` attributes on page elements.
- Async pages: mark the root `data-guide-busy={loading}` to defer target selection.
- Styles: `.vacancy-admin-guide` in `resources/css/app.css`.
- Run `node --test tests/Frontend/*.test.mjs` and `npm run build`.
- Browser checks should cover desktop/mobile, RU/KK, empty lists, keyboard
  navigation, closing/restarting, route cleanup and zero mutation requests.

No database migration or seeding is required for this feature.
