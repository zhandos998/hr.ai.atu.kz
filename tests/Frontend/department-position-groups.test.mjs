import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/react/Pages/Admin/Departments/positionGroups.js', import.meta.url), 'utf8');
const { groupPositionsByDepartment } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const root = { id: 186, name: 'Департамент академического развития' };

test('root group comes first and child positions stay together without changing input', () => {
    const child = { id: 17, name: 'Отдел академической мобильности' };
    const positions = [
        { id: 69, name: 'Начальник ОАМ', department: child },
        { id: 814, name: 'Заместитель директора', department: root },
        { id: 70, name: 'Специалист ОАМ', department: child },
    ];
    const groups = groupPositionsByDepartment(positions, root);
    assert.deepEqual(groups.map(group => group.id), [186, 17]);
    assert.deepEqual(groups[0].positions.map(position => position.id), [814]);
    assert.deepEqual(groups[1].positions.map(position => position.id), [69, 70]);
    assert.deepEqual(positions.map(position => position.id), [69, 814, 70]);
});

test('numeric/string owner IDs share a group and old responses fall back to root', () => {
    const groups = groupPositionsByDepartment([
        { id: 1, department: { id: '186', name: root.name } },
        { id: 2, department_id: 186 },
        { id: 3 },
    ], root);
    assert.equal(groups.length, 1);
    assert.equal(groups[0].positions.length, 3);
});

test('filtered positions do not introduce empty groups and empty data is supported', () => {
    assert.deepEqual(groupPositionsByDepartment([], root), []);
    assert.deepEqual(groupPositionsByDepartment([], null), []);
    const groups = groupPositionsByDepartment([{ id: 69, department: { id: 17, name: 'Отдел академической мобильности' } }], root, 'kk');
    assert.deepEqual(groups.map(group => group.id), [17]);
});
