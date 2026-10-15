import { useI18n as useTextI18n } from "../I18nContext";
import React from "react";

export const inputClass =
    "mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#005eb8] focus:ring-2 focus:ring-blue-100";
export const primaryButtonClass =
    "rounded-md bg-[#005eb8] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#244f9d] disabled:cursor-not-allowed disabled:opacity-60";
export const secondaryButtonClass =
    "rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-60";

export function Field({ label, children, hint }) {
    const { t: _t } = useTextI18n();
    return (
        <label className="block text-sm font-medium text-gray-700">
            {_t(label)}
            {_t(children)}
            {hint && (
                <span className="mt-1 block text-xs font-normal text-gray-500">
                    {_t(hint)}
                </span>
            )}
        </label>
    );
}

export function Alert({ type = "error", children }) {
    const { t: _t } = useTextI18n();
    if (!children) return null;
    const classes =
        type === "success"
            ? "border-emerald-200 bg-emerald-50 text-emerald-700"
            : "border-red-200 bg-red-50 text-red-700";
    return (
        <div className={`rounded-md border px-4 py-3 text-sm ${classes}`}>
            {_t(children)}
        </div>
    );
}

export function AuthCard({ title, children }) {
    const { t: _t } = useTextI18n();
    return (
        <div className="mx-auto w-full max-w-md px-4 py-12">
            <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h1 className="mb-6 text-center text-xl font-bold text-[#174a9c] sm:text-2xl">
                    {_t(title)}
                </h1>
                {_t(children)}
            </section>
        </div>
    );
}

export const errorText = (error, fallback = "Не удалось выполнить операцию.") =>
    Object.values(error?.response?.data?.errors || {})?.[0]?.[0] ||
    error?.response?.data?.message ||
    fallback;
