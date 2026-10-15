import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import SearchSelect from '../../Components/SearchSelect';
import { Alert, Field, inputClass, primaryButtonClass, secondaryButtonClass } from '../../Components/Form';
import { errorText } from '../../personnel';

export default function PersonnelApprovalRoutes() {
    const { t: _t } = useTextI18n();
    const [departments, setDepartments] = useState([]);
    const [users, setUsers] = useState([]);
    const [routes, setRoutes] = useState([]);
    const [form, setForm] = useState({ department_id: null, approver_user_id: null, approval_order: 1 });
    const [filterDepartment, setFilterDepartment] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const load = async () => {
        setLoading(true);
        try {
            const [referenceResponse, routesResponse] = await Promise.all([
                axios.get('/api/admin/personnel/reference-data'),
                axios.get('/api/admin/personnel/approval-routes'),
            ]);
            setDepartments(referenceResponse.data.departments);
            setUsers(referenceResponse.data.users);
            setRoutes(routesResponse.data);
        } catch (requestError) { setError(errorText(requestError)); }
        finally { setLoading(false); }
    };
    useEffect(() => { load(); }, []);

    const departmentOptions = useMemo(() => departments.map((item) => ({ id: item.id, name: item.name })), [departments]);
    const usedUsers = useMemo(() => new Set(routes.filter((route) => Number(route.department_id) === Number(form.department_id)).map((route) => Number(route.approver_user_id))), [form.department_id, routes]);
    const userOptions = useMemo(() => users.filter((user) => !usedUsers.has(Number(user.id))).map((user) => ({ id: user.id, name: user.name, secondary: user.email })), [usedUsers, users]);
    const visibleRoutes = filterDepartment ? routes.filter((route) => Number(route.department_id) === Number(filterDepartment)) : routes;

    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setError('');
        try {
            await axios.post('/api/admin/personnel/approval-routes', form);
            setForm((current) => ({ ...current, approver_user_id: null }));
            await load();
        } catch (requestError) { setError(errorText(requestError)); }
        finally { setSaving(false); }
    };
    const remove = async (route) => {
        if (!window.confirm(`Удалить ${route.approver?.name} из комиссии по умолчанию?`)) return;
        try { await axios.delete(`/api/admin/personnel/approval-routes/${route.id}`); await load(); }
        catch (requestError) { setError(errorText(requestError)); }
    };

    return <Layout admin title={_t("Комиссия по умолчанию")} description={_t("Состав комиссии копируется в новую кадровую заявку по выбранному подразделению.")} actions={<Link href="/admin/personnel-requests" className={secondaryButtonClass}>{_t("К заявкам")}</Link>}>
        <Head title={_t("Комиссия по умолчанию")} />
        <div className="mx-auto max-w-6xl space-y-6 px-4 py-8">
            <Alert>{_t(error)}</Alert>
            <form className="grid gap-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm md:grid-cols-[1fr_1fr_120px_auto] md:items-end" onSubmit={submit}>
                <Field label={_t("Подразделение")}><SearchSelect value={form.department_id} onChange={(value) => setForm({ ...form, department_id: value, approver_user_id: null })} options={departmentOptions} placeholder={_t("Найти подразделение")} /></Field>
                <Field label={_t("Участник комиссии")}><SearchSelect value={form.approver_user_id} onChange={(value) => setForm({ ...form, approver_user_id: value })} options={userOptions} disabled={!form.department_id} placeholder={_t("Найти пользователя")} /></Field>
                <Field label={_t("Порядок")}><input required type="number" min="1" max="100" className={inputClass} value={form.approval_order} onChange={(event) => setForm({ ...form, approval_order: event.target.value })} /></Field>
                <button className={primaryButtonClass} disabled={saving || !form.department_id || !form.approver_user_id}>{_t(saving ? 'Добавление...' : 'Добавить')}</button>
            </form>
            <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-200 bg-[#edf4ff] p-4"><div className="max-w-md"><SearchSelect value={filterDepartment} onChange={setFilterDepartment} options={departmentOptions} placeholder={_t("Фильтр по подразделению")} /></div>{filterDepartment && <button className="mt-2 text-xs font-semibold text-[#174a9c]" onClick={() => setFilterDepartment(null)}>{_t("Показать все")}</button>}</div>
                {loading ? <div className="p-10 text-center text-gray-500">{_t("Загрузка...")}</div> : visibleRoutes.length === 0 ? <div className="p-10 text-center text-gray-500">{_t("Комиссия не настроена.")}</div> : <div className="divide-y">{visibleRoutes.map((route) => <div key={route.id} className="grid gap-2 px-5 py-4 text-sm md:grid-cols-[1fr_1fr_100px_auto] md:items-center"><div className="font-semibold">{route.department?.name}</div><div><div>{route.approver?.name}</div><div className="text-xs text-gray-500">{route.approver?.email}</div></div><div className="text-gray-500">№ {_t(route.approval_order)}</div><button className="text-left font-semibold text-red-600 md:text-right" onClick={() => remove(route)}>{_t("Удалить")}</button></div>)}</div>}
            </section>
        </div>
    </Layout>;
}
