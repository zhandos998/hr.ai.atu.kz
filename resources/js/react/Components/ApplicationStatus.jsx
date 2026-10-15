import { useI18n as useTextI18n } from "../I18nContext";
import React from "react";

export const applicationStageLabels = {
    pending: "На рассмотрении",
    accepted: "Принято",
    rejected: "Отклонено",
    awaiting_upload: "Ожидается загрузка",
    not_requested: "Ожидается загрузка",
    uploaded: "Загружены",
    not_started: "Не начато",
    clear: "Не выявлена",
    flagged: "Выявлена",
    voting: "Голосование",
    hired: "Взять на работу",
    hired_1_year: "Взять на 1 год",
    hired_3_year: "Взять на 3 года",
};

const tone = (value) => {
    if (
        ["accepted", "clear", "hired", "hired_1_year", "hired_3_year"].includes(
            value,
        )
    )
        return "success";
    if (["rejected", "flagged"].includes(value)) return "danger";
    if (
        [
            "pending",
            "awaiting_upload",
            "not_requested",
            "uploaded",
            "voting",
            "not_started",
        ].includes(value)
    )
        return "pending";
    return "empty";
};
const dots = {
    success: "bg-emerald-500 ring-emerald-100",
    danger: "bg-red-500 ring-red-100",
    pending: "bg-amber-400 ring-amber-100",
    empty: "bg-gray-400 ring-gray-100",
};
const badges = {
    success: "bg-emerald-100 text-emerald-800",
    danger: "bg-red-50 text-red-700",
    pending: "bg-amber-100 text-amber-800",
    empty: "bg-gray-100 text-gray-600",
};

export function StatusDot({ label, value }) {
    const { t: _t } = useTextI18n();
    const description = `${label}: ${applicationStageLabels[value] || "Не указано"}`;
    return (
        <span
            tabIndex={0}
            aria-label={_t(description)}
            className="group relative inline-flex h-7 w-7 shrink-0 items-center justify-center rounded focus-visible:outline-2 focus-visible:outline-blue-500"
        >
            <span
                aria-hidden="true"
                className={`h-2.5 w-2.5 rounded-full ring-4 ${dots[tone(value)]}`}
            />
            <span
                role="tooltip"
                className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden w-max max-w-48 -translate-x-1/2 rounded bg-gray-800 px-2 py-1.5 text-center text-xs font-normal text-white shadow-sm group-hover:block group-focus:block"
            >
                {_t(description)}
            </span>
        </span>
    );
}

export function StatusBadge({ value, children }) {
    const { t: _t } = useTextI18n();
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${badges[tone(value)]}`}
        >
            {_t(children || applicationStageLabels[value] || "Не указано")}
        </span>
    );
}
