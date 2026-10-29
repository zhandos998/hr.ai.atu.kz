import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../../Components/Layout";
import { errorText, inputClass } from "../../../Components/Form";

const decorate = (items) => {
    const map = new Map(items.map((item) => [Number(item.id), item]));
    const fullName = (item) => {
        const names = [item.name];
        let current = item;
        const seen = new Set([Number(item.id)]);
        while (
            current?.parent_id &&
            map.has(Number(current.parent_id)) &&
            !seen.has(Number(current.parent_id))
        ) {
            current = map.get(Number(current.parent_id));
            seen.add(Number(current.id));
            names.unshift(current.name);
        }
        return names.join(" / ");
    };
    return items.map((item) => ({ ...item, full_name: fullName(item) }));
};

export default function Departments() {
    const { t: _t } = useTextI18n();
    const { academic = false } = usePage().props;
    const base = academic ? "/admin/academic-units" : "/admin/departments";
    const title = academic ? "Факультеты и кафедры" : "Департаменты";
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState("");
    const [parent, setParent] = useState(academic ? "" : "none");
    const load = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get(`/api${base}`);
            setItems(decorate(data));
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        setSearch("");
        setParent(academic ? "" : "none");
        load();
    }, [base]);
    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        return items.filter(
            (item) =>
                (!parent ||
                    (parent === "none"
                        ? !item.parent_id
                        : Number(item.parent_id) === Number(parent))) &&
                (!query ||
                    [item.name, item.description, item.full_name].some(
                        (value) =>
                            String(value || "")
                                .toLowerCase()
                                .includes(query),
                    )),
        );
    }, [items, parent, search]);
    const remove = async (item) => {
        if (!window.confirm(`Удалить подразделение «${item.name}»?`)) return;
        try {
            await axios.delete(`/api${base}/${item.id}`);
            await load();
        } catch (error) {
            window.alert(errorText(error));
        }
    };
    const actions = (
        <Link
            href={`${base}/create`}
            className="rounded-md bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white"
        >
            {_t(academic ? "Добавить факультет / кафедру" : "Добавить департамент")}
        </Link>
    );
    return (
        <Layout
            admin
            title={_t(title)}
            description={_t("Структурные подразделения университета.")}
            actions={actions}
        >
            <Head title={_t(title)} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <section className="grid gap-3 rounded-md border border-gray-200 bg-white p-4 md:grid-cols-[1fr_280px]">
                    <input
                        value={search}
                        className={inputClass.replace("mt-1 ", "")}
                        placeholder={_t("Поиск по названию или описанию")}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <select
                        value={parent}
                        className={inputClass.replace("mt-1 ", "")}
                        onChange={(event) => setParent(event.target.value)}
                    >
                        <option value="">
                            {_t(academic
                                ? "Все факультеты"
                                : "Все родительские подразделения")}
                        </option>
                        <option value="none">
                            {_t(academic ? "Только факультеты" : "Без родителя")}
                        </option>
                        {items
                            .filter(
                                (item) => !academic || item.kind === "faculty",
                            )
                            .map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.full_name}
                                </option>
                            ))}
                    </select>
                </section>
                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    {loading ? (
                        <div className="p-10 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">{_t("Подразделение")}</th>
                                        <th className="px-4 py-3">{_t("Родитель")}</th>
                                        <th className="px-4 py-3">{_t("Описание")}</th>
                                        <th className="px-4 py-3">{_t("Должности")}</th>
                                        <th className="px-4 py-3 text-right">{_t("Действия")}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filtered.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-3">
                                                {academic ? (
                                                    <div className="font-semibold">
                                                        {item.name}
                                                    </div>
                                                ) : (
                                                    <Link
                                                        href={`/admin/departments/${item.id}`}
                                                        className="font-semibold text-[#005eb8] hover:underline"
                                                    >
                                                        {item.name}
                                                    </Link>
                                                )}
                                                <div className="mt-1 text-xs text-gray-400">
                                                    {item.full_name}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-600">
                                                {_t(items.find(
                                                    (candidate) =>
                                                        Number(candidate.id) ===
                                                        Number(item.parent_id),
                                                )?.name || "Нет")}
                                            </td>
                                            <td className="max-w-sm px-4 py-3 text-gray-600">
                                                {_t(item.description || "—")}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.subtree_positions_count ?? item.positions?.length ?? 0}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-3">
                                                    {!academic && (
                                                        <Link
                                                            href={`/admin/departments/${item.id}`}
                                                            className="font-semibold text-[#005eb8]"
                                                        >{_t("Открыть")}</Link>
                                                    )}
                                                    <Link
                                                        href={`${base}/${item.id}/edit`}
                                                        className="font-semibold text-[#174a9c]"
                                                    >{_t("Изменить")}</Link>
                                                    <button
                                                        type="button"
                                                        className="font-semibold text-red-600"
                                                        onClick={() =>
                                                            remove(item)
                                                        }
                                                    >{_t("Удалить")}</button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filtered.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="5"
                                                className="p-10 text-center text-gray-500"
                                            >{_t("Ничего не найдено.")}</td>
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
