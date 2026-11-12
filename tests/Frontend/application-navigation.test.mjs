import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const load = async path => {
    const source = await readFile(new URL(path, import.meta.url), 'utf8');
    return import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
};
const { applicationTabs, applicationListType, applicationListUrl, applicationTabUrl, applicationType } = await load('../../resources/js/react/applicationNavigation.js');

test('application category defaults to all, remembers valid types and respects explicit URLs', () => {
    assert.equal(applicationListType(null), 'all');
    assert.equal(applicationListType(null, 'staff'), 'staff');
    assert.equal(applicationListType('all', 'pps'), 'all');
    assert.equal(applicationListType('pps', 'staff'), 'pps');
    assert.equal(applicationListType('uvp'), 'uvp');
    for (const type of ['', 'unknown', 'constructor', '__proto__']) {
        assert.equal(applicationListType(type, 'staff'), 'all');
    }
});

test('mixed list rows use their own application type, not the selected tab', () => {
    assert.equal(applicationType({ vacancy: { type: 'pps' } }), 'pps');
    assert.equal(applicationType({ vacancy: { type: 'staff' } }), 'staff');
    assert.equal(applicationType({ vacancy: { type: 'uvp' } }), 'uvp');
    assert.equal(applicationType({}), 'pps');
});

test('tabs retain applied search, statuses, archive and page size but reset pagination', () => {
    const filters = { type: 'all', archived: 1, q: 'Имя & фамилия', resume_status: 'accepted', hiring_status: 'voting', per_page: 10, page: 4 };
    const url = new URL(applicationTabUrl(filters, 'pps'), 'http://localhost');
    assert.equal(url.pathname, '/admin/applications');
    for (const [key, value] of Object.entries({ ...filters, type: 'pps', page: 1 })) {
        assert.equal(url.searchParams.get(key), String(value));
    }
    assert.equal(new URL(applicationListUrl(), url).searchParams.get('type'), 'all');
    assert.equal(new URL(applicationListUrl(), url).searchParams.get('page'), '1');
});

test('only category-specific hiring filters are cleared when they cannot apply to the target tab', () => {
    for (const status of ['hired_1_year', 'hired_3_year']) {
        assert.equal(new URL(applicationTabUrl({ hiring_status: status }, 'staff'), 'http://localhost').searchParams.has('hiring_status'), false);
        assert.equal(new URL(applicationTabUrl({ hiring_status: status }, 'all'), 'http://localhost').searchParams.get('hiring_status'), status);
    }
    assert.equal(new URL(applicationTabUrl({ hiring_status: 'hired' }, 'pps'), 'http://localhost').searchParams.has('hiring_status'), false);
});

test('all category tab labels have Kazakh translations', async () => {
    const { kazakh } = await load('../../resources/js/react/translations.js');
    assert.deepEqual(applicationTabs.map(tab => tab.value), ['all', 'pps', 'uvp', 'staff']);
    for (const { label } of applicationTabs) assert(kazakh[label], `Missing translation for ${label}`);
});
