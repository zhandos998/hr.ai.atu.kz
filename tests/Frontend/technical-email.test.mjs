import assert from "node:assert/strict";
import test from "node:test";
import { isTechnicalEmail } from "../../resources/js/react/lib/email.js";
import { kazakh } from "../../resources/js/react/translations.js";

test("technical email detection accepts the exact domain regardless of case", () => {
    for (const email of ["worker@hr-ai.invalid", "Worker@HR-AI.INVALID", " worker@hr-ai.invalid "]) {
        assert.equal(isTechnicalEmail(email), true);
    }
});

test("ordinary addresses, lookalike domains and missing users do not trigger the warning", () => {
    for (const email of [undefined, null, "", "worker@atu.edu.kz", "hr-ai.invalid@atu.edu.kz", "worker@hr-ai.invalid.example.com", "worker@not-hr-ai.invalid"]) {
        assert.equal(isTechnicalEmail(email), false);
    }
});

test("the email and password warning and both actions have Kazakh translations", () => {
    for (const message of [
        "Обновите email и пароль",
        "Для безопасности смените выданный вам пароль на собственный.",
        "В вашем аккаунте указана техническая почта. Замените её на действующий адрес, чтобы получать письма и восстанавливать пароль.",
        "Техническая почта",
        "Сменить email",
        "Сменить пароль",
    ]) {
        assert.ok(kazakh[message]);
        assert.notEqual(kazakh[message], message);
    }
});
