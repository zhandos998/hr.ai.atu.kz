import React from "react";
import { Link } from "@inertiajs/react";
import { MailWarning } from "lucide-react";
import { useI18n } from "../I18nContext";
import { isTechnicalEmail } from "../lib/email";

export default function TechnicalEmailWarning({ email }) {
    const { t } = useI18n();

    if (!isTechnicalEmail(email)) return null;

    return (
        <section
            role="alert"
            aria-label={t("Обновите email и пароль")}
            className="border-b border-amber-200 bg-amber-50 text-amber-950"
        >
            <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                <div className="flex min-w-0 gap-3">
                    <MailWarning size={21} aria-hidden="true" className="mt-0.5 shrink-0 text-amber-700" />
                    <div className="min-w-0">
                        <h2 className="text-sm font-semibold">{t("Обновите email и пароль")}</h2>
                        <p className="mt-1 text-sm leading-5">
                            {t("В вашем аккаунте указана техническая почта. Замените её на действующий адрес, чтобы получать письма и восстанавливать пароль.")}
                        </p>
                        <p className="mt-1 text-sm leading-5">
                            {t("Для безопасности смените выданный вам пароль на собственный.")}
                        </p>
                    </div>
                </div>
                <div className="flex max-w-full shrink-0 flex-wrap gap-2 sm:justify-end">
                    <Link
                        href="/profile/change-email"
                        className="inline-flex items-center justify-center rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-950 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700"
                    >
                        {t("Сменить email")}
                    </Link>
                    <Link
                        href="/profile/change-password"
                        className="inline-flex items-center justify-center rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-950 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700"
                    >
                        {t("Сменить пароль")}
                    </Link>
                </div>
            </div>
        </section>
    );
}
