import assert from "node:assert/strict";
import test from "node:test";
import { kazakh } from "../../resources/js/react/translations.js";

test("home headings and service links are translated into Kazakh", () => {
    for (const message of [
        "Алматинский технологический университет",
        "Кадровый портал АТУ",
        "Кадровые процессы и конкурсы университета",
        "Сотрудникам и членам комиссии",
        "Приём на работу, перевод, увольнение и отпуск",
        "Должности преподавателей и сотрудников",
        "Войти в личный кабинет",
        "Разделы портала",
        "Личный кабинет",
        "Квалификационные требования",
        "Статус кадрового процесса",
        "Открыть документ",
        "Перейти в профиль",
        "Проверить статус",
    ]) {
        assert.ok(kazakh[message], `Missing translation: ${message}`);
        assert.notEqual(kazakh[message], message);
    }
});
