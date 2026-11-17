import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/react/lib/directoryStructure.js', import.meta.url), 'utf8');
const { buildDepartmentTree, filterDepartmentTree } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

const items = [
    { id: 1, name: 'Департамент науки', positions: [{ id: 10, name: 'Директор' }] },
    { id: 2, parent_id: '1', name: 'Отдел исследований', positions: [] },
    { id: 3, parent_id: 2, name: 'Лаборатория', positions: [{ id: 11, name: 'Инженер', duties: 'Анализ данных', qualification: 'Высшее образование' }] },
    { id: 4, parent_id: 1, name: 'Архив', positions: [] },
    { id: 5, name: 'Администрация', positions: [] },
];

test('department tree links numeric and string IDs without mutating API rows', () => {
    const before = structuredClone(items);
    const tree = buildDepartmentTree(items);
    assert.deepEqual(tree.map(item => item.id), [1, 5]);
    assert.deepEqual(tree[0].children.map(item => item.id), [2, 4]);
    assert.equal(tree[0].children[0].children[0].id, 3);
    assert.deepEqual(items, before);
});

test('empty search preserves the complete tree', () => {
    const tree = buildDepartmentTree(items);
    assert.equal(filterDepartmentTree(tree, '  '), tree);
});

test('deep position name, duties and qualification retain only matching ancestors', () => {
    const tree = buildDepartmentTree(items);
    const before = structuredClone(tree);
    for (const query of [' ИНЖЕНЕР ', 'анализ данных', 'образование']) {
        const result = filterDepartmentTree(tree, query);
        assert.equal(result.length, 1);
        assert.deepEqual(result[0].children.map(item => item.id), [2]);
        assert.deepEqual(result[0].positions, []);
        assert.equal(result[0].children[0].children[0].positions[0].id, 11);
    }
    assert.deepEqual(tree, before);
});

test('matching a department keeps its descendants and positions', () => {
    const tree = buildDepartmentTree(items);
    const result = filterDepartmentTree(tree, 'департамент науки');
    assert.deepEqual(result, [tree[0]]);
    assert.equal(result[0].children.length, 2);
    assert.equal(result[0].positions[0].id, 10);
});

test('unmatched and empty data return an empty tree', () => {
    assert.deepEqual(buildDepartmentTree([]), []);
    assert.deepEqual(filterDepartmentTree([], 'test'), []);
    assert.deepEqual(filterDepartmentTree(buildDepartmentTree(items), 'несуществующее'), []);
});

test('shared directory headings, counters and actions have Kazakh translations', async () => {
    const translations = await readFile(new URL('../../resources/js/react/translations.js', import.meta.url), 'utf8');
    const { kazakh } = await import(`data:text/javascript;base64,${Buffer.from(translations).toString('base64')}`);
    for (const label of ['Подразделения', 'Показано:', 'из', 'Должности', 'Кафедры', 'Факультеты', 'Сотрудники', 'Открыть', 'Изменить', 'Удалить', 'Свернуть', 'Развернуть', 'Сохранить', 'Отмена', 'Должности учебно-вспомогательного персонала.', 'Удалить подразделение?', 'Изменить должность ППС', 'Должность будет удалена во всех кафедрах.']) {
        assert(kazakh[label], label);
        assert.notEqual(kazakh[label], label);
    }
});
