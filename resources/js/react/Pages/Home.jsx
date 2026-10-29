import { useI18n as useTextI18n } from "../I18nContext";
import React from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, ArrowUpRight, BookOpen, ClipboardCheck, UserRound } from "lucide-react";
import Layout from "../Components/Layout";
import { useAuth } from "../AuthContext";

const requirementsUrl = "https://library.atu.edu.kz/files/doc/231020231/";

export default function Home() {
    const { t: _t } = useTextI18n();
    const { user } = useAuth();
    const accountHref = user ? "/profile" : "/login";
    const services = [
        {
            id: "account",
            icon: UserRound,
            title: "Личный кабинет",
            detail: user ? user.name : _t("Сотрудникам и членам комиссии"),
            action: user ? "Перейти в профиль" : "Войти в личный кабинет",
            href: accountHref,
            color: "border-blue-100 bg-[#edf3ff] text-[#355da8]",
        },
        {
            id: "status",
            icon: ClipboardCheck,
            title: "Статус кадрового процесса",
            detail: _t("Приём на работу, перевод, увольнение и отпуск"),
            action: "Проверить статус",
            href: "/personnel-status",
            color: "border-emerald-100 bg-emerald-50 text-emerald-700",
        },
        {
            id: "requirements",
            icon: BookOpen,
            title: "Квалификационные требования",
            detail: _t("Должности преподавателей и сотрудников"),
            action: "Открыть документ",
            href: requirementsUrl,
            external: true,
            color: "border-amber-100 bg-amber-50 text-amber-700",
        },
    ];

    return (
        <Layout>
            <Head title={_t("Главная")} />
            <div className="flex min-h-[calc(100svh-80px)] flex-col bg-[#f5f8fd]">
                <section className="border-b border-[#dfe7f5] bg-white">
                    <div className="mx-auto flex max-w-7xl flex-col items-start gap-6 px-4 py-9 sm:px-6 sm:py-12 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-[#355da8]">{_t("Алматинский технологический университет")}</p>
                            <h1 className="mt-3 text-3xl font-semibold leading-tight text-gray-950 sm:text-4xl">{_t("Кадровый портал АТУ")}</h1>
                            <p className="mt-3 text-base leading-7 text-gray-500">{_t("Кадровые процессы и конкурсы университета")}</p>
                        </div>
                        <Link
                            href={accountHref}
                            className="inline-flex max-w-full shrink-0 items-center justify-center gap-3 rounded-md bg-[#355da8] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#355da8]"
                        >
                            {_t(user ? "Перейти в профиль" : "Войти в личный кабинет")}
                            <ArrowRight size={17} aria-hidden="true" className="shrink-0" />
                        </Link>
                    </div>
                </section>

                <section aria-label={_t("Разделы портала")} className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {services.map(({ id, icon: Icon, title, detail, action, href, external, color }) => {
                            const Component = external ? "a" : Link;
                            return (
                                <Component
                                    key={id}
                                    href={href}
                                    {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})}
                                    data-testid={`home-service-${id}`}
                                    className="group flex min-h-[216px] min-w-0 flex-col rounded-lg border border-[#dfe7f5] bg-white p-5 shadow-sm transition hover:border-[#b9ccea] hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#355da8] md:last:col-span-2 lg:last:col-span-1 sm:p-6"
                                >
                                    <div className="flex min-h-[52px] items-start gap-3">
                                        <span className={`inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border ${color}`}>
                                            <Icon size={21} aria-hidden="true" />
                                        </span>
                                        <h2 className="min-w-0 text-lg font-semibold leading-7 text-gray-950 [overflow-wrap:anywhere]">{_t(title)}</h2>
                                    </div>
                                    <p className="mt-3 min-h-12 break-words text-sm leading-6 text-gray-500">{detail}</p>
                                    <div className="mt-auto flex items-center justify-between gap-3 border-t border-[#edf1f7] pt-4 text-sm font-semibold text-[#355da8]">
                                        <span>{_t(action)}</span>
                                        {external ? <ArrowUpRight size={18} aria-hidden="true" className="shrink-0" /> : <ArrowRight size={18} aria-hidden="true" className="shrink-0" />}
                                    </div>
                                </Component>
                            );
                        })}
                    </div>
                </section>

                <footer className="border-t border-[#dfe7f5] bg-white">
                    <div className="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs leading-5 text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <p>{_t("Алматинский технологический университет")}</p>
                        <span className="font-medium text-[#355da8]">vacancy.atu.kz</span>
                    </div>
                </footer>
            </div>
        </Layout>
    );
}
