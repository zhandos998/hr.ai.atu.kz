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
            aria-label={t("Укажите действующий email")}
            className="border-b border-amber-200 bg-amber-50 text-amber-950"
        >
            <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                <div className="flex min-w-0 gap-3">
                    <MailWarning size={21} aria-hidden="true" className="mt-0.5 shrink-0 text-amber-700" />
                    <div className="min-w-0">
                        <h2 className="text-sm font-semibold">{t("Укажите действующий email")}</h2>
                        <p className="mt-1 text-sm leading-5">
                            {t("В вашем аккаунте указана техническая почта. Замените её на действующий адрес, чтобы получать письма и восстанавливать пароль.")}
                        </p>
                    </div>
                </div>
                <Link
                    href="/profile/change-email"
                    className="inline-flex shrink-0 items-center justify-center self-start rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-950 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700 sm:self-center"
                >
                    {t("Сменить email")}
                </Link>
            </div>
        </section>
    );
}
