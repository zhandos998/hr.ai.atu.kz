import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { ArrowLeft, Save, UserMinus, UserPlus } from "lucide-react";
import Layout from "../../../Components/Layout";
import SearchSelect from "../../../Components/SearchSelect";
import Dialog from '../../../Components/Dialog';
import { DirectoryAction, DirectoryList, DirectoryPage, DirectoryRow, directoryFormClass } from '../../../Components/Directory';
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
    const [removing, setRemoving] = useState(null);
    const [assigning, setAssigning] = useState(false);

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
        if (!selectedUser || assigning) return;
        setAssigning(true);
        setError("");
        try {
            await axios.post(`/api/admin/positions/${position.id}/users`, {
                user_id: selectedUser,
            });
            setSelectedUser(null);
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setAssigning(false);
        }
    };

    const detach = async () => {
        if (!removing || assigning) return;
        const user = removing;
        setAssigning(true);
        setError("");
        try {
            await axios.delete(
                `/api/admin/positions/${position.id}/users/${user.id}`,
            );
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setAssigning(false);
            setRemoving(null);
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
            <DirectoryPage loading={loading}>
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
                            className={directoryFormClass}
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

                        <DirectoryList count={position.users?.length || 0} total={position.users?.length || 0} heading={_t('Назначенные сотрудники')} emptyMessage={_t('Сотрудники не назначены.')} toolbar={
                            <div className="flex flex-col gap-2 border-b border-gray-100 p-5 sm:flex-row">
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
                                    disabled={!selectedUser || assigning}
                                    onClick={assign}
                                >
                                    <UserPlus size={16} />{_t("Назначить")}</button>
                            </div>}>
                                {(position.users || []).map((user) => (
                                    <DirectoryRow key={user.id} name={user.name} secondary={user.email || user.phone || _t('Контакты не указаны')}
                                        actions={<DirectoryAction icon={UserMinus} danger disabled={assigning} label={`${_t('Убрать сотрудника')}: ${user.name}`} onClick={() => setRemoving(user)} />} />
                                ))}
                        </DirectoryList>
                    </>
                ) : null}
            </DirectoryPage>
            <Dialog open={Boolean(removing)} title={_t('Убрать сотрудника')} message={removing?.name || ''} confirmText={_t('Убрать')} danger onConfirm={detach} onClose={() => { if (!assigning) setRemoving(null); }} />
        </Layout>
    );
}
