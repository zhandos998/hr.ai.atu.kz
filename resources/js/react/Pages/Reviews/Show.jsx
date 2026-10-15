import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import ApplicationSummary from "../../Components/ApplicationSummary";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../Components/Form";
import { reviewConfig } from "../../reviewConfig";

export default function ReviewShow() {
    const { t: _t } = useTextI18n();
    const { reviewType, applicationId } = usePage().props;
    const config = reviewConfig(reviewType);
    const [application, setApplication] = useState(null);
    const [draft, setDraft] = useState({});
    const [files, setFiles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [pdfLoading, setPdfLoading] = useState(false);
    const [error, setError] = useState("");
    const [legalComment, setLegalComment] = useState("");
    const sync = (item) => {
        setApplication(item);
        setDraft(
            Object.fromEntries(
                config.fields.map((field) => [
                    field.key,
                    item.pps_profile?.[field.key] || "",
                ]),
            ),
        );
        setLegalComment(item.compliance_comment || "");
        setFiles([]);
    };
    const load = async () => {
        setLoading(true);
        setError("");
        try {
            const { data } = await axios.get(
                `${config.apiPath}/${applicationId}`,
            );
            sync(data);
        } catch (requestError) {
            setError(errorText(requestError, "Ошибка при загрузке заявки."));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, [applicationId, config.apiPath]);
    const save = async () => {
        setSaving(true);
        setError("");
        const payload = new FormData();
        config.fields.forEach((field) =>
            payload.append(field.key, draft[field.key] || ""),
        );
        files.forEach((file) => payload.append("documents[]", file));
        try {
            const { data } = await axios.post(
                `${config.apiPath}/${applicationId}/${config.saveSuffix}`,
                payload,
            );
            sync(
                data.application ||
                    (await axios.get(`${config.apiPath}/${applicationId}`))
                        .data,
            );
            return true;
        } catch (requestError) {
            setError(errorText(requestError));
            return false;
        } finally {
            setSaving(false);
        }
    };
    const removeDocument = async (documentId) => {
        if (!window.confirm("Удалить документ?")) return;
        try {
            await axios.delete(
                `${config.apiPath}/${applicationId}/${config.deleteSuffix}/${documentId}`,
            );
            await load();
        } catch (requestError) {
            window.alert(errorText(requestError));
        }
    };
    const setLegalStatus = async (status) => {
        if (application?.vacancy?.type === "pps" && !(await save())) return;
        setSaving(true);
        setError("");
        try {
            await axios.put(
                `/api/lawyer/applications/${applicationId}/corruption-status`,
                { status_code: status, comment: legalComment.trim() || null },
            );
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const openPdf = async () => {
        if (config.pdfField && !String(draft[config.pdfField] || "").trim()) {
            window.alert("Заполните заключение перед генерацией PDF.");
            return;
        }
        setPdfLoading(true);
        const preview = window.open("", "_blank");
        try {
            if (!config.compliance && !(await save())) {
                preview?.close();
                return;
            }
            const { data } = await axios.get(
                `${config.apiPath}/${applicationId}/${config.pdfSuffix}`,
                { responseType: "blob" },
            );
            const url = URL.createObjectURL(
                new Blob([data], { type: "application/pdf" }),
            );
            if (preview) preview.location.href = url;
            else window.open(url, "_blank");
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (requestError) {
            preview?.close();
            window.alert(errorText(requestError, "Ошибка при генерации PDF."));
        } finally {
            setPdfLoading(false);
        }
    };
    const isStaffCompliance =
        config.compliance && application?.vacancy?.type !== "pps";
    const existingDocuments = config.documentsField
        ? application?.pps_profile?.[config.documentsField] || []
        : [];
    return (
        <Layout requireAuth title={_t(`${config.title}: заявка №${applicationId}`)}>
            <Head title={_t(`${config.title} №${applicationId}`)} />
            <div className="mx-auto max-w-6xl space-y-5 px-4 py-8">
                <Link
                    href={config.basePath}
                    className="text-sm font-semibold text-[#174a9c]"
                >{_t("← Вернуться к списку")}</Link>
                <Alert>{_t(error)}</Alert>
                {loading ? (
                    <div className="rounded-md border border-gray-200 bg-white py-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    application && (
                        <>
                            <ApplicationSummary application={application} />
                            <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                                <div className="flex flex-col gap-3 border-b border-gray-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="font-bold text-[#174a9c]">
                                            {_t(isStaffCompliance
                                                ? "Юридическая проверка"
                                                : config.sectionTitle)}
                                        </h2>
                                        <p className="mt-1 text-sm text-gray-500">{_t("Сохраните заполненные данные перед завершением проверки.")}</p>
                                    </div>
                                    {config.pdfSuffix && (
                                        <button
                                            type="button"
                                            className={secondaryButtonClass}
                                            disabled={pdfLoading}
                                            onClick={openPdf}
                                        >
                                            {_t(pdfLoading
                                                ? "Подготовка PDF..."
                                                : config.compliance
                                                  ? "Открыть PDF ответа юриста"
                                                  : "Сгенерировать PDF")}
                                        </button>
                                    )}
                                </div>
                                {isStaffCompliance ? (
                                    <div className="mt-5 space-y-4">
                                        <textarea
                                            className={inputClass}
                                            rows="5"
                                            placeholder={_t("Комментарий юридической проверки")}
                                            value={legalComment}
                                            onChange={(event) =>
                                                setLegalComment(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <div className="flex flex-wrap justify-end gap-3">
                                            <button
                                                type="button"
                                                disabled={
                                                    saving ||
                                                    application.documents_status !==
                                                        "accepted"
                                                }
                                                className="rounded-md border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 disabled:opacity-50"
                                                onClick={() =>
                                                    setLegalStatus("flagged")
                                                }
                                            >{_t("Выявлена")}</button>
                                            <button
                                                type="button"
                                                disabled={
                                                    saving ||
                                                    application.documents_status !==
                                                        "accepted"
                                                }
                                                className="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                                onClick={() =>
                                                    setLegalStatus("clear")
                                                }
                                            >{_t("Не выявлена")}</button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="mt-5 space-y-4">
                                        {config.fields.map((field) => (
                                            <label
                                                key={field.key}
                                                className="block text-sm font-semibold text-gray-700"
                                            >
                                                {_t(field.label)}
                                                <textarea
                                                    className={inputClass}
                                                    rows={
                                                        field.key.includes(
                                                            "conclusion",
                                                        )
                                                            ? 5
                                                            : 4
                                                    }
                                                    value={
                                                        draft[field.key] || ""
                                                    }
                                                    onChange={(event) =>
                                                        setDraft({
                                                            ...draft,
                                                            [field.key]:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </label>
                                        ))}
                                        {config.documentsField && (
                                            <div>
                                                <label className="block text-sm font-semibold text-gray-700">{_t("Подтверждающие документы")}<input
                                                        type="file"
                                                        multiple
                                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                                        onChange={(event) =>
                                                            setFiles(
                                                                Array.from(
                                                                    event.target
                                                                        .files ||
                                                                        [],
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </label>
                                                {files.length > 0 && (
                                                    <ul className="mt-2 space-y-1 text-sm text-gray-600">
                                                        {files.map(
                                                            (file, index) => (
                                                                <li
                                                                    key={`${file.name}-${index}`}
                                                                >
                                                                    {_t(index + 1)}.{" "}
                                                                    {file.name}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                                {existingDocuments.length >
                                                    0 && (
                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        {existingDocuments.map(
                                                            (document) => (
                                                                <span
                                                                    key={
                                                                        document.id
                                                                    }
                                                                    className="inline-flex items-center gap-1"
                                                                >
                                                                    <a
                                                                        href={
                                                                            document.url
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="rounded-md border border-[#005eb8] px-3 py-2 text-sm text-[#174a9c]"
                                                                    >
                                                                        {
                                                                            document.name
                                                                        }
                                                                    </a>
                                                                    <button
                                                                        type="button"
                                                                        className="flex h-8 w-8 items-center justify-center rounded-md border border-red-300 text-red-600"
                                                                        onClick={() =>
                                                                            removeDocument(
                                                                                document.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        ×
                                                                    </button>
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        <div className="flex justify-end">
                                            <button
                                                type="button"
                                                className={primaryButtonClass}
                                                disabled={saving}
                                                onClick={save}
                                            >
                                                {_t(saving
                                                    ? "Сохранение..."
                                                    : "Сохранить")}
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {config.compliance && !isStaffCompliance && (
                                    <div className="mt-5 space-y-3 border-t border-gray-200 pt-4">
                                        <textarea className={inputClass} rows="3" placeholder={_t("Комментарий юридической проверки")} value={legalComment} onChange={(event) => setLegalComment(event.target.value)} />
                                        <div className="flex flex-wrap justify-end gap-3">
                                            <button type="button" className={secondaryButtonClass} disabled={saving || application.documents_status !== "accepted"} onClick={() => setLegalStatus("flagged")}>{_t("Выявлена")}</button>
                                            <button type="button" className={primaryButtonClass} disabled={saving || application.documents_status !== "accepted"} onClick={() => setLegalStatus("clear")}>{_t("Не выявлена")}</button>
                                        </div>
                                    </div>
                                )}
                            </section>
                        </>
                    )
                )}
            </div>
        </Layout>
    );
}
