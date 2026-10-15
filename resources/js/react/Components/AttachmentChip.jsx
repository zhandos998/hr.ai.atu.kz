import { useI18n as useTextI18n } from "../I18nContext";
import React from "react";
import { FileText, X } from "lucide-react";

export default function AttachmentChip({
    name,
    url,
    onRemove,
    disabled = false,
}) {
    const { t: _t } = useTextI18n();
    return (
        <span className="inline-flex max-w-full items-center overflow-hidden rounded-md border border-blue-100 bg-blue-50/60 text-xs text-[#174a9c]">
            {url ? (
                <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    title={_t(name)}
                    className="flex min-w-0 items-center gap-1.5 px-2.5 py-2 hover:bg-blue-100"
                >
                    <FileText size={14} className="shrink-0" />
                    <span className="truncate">{_t(name)}</span>
                </a>
            ) : (
                <span
                    className="flex min-w-0 items-center gap-1.5 px-2.5 py-2"
                    title={_t(name)}
                >
                    <FileText size={14} className="shrink-0" />
                    <span className="truncate">{_t(name)}</span>
                </span>
            )}
            {onRemove && (
                <button
                    type="button"
                    title={_t(`Удалить: ${name}`)}
                    aria-label={_t(`Удалить: ${name}`)}
                    disabled={disabled}
                    className="flex h-8 w-8 shrink-0 items-center justify-center border-l border-blue-100 text-gray-500 hover:bg-red-50 hover:text-red-600 disabled:opacity-40"
                    onClick={onRemove}
                >
                    <X size={14} />
                </button>
            )}
        </span>
    );
}
