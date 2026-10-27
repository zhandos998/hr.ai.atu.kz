import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useRef, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import { useAuth } from "../../AuthContext";
import PersonnelArchiveButton from "../../Components/PersonnelArchiveButton";
import { Archive, ArrowLeft } from 'lucide-react';
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
    const [error, setError] = useState('');
    const [filters, setFilters] = useState(() => {
        const params = new URLSearchParams(window.location.search);
        return { q: params.get('q') || '', status: params.get('status') || '', action_type: params.get('action_type') || '', archived: params.get('archived') === '1' ? '1' : '0' };
    });
    const [draft, setDraft] = useState(filters);
    const latestLoad = useRef(0);

    const load = async (page = 1, activeFilters = filters) => {
        const sequence = ++latestLoad.current;
        setLoading(true);
        setError('');
        try {
            const { data } = await axios.get("/api/personnel/requests", {
                params: { ...activeFilters, page },
            });
            if (sequence !== latestLoad.current) return;
            if (page > data.meta.last_page) return load(data.meta.last_page, activeFilters);
            setRequests(data.data);
            setMeta(data.meta);
            const params = new URLSearchParams({ ...activeFilters, page: String(page) });
            window.history.replaceState(window.history.state, '', `${window.location.pathname}?${params}`);
        } catch (error) {
            if (sequence === latestLoad.current) setError(errorText(error));
        } finally {
            if (sequence === latestLoad.current) setLoading(false);
        }
    };

    useEffect(() => {
        load(Math.max(1, Number(new URLSearchParams(window.location.search).get('page')) || 1));
        return () => { latestLoad.current += 1; };
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
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold text-gray-700">{_t(filters.archived === '1' ? 'Архивные кадровые процессы' : 'Кадровые процессы')} <span className="text-gray-400">({meta.total})</span></h2>
                    <button type="button" disabled={loading} className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 disabled:opacity-50" onClick={() => {
                        const next = { ...filters, archived: filters.archived === '1' ? '0' : '1' };
                        setFilters(next); setDraft((current) => ({ ...current, archived: next.archived })); load(1, next);
                    }}>{filters.archived === '1' ? <ArrowLeft size={16} /> : <Archive size={16} />}{_t(filters.archived === '1' ? 'К активным' : 'Архивные')}</button>
                </div>
                {error && <p role="alert" className="rounded-md bg-red-50 p-4 text-sm text-red-700">{_t(error)}</p>}
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
                                                        className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${statusClass(item.status)}`}
                                                    >
                                                        {_t(statusLabel(
                                                            item.status,
                                                        ))}
                                                    </span>
                                                    <div className="mt-1 whitespace-nowrap text-xs text-gray-500">
                                                        {`${approved} ${_t("из")} ${item.approvals.length}`}
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
                                                    <div className="flex items-center justify-end gap-3 whitespace-nowrap">
                                                        <Link
                                                            href={`${base}/${item.id}${window.location.search}`}
                                                            className="font-semibold text-[#174a9c]"
                                                        >{_t("Открыть")}</Link>
                                                        {admin && !item.archived_at && (
                                                            <Link
                                                                href={`${base}/${item.id}/edit`}
                                                                className="font-semibold text-gray-600"
                                                            >{_t("Изменить")}</Link>
                                                        )}
                                                        {admin && <PersonnelArchiveButton item={item} onChanged={() => load(meta.current_page)} onError={setError} />}
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
