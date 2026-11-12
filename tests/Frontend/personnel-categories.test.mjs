import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const load = async path => {
    const source = await readFile(new URL(path, import.meta.url), 'utf8');
    return import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
};
const { personnelCategories, personnelCategory, usesAcademicStructure, personnelTabs, personnelListCategory, personnelListUrl, personnelPosition, emptyPersonnelForm } = await load('../../resources/js/react/personnel.js');

test('UVP and PPS use faculty/chair structure while staff keeps departments', () => {
    assert.deepEqual(Object.keys(personnelCategories), ['staff', 'pps', 'uvp']);
    assert.equal(usesAcademicStructure('uvp'), true);
    assert.equal(usesAcademicStructure('pps'), true);
    assert.equal(usesAcademicStructure('staff'), false);
    assert.equal(personnelCategory('uvp').positionLabel, 'Должность УВП');
    assert.equal(personnelCategory('pps').positionLabel, 'Позиция ППС');
});

test('UVP display uses the separate catalog and retains legacy position display', () => {
    const legacy = { id: 4, name: 'Legacy position' };
    const uvp = { id: 4, name: 'UVP position' };
    assert.equal(personnelPosition({ employee_type: 'uvp', position: legacy, uvp_position: uvp }), uvp);
    assert.equal(personnelPosition({ employee_type: 'uvp', position: legacy }), legacy);
    assert.equal(personnelPosition({ employee_type: 'pps', position: legacy, uvp_position: uvp }), legacy);
    assert.equal(personnelPosition({ employee_type: 'uvp', target_position: legacy, target_uvp_position: uvp }, true), uvp);
    assert.equal(emptyPersonnelForm.uvp_position_id, null);
    assert.equal(emptyPersonnelForm.target_uvp_position_id, null);
});

test('unknown types and prototype names cannot resolve to category objects', () => {
    for (const type of [null, undefined, '', 'constructor', '__proto__', 'toString', 'unknown']) {
        assert.equal(personnelCategory(type), personnelCategories.staff);
    }
});

test('UVP names and commands have Kazakh translations', async () => {
    const { kazakh } = await load('../../resources/js/react/translations.js');
    for (const key of ['label', 'title', 'createTitle', 'nameLabel', 'description', 'positionLabel']) {
        assert(kazakh[personnelCategories.uvp[key]], `Missing translation for ${key}`);
    }
});

test('personnel list defaults to all and only restores a remembered category when the URL omits it', () => {
    assert.equal(personnelListCategory(null), '');
    assert.equal(personnelListCategory(null, 'uvp'), 'uvp');
    assert.equal(personnelListCategory('pps', 'staff'), 'pps');
    assert.equal(personnelListCategory('all', 'staff'), '');
    assert.equal(personnelListCategory('', 'staff'), '');
    assert.equal(personnelListCategory('constructor', 'pps'), '');
    assert.equal(personnelListCategory(null, '__proto__'), '');
});

test('category navigation retains archive and applied filters but resets pagination', () => {
    const url = new URL(personnelListUrl({ employee_type: '', archived: '1', q: 'Тест & имя', status: 'pending', action_type: 'hire' }), 'http://localhost');
    assert.equal(url.pathname, '/admin/personnel-requests');
    assert.equal(url.searchParams.get('employee_type'), 'all');
    assert.equal(personnelListCategory(url.searchParams.get('employee_type'), 'uvp'), '');
    assert.equal(url.searchParams.get('page'), '1');
    assert.equal(url.searchParams.get('archived'), '1');
    assert.equal(url.searchParams.get('q'), 'Тест & имя');
    assert.equal(url.searchParams.get('status'), 'pending');
    assert.equal(url.searchParams.get('action_type'), 'hire');
    assert.equal(new URL(personnelListUrl({ employee_type: 'pps' }, 2), url).searchParams.get('page'), '2');
});

test('all list tabs and category picker labels have Kazakh translations', async () => {
    const { kazakh } = await load('../../resources/js/react/translations.js');
    assert.deepEqual(personnelTabs.map(tab => tab.value), ['', 'pps', 'uvp', 'staff']);
    for (const label of [...personnelTabs.map(tab => tab.label), 'Все заявки', 'Выберите категорию', 'Новый кадровый процесс', 'Факультет, кафедра и должность', 'Департамент и должность', 'Изменить категорию', 'ФИО, подразделение или должность']) {
        assert(kazakh[label], `Missing translation for ${label}`);
    }
});
