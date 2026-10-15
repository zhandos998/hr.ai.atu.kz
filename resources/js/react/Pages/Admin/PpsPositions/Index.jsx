import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { Building2, GraduationCap, Plus } from "lucide-react";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
} from "../../../Components/Form";

export default function PpsPositions() {
    const { t: _t } = useTextI18n();
    const [positions, setPositions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState("");
    const [error, setError] = useState("");

    useEffect(() => {
        axios
            .get("/api/admin/pps-positions")
            .then(({ data }) => setPositions(data))
            .catch((requestError) => setError(errorText(requestError)))
            .finally(() => setLoading(false));
    }, []);

    const filtered = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return positions.filter(
            (position) =>
                !query ||
                [
                    position.name,
                    ...position.chairs.flatMap((chair) => [
                        chair.name,
                        chair.faculty,
                    ]),
                ]
                    .filter(Boolean)
                    .some((value) => value.toLocaleLowerCase().includes(query)),
        );
    }, [positions, search]);

    return (
        <Layout
            admin
            title={_t("Позиции ППС")}
            description={_t("Уникальные позиции преподавателей и их распределение по кафедрам.")}
            actions={
                <Link
                    href="/admin/pps-positions/create"
                    className={`${primaryButtonClass} inline-flex items-center gap-2`}
                >
                    <Plus size={16} />{_t("Добавить позицию")}</Link>
            }
        >
            <Head title={_t("Позиции ППС")} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <Alert>{_t(error)}</Alert>
                <section className="rounded-md border border-gray-200 bg-white p-4 shadow-sm">
                    <input
                        className={inputClass.replace("mt-1 ", "")}
                        value={search}
                        placeholder={_t("Поиск по позиции, факультету или кафедре")}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </section>

                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-500">{_t("Показано:")}{" "}
                        <span className="font-semibold text-gray-800">
                            {_t(filtered.length)}
                        </span>{" "}{_t("из")}{_t(positions.length)}
                    </div>
                    {loading ? (
                        <div className="p-10 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filtered.map((position) => (
                                <div
                                    key={position.id}
                                    className="grid items-center gap-3 px-5 py-4 hover:bg-gray-50 md:grid-cols-[1fr_190px_190px]"
                                >
                                    <div>
                                        <div className="font-semibold text-gray-900">
                                            {position.name}
                                        </div>
                                        <div className="mt-1 text-xs text-gray-400">{_t("Позиция ППС")}</div>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-gray-600">
                                        <Building2
                                            size={16}
                                            className="text-gray-400"
                                        />
                                        {_t(position.chairs_count)}{_t("кафедр")}</div>
                                    <div className="flex items-center gap-2 text-sm text-gray-600">
                                        <GraduationCap
                                            size={16}
                                            className="text-gray-400"
                                        />
                                        {_t(position.faculties_count)}{_t("факультетов")}</div>
                                </div>
                            ))}
                            {!loading && filtered.length === 0 && (
                                <div className="p-10 text-center text-sm text-gray-500">{_t("Позиции ППС не найдены.")}</div>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </Layout>
    );
}
