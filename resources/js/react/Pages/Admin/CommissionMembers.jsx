import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import { errorText, inputClass } from "../../Components/Form";

export default function CommissionMembers() {
    const { t: _t } = useTextI18n();
    const [type, setType] = useState("pps");
    const [members, setMembers] = useState([]);
    const [candidates, setCandidates] = useState([]);
    const [memberSearch, setMemberSearch] = useState("");
    const [candidateSearch, setCandidateSearch] = useState("");
    const [loading, setLoading] = useState(true);
    const loadMembers = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get("/api/admin/commission-members", {
                params: { type, q: memberSearch },
            });
            setMembers(data);
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setLoading(false);
        }
    };
    const loadCandidates = async () => {
        try {
            const { data } = await axios.get(
                "/api/admin/commission-candidates",
                { params: { type, q: candidateSearch } },
            );
            setCandidates(data);
        } catch (error) {
            window.alert(errorText(error));
        }
    };
    useEffect(() => {
        loadMembers();
        loadCandidates();
    }, [type]);
    const add = async (user) => {
        try {
            await axios.post("/api/admin/commission-members", {
                user_id: user.id,
                type,
            });
            await Promise.all([loadMembers(), loadCandidates()]);
        } catch (error) {
            window.alert(errorText(error));
        }
    };
    const remove = async (member) => {
        if (!window.confirm(`Удалить ${member.user?.name} из комиссии?`))
            return;
        try {
            await axios.delete(
                `/api/admin/commission-members/${member.user_id}`,
                { params: { type } },
            );
            await Promise.all([loadMembers(), loadCandidates()]);
        } catch (error) {
            window.alert(errorText(error));
        }
    };
    return (
        <Layout
            admin
            title={_t("Комиссия")}
            description={_t("Основной состав комиссий для ППС и АУП.")}
        >
            <Head title={_t("Комиссия")} />
            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8">
                <div className="flex gap-2">
                    {[
                        ["pps", "ППС"],
                        ["staff", "АУП"],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            className={`rounded-md px-5 py-2.5 text-sm font-semibold ${type === value ? "bg-[#005eb8] text-white" : "border border-gray-300 bg-white"}`}
                            onClick={() => setType(value)}
                        >
                            {_t(label)}
                        </button>
                    ))}
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                        <div className="border-b bg-[#edf4ff] p-4">
                            <h2 className="font-bold text-[#174a9c]">{_t("Текущий состав")}</h2>
                            <form
                                className="mt-3 flex gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    loadMembers();
                                }}
                            >
                                <input
                                    className={inputClass.replace("mt-1 ", "")}
                                    value={memberSearch}
                                    placeholder={_t("Поиск по комиссии")}
                                    onChange={(event) =>
                                        setMemberSearch(event.target.value)
                                    }
                                />
                                <button className="rounded-md bg-[#005eb8] px-3 text-sm font-semibold text-white">{_t("Найти")}</button>
                            </form>
                        </div>
                        {loading ? (
                            <div className="p-8 text-center text-gray-500">{_t("Загрузка...")}</div>
                        ) : (
                            members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between gap-3 border-b border-gray-100 p-4"
                                >
                                    <div>
                                        <div className="font-semibold">
                                            {member.user?.name}
                                        </div>
                                        <div className="text-xs text-gray-500">
                                            {member.user?.email}
                                        </div>
                                    </div>
                                    <button
                                        className="text-sm font-semibold text-red-600"
                                        onClick={() => remove(member)}
                                    >{_t("Удалить")}</button>
                                </div>
                            ))
                        )}
                        {!loading && members.length === 0 && (
                            <div className="p-8 text-center text-sm text-gray-500">{_t("Состав комиссии пуст.")}</div>
                        )}
                    </section>
                    <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                        <div className="border-b bg-[#edf4ff] p-4">
                            <h2 className="font-bold text-[#174a9c]">{_t("Добавить в комиссию")}</h2>
                            <form
                                className="mt-3 flex gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    loadCandidates();
                                }}
                            >
                                <input
                                    className={inputClass.replace("mt-1 ", "")}
                                    value={candidateSearch}
                                    placeholder={_t("Найти пользователя")}
                                    onChange={(event) =>
                                        setCandidateSearch(event.target.value)
                                    }
                                />
                                <button className="rounded-md bg-[#005eb8] px-3 text-sm font-semibold text-white">{_t("Найти")}</button>
                            </form>
                        </div>
                        {candidates.map((user) => (
                            <div
                                key={user.id}
                                className="flex items-center justify-between gap-3 border-b border-gray-100 p-4"
                            >
                                <div>
                                    <div className="font-semibold">
                                        {user.name}
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {user.email}
                                    </div>
                                </div>
                                <button
                                    className="text-sm font-semibold text-[#174a9c]"
                                    onClick={() => add(user)}
                                >{_t("Добавить")}</button>
                            </div>
                        ))}
                        {candidates.length === 0 && (
                            <div className="p-8 text-center text-sm text-gray-500">{_t("Пользователи не найдены.")}</div>
                        )}
                    </section>
                </div>
            </div>
        </Layout>
    );
}
