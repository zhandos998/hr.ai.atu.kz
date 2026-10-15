import { useI18n as useTextI18n } from "../../I18nContext";
import React from "react";
import { Head, Link } from "@inertiajs/react";
import Layout from "../../Components/Layout";

const sections = [
    [
        "Конкурс и заявки",
        [
            {
                href: "/admin/applications",
                title: "Заявки",
                description:
                    "Кандидаты, документы, статусы и результаты конкурса.",
            },
            {
                href: "/admin/personnel-requests",
                title: "Кадровые процессы",
                description:
                    "Приём, перевод, увольнение и согласование кадровых решений.",
            },
        ],
    ],
    [
        "Справочник",
        [
            {
                href: "/admin/departments",
                title: "Департаменты",
                description: "Факультеты, кафедры и подразделения.",
            },
            {
                href: "/admin/positions",
                title: "Должности",
                description: "Должности, обязанности и требования.",
            },
            {
                href: "/admin/structure-tree",
                title: "Структура",
                description: "Иерархия подразделений и должностей.",
            },
        ],
    ],
    [
        "Доступ",
        [
            {
                href: "/admin/commission-members",
                title: "Комиссия",
                description: "Состав комиссий ППС и АУП.",
            },
            {
                href: "/admin/users",
                title: "Пользователи",
                description:
                    "Учётные записи, роли и вход от имени пользователя.",
            },
        ],
    ],
];

export default function Dashboard() {
    const { t: _t } = useTextI18n();
    return (
        <Layout
            admin
            title={_t("Панель администратора")}
            description={_t("Управление конкурсом и кадровыми процессами.")}
        >
            <Head title={_t("Панель администратора")} />
            <div className="mx-auto max-w-7xl space-y-8 px-4 py-8">
                {sections.map(([title, items]) => (
                    <section key={title}>
                        <h2 className="mb-3 text-sm font-bold uppercase text-gray-500">
                            {_t(title)}
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {items.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="rounded-md border border-blue-100 bg-white p-5 shadow-sm transition hover:border-[#005eb8] hover:shadow"
                                >
                                    <h3 className="font-bold text-[#174a9c]">
                                        {_t(item.title)}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-gray-600">
                                        {_t(item.description)}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </Layout>
    );
}
