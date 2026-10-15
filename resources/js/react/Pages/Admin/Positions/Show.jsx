import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { ArrowLeft, Save, UserMinus, UserPlus } from "lucide-react";
import Layout from "../../../Components/Layout";
import SearchSelect from "../../../Components/SearchSelect";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

export default function PositionShow() {
    const { t: _t } = useTextI18n();
    const { positionId } = usePage().props;
    const [position, setPosition] = useState(null);
    const [form, setForm] = useState({
        name: "",
        duties: "",
        qualification: "",
    });
    const [users, setUsers] = useState([]);
    const [selectedUser, setSelectedUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");

    const load = async () => {
        const [{ data: positionData }, { data: userData }] = await Promise.all([
            axios.get(`/api/admin/positions/${positionId}`),
            axios.get("/api/admin/position-users"),
        ]);
        setPosition(positionData);
        setForm({
            name: positionData.name || "",
            duties: positionData.duties || "",
            qualification: positionData.qualification || "",
        });
        setUsers(userData);
    };

    useEffect(() => {
        load()
            .catch((requestError) => setError(errorText(requestError)))
            .finally(() => setLoading(false));
    }, [positionId]);

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError("");
        setMessage("");
        try {
            const owner = position.academic_unit_id
                ? {
                      academic_unit_id: position.academic_unit_id,
                      department_id: null,
                  }
                : {
                      department_id: position.department_id,
                      academic_unit_id: null,
                  };
            const { data } = await axios.put(
                `/api/admin/positions/${position.id}`,
                { ...form, ...owner },
            );
            setPosition((current) => ({ ...current, ...data }));
            setMessage("Должность обновлена.");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };

    const assign = async () => {
        if (!selectedUser) return;
        setError("");
        try {
            await axios.post(`/api/admin/positions/${position.id}/users`, {
                user_id: selectedUser,
            });
            setSelectedUser(null);
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };

    const detach = async (user) => {
        if (!window.confirm(`Убрать ${user.name} с этой должности?`)) return;
        setError("");
        try {
            await axios.delete(
                `/api/admin/positions/${position.id}/users/${user.id}`,
            );
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };

    const owner = position?.department || position?.academic_unit;
    const backHref = position?.department_id
        ? `/admin/departments/${position.department_id}`
        : "/admin/positions";
    const availableUsers = users
        .filter(
            (user) =>
                !(position?.users || []).some(
                    (assigned) => assigned.id === user.id,
                ),
        )
        .map((user) => ({
            id: user.id,
            name: user.name,
            secondary: user.email || user.phone,
        }));

    return (
        <Layout
            admin
            title={_t(position?.name || "Должность")}
            description={_t(owner?.name || "Карточка должности")}
        >
            <Head title={_t(position?.name || "Должность")} />
            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8">
                <Link
                    href={backHref}
                    className="inline-flex items-center gap-2 text-sm font-semibold text-[#005eb8]"
                >
                    <ArrowLeft size={16} />{_t("Назад к должностям департамента")}</Link>
                <Alert>{_t(error)}</Alert>
                {message && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {_t(message)}
                    </div>
                )}

                {loading ? (
                    <div className="rounded-md border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                ) : position ? (
                    <>
                        <form
                            className="space-y-5 rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                            onSubmit={save}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field label={_t("Департамент")}>
                                    <input
                                        className={`${inputClass} bg-gray-50`}
                                        value={owner?.name || "Не указан"}
                                        disabled
                                    />
                                </Field>
                                <Field label={_t("Название должности")}>
                                    <input
                                        required
                                        className={inputClass}
                                        value={form.name}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                name: event.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            </div>
                            <Field label={_t("Должностные обязанности")}>
                                <textarea
                                    rows="10"
                                    className={inputClass}
                                    value={form.duties}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            duties: event.target.value,
                                        })
                                    }
                                    placeholder={_t("Укажите должностные обязанности")}
                                />
                            </Field>
                            <Field label={_t("Квалификационные требования")}>
                                <textarea
                                    rows="8"
                                    className={inputClass}
                                    value={form.qualification}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            qualification: event.target.value,
                                        })
                                    }
                                    placeholder={_t("Укажите квалификационные требования")}
                                />
                            </Field>
                            <div className="flex justify-end">
                                <button
                                    className={`${primaryButtonClass} inline-flex items-center gap-2`}
                                    disabled={saving}
                                >
                                    <Save size={16} />{" "}
                                    {_t(saving ? "Сохранение..." : "Сохранить")}
                                </button>
                            </div>
                        </form>

                        <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-gray-200 bg-gray-50 px-5 py-4">
                                <h2 className="font-bold text-gray-900">{_t("Назначенные сотрудники")}</h2>
                                <p className="mt-1 text-sm text-gray-500">{_t("Сотрудников:")}{position.users?.length || 0}
                                </p>
                            </div>
                            <div className="flex gap-2 border-b border-gray-100 p-5">
                                <div className="min-w-0 flex-1">
                                    <SearchSelect
                                        value={selectedUser}
                                        onChange={setSelectedUser}
                                        options={availableUsers}
                                        placeholder={_t("Найти сотрудника по ФИО, email или телефону")}
                                    />
                                </div>
                                <button
                                    type="button"
                                    className={`${secondaryButtonClass} inline-flex items-center gap-2`}
                                    disabled={!selectedUser}
                                    onClick={assign}
                                >
                                    <UserPlus size={16} />{_t("Назначить")}</button>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {(position.users || []).map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div className="min-w-0">
                                            <div className="font-semibold text-gray-900">
                                                {user.name}
                                            </div>
                                            <div className="truncate text-sm text-gray-500">
                                                {_t(user.email ||
                                                    user.phone ||
                                                    "Контакты не указаны")}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            title={_t("Убрать сотрудника")}
                                            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                                            onClick={() => detach(user)}
                                        >
                                            <UserMinus size={17} />
                                        </button>
                                    </div>
                                ))}
                                {!position.users?.length && (
                                    <div className="p-8 text-center text-sm text-gray-500">{_t("Сотрудники не назначены.")}</div>
                                )}
                            </div>
                        </section>
                    </>
                ) : null}
            </div>
        </Layout>
    );
}
