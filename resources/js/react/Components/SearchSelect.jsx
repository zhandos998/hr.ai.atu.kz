import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useMemo, useRef, useState } from "react";

export default function SearchSelect({
    value,
    onChange,
    options = [],
    placeholder = "Начните вводить",
    disabled = false,
}) {
    const { t: _t } = useTextI18n();
    const selected = options.find(
        (option) => String(option.id) === String(value ?? ""),
    );
    const [query, setQuery] = useState(selected?.name || "");
    const [open, setOpen] = useState(false);
    const closeTimer = useRef(null);

    useEffect(() => {
        setQuery(selected?.name || "");
    }, [selected?.id, selected?.name]);

    useEffect(() => () => window.clearTimeout(closeTimer.current), []);

    const filtered = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase();
        if (!needle || selected?.name === query) return options;
        return options.filter((option) =>
            `${option.name} ${option.secondary || ""}`
                .toLocaleLowerCase()
                .includes(needle),
        );
    }, [options, query, selected?.name]);

    return (
        <div className="relative">
            <input
                value={query}
                type="text"
                autoComplete="off"
                disabled={disabled}
                placeholder={_t(placeholder)}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-[#005eb8] focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
                onFocus={() => setOpen(true)}
                onChange={(event) => {
                    setQuery(event.target.value);
                    onChange(null);
                    setOpen(true);
                }}
                onBlur={() => {
                    closeTimer.current = window.setTimeout(() => {
                        setOpen(false);
                        setQuery(selected?.name || "");
                    }, 120);
                }}
            />
            {open && filtered.length > 0 && (
                <div className="absolute z-40 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                    {filtered.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            className="block w-full px-3 py-2 text-left text-sm transition hover:bg-blue-50 hover:text-[#174a9c]"
                            onMouseDown={(event) => {
                                event.preventDefault();
                                onChange(option.id);
                                setQuery(option.name);
                                setOpen(false);
                            }}
                        >
                            <span className="block font-medium">
                                {option.name}
                            </span>
                            {option.secondary && (
                                <span className="block text-xs text-gray-500">
                                    {_t(option.secondary)}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            )}
            {open && query && filtered.length === 0 && (
                <div className="absolute z-40 mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-lg">{_t("Ничего не найдено")}</div>
            )}
        </div>
    );
}
