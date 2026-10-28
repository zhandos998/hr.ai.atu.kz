import { useI18n as useTextI18n } from "../../I18nContext";
import React, { Fragment, useEffect, useMemo, useRef, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { BriefcaseBusiness, UserMinus, UserPlus, X } from "lucide-react";
import Layout from "../../Components/Layout";
import SearchSelect from "../../Components/SearchSelect";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../Components/Form";
import { useAuth } from "../../AuthContext";

const roles = [
    ["user", "Пользователь"],
    ["economist", "Экономист"],
    ["lawyer", "Юрист / комплаенс"],
    ["science_director", "Департамент науки"],
    ["digital_director", "Департамент цифровизации"],
    ["strategy_director", "Стратегическое развитие"],
    ["academic_director", "Академическое развитие"],
    ["library_director", "Научная библиотека"],
    ["professional_development_director", "Повышение квалификации"],
    ["admin", "Администратор"],
];
const roleLabel = (value) =>
    roles.find(([role]) => role === value)?.[1] || value;
const positionUnit = (position) =>
    position.academic_unit || position.department;
const emptyFilters = { q: '', role: '', email_verified: '', commission: '' };

export default function Users() {
    const { t: _t } = useTextI18n();
    const technical = Boolean(usePage().props.technical);
    const { user: currentUser, setSession } = useAuth();
    const [users, setUsers] = useState([]);
    const [positions, setPositions] = useState([]);
    const [filters, setFilters] = useState(emptyFilters);
    const [applied, setApplied] = useState(filters);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({
        name: "",
        email: "",
        phone: "",
        role: "user",
        password: "",
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [positionUserId, setPositionUserId] = useState(null);
    const [selectedPosition, setSelectedPosition] = useState(null);
    const [savingPosition, setSavingPosition] = useState(false);
    const latestLoad = useRef(0);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

    const load = async (params = applied, page = 1, perPage = meta.per_page) => {
        const sequence = ++latestLoad.current;
        setLoading(true);
        try {
            const { data } = await axios.get("/api/admin/users", { params: { ...params, account_type: technical ? 'technical' : 'regular', paginate: 1, page, per_page: perPage } });
            if (sequence !== latestLoad.current) return;
            setUsers(data.data);
            setMeta(data.meta);
        } catch (requestError) {
            if (sequence === latestLoad.current) setError(errorText(requestError));
        } finally {
            if (sequence === latestLoad.current) setLoading(false);
        }
    };

    useEffect(() => {
        setFilters(emptyFilters); setApplied(emptyFilters); setShowCreate(false); setPositionUserId(null);
        load(emptyFilters, 1);
        return () => { latestLoad.current += 1; };
    }, [technical]);

    useEffect(() => {
        axios
            .get("/api/admin/user-position-options")
            .then(({ data }) => setPositions(data))
            .catch((requestError) => setError(errorText(requestError)));
    }, []);

    const submitFilters = (event) => {
        event.preventDefault();
        setApplied(filters);
        setPositionUserId(null);
        load(filters);
    };

    const create = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError("");
        setMessage("");
        try {
            await axios.post("/api/admin/users", form);
            setForm({
                name: "",
                email: "",
                phone: "",
                role: "user",
                password: "",
            });
            setShowCreate(false);
            setMessage("Пользователь создан.");
            await load();
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };

    const changeRole = async (item, role) => {
        if (
            !window.confirm(
                `Изменить роль пользователя «${item.name}» на «${roleLabel(role)}»?`,
            )
        )
            return;
        try {
            await axios.put(`/api/admin/users/${item.id}/role`, { role });
            await load();
        } catch (requestError) {
            window.alert(errorText(requestError));
        }
    };

    const impersonate = async (item) => {
        if (localStorage.getItem("impersonator_token")) {
            window.alert("Сначала вернитесь в свой аккаунт администратора.");
            return;
        }
        if (!window.confirm(`Войти от имени пользователя «${item.name}»?`))
            return;
        try {
            const adminToken = localStorage.getItem("token");
            const { data } = await axios.post(
                `/api/admin/users/${item.id}/impersonate`,
            );
            localStorage.setItem("impersonator_token", adminToken);
            localStorage.setItem(
                "impersonator_user",
                JSON.stringify(currentUser),
            );
            setSession(data.token, data.user);
            window.location.assign("/");
        } catch (requestError) {
            window.alert(errorText(requestError));
        }
    };

    const updateUser = (updatedUser) => {
        setUsers((current) =>
            current.map((item) =>
                Number(item.id) === Number(updatedUser.id) ? updatedUser : item,
            ),
        );
    };

    const assignPosition = async (item) => {
        if (!selectedPosition) return;
        setSavingPosition(true);
        setError("");
        setMessage("");
        try {
            const { data } = await axios.post(
                `/api/admin/users/${item.id}/positions`,
                { position_id: selectedPosition },
            );
            updateUser(data.user);
            setSelectedPosition(null);
            setMessage(data.message);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSavingPosition(false);
        }
    };

    const detachPosition = async (item, position) => {
        if (
            !window.confirm(
                `Снять должность «${position.name}» с пользователя «${item.name}»?`,
            )
        )
            return;
        setSavingPosition(true);
        setError("");
        setMessage("");
        try {
            const { data } = await axios.delete(
                `/api/admin/users/${item.id}/positions/${position.id}`,
            );
            updateUser(data.user);
            setMessage(data.message);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSavingPosition(false);
        }
    };

    const managedUser = users.find(
        (item) => Number(item.id) === Number(positionUserId),
    );
    const availablePositions = useMemo(() => {
        const used = new Set(
            (managedUser?.positions || []).map((position) =>
                Number(position.id),
            ),
        );
        return positions
            .filter((position) => !used.has(Number(position.id)))
            .map((position) => ({
                id: position.id,
                name: position.name,
                secondary: [position.parent_name, position.unit_name]
                    .filter(Boolean)
                    .join(" / "),
            }));
    }, [managedUser?.positions, positions]);

    const actions = !technical ? (
        <button
            className={primaryButtonClass}
            onClick={() => setShowCreate((value) => !value)}
        >
            {_t(showCreate ? "Закрыть форму" : "Добавить пользователя")}
        </button>
    ) : null;

    return (
        <Layout
            admin
            title={_t(technical ? "Пользователи с технической почтой" : "Пользователи")}
            description={_t("Учётные записи, роли, должности и проверка доступа от имени пользователя.")}
            actions={actions}
        >
            <Head title={_t(technical ? "Пользователи с технической почтой" : "Пользователи")} />
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8">
                <Alert>{_t(error)}</Alert>
                {message && <Alert type="success">{_t(message)}</Alert>}
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm"><span className="text-gray-500">{_t("Всего")}: {meta.total}</span><Link href={technical ? '/admin/users' : '/admin/users/technical'} className={secondaryButtonClass}>{_t(technical ? 'Обычные пользователи' : 'Пользователи с технической почтой')}</Link></div>

                {showCreate && (
                    <form
                        className="rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                        onSubmit={create}
                    >
                        <h2 className="mb-4 font-bold text-[#174a9c]">{_t("Новый пользователь")}</h2>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Field label={_t("ФИО")}>
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
                            <Field label={_t("Email")}>
                                <input
                                    required
                                    type="email"
                                    className={inputClass}
                                    value={form.email}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            email: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field label={_t("Телефон")}>
                                <input
                                    className={inputClass}
                                    value={form.phone}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            phone: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field label={_t("Роль")}>
                                <select
                                    className={inputClass}
                                    value={form.role}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            role: event.target.value,
                                        })
                                    }
                                >
                                    {roles.map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {_t(label)}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label={_t("Пароль")}>
                                <input
                                    required
                                    minLength="4"
                                    className={inputClass}
                                    value={form.password}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            password: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button
                                className={primaryButtonClass}
                                disabled={saving}
                            >
                                {_t(saving ? "Создание..." : "Создать")}
                            </button>
                        </div>
                    </form>
                )}

                <form
                    className="grid gap-3 rounded-md border border-gray-200 bg-white p-4 md:grid-cols-[1fr_220px_180px_180px_auto]"
                    onSubmit={submitFilters}
                >
                    <input
                        className={inputClass.replace("mt-1 ", "")}
                        value={filters.q}
                        placeholder={_t("ФИО, email, телефон")}
                        onChange={(event) =>
                            setFilters({ ...filters, q: event.target.value })
                        }
                    />
                    <select
                        className={inputClass.replace("mt-1 ", "")}
                        value={filters.role}
                        onChange={(event) =>
                            setFilters({ ...filters, role: event.target.value })
                        }
                    >
                        <option value="">{_t("Все роли")}</option>
                        {roles.map(([value, label]) => (
                            <option key={value} value={value}>
                                {_t(label)}
                            </option>
                        ))}
                    </select>
                    <select
                        className={inputClass.replace("mt-1 ", "")}
                        value={filters.email_verified}
                        onChange={(event) =>
                            setFilters({
                                ...filters,
                                email_verified: event.target.value,
                            })
                        }
                    >
                        <option value="">{_t("Любой email")}</option>
                        <option value="yes">{_t("Подтверждён")}</option>
                        <option value="no">{_t("Не подтверждён")}</option>
                    </select>
                    <select
                        className={inputClass.replace("mt-1 ", "")}
                        value={filters.commission}
                        onChange={(event) =>
                            setFilters({
                                ...filters,
                                commission: event.target.value,
                            })
                        }
                    >
                        <option value="">{_t("Любая комиссия")}</option>
                        <option value="yes">{_t("В комиссии")}</option>
                        <option value="no">{_t("Без комиссии")}</option>
                    </select>
                    <button className={primaryButtonClass}>{_t("Фильтр")}</button>
                </form>

                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    {loading ? (
                        <div className="p-10 text-center text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-[#edf4ff] text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-5 py-3">{_t("Пользователь")}</th>
                                        <th className="px-5 py-3">{_t("Роль")}</th>
                                        <th className="px-5 py-3">{_t("Контакты")}</th>
                                        <th className="px-5 py-3">{_t("Должности")}</th>
                                        <th className="px-5 py-3">{_t("Создан")}</th>
                                        <th className="px-5 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {users.map((item) => (
                                        <Fragment key={item.id}>
                                            <tr className="hover:bg-gray-50">
                                                <td className="px-5 py-4">
                                                    <div className="font-semibold">{item.name}</div>
                                                    <div className="text-xs text-gray-500">{item.email}</div>
                                                    <div className="mt-1 text-xs text-gray-400">ID: {_t(item.id)}</div>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <select
                                                        disabled={Number(item.id) === Number(currentUser?.id)}
                                                        className="rounded-md border border-gray-300 px-2 py-2 text-sm disabled:bg-gray-100"
                                                        value={item.role || "user"}
                                                        onChange={(event) => changeRole(item, event.target.value)}
                                                    >
                                                        {roles.map(([value, label]) => <option key={value} value={value}>{_t(label)}</option>)}
                                                    </select>
                                                    {item.is_commission_member && <div className="mt-1 text-xs text-emerald-700">{_t("Член комиссии")}</div>}
                                                </td>
                                                <td className="px-5 py-4 text-gray-600">
                                                    <div>{_t(item.phone || "Телефон не указан")}</div>
                                                    <div className={`mt-1 text-xs ${item.email_verified_at ? "text-emerald-700" : "text-red-600"}`}>{_t(item.email_verified_at ? "Email подтверждён" : "Email не подтверждён")}</div>
                                                </td>
                                                <td className="max-w-sm px-5 py-4 text-gray-600">
                                                    <div className="space-y-1">
                                                        {item.positions?.length ? item.positions.map((position) => (
                                                            <div key={position.id}>
                                                                <span className="font-medium text-gray-900">{position.name}</span>
                                                                <span className="text-xs text-gray-500"> · {positionUnit(position)?.name}</span>
                                                            </div>
                                                        )) : <span className="text-gray-400">{_t("Не назначены")}</span>}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        disabled={item.role === "admin"}
                                                        className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-[#005eb8] hover:text-[#174a9c] disabled:cursor-not-allowed disabled:text-gray-400"
                                                        onClick={() => {
                                                            setPositionUserId(Number(positionUserId) === Number(item.id) ? null : item.id);
                                                            setSelectedPosition(null);
                                                            setError("");
                                                            setMessage("");
                                                        }}
                                                    >
                                                        <BriefcaseBusiness size={14} />{_t("Управлять")}</button>
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4 text-gray-500">{_t(new Date(item.created_at).toLocaleDateString("ru-RU"))}</td>
                                                <td className="px-5 py-4 text-right">
                                                    <button disabled={Number(item.id) === Number(currentUser?.id)} className={`${secondaryButtonClass} whitespace-nowrap`} onClick={() => impersonate(item)}>{_t("Войти как")}</button>
                                                </td>
                                            </tr>
                                            {Number(positionUserId) === Number(item.id) && (
                                                <tr className="bg-[#f8faff]">
                                                    <td colSpan="6" className="px-5 py-5">
                                                        <div className="mx-auto max-w-4xl">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <h3 className="font-bold text-gray-900">{_t("Должности пользователя")}</h3>
                                                                    <p className="mt-1 text-sm text-gray-500">{item.name}</p>
                                                                </div>
                                                                <button type="button" title={_t("Закрыть")} className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-500 hover:bg-gray-50" onClick={() => setPositionUserId(null)}><X size={17} /></button>
                                                            </div>
                                                            <div className="mt-4 divide-y divide-gray-100 rounded-md border border-gray-200 bg-white">
                                                                {(item.positions || []).map((position) => (
                                                                    <div key={position.id} className="flex items-center justify-between gap-4 px-4 py-3">
                                                                        <div className="min-w-0">
                                                                            <div className="font-semibold text-gray-900">{position.name}</div>
                                                                            <div className="mt-0.5 text-xs text-gray-500">{_t(positionUnit(position)?.name || "Подразделение не указано")}</div>
                                                                        </div>
                                                                        <button type="button" title={_t("Снять должность")} disabled={savingPosition} className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50" onClick={() => detachPosition(item, position)}><UserMinus size={17} /></button>
                                                                    </div>
                                                                ))}
                                                                {!item.positions?.length && <div className="px-4 py-5 text-center text-sm text-gray-500">{_t("Должности пока не назначены.")}</div>}
                                                            </div>
                                                            <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                                                                <SearchSelect value={selectedPosition} onChange={setSelectedPosition} options={availablePositions} placeholder={_t("Найти должность или подразделение")} />
                                                                <button type="button" disabled={!selectedPosition || savingPosition} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`} onClick={() => assignPosition(item)}><UserPlus size={16} /> {_t(savingPosition ? "Назначение..." : "Назначить")}</button>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    ))}
                                    {users.length === 0 && <tr><td colSpan="6" className="p-10 text-center text-gray-500">{_t("Пользователи не найдены.")}</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <label className="flex items-center gap-2 text-gray-500">{_t("На странице")}<select className="rounded-md border border-gray-300 bg-white px-2 py-2" value={meta.per_page} disabled={loading} onChange={(event) => load(applied, 1, Number(event.target.value))}>{[25,50,100].map((count) => <option key={count} value={count}>{count}</option>)}</select></label>
                    <div className="flex items-center gap-3"><button type="button" className={secondaryButtonClass} disabled={loading || meta.current_page <= 1} onClick={() => load(applied, meta.current_page - 1)}>{_t("Назад")}</button><span>{meta.current_page} / {meta.last_page}</span><button type="button" className={secondaryButtonClass} disabled={loading || meta.current_page >= meta.last_page} onClick={() => load(applied, meta.current_page + 1)}>{_t("Далее")}</button></div>
                </div>
            </div>
        </Layout>
    );
}
