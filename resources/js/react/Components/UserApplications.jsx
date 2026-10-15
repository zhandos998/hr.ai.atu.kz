import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useState } from "react";
import axios from "axios";
import UploadDocsModal from "./UploadDocsModal";
import { errorText } from "./Form";

const stages = ["resume", "documents", "compliance", "hiring"];
const stageTitles = {
    resume: "Статус резюме",
    documents: "Статус документов",
    compliance: "Статус коррупции",
    hiring: "Принятие на работу",
};
const labels = {
    pending: "На рассмотрении",
    accepted: "Принято",
    rejected: "Отклонено",
    awaiting_upload: "Ожидается загрузка",
    uploaded: "Ожидается загрузка",
    not_started: "Не начато",
    clear: "Не выявлена",
    flagged: "Выявлена",
    voting: "Голосование",
    hired: "Взять на работу",
    hired_1_year: "Взять на 1 год",
    hired_3_year: "Взять на 3 года",
};
const statusClass = (status) =>
    status === "accepted" ||
    status === "clear" ||
    String(status).startsWith("hired")
        ? "bg-emerald-100 text-emerald-800"
        : status === "rejected" || status === "flagged"
          ? "bg-red-100 text-red-800"
          : status === "voting"
            ? "bg-amber-100 text-amber-800"
            : "bg-slate-100 text-slate-700";
const docLabels = {
    diploma: "Дипломы и сертификаты",
    recommendation_letter: "Рекомендательное письмо",
    scientific_works: "Список научных трудов",
    articles: "Список научных трудов",
    other: "Другое",
};
const baseType = (value) => String(value).replace(/_\d+$/, "");
const docLabel = (type) => {
    const match = String(type).match(/^(.*)_(\d+)$/);
    const base = match?.[1] || type;
    return `${docLabels[base] || base}${match ? ` #${match[2]}` : ""}`;
};

export default function UserApplications() {
    const { t: _t } = useTextI18n();
    const [applications, setApplications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(null);
    const load = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get("/api/applications");
            setApplications(data);
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, []);
    const canManage = (app) =>
        app.resume_status === "accepted" &&
        ["awaiting_upload", "uploaded", "rejected"].includes(
            app.documents_status,
        ) &&
        app.compliance_status === "not_started" &&
        !["hired", "rejected"].includes(app.hiring_status);
    const removeDocument = async (applicationId, documentId) => {
        if (!window.confirm("Удалить этот документ?")) return;
        try {
            await axios.delete(
                `/api/applications/${applicationId}/documents/${documentId}`,
            );
            await load();
        } catch (requestError) {
            window.alert(
                errorText(requestError, "Не удалось удалить документ."),
            );
        }
    };
    return (
        <div className="mx-auto max-w-7xl px-4 py-8">
            <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-blue-100 bg-[#edf4ff] px-5 py-4">
                    <h2 className="font-bold text-[#174a9c]">{_t("Мои заявки")}</h2>
                </div>
                {loading ? (
                    <div className="p-10 text-center text-sm text-gray-500">{_t("Загрузка заявок...")}</div>
                ) : applications.length === 0 ? (
                    <div className="p-10 text-center text-sm text-gray-500">{_t("У вас пока нет заявок.")}</div>
                ) : (
                    <div className="space-y-4 p-5">
                        {applications.map((app) => (
                            <article
                                key={app.id}
                                className="rounded-md border border-gray-200 p-4"
                            >
                                <div className="flex flex-col justify-between gap-3 lg:flex-row">
                                    <div>
                                        <h3 className="text-lg font-bold text-[#174a9c]">
                                            {_t(app.vacancy?.title ||
                                                app.pps_profile
                                                    ?.position_name ||
                                                "Заявка")}
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600">{_t("Тип:")}{" "}
                                            {_t(app.vacancy?.type === "pps"
                                                ? "ППС"
                                                : "АУП")}{" "}{_t("· Дата:")}{" "}
                                            {_t(new Date(
                                                app.created_at,
                                            ).toLocaleDateString("ru-RU"))}
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    {stages.map((stage) => {
                                        const status = app[`${stage}_status`];
                                        return (
                                            <div
                                                key={stage}
                                                className="rounded-md border border-gray-200 bg-gray-50 p-3"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-sm font-semibold">
                                                        {_t(stageTitles[stage])}
                                                    </span>
                                                    <span
                                                        className={`rounded-full px-2 py-1 text-xs font-semibold ${statusClass(status)}`}
                                                    >
                                                        {_t(labels[status] ||
                                                            status ||
                                                            "—")}
                                                    </span>
                                                </div>
                                                <p className="mt-2 min-h-10 whitespace-pre-wrap text-sm text-gray-600">
                                                    {_t(app[`${stage}_comment`] ||
                                                        "Комментарий отсутствует.")}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    {app.resume_url && (
                                        <a
                                            href={app.resume_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="rounded-md border border-[#005eb8] px-3 py-2 text-sm font-semibold text-[#174a9c] hover:bg-blue-50"
                                        >{_t("Резюме")}</a>
                                    )}
                                    {Object.entries(
                                        app.documents_map || {},
                                    ).map(([type, document]) => (
                                        <span
                                            key={type}
                                            className="inline-flex items-center gap-1"
                                        >
                                            <a
                                                href={document.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="rounded-md border border-emerald-500 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                                            >
                                                {_t(docLabel(type))}
                                            </a>
                                            {canManage(app) && document.id && (
                                                <button
                                                    type="button"
                                                    className="flex h-8 w-8 items-center justify-center rounded-md border border-red-300 text-red-600 hover:bg-red-50"
                                                    title={_t("Удалить документ")}
                                                    onClick={() =>
                                                        removeDocument(
                                                            app.id,
                                                            document.id,
                                                        )
                                                    }
                                                >
                                                    ×
                                                </button>
                                            )}
                                        </span>
                                    ))}
                                </div>
                                {canManage(app) && (
                                    <div className="mt-4">
                                        <button
                                            type="button"
                                            className="rounded-md bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white"
                                            onClick={() =>
                                                setModal({
                                                    application: app,
                                                    mode: Object.keys(
                                                        app.documents_map || {},
                                                    ).length
                                                        ? "replace"
                                                        : "create",
                                                })
                                            }
                                        >
                                            {_t(Object.keys(
                                                app.documents_map || {},
                                            ).length
                                                ? "Дополнить документы"
                                                : "Загрузить документы")}
                                        </button>
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </section>
            {modal && (
                <UploadDocsModal
                    application={modal.application}
                    mode={modal.mode}
                    onClose={() => setModal(null)}
                    onSaved={async () => {
                        setModal(null);
                        await load();
                    }}
                />
            )}
        </div>
    );
}
