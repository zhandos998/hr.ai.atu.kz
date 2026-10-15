import { useI18n as useTextI18n } from "../I18nContext";
import React, { useMemo, useState } from "react";
import axios from "axios";
import { errorText, primaryButtonClass, secondaryButtonClass } from "./Form";

const labels = {
    diploma: "Дипломы и сертификаты",
    recommendation_letter: "Рекомендательное письмо",
    scientific_works: "Список научных трудов",
    other: "Другое",
};
const accept = {
    diploma: ".pdf,.jpg,.jpeg,.png",
    recommendation_letter: ".pdf,.jpg,.jpeg,.png",
    scientific_works: ".pdf,.zip",
    other: ".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip",
};
const hints = {
    diploma: "PDF/JPG/PNG до 2 МБ каждый",
    recommendation_letter: "PDF/JPG/PNG до 2 МБ каждый",
    scientific_works: "PDF/ZIP до 5 МБ каждый",
    other: "PDF/DOC/DOCX/JPG/PNG/ZIP до 5 МБ каждый",
};

export default function UploadDocsModal({
    application,
    mode = "create",
    uploadUrl = "",
    onClose,
    onSaved,
}) {
    const { t: _t } = useTextI18n();
    const types =
        application?.vacancy?.type === "pps"
            ? ["recommendation_letter", "other"]
            : ["diploma", "recommendation_letter", "other"];
    const [selected, setSelected] = useState({
        diploma: [],
        recommendation_letter: [],
        scientific_works: [],
        other: [],
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState("");
    const canSave = useMemo(
        () => types.some((type) => selected[type].length),
        [selected, types],
    );
    const addFiles = (type, fileList) =>
        setSelected((current) => ({
            ...current,
            [type]: [...current[type], ...Array.from(fileList || [])],
        }));
    const remove = (type, index) =>
        setSelected((current) => ({
            ...current,
            [type]: current[type].filter((_, itemIndex) => itemIndex !== index),
        }));
    const hasUploaded = (type) =>
        Object.keys(application?.documents_map || {}).some(
            (key) =>
                String(key)
                    .replace(/_\d+$/, "")
                    .replace("articles", "scientific_works") === type,
        );
    const submit = async () => {
        if (!canSave) return;
        setSubmitting(true);
        setError("");
        const payload = new FormData();
        payload.append("mode", "append");
        types.forEach((type) =>
            selected[type].forEach((file) => payload.append(`${type}[]`, file)),
        );
        try {
            await axios.post(
                uploadUrl || `/api/applications/${application.id}/upload-docs`,
                payload,
            );
            onSaved();
        } catch (requestError) {
            setError(
                errorText(requestError, "Не удалось загрузить документы."),
            );
        } finally {
            setSubmitting(false);
        }
    };
    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 px-4 py-10"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <div className="w-full max-w-3xl overflow-hidden rounded-md border border-gray-200 bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b px-5 py-4">
                    <h2 className="text-lg font-bold text-[#174a9c]">
                        {_t(mode === "replace"
                            ? "Дополнить документы"
                            : "Загрузить документы")}
                    </h2>
                    <button
                        type="button"
                        className="flex h-9 w-9 items-center justify-center rounded-md text-2xl text-gray-500 hover:bg-gray-100"
                        aria-label={_t("Закрыть")}
                        onClick={onClose}
                    >
                        ×
                    </button>
                </div>
                <div className="grid gap-4 p-5 md:grid-cols-2">
                    {types.map((type) => (
                        <section
                            key={type}
                            className="rounded-md border border-gray-200 p-4"
                        >
                            <div className="flex min-h-12 items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-semibold">
                                        {_t(labels[type])}
                                    </h3>
                                    <p className="mt-1 text-xs text-gray-500">{_t("необязательно")}</p>
                                </div>
                                <span
                                    className={`rounded-full px-2 py-1 text-xs ${hasUploaded(type) ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-600"}`}
                                >
                                    {_t(hasUploaded(type)
                                        ? "Загружен"
                                        : "Не загружен")}
                                </span>
                            </div>
                            <label className="mt-3 flex min-h-24 cursor-pointer items-center justify-center rounded-md border-2 border-dashed border-gray-300 p-4 text-center text-sm text-gray-600 hover:border-[#005eb8] hover:bg-blue-50">
                                <input
                                    className="sr-only"
                                    type="file"
                                    multiple
                                    accept={accept[type]}
                                    onChange={(event) => {
                                        addFiles(type, event.target.files);
                                        event.target.value = "";
                                    }}
                                />
                                {_t(selected[type].length
                                    ? `${selected[type].length} файл(ов) выбрано`
                                    : "Выберите несколько файлов")}
                            </label>
                            <p className="mt-2 text-xs text-gray-500">
                                {_t(hints[type])}
                            </p>
                            {selected[type].length > 0 && (
                                <ul className="mt-3 space-y-1">
                                    {selected[type].map((file, index) => (
                                        <li
                                            key={`${file.name}-${index}`}
                                            className="flex items-center justify-between gap-2 rounded border border-gray-200 px-2 py-1 text-xs"
                                        >
                                            <span className="truncate">
                                                {_t(index + 1)}. {file.name}
                                            </span>
                                            <button
                                                type="button"
                                                className="text-red-600"
                                                onClick={() =>
                                                    remove(type, index)
                                                }
                                            >{_t("Убрать")}</button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
                {error && (
                    <div className="mx-5 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {_t(error)}
                    </div>
                )}
                <div className="flex items-center justify-between gap-4 border-t bg-gray-50 px-5 py-4">
                    <p className="text-xs text-gray-500">{_t("На один тип можно выбрать несколько файлов.")}</p>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            className={secondaryButtonClass}
                            onClick={onClose}
                        >{_t("Отмена")}</button>
                        <button
                            type="button"
                            className={primaryButtonClass}
                            disabled={!canSave || submitting}
                            onClick={submit}
                        >
                            {_t(submitting
                                ? "Загрузка..."
                                : mode === "replace"
                                  ? "Дополнить"
                                  : "Загрузить")}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
