import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const load = async path => {
    const source = await readFile(new URL(path, import.meta.url), 'utf8');
    return import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
};
const { roleHome, workspaceNavigation } = await load('../../resources/js/react/lib/workspaceNavigation.js');

test('reviewer roles keep their own workspace and also show applications when assigned to a commission', () => {
    for (const role of ['lawyer', 'science_director', 'digital_director', 'strategy_director', 'academic_director', 'library_director', 'professional_development_director', 'economist']) {
        const links = workspaceNavigation({ is_commission_member: true }, role);
        assert(links.some(link => link.href === roleHome[role] && link.label === 'workspace'));
        assert(links.some(link => link.href === '/commission/applications' && link.label === 'applications'));
    }
});

test('ordinary and commission-role users get one applications link with no duplicate destinations', () => {
    for (const role of ['user', 'commission']) {
        const links = workspaceNavigation({ is_commission_member: true }, role);
        assert.deepEqual(links, [{ href: '/commission/applications', label: 'applications' }]);
    }
    assert.deepEqual(workspaceNavigation({}, 'commission'), [{ href: '/commission/applications', label: 'applications' }]);
});

test('commission access is not inferred from reviewer role or personnel assignments', () => {
    assert.deepEqual(workspaceNavigation({}, 'strategy_director'), [{ href: '/strategy/applications', label: 'workspace' }]);
    assert.deepEqual(workspaceNavigation({ has_personnel_approvals: true }, 'user'), [{ href: '/personnel/requests', label: 'personnel' }]);
    assert.deepEqual(workspaceNavigation(null, 'commission'), []);
    assert.deepEqual(workspaceNavigation({}, 'user'), []);
});

test('multiple assignments are all reachable and economist personnel link is not duplicated', () => {
    const user = { is_commission_member: true, has_personnel_approvals: true };
    const links = workspaceNavigation(user, 'strategy_director');
    assert.equal(links.length, 3);
    assert.equal(new Set(links.map(link => link.href)).size, 3);
    assert.equal(workspaceNavigation(user, 'economist').length, 2);
    assert(!workspaceNavigation(user, 'admin').some(link => link.href === '/personnel/requests'));
});

test('profile menu commands have Kazakh translations', async () => {
    const { kazakh } = await load('../../resources/js/react/translations.js');
    assert.equal(kazakh['Открыть профиль'], 'Профильді ашу');
});
