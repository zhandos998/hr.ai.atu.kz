import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import { useAuth } from "../../AuthContext";
import PersonnelArchiveButton from "../../Components/PersonnelArchiveButton";
import PersonnelCommission from "../../Components/PersonnelCommission";

const errorText = (error) =>
    error?.response?.data?.message ||
    Object.values(error?.response?.data?.errors || {})?.[0]?.[0] ||
    "Не удалось выполнить операцию.";
const actionLabel = (type) =>
    ({
        hire: "Приём на работу",
        dismissal: "Увольнение",
        transfer: "Перевод",
        maternity_leave: "Отпуск по беременности и родам",
    })[type] || type;
const statusLabel = (status) =>
    ({
        pending: "На согласовании",
        approved: "Согласовано",
        rejected: "Отклонено",
    })[status] || status;
const statusClass = (status) =>
    ({
        pending: "bg-amber-100 text-amber-800",
        approved: "bg-emerald-100 text-emerald-800",
        rejected: "bg-red-100 text-red-800",
    })[status] || "bg-gray-100 text-gray-700";
const decisionLabel = (decision) =>
    ({
        pending: "Ожидает решения",
        approved: "Разрешено",
        rejected: "Отказано",
    })[decision] || decision;
const decisionClass = (decision) =>
    ({
        pending: "bg-gray-100 text-gray-700",
        approved: "bg-emerald-50 text-emerald-700",
        rejected: "bg-red-50 text-red-700",
    })[decision] || "bg-gray-100 text-gray-700";
