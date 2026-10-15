import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import { errorText, inputClass } from "../../Components/Form";

export default function LawyerQueue() {
    const { t: _t } = useTextI18n();
    const [items, setItems] = useState([]);
    const [comments, setComments] = useState({});
    const [loading, setLoading] = useState(true);
    const [savingId, setSavingId] = useState(null);
    const load = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get("/api/lawyer/applications");
            setItems(data);
            setComments(
                Object.fromEntries(
                    data.map((item) => [
                        item.id,
                        item.compliance_comment || "",
                    ]),
                ),
            );
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, []);
    const save = async (id, status) => {
        setSavingId(id);
        try {
            await axios.put(
                `/api/lawyer/applications/${id}/corruption-status`,
                { status_code: status, comment: comments[id]?.trim() || null },
            );
            await load();
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setSavingId(null);
        }
    };
    return (
        <Layout
            requireAuth
            title={_t("Проверка заявок юристом")}
            description={_t("Юридическая проверка заявок с принятыми документами.")}
        >
            <Head title={_t("Проверка заявок юристом")} />
            <div className="mx-auto max-w-6xl px-4 py-8">
                {loading ? (
                    <div className="text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    <div className="space-y-4">
                        {items.map((item) => (
                            <section
                                key={item.id}
                                className="rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex flex-col justify-between gap-3 sm:flex-row">
                                    <div>
                                        <h2 className="font-bold text-[#174a9c]">
                                            {_t(item.vacancy?.title || "Заявка")}
                                        </h2>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {item.user?.name} ·{" "}
                                            {item.user?.email}
                                        </p>
                                        <p className="text-xs text-gray-400">{_t("Подана:")}{" "}
                                            {_t(new Date(
                                                item.created_at,
                                            ).toLocaleDateString("ru-RU"))}
                                        </p>
                                    </div>
                                    <span className="h-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold">
                                        {_t(item.compliance_status === "clear"
                                            ? "Не выявлена"
                                            : item.compliance_status ===
                                                "flagged"
                                              ? "Выявлена"
                                              : "Не проверено")}
                                    </span>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {item.resume_url && (
                                        <a
                                            href={item.resume_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="rounded-md border border-[#005eb8] px-3 py-2 text-sm text-[#174a9c]"
                                        >{_t("Резюме")}</a>
                                    )}
                                    {Object.entries(
                                        item.documents_map || {},
                                    ).map(([type, document]) => (
                                        <a
                                            key={type}
                                            href={document.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="rounded-md border border-emerald-500 px-3 py-2 text-sm text-emerald-700"
                                        >
                                            {_t(type)}
                                        </a>
                                    ))}
                                </div>
                                <textarea
                                    className={inputClass}
                                    rows="4"
                                    placeholder={_t("Комментарий юридической проверки")}
                                    value={comments[item.id] || ""}
                                    onChange={(event) =>
                                        setComments({
                                            ...comments,
                                            [item.id]: event.target.value,
                                        })
                                    }
                                />
                                <div className="mt-3 flex justify-end gap-2">
                                    <button
                                        disabled={savingId === item.id}
                                        className="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700"
                                        onClick={() => save(item.id, "flagged")}
                                    >{_t("Выявлена")}</button>
                                    <button
                                        disabled={savingId === item.id}
                                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"
                                        onClick={() => save(item.id, "clear")}
                                    >{_t("Не выявлена")}</button>
                                </div>
                            </section>
                        ))}
                        {items.length === 0 && (
                            <div className="rounded-md border border-gray-200 bg-white py-12 text-center text-gray-500">{_t("Заявок с принятыми документами нет.")}</div>
                        )}
                    </div>
                )}
            </div>
        </Layout>
    );
}
