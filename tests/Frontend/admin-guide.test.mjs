import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/react/guides/admin.js', import.meta.url), 'utf8');
const { adminGuideFor, adminGuideLabels, findGuideTarget } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

const routes = {
    '/admin': 'dashboard',
    '/admin/competitions': 'competitions',
    '/admin/competitions/create': 'competitionForm',
    '/admin/competitions/1/edit': 'competitionForm',
    '/admin/competitions/1?type=pps&page=2': 'applications',
    '/admin/applications?type=staff': 'applications',
    '/admin/applications/63?type=pps': 'application',
    '/admin/personnel-requests': 'personnel',
    '/admin/personnel-requests/create?employee_type=uvp': 'personnelForm',
    '/admin/personnel-requests/1/edit': 'personnelForm',
    '/admin/personnel-requests/1': 'personnelDetail',
    '/admin/personnel-approval-routes': 'approvalRoutes',
    '/admin/users/technical': 'users',
    '/admin/departments/1': 'directory',
    '/admin/positions/1': 'directoryForm',
    '/admin/uvp-positions': 'directory',
    '/admin/commission-members': 'commission',
    '/admin/leaves': 'generic',
};

test('admin guides match list, detail and form routes without confusing create with an ID', () => {
    for (const [url, id] of Object.entries(routes)) assert.equal(adminGuideFor(url).id, id, url);
    assert.equal(adminGuideFor('/admin/').id, 'dashboard');
    for (const url of ['/', '/login', '/commission/applications', '/personnel/requests', '/administrator']) assert.equal(adminGuideFor(url), null);
});

test('every route has static RU and KK steps with no mutation handlers or candidate HTML', () => {
    for (const url of Object.keys(routes)) {
        const ru = adminGuideFor(url, 'ru');
        const kk = adminGuideFor(url, 'kk');
        assert.equal(ru.steps.length, kk.steps.length);
        assert(ru.steps.length >= 3);
        ru.steps.forEach((item, index) => {
            assert.deepEqual(Object.keys(item).sort(), ['description', 'target', 'title']);
            assert(item.title && item.description && kk.steps[index].title && kk.steps[index].description);
            assert.notEqual(item.title, kk.steps[index].title);
            assert(!/[<>]/.test(item.title + item.description));
        });
        assert.equal(ru.steps.at(-1).target, 'guide-trigger');
    }
    for (const key of Object.keys(adminGuideLabels.ru)) assert(adminGuideLabels.kk[key]);
});

test('target resolution skips hidden desktop elements and uses mobile fallback', () => {
    const original = globalThis.getComputedStyle;
    globalThis.getComputedStyle = element => ({ visibility: element.hidden ? 'hidden' : 'visible' });
    const hidden = { hidden: true, getClientRects: () => [1] };
    const absent = { getClientRects: () => [] };
    const visible = { getClientRects: () => [1] };
    const root = { querySelectorAll: selector => selector.includes('menu-toggle') ? [visible] : [hidden, absent] };
    try {
        assert.equal(findGuideTarget(['navigation', 'menu-toggle'], root), visible);
        assert.equal(findGuideTarget('missing', root), null);
    } finally { globalThis.getComputedStyle = original; }
});