const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat("ru-RU", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "—";
const formatSalary = (value) =>
    value === null
        ? "—"
        : `${new Intl.NumberFormat("ru-RU").format(Number(value))} ₸`;
const formatFileSize = (value) => {
    const bytes = Number(value || 0);
    if (bytes < 1024) return `${bytes} Б`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} КБ`;
    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
};

function DataItem({ label, children, wide = false }) {
    const { t: _t } = useTextI18n();
    return (
        <div className={wide ? "sm:col-span-2" : ""}>
            <dt className="text-xs font-semibold uppercase text-gray-400">
                {_t(label)}
            </dt>
            <dd className="mt-1 whitespace-pre-wrap text-sm font-medium text-gray-900">
                {_t(children)}
            </dd>
        </div>
    );
}

export default function PersonnelRequestDetails() {
    const { t: _t } = useTextI18n();
    const { admin, requestId } = usePage().props;
    const { user } = useAuth();
    const [item, setItem] = useState(null);
    const [loading, setLoading] = useState(true);
    const [savingDecision, setSavingDecision] = useState(false);
    const [decisionComment, setDecisionComment] = useState("");
    const [decisionError, setDecisionError] = useState("");
    const [actionError, setActionError] = useState("");

    const ownApproval = useMemo(() => {
        if (admin || !item) return null;
        return item.approvals.find(
            (approval) => Number(approval.user_id) === Number(user?.id),
        );
    }, [admin, item, user?.id]);
    const canViewFinancialData = Boolean(
        admin || item?.can_view_financial_data,
    );

    const backBase = admin
        ? "/admin/personnel-requests"
        : "/personnel/requests";
    const backPath = `${backBase}${window.location.search}`;
    const actions = admin ? (
        <>
        {item && <PersonnelArchiveButton item={item} onChanged={setItem} onError={setActionError} />}
        {!item?.archived_at && <Link
            href={`/admin/personnel-requests/${requestId}/edit`}
            className="rounded-md bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#244f9d]"
        >{_t("Изменить")}</Link>}
        </>
    ) : null;

    const loadRequest = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `/api/personnel/requests/${requestId}`,
            );
            setItem(response.data);
            const currentApproval = response.data.approvals.find(
                (approval) => Number(approval.user_id) === Number(user?.id),
            );
            setDecisionComment(currentApproval?.comment || "");
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (user?.id) loadRequest();
    }, [requestId, user?.id]);

    const saveDecision = async (decision) => {
        setDecisionError("");
        if (decision === "rejected" && !decisionComment.trim()) {
            setDecisionError("При отказе укажите комментарий.");
            return;
        }

        setSavingDecision(true);
        try {
            const response = await axios.post(
                `/api/personnel/requests/${requestId}/decision`,
                {
                    decision,
                    comment: decisionComment.trim(),
                },
            );
            setItem(response.data.request);
        } catch (error) {
            setDecisionError(errorText(error));
        } finally {
            setSavingDecision(false);
        }
    };

    const openDocument = async (document) => {
        const previewable = document.mime_type === "application/pdf" || ["image/jpeg", "image/png"].includes(document.mime_type);
        const preview = previewable ? window.open("", "_blank") : null;
        if (preview) preview.opener = null;
        try {
            const response = await axios.get(
                `/api/personnel/requests/${requestId}/documents/${document.id}`,
                { responseType: "blob" },
            );
            const blobUrl = URL.createObjectURL(response.data);
            if (preview) {
                preview.location.href = blobUrl;
            } else {
                const link = window.document.createElement("a");
                link.href = blobUrl;
                link.download = document.original_name;
                link.click();
            }
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        } catch (error) {
            preview?.close();
            window.alert(errorText(error));
        }
    };

    return (
        <Layout
            admin={admin}
            title={_t(`Кадровая заявка №${requestId}`)}
            description={_t("Данные сотрудника, документы и решения согласующих.")}
            actions={actions}
        >
            <Head title={_t(`Кадровая заявка №${requestId}`)} />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
                <Link
                    href={backPath}
                    className="inline-flex text-sm font-semibold text-[#005eb8] hover:text-[#174a9c]"
                >{_t("← Вернуться к списку")}</Link>

                {actionError && <p role="alert" className="rounded-md bg-red-50 p-4 text-sm text-red-700">{_t(actionError)}</p>}
                {item?.archived_at && <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{_t("Кадровый процесс в архиве. Согласование приостановлено.")} <span>{formatDateTime(item.archived_at)}</span></div>}

                {loading && (
                    <div className="rounded-md border border-gray-200 bg-white px-5 py-12 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                )}

                {!loading && item && (
                    <>
                        <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div className="flex flex-col gap-3 border-b border-blue-100 bg-[#edf4ff] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 className="text-lg font-bold text-[#174a9c]">
                                        {item.employee_name}
                                    </h2>
                                    <p className="mt-1 text-sm text-gray-600">
                                        {_t(actionLabel(item.action_type))}
                                    </p>
                                </div>
                                <span
                                    className={`inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-bold ${statusClass(item.status)}`}
                                >
                                    {_t(statusLabel(item.status))}
                                </span>
                            </div>
                            <dl className="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2">
                                <DataItem label={_t("Подразделение")}>
                                    {_t(item.department?.name || "—")}
                                </DataItem>
                                <DataItem label={_t("Должность")}>
                                    {_t(item.position?.name || "Не указана")}
                                </DataItem>
                                {item.action_type === "transfer" && (
                                    <DataItem label={_t("Новое подразделение")}>
                                        {_t(item.target_department?.name || "—")}
                                    </DataItem>
                                )}
                                {item.action_type === "transfer" && (
                                    <DataItem label={_t("Новая должность")}>
                                        {_t(item.target_position?.name ||
                                            "Не указана")}
                                    </DataItem>
                                )}
                                {item.action_type === "hire" &&
                                    canViewFinancialData && (
                                    <DataItem label={_t("Заработная плата")}>
                                        {_t(formatSalary(item.salary))}
                                    </DataItem>
                                )}
                                {item.action_type === "hire" && (
                                    <DataItem label={_t("Стаж работы")}>
                                        {_t(item.work_experience || "—")}
                                    </DataItem>
                                )}
                                <DataItem label={_t("Дата создания")}>
                                    {_t(formatDateTime(item.created_at))}
                                </DataItem>
                                {item.details && (
                                    <DataItem
                                        label={_t("Дополнительная информация")}
                                        wide
                                    >
                                        {_t(item.details)}
                                    </DataItem>
                                )}
                            </dl>
                        </section>

                        {canViewFinancialData && <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-blue-100 bg-[#edf4ff] px-5 py-4">
                                <h2 className="font-bold text-[#174a9c]">{_t("Документы")}</h2>
                            </div>
                            {item.documents.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-gray-500">{_t("Документы не приложены.")}</div>
                            ) : (
                                item.documents.map((document) => (
                                    <button
                                        key={document.id}
                                        type="button"
                                        className="flex w-full items-center justify-between gap-4 border-b border-gray-100 px-5 py-3 text-left transition last:border-0 hover:bg-gray-50"
                                        onClick={() => openDocument(document)}
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-semibold text-[#005eb8]">
                                                {_t(document.original_name)}
                                            </span>
                                            <span className="mt-0.5 block text-xs text-gray-500">
                                                {_t(formatFileSize(document.size))}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-xs font-semibold text-gray-500">{_t("Открыть")}</span>
                                    </button>
                                ))
                            )}
                        </section>}

                        <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-blue-100 bg-[#edf4ff] px-5 py-4">
                                <h2 className="font-bold text-[#174a9c]">{_t("Согласование")}</h2>
                            </div>
                            {item.approvals.map((approval) => (
                                <div
                                    key={approval.id}
                                    className="grid gap-3 border-b border-gray-100 px-5 py-4 last:border-0 md:grid-cols-[1fr_160px_1.5fr] md:items-start"
                                >
                                    <div>
                                        <div className="font-semibold text-gray-900">
                                            {approval.user?.name}
                                        </div>
                                        <div className="mt-1 text-xs text-gray-500">
                                            {approval.user?.email}
                                        </div>
                                    </div>
                                    <div>
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${decisionClass(approval.decision)}`}
                                        >
                                            {_t(decisionLabel(approval.decision))}
                                        </span>
                                        {approval.decided_at && (
                                            <div className="mt-1 text-xs text-gray-500">
                                                {_t(formatDateTime(
                                                    approval.decided_at,
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    <div className="whitespace-pre-wrap text-sm text-gray-600">
                                        {_t(approval.comment ||
                                            "Комментарий не указан")}
                                    </div>
                                </div>
                            ))}
                        </section>

                        {admin && !item.archived_at && <PersonnelCommission item={item} onChanged={setItem} />}

                        {ownApproval && !item.archived_at && (
                            <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                                <h2 className="font-bold text-gray-900">{_t("Ваше решение")}</h2>
                                <p className="mt-1 text-sm text-gray-500">{_t("При отказе комментарий обязателен. Решение можно изменить.")}</p>
                                <textarea
                                    value={decisionComment}
                                    rows="4"
                                    placeholder={_t("Комментарий")}
                                    className="mt-4 w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    onChange={(event) =>
                                        setDecisionComment(event.target.value)
                                    }
                                />
                                {decisionError && (
                                    <div className="mt-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                        {_t(decisionError)}
                                    </div>
                                )}
                                <div className="mt-4 flex flex-wrap justify-end gap-3">
                                    <button
                                        type="button"
                                        disabled={savingDecision}
                                        className="rounded-md border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-60"
                                        onClick={() => saveDecision("rejected")}
                                    >{_t("Отказать")}</button>
                                    <button
                                        type="button"
                                        disabled={savingDecision}
                                        className="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                                        onClick={() => saveDecision("approved")}
                                    >{_t("Разрешаю")}</button>
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>
        </Layout>
    );
}
