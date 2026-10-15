import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import Layout from "../Components/Layout";
import { useAuth } from "../AuthContext";

export default function Vacancies() {
    const { t: _t } = useTextI18n();
    const { user } = useAuth();
    const [vacancies, setVacancies] = useState([]);
    const [type, setType] = useState("all");
    const [loading, setLoading] = useState(true);
    useEffect(() => {
        axios
            .get("/api/vacancies")
            .then(({ data }) => setVacancies(data))
            .finally(() => setLoading(false));
    }, []);
    const filtered = useMemo(
        () =>
            type === "all"
                ? vacancies
                : vacancies.filter((item) => item.type === type),
        [type, vacancies],
    );
    return (
        <Layout
            title={_t("Актуальные вакансии")}
            description={_t("Выберите подходящую позицию и подайте заявку.")}
        >
            <Head title={_t("Вакансии")} />
            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-6 flex flex-wrap gap-2">
                    {[
                        ["all", "Все"],
                        ["staff", "АУП"],
                        ["pps", "ППС"],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            className={`rounded-md px-4 py-2 text-sm font-semibold ${type === value ? "bg-[#005eb8] text-white" : "border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"}`}
                            onClick={() => setType(value)}
                        >
                            {_t(label)}
                        </button>
                    ))}
                </div>
                {loading ? (
                    <div className="py-16 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                ) : filtered.length === 0 ? (
                    <div className="rounded-md border border-gray-200 bg-white py-16 text-center text-sm text-gray-500">{_t("Вакансии не найдены.")}</div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {filtered.map((vacancy) => (
                            <article
                                key={vacancy.id}
                                className="flex flex-col rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                            >
                                <h2 className="text-lg font-bold text-[#174a9c]">
                                    {_t(vacancy.title)}
                                </h2>
                                <p className="mt-2 flex-1 whitespace-pre-wrap text-sm leading-6 text-gray-600">
                                    {_t(vacancy.description)}
                                </p>
                                <dl className="mt-4 space-y-1 text-sm text-gray-500">
                                    <div>{_t("Тип:")}{" "}
                                        {_t(vacancy.type === "pps" ? "ППС" : "АУП")}
                                    </div>
                                    <div>{_t("Должность:")}{" "}
                                        {_t(vacancy.position?.name || "Не указана")}
                                    </div>
                                </dl>
                                <Link
                                    href={
                                        user
                                            ? `/apply?vacancy_id=${vacancy.id}`
                                            : `/login?next=${encodeURIComponent(`/apply?vacancy_id=${vacancy.id}`)}`
                                    }
                                    className="mt-5 rounded-md bg-[#005eb8] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#244f9d]"
                                >{_t("Откликнуться")}</Link>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </Layout>
    );
}
