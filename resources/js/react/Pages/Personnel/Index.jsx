import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import { useAuth } from "../../AuthContext";
import {
    actionLabel,
    actionOptions,
    errorText,
    formatDate,
    statusClass,
    statusLabel,
} from "../../personnel";

export default function PersonnelRequests() {
    const { t: _t } = useTextI18n();
    const { admin } = usePage().props;
    const { user } = useAuth();
    const canViewFinancialData = admin || user?.role === "economist";
    const [requests, setRequests] = useState([]);
    const [meta, setMeta] = useState({
        current_page: 1,
        last_page: 1,
        total: 0,
    });
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({
        q: "",
        status: "",
        action_type: "",
    });
    const [draft, setDraft] = useState(filters);

    const load = async (page = 1, activeFilters = filters) => {
        setLoading(true);
        try {
            const { data } = await axios.get("/api/personnel/requests", {
                params: { ...activeFilters, page },
            });
            setRequests(data.data);
            setMeta(data.meta);
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, []);

    const actions = admin ? (
        <>
            <Link
                href="/admin/personnel-requests/create"
                className="rounded-md bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#244f9d]"
            >{_t("Добавить заявку")}</Link>
        </>
    ) : null;

    return (
        <Layout
            admin={admin}
            title={_t(admin ? "Кадровые процессы" : "Кадровое согласование")}
            description={
                _t(admin
                    ? "Приём, увольнение, перевод и отпуск по беременности."
                    : "Заявки, в которых требуется ваше решение.")
            }
            actions={actions}
        >
            <Head
                title={_t(admin ? "Кадровые процессы" : "Кадровое согласование")}
            />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <form
                    className="grid gap-3 rounded-md border border-gray-200 bg-white p-4 md:grid-cols-[1fr_220px_200px_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setFilters(draft);
                        load(1, draft);
                    }}
                >
                    <input
                        value={draft.q}
                        className="rounded-md border border-gray-300 px-3 py-2.5 text-sm"
                        placeholder={_t("Сотрудник, подразделение или должность")}
                        onChange={(event) =>
                            setDraft({ ...draft, q: event.target.value })
                        }
                    />
                    <select
                        value={draft.action_type}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm"
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                action_type: event.target.value,
                            })
                        }
                    >
                        <option value="">{_t("Все действия")}</option>
                        {actionOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {_t(option.label)}
                            </option>
                        ))}
                    </select>
                    <select
                        value={draft.status}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm"
                        onChange={(event) =>
                            setDraft({ ...draft, status: event.target.value })
                        }
                    >
                        <option value="">{_t("Все статусы")}</option>
                        <option value="pending">{_t("На согласовании")}</option>
                        <option value="approved">{_t("Согласовано")}</option>
                        <option value="rejected">{_t("Отклонено")}</option>
                    </select>
                    <button className="rounded-md bg-[#005eb8] px-5 py-2.5 text-sm font-semibold text-white">{_t("Найти")}</button>
                </form>

                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    {loading ? (
                        <div className="p-12 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                    ) : requests.length === 0 ? (
                        <div className="p-12 text-center text-sm text-gray-500">{_t("Заявки не найдены.")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-[#edf4ff] text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-5 py-3">{_t("Сотрудник")}</th>
                                        <th className="px-5 py-3">{_t("Действие")}</th>
                                        <th className="px-5 py-3">{_t("Подразделение")}</th>
                                        <th className="px-5 py-3">{_t("Согласование")}</th>
                                        {canViewFinancialData && <th className="px-5 py-3">{_t("Документы")}</th>}
                                        <th className="px-5 py-3">{_t("Дата")}</th>
                                        <th className="px-5 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {requests.map((item) => {
                                        const approved = item.approvals.filter(
                                            (approval) =>
                                                approval.decision ===
                                                "approved",
                                        ).length;
                                        const base = admin
                                            ? "/admin/personnel-requests"
                                            : "/personnel/requests";
                                        return (
                                            <tr
                                                key={item.id}
                                                className="hover:bg-gray-50"
                                            >
                                                <td className="px-5 py-4">
                                                    <div className="font-semibold">
                                                        {item.employee_name}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {_t(item.position?.name ||
                                                            "Должность не указана")}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4">
                                                    {_t(actionLabel(
                                                        item.action_type,
                                                    ))}
                                                </td>
                                                <td className="max-w-xs px-5 py-4 text-gray-600">
                                                    {item.department?.name}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span
                                                        className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusClass(item.status)}`}
                                                    >
                                                        {_t(statusLabel(
                                                            item.status,
                                                        ))}
                                                    </span>
                                                    <div className="mt-1 text-xs text-gray-500">
                                                        {_t(approved)}{_t("из")}{" "}
                                                        {_t(item.approvals.length)}
                                                    </div>
                                                </td>
                                                {canViewFinancialData && <td className="px-5 py-4 text-gray-600">
                                                    {item.documents_count || 0}
                                                </td>}
                                                <td className="whitespace-nowrap px-5 py-4 text-gray-500">
                                                    {_t(formatDate(
                                                        item.created_at,
                                                    ))}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <div className="flex justify-end gap-3">
                                                        <Link
                                                            href={`${base}/${item.id}`}
                                                            className="font-semibold text-[#174a9c]"
                                                        >{_t("Открыть")}</Link>
                                                        {admin && (
                                                            <Link
                                                                href={`${base}/${item.id}/edit`}
                                                                className="font-semibold text-gray-600"
                                                            >{_t("Изменить")}</Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {meta.last_page > 1 && (
                        <div className="flex items-center justify-between border-t p-4 text-sm">
                            <span>{_t("Всего:")}{_t(meta.total)}</span>
                            <div className="flex items-center gap-2">
                                <button
                                    disabled={meta.current_page <= 1}
                                    className="rounded-md border px-3 py-1.5 disabled:opacity-40"
                                    onClick={() => load(meta.current_page - 1)}
                                >{_t("Назад")}</button>
                                <span>
                                    {_t(meta.current_page)} / {_t(meta.last_page)}
                                </span>
                                <button
                                    disabled={
                                        meta.current_page >= meta.last_page
                                    }
                                    className="rounded-md border px-3 py-1.5 disabled:opacity-40"
                                    onClick={() => load(meta.current_page + 1)}
                                >{_t("Далее")}</button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </Layout>
    );
}
