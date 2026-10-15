import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import { inputClass } from "../../Components/Form";
import { reviewConfig } from "../../reviewConfig";

export default function ReviewQueue() {
    const { t: _t } = useTextI18n();
    const config = reviewConfig(usePage().props.reviewType);
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState("");
    useEffect(() => {
        axios
            .get(config.apiPath)
            .then(({ data }) => setItems(data))
            .catch((error) =>
                window.alert(
                    error?.response?.data?.message ||
                        "Ошибка при загрузке заявок.",
                ),
            )
            .finally(() => setLoading(false));
    }, [config.apiPath]);
    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        return items.filter(
            (item) =>
                !query ||
                [
                    item.user?.name,
                    item.user?.email,
                    item.vacancy?.title,
                    item.pps_profile?.desired_position,
                    item.pps_profile?.faculty_name,
                    item.pps_profile?.department_name,
                ].some((value) =>
                    String(value || "")
                        .toLowerCase()
                        .includes(query),
                ),
        );
    }, [items, search]);
    const ready = (item) =>
        config.compliance && item.vacancy?.type !== "pps"
            ? ["clear", "flagged"].includes(item.compliance_status)
            : config.fields.some((field) =>
                  Boolean(item.pps_profile?.[field.key]),
              ) ||
              Boolean(
                  config.documentsField &&
                  item.pps_profile?.[config.documentsField]?.length,
              );
    return (
        <Layout
            requireAuth
            title={_t(config.title)}
            description={_t(config.description)}
        >
            <Head title={_t(config.title)} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <div className="flex flex-col gap-3 rounded-md border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <input
                        className={`${inputClass.replace("mt-1 ", "")} max-w-xl`}
                        value={search}
                        placeholder={_t("Поиск по кандидату, должности или подразделению")}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <div className="text-sm text-gray-500">{_t("Всего заявок:")}<strong>{_t(filtered.length)}</strong>
                    </div>
                </div>
                {loading ? (
                    <div className="py-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : filtered.length === 0 ? (
                    <div className="rounded-md border border-gray-200 bg-white py-12 text-center text-gray-500">{_t("Заявок для обработки пока нет.")}</div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {filtered.map((item) => (
                            <Link
                                key={item.id}
                                href={`${config.basePath}/${item.id}`}
                                className="rounded-md border border-gray-200 bg-white p-4 shadow-sm transition hover:border-[#005eb8] hover:shadow"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="text-xs font-bold uppercase text-[#174a9c]">
                                            {_t(item.vacancy?.type === "pps"
                                                ? "ППС"
                                                : "АУП")}
                                        </div>
                                        <h2 className="mt-1 truncate font-bold">
                                            {_t(item.pps_profile
                                                ?.desired_position ||
                                                item.vacancy?.title ||
                                                "Без названия")}
                                        </h2>
                                        <p className="mt-1 truncate text-sm text-gray-600">
                                            {_t(item.user?.name ||
                                                "Кандидат не указан")}
                                        </p>
                                    </div>
                                    <span
                                        className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${ready(item) ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700"}`}
                                    >
                                        {_t(ready(item)
                                            ? "Заполнено"
                                            : "Нужно заполнить")}
                                    </span>
                                </div>
                                <div className="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div className="rounded-md bg-gray-50 p-2">
                                        <span className="text-gray-400">{_t("Факультет")}</span>
                                        <div className="mt-1 line-clamp-2 font-medium">
                                            {_t(item.pps_profile?.faculty_name ||
                                                "—")}
                                        </div>
                                    </div>
                                    <div className="rounded-md bg-gray-50 p-2">
                                        <span className="text-gray-400">{_t("Кафедра")}</span>
                                        <div className="mt-1 line-clamp-2 font-medium">
                                            {_t(item.pps_profile
                                                ?.department_name || "—")}
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-4 flex justify-between text-sm">
                                    <span className="text-gray-400">
                                        ID: {_t(item.id)}
                                    </span>
                                    <span className="font-semibold text-[#174a9c]">{_t("Подробнее")}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </Layout>
    );
}
