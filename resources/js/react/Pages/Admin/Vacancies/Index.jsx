import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { loadPositionUnits, positionUnitKey } from "../../../lib/positionUnits";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

export default function AdminVacancies() {
    const { t: _t } = useTextI18n();
    const [vacancies, setVacancies] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [positions, setPositions] = useState([]);
    const [search, setSearch] = useState("");
    const [type, setType] = useState("");
    const [editId, setEditId] = useState(null);
    const [edit, setEdit] = useState({
        title: "",
        description: "",
        type: "",
        unit_key: "",
        position_id: "",
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const load = async () => {
        setLoading(true);
        try {
            const [vacancyResponse, departmentResponse, positionResponse] =
                await Promise.all([
                    axios.get("/api/admin/vacancies"),
                    loadPositionUnits(),
                    axios.get("/api/admin/positions"),
                ]);
            setVacancies(vacancyResponse.data);
            setDepartments(departmentResponse);
            setPositions(positionResponse.data);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, []);
    const departmentName = (id) =>
        departments.find((item) => item.id === id)?.name || "Не указано";
    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        return vacancies.filter(
            (item) =>
                (!type || item.type === type) &&
                (!query ||
                    [
                        item.title,
                        item.description,
                        item.position?.name,
                        departmentName(positionUnitKey(item.position)),
                    ].some((value) =>
                        String(value || "")
                            .toLowerCase()
                            .includes(query),
                    )),
        );
    }, [departments, search, type, vacancies]);
    const editPositions = positions.filter(
        (item) => positionUnitKey(item) === edit.unit_key,
    );
    const startEdit = (item) => {
        setEditId(item.id);
        setEdit({
            title: item.title || "",
            description: item.description || "",
            type: item.type || "",
            unit_key: positionUnitKey(item.position),
            position_id: item.position_id || "",
        });
    };
    const save = async (event) => {
        event.preventDefault();
        try {
            await axios.put(`/api/admin/vacancies/${editId}`, {
                title: edit.title,
                description: edit.description,
                type: edit.type,
                ...(edit.position_id
                    ? { position_id: Number(edit.position_id) }
                    : {}),
            });
            setEditId(null);
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    const remove = async (item) => {
        if (!window.confirm(`Удалить вакансию «${item.title}»?`)) return;
        try {
            await axios.delete(`/api/admin/vacancies/${item.id}`);
            await load();
        } catch (requestError) {
            window.alert(errorText(requestError));
        }
    };
    const actions = (
        <Link href="/admin/vacancies/create" className={primaryButtonClass}>{_t("Добавить вакансию")}</Link>
    );
    return (
        <Layout
            admin
            title={_t("Вакансии")}
            description={_t("Список конкурсных вакансий.")}
            actions={actions}
        >
            <Head title={_t("Вакансии")} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <Alert>{_t(error)}</Alert>
                <section className="grid gap-3 rounded-md border border-gray-200 bg-white p-4 md:grid-cols-[1fr_240px]">
                    <input
                        className={inputClass.replace("mt-1 ", "")}
                        value={search}
                        placeholder={_t("Вакансия, должность или описание")}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <select
                        className={inputClass.replace("mt-1 ", "")}
                        value={type}
                        onChange={(e) => setType(e.target.value)}
                    >
                        <option value="">{_t("Все типы")}</option>
                        <option value="pps">{_t("ППС")}</option>
                        <option value="staff">{_t("АУП")}</option>
                    </select>
                </section>
                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    {loading ? (
                        <div className="p-10 text-center text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">{_t("Вакансия")}</th>
                                        <th className="px-4 py-3">{_t("Тип")}</th>
                                        <th className="px-4 py-3">{_t("Департамент / должность")}</th>
                                        <th className="px-4 py-3">{_t("Описание")}</th>
                                        <th className="px-4 py-3">{_t("Дата")}</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filtered.map((item) =>
                                        editId === item.id ? (
                                            <tr key={item.id}>
                                                <td
                                                    colSpan="6"
                                                    className="bg-gray-50 p-5"
                                                >
                                                    <form
                                                        className="space-y-4"
                                                        onSubmit={save}
                                                    >
                                                        <div className="grid gap-4 md:grid-cols-2">
                                                            <input
                                                                required
                                                                className={
                                                                    inputClass
                                                                }
                                                                value={
                                                                    edit.title
                                                                }
                                                                onChange={(e) =>
                                                                    setEdit({
                                                                        ...edit,
                                                                        title: e
                                                                            .target
                                                                            .value,
                                                                    })
                                                                }
                                                            />
                                                            <input
                                                                disabled
                                                                className={`${inputClass} bg-gray-100`}
                                                                value={
                                                                    edit.type ===
                                                                    "pps"
                                                                        ? "ППС"
                                                                        : "АУП"
                                                                }
                                                            />
                                                            <select
                                                                className={
                                                                    inputClass
                                                                }
                                                                value={
                                                                    edit.unit_key
                                                                }
                                                                onChange={(e) =>
                                                                    setEdit({
                                                                        ...edit,
                                                                        unit_key:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        position_id:
                                                                            "",
                                                                    })
                                                                }
                                                            >
                                                                <option value="">{_t("Выберите подразделение")}</option>
                                                                {departments
                                                                    .filter(
                                                                        (
                                                                            unit,
                                                                        ) =>
                                                                            unit.type ===
                                                                            edit.type,
                                                                    )
                                                                    .map(
                                                                        (
                                                                            department,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    department.id
                                                                                }
                                                                                value={
                                                                                    department.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    department.name
                                                                                }
                                                                            </option>
                                                                        ),
                                                                    )}
                                                            </select>
                                                            <select
                                                                className={
                                                                    inputClass
                                                                }
                                                                value={
                                                                    edit.position_id
                                                                }
                                                                onChange={(e) =>
                                                                    setEdit({
                                                                        ...edit,
                                                                        position_id:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    })
                                                                }
                                                            >
                                                                <option value="">{_t("Без должности")}</option>
                                                                {editPositions.map(
                                                                    (
                                                                        position,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                position.id
                                                                            }
                                                                            value={
                                                                                position.id
                                                                            }
                                                                        >
                                                                            {
                                                                                position.name
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <textarea
                                                                className={`${inputClass} md:col-span-2`}
                                                                rows="4"
                                                                value={
                                                                    edit.description
                                                                }
                                                                onChange={(e) =>
                                                                    setEdit({
                                                                        ...edit,
                                                                        description:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                className={
                                                                    secondaryButtonClass
                                                                }
                                                                onClick={() =>
                                                                    setEditId(
                                                                        null,
                                                                    )
                                                                }
                                                            >{_t("Отмена")}</button>
                                                            <button
                                                                className={
                                                                    primaryButtonClass
                                                                }
                                                            >{_t("Сохранить")}</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr
                                                key={item.id}
                                                className="hover:bg-gray-50"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold">
                                                        {_t(item.title)}
                                                    </div>
                                                    <div className="text-xs text-gray-400">
                                                        ID: {_t(item.id)}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {_t(item.type === "pps"
                                                        ? "ППС"
                                                        : "АУП")}
                                                </td>
                                                <td className="px-4 py-3 text-gray-600">
                                                    {_t(departmentName(
                                                        positionUnitKey(
                                                            item.position,
                                                        ),
                                                    ))}
                                                    <div className="text-xs text-gray-400">
                                                        {_t(item.position?.name ||
                                                            "Должность не указана")}
                                                    </div>
                                                </td>
                                                <td className="max-w-sm px-4 py-3 text-gray-600">
                                                    {_t(item.description || "—")}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-gray-500">
                                                    {_t(new Date(
                                                        item.created_at,
                                                    ).toLocaleDateString(
                                                        "ru-RU",
                                                    ))}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-3">
                                                        <button
                                                            className="font-semibold text-[#174a9c]"
                                                            onClick={() =>
                                                                startEdit(item)
                                                            }
                                                        >{_t("Изменить")}</button>
                                                        <button
                                                            className="font-semibold text-red-600"
                                                            onClick={() =>
                                                                remove(item)
                                                            }
                                                        >{_t("Удалить")}</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                    {filtered.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="p-10 text-center text-gray-500"
                                            >{_t("Вакансии не найдены.")}</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </Layout>
    );
}
