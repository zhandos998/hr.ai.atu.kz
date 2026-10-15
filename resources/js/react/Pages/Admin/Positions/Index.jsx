import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { ChevronRight, Plus, Users } from "lucide-react";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
} from "../../../Components/Form";
import { loadPositionUnits, positionUnitKey } from "../../../lib/positionUnits";

export default function Positions() {
    const { t: _t } = useTextI18n();
    const [positions, setPositions] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState("");
    const [departmentId, setDepartmentId] = useState("");
    const [error, setError] = useState("");

    useEffect(() => {
        Promise.all([
            axios.get("/api/admin/positions", { params: { scope: "staff" } }),
            loadPositionUnits({ includeAcademic: false }),
        ])
            .then(([positionResponse, units]) => {
                setPositions(positionResponse.data);
                setDepartments(units);
            })
            .catch((requestError) => setError(errorText(requestError)))
            .finally(() => setLoading(false));
    }, []);

    const departmentName = (key) =>
        departments.find((item) => item.id === key)?.name || "Не указано";
    const filtered = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return positions.filter((position) => {
            const unitKey = positionUnitKey(position);
            return (
                (!departmentId || unitKey === departmentId) &&
                (!query ||
                    [position.name, departmentName(unitKey)].some((value) =>
                        String(value).toLocaleLowerCase().includes(query),
                    ))
            );
        });
    }, [departmentId, departments, positions, search]);

    const actions = (
        <Link
            href="/admin/positions/create"
            className={`${primaryButtonClass} inline-flex items-center gap-2`}
        >
            <Plus size={16} />{_t("Добавить должность")}</Link>
    );

    return (
        <Layout
            admin
            title={_t("Должности АУП")}
            description={_t("Список должностей подразделений АУП.")}
            actions={actions}
        >
            <Head title={_t("Должности АУП")} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <Alert>{_t(error)}</Alert>
                <section className="grid gap-3 rounded-md border border-gray-200 bg-white p-4 md:grid-cols-[1fr_320px]">
                    <input
                        className={inputClass.replace("mt-1 ", "")}
                        value={search}
                        placeholder={_t("Поиск по должности или департаменту")}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <select
                        className={inputClass.replace("mt-1 ", "")}
                        value={departmentId}
                        onChange={(event) =>
                            setDepartmentId(event.target.value)
                        }
                    >
                        <option value="">{_t("Все департаменты")}</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                </section>

                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-500">{_t("Показано:")}{" "}
                        <span className="font-semibold text-gray-800">
                            {_t(filtered.length)}
                        </span>
                    </div>
                    {loading ? (
                        <div className="p-10 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filtered.map((position) => (
                                <div
                                    key={position.id}
                                    className="grid items-center gap-3 px-5 py-4 hover:bg-gray-50 md:grid-cols-[1fr_1fr_170px_110px]"
                                >
                                    <div className="min-w-0">
                                        <Link
                                            href={`/admin/positions/${position.id}`}
                                            className="font-semibold text-[#005eb8] hover:underline"
                                        >
                                            {position.name}
                                        </Link>
                                        <div className="mt-1 text-xs text-gray-400">
                                            ID: {_t(position.id)}
                                        </div>
                                    </div>
                                    <div className="text-sm text-gray-600">
                                        {_t(departmentName(
                                            positionUnitKey(position),
                                        ))}
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-gray-600">
                                        <Users
                                            size={16}
                                            className="text-gray-400"
                                        />
                                        {position.users?.length || 0}{" "}{_t("сотрудников")}</div>
                                    <Link
                                        href={`/admin/positions/${position.id}`}
                                        className="inline-flex items-center justify-end gap-1 text-sm font-semibold text-[#005eb8]"
                                    >{_t("Открыть")}<ChevronRight size={16} />
                                    </Link>
                                </div>
                            ))}
                            {filtered.length === 0 && (
                                <div className="p-10 text-center text-sm text-gray-500">{_t("Должности не найдены.")}</div>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </Layout>
    );
}
