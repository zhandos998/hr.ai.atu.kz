import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    BriefcaseBusiness,
    ChevronRight,
    Pencil,
    Plus,
    Users,
} from "lucide-react";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
} from "../../../Components/Form";

export default function DepartmentShow() {
    const { t: _t } = useTextI18n();
    const { departmentId } = usePage().props;
    const [department, setDepartment] = useState(null);
    const [search, setSearch] = useState("");
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        axios
            .get(`/api/admin/departments/${departmentId}`)
            .then(({ data }) => setDepartment(data))
            .catch((requestError) => setError(errorText(requestError)))
            .finally(() => setLoading(false));
    }, [departmentId]);

    const positions = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return (department?.positions || []).filter(
            (position) =>
                !query ||
                [
                    position.name,
                    ...(position.users || []).map((user) => user.name),
                ]
                    .filter(Boolean)
                    .some((value) => value.toLocaleLowerCase().includes(query)),
        );
    }, [department, search]);

    const actions = department ? (
        <div className="flex flex-wrap items-center gap-2">
            <Link
                href={`/admin/departments/${department.id}/edit`}
                className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700"
            >
                <Pencil size={16} />{_t("Изменить")}</Link>
            <Link
                href={`/admin/positions/create?department_id=${department.id}`}
                className={`${primaryButtonClass} inline-flex items-center gap-2`}
            >
                <Plus size={16} />{_t("Добавить должность")}</Link>
        </div>
    ) : null;

    return (
        <Layout
            admin
            title={_t(department?.name || "Департамент")}
            description={_t("Список должностей выбранного департамента.")}
            actions={actions}
        >
            <Head title={_t(department?.name || "Департамент")} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <Link
                    href="/admin/departments"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-[#005eb8]"
                >
                    <ArrowLeft size={16} />{_t("К списку департаментов")}</Link>
                <Alert>{_t(error)}</Alert>

                {loading ? (
                    <div className="rounded-md border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                ) : department ? (
                    <>
                        <section className="grid gap-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm md:grid-cols-3">
                            <div>
                                <div className="text-xs font-semibold uppercase text-gray-400">{_t("Подразделение")}</div>
                                <div className="mt-1 font-semibold text-gray-900">
                                    {department.name}
                                </div>
                                <div className="mt-1 text-sm text-gray-500">
                                    {_t(department.description ||
                                        "Описание не указано")}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs font-semibold uppercase text-gray-400">{_t("Родитель")}</div>
                                <div className="mt-1 text-sm text-gray-700">
                                    {_t(department.parent?.name || "Нет")}
                                </div>
                            </div>
                            <div className="flex items-center gap-3 md:justify-end">
                                <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-50 text-[#005eb8]">
                                    <BriefcaseBusiness size={20} />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold text-gray-900">
                                        {_t(department.positions.length)}
                                    </div>
                                    <div className="text-xs text-gray-500">{_t("должностей")}</div>
                                </div>
                            </div>
                        </section>

                        <section className="rounded-md border border-gray-200 bg-white p-4 shadow-sm">
                            <input
                                className={inputClass.replace("mt-1 ", "")}
                                value={search}
                                placeholder={_t("Поиск по должности или сотруднику")}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </section>

                        <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-gray-200 bg-gray-50 px-5 py-4">
                                <h2 className="font-bold text-gray-900">{_t("Должности департамента")}</h2>
                                <p className="mt-1 text-sm text-gray-500">{_t("Показано:")}{_t(positions.length)}{_t("из")}{" "}
                                    {_t(department.positions.length)}
                                </p>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {positions.map((position) => (
                                    <div
                                        key={position.id}
                                        className="grid items-center gap-3 px-5 py-4 hover:bg-gray-50 md:grid-cols-[1fr_220px_120px]"
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
                                {positions.length === 0 && (
                                    <div className="p-10 text-center text-sm text-gray-500">
                                        {_t(department.positions.length
                                            ? "По вашему запросу ничего не найдено."
                                            : "В этом департаменте пока нет должностей.")}
                                    </div>
                                )}
                            </div>
                        </section>
                    </>
                ) : null}
            </div>
        </Layout>
    );
}
