import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { Plus, Pencil } from "lucide-react";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

export default function Competitions() {
    const { t: _t } = useTextI18n();
    const [filters, setFilters] = useState({ q: "", type: "" });
    const [applied, setApplied] = useState(filters);
    const [result, setResult] = useState({
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const load = async (page = 1, next = applied) => {
        setLoading(true);
        setError("");
        try {
            const { data } = await axios.get("/api/admin/competitions", {
                params: { ...next, page },
            });
            setResult(data);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, []);
    return (
        <Layout
            admin
            title={_t("Конкурсы")}
            actions={
                <Link
                    href="/admin/competitions/create"
                    className={`${primaryButtonClass} inline-flex items-center gap-2`}
                >
                    <Plus size={16} />{_t("Создать конкурс")}</Link>
            }
        >
            <Head title={_t("Конкурсы")} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-6">
                <Alert>{_t(error)}</Alert>
                <form
                    className="grid items-center gap-3 md:grid-cols-[minmax(0,1fr)_180px_auto_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setApplied(filters);
                        load(1, filters);
                    }}
                >
                    <input
                        aria-label={_t("Название конкурса")}
                        className={`${inputClass} mt-0 min-w-0`}
                        placeholder={_t("Название конкурса")}
                        value={filters.q}
                        onChange={(event) =>
                            setFilters({ ...filters, q: event.target.value })
                        }
                    />
                    <select
                        aria-label={_t("Тип конкурса")}
                        className={`${inputClass} mt-0`}
                        value={filters.type}
                        onChange={(event) =>
                            setFilters({ ...filters, type: event.target.value })
                        }
                    >
                        <option value="">{_t("Все конкурсы")}</option>
                        <option value="pps">{_t("ППС")}</option>
                        <option value="staff">{_t("АУП")}</option>
                    </select>
                    <button className={primaryButtonClass} disabled={loading}>{_t("Поиск")}</button>
                    <button
                        type="button"
                        className={secondaryButtonClass}
                        disabled={loading}
                        onClick={() => {
                            const next = { q: "", type: "" };
                            setFilters(next);
                            setApplied(next);
                            load(1, next);
                        }}
                    >{_t("Сброс")}</button>
                </form>
                <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    {loading ? (
                        <div className="p-12 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[650px] text-left text-sm">
                                <thead className="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">{_t("Конкурс")}</th>
                                        <th className="px-4 py-3">{_t("Тип")}</th>
                                        <th className="px-4 py-3">{_t("Конкурсанты")}</th>
                                        <th className="px-4 py-3">{_t("Создан")}</th>
                                        <th className="px-4 py-3 text-right">{_t("Действия")}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {result.data.map((competition) => (
                                        <tr
                                            key={competition.id}
                                            className="hover:bg-gray-50"
                                        >
                                            <td className="max-w-md break-words px-4 py-4">
                                                <Link
                                                    href={`/admin/competitions/${competition.id}`}
                                                    className="font-semibold text-[#005eb8] hover:underline"
                                                >
                                                    {_t(competition.title)}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={`rounded px-2 py-1 text-xs ${competition.type === "pps" ? "bg-blue-50 text-blue-700" : "bg-emerald-50 text-emerald-700"}`}
                                                >
                                                    {_t(competition.type === "pps"
                                                        ? "ППС"
                                                        : "АУП")}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className="font-medium tabular-nums">
                                                    {
                                                        _t(competition.applications_count)
                                                    }
                                                </span>
                                                <span className="ml-2 text-xs text-gray-400">{_t("В архиве:")}{" "}
                                                    {_t(competition.applications_count -
                                                        competition.active_applications_count)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-gray-500">
                                                {_t(new Date(
                                                    competition.created_at,
                                                ).toLocaleDateString("ru-RU"))}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={`/admin/competitions/${competition.id}`}
                                                        className="font-medium text-[#005eb8]"
                                                    >{_t("Открыть")}</Link>
                                                    <Link
                                                        href={`/admin/competitions/${competition.id}/edit`}
                                                        title={_t("Изменить конкурс")}
                                                        aria-label={_t(`Изменить: ${competition.title}`)}
                                                        className="rounded p-2 text-gray-500 hover:bg-blue-50"
                                                    >
                                                        <Pencil size={16} />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!result.data.length && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="p-12 text-center text-gray-500"
                                            >{_t("Конкурсы не найдены.")}</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4 text-sm text-gray-500">
                        <span>{_t("Всего:")}{_t(result.total)}</span>
                        <div className="flex items-center gap-2">
                            <button
                                className={secondaryButtonClass}
                                disabled={loading || result.current_page <= 1}
                                onClick={() => load(result.current_page - 1)}
                            >{_t("Назад")}</button>
                            <span>
                                {_t(result.current_page)} / {_t(result.last_page)}
                            </span>
                            <button
                                className={secondaryButtonClass}
                                disabled={
                                    loading ||
                                    result.current_page >= result.last_page
                                }
                                onClick={() => load(result.current_page + 1)}
                            >{_t("Вперёд")}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
