import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

test('Google fallback messages have Kazakh translations', async () => {
    const source = await readFile(new URL('../../resources/js/react/translations.js', import.meta.url), 'utf8');
    const { kazakh } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
    assert.equal(kazakh['или'], 'немесе');
    assert.equal(kazakh['Не удалось войти'], 'Кіру мүмкін болмады');
    assert.equal(kazakh['Закрыть уведомление'], 'Хабарламаны жабу');
    assert.equal(kazakh['Не удалось войти через Google. Используйте email и пароль.'], 'Google арқылы кіру мүмкін болмады. Email және құпиясөзді пайдаланыңыз.');
});
