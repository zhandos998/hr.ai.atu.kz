import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

export default function AddCandidates() {
    const { t: _t } = useTextI18n();
    const { competitionId } = usePage().props;
    const [competition, setCompetition] = useState(null);
    const [result, setResult] = useState({
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
    });
    const [query, setQuery] = useState("");
    const [applied, setApplied] = useState("");
    const [selected, setSelected] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const load = async (page = 1, q = applied) => {
        setLoading(true);
        setError("");
        try {
            const { data } = await axios.get(
                `/api/admin/competitions/${competitionId}/candidates`,
                { params: { page, q } },
            );
            setResult(data);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        setSelected([]);
        axios
            .get(`/api/admin/competitions/${competitionId}`)
            .then(({ data }) => setCompetition(data))
            .catch((requestError) => setError(errorText(requestError)));
        load();
    }, [competitionId]);
    const submit = async () => {
        setSaving(true);
        setError("");
        try {
            await axios.post(
                `/api/admin/competitions/${competitionId}/applications`,
                { application_ids: selected },
            );
            router.visit(`/admin/competitions/${competitionId}`);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const pageIds = result.data.map((item) => item.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    return (
        <Layout
            admin
            title={_t("Добавить конкурсантов")}
            description={_t(competition?.title)}
            actions={
                <Link
                    href={`/admin/competitions/${competitionId}`}
                    className={secondaryButtonClass}
                >{_t("К конкурсу")}</Link>
            }
        >
            <Head title={_t("Добавить конкурсантов")} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-6">
                <Alert>{_t(error)}</Alert>
                <form
                    className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setApplied(query);
                        load(1, query);
                    }}
                >
                    <input
                        aria-label={_t("Поиск кандидата")}
                        placeholder={_t("ФИО кандидата")}
                        className={`${inputClass} mt-0 min-w-0`}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <button
                        className={secondaryButtonClass}
                        disabled={loading || saving}
                    >{_t("Поиск")}</button>
                </form>
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span className="text-gray-500">{_t("Выбрано:")}<strong>{_t(selected.length)}</strong> / 100
                    </span>
                    <button
                        className={primaryButtonClass}
                        disabled={
                            !selected.length ||
                            saving ||
                            loading ||
                            !competition
                        }
                        onClick={submit}
                    >
                        {_t(saving ? "Добавление..." : "Добавить в конкурс")}
                    </button>
                </div>
                <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    {loading ? (
                        <div className="p-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[650px] text-left text-sm">
                                <thead className="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="w-12 px-4 py-3">
                                            <input
                                                type="checkbox"
                                                aria-label={_t("Выбрать страницу")}
                                                checked={allSelected}
                                                disabled={
                                                    saving ||
                                                    !pageIds.length ||
                                                    (!allSelected &&
                                                        new Set([
                                                            ...selected,
                                                            ...pageIds,
                                                        ]).size > 100)
                                                }
                                                onChange={() =>
                                                    setSelected(
                                                        allSelected
                                                            ? selected.filter(
                                                                  (id) =>
                                                                      !pageIds.includes(
                                                                          id,
                                                                      ),
                                                              )
                                                            : [
                                                                  ...new Set([
                                                                      ...selected,
                                                                      ...pageIds,
                                                                  ]),
                                                              ],
                                                    )
                                                }
                                            />
                                        </th>
                                        <th className="px-4 py-3">{_t("Конкурсант")}</th>
                                        <th className="px-4 py-3">{_t("Должность")}</th>
                                        <th className="px-4 py-3">{_t("Заявка")}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {result.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className={
                                                selected.includes(item.id)
                                                    ? "bg-blue-50/50"
                                                    : "hover:bg-gray-50"
                                            }
                                        >
                                            <td className="px-4 py-4">
                                                <input
                                                    type="checkbox"
                                                    aria-label={_t(`Добавить: ${item.user?.name}`)}
                                                    checked={selected.includes(
                                                        item.id,
                                                    )}
                                                    disabled={
                                                        saving ||
                                                        (!selected.includes(
                                                            item.id,
                                                        ) &&
                                                            selected.length >=
                                                                100)
                                                    }
                                                    onChange={() =>
                                                        setSelected(
                                                            selected.includes(
                                                                item.id,
                                                            )
                                                                ? selected.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          item.id,
                                                                  )
                                                                : [
                                                                      ...selected,
                                                                      item.id,
                                                                  ],
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="max-w-md break-words px-4 py-4 font-medium">
                                                {item.user?.name}
                                            </td>
                                            <td className="max-w-md break-words px-4 py-4 text-gray-600">
                                                {_t(item.pps_profile
                                                    ?.desired_position ||
                                                    item.vacancy?.position
                                                        ?.name ||
                                                    item.vacancy?.title)}
                                            </td>
                                            <td className="px-4 py-4 text-gray-500">
                                                #{_t(item.id)}
                                            </td>
                                        </tr>
                                    ))}
                                    {!result.data.length && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="p-12 text-center text-gray-500"
                                            >{_t("Нет подходящих заявок без конкурса.")}</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4 text-sm text-gray-500">
                        <span>{_t("Всего:")}{_t(result.total)}</span>
                        <div className="flex items-center gap-2">
                            <button
                                className={secondaryButtonClass}
                                disabled={
                                    loading ||
                                    saving ||
                                    result.current_page <= 1
                                }
                                onClick={() => load(result.current_page - 1)}
                            >{_t("Назад")}</button>
                            <span>
                                {_t(result.current_page)} / {_t(result.last_page)}
                            </span>
                            <button
                                className={secondaryButtonClass}
                                disabled={
                                    loading ||
                                    saving ||
                                    result.current_page >= result.last_page
                                }
                                onClick={() => load(result.current_page + 1)}
                            >{_t("Вперёд")}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
