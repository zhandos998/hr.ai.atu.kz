import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const importFile = async relative => {
    const text = await readFile(new URL(relative, import.meta.url), 'utf8');
    return import(`data:text/javascript;base64,${Buffer.from(text).toString('base64')}`);
};

test('both personnel registers have localized titles, fields and commands', async () => {
    const { registers } = await importFile('../../resources/js/react/Pages/Admin/PersonnelRegister/config.js');
    const { kazakh } = await importFile('../../resources/js/react/translations.js');
    assert.deepEqual(Object.keys(registers), ['sick_leave', 'leave']);
    for (const register of Object.values(registers)) {
        for (const key of ['title', 'singular', 'add', 'edit', 'documentLabel', 'detailLabel']) {
            assert(kazakh[register[key]], `Missing Kazakh translation for ${register[key]}`);
        }
    }
});

test('date formatting preserves calendar dates and supports missing values', async () => {
    const { formatRegisterDate } = await importFile('../../resources/js/react/Pages/Admin/PersonnelRegister/config.js');
    for (const locale of ['ru', 'kk']) {
        assert(formatRegisterDate('2026-10-01', locale).includes('2026'));
        assert.equal(formatRegisterDate(null, locale), '-');
    }
});
