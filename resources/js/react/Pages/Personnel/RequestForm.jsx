import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { UserPlus, X } from 'lucide-react';
import Layout from '../../Components/Layout';
import SearchSelect from '../../Components/SearchSelect';
import { Alert, Field, inputClass, primaryButtonClass, secondaryButtonClass } from '../../Components/Form';
import { actionOptions, decisionLabel, emptyPersonnelForm, errorText, formatFileSize } from '../../personnel';

export default function PersonnelRequestForm() {
    const { t: _t } = useTextI18n();
    const { mode, requestId } = usePage().props;
    const editing = mode === 'edit';
    const [form, setForm] = useState(emptyPersonnelForm);
    const [item, setItem] = useState(null);
    const [departments, setDepartments] = useState([]);
    const [users, setUsers] = useState([]);
    const [defaultRoutes, setDefaultRoutes] = useState([]);
    const [files, setFiles] = useState([]);
    const [createApprovers, setCreateApprovers] = useState([]);
    const [selectedApprover, setSelectedApprover] = useState(null);
    const [approvalOrder, setApprovalOrder] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const fileInput = useRef(null);

    useEffect(() => {
        const load = async () => {
            try {
                const requests = [
                    axios.get('/api/admin/personnel/reference-data'),
                    axios.get('/api/admin/personnel/approval-routes'),
                ];
                if (editing) requests.push(axios.get(`/api/personnel/requests/${requestId}`));
                const [referenceResponse, routesResponse, requestResponse] = await Promise.all(requests);
                setDepartments(referenceResponse.data.departments);
                setUsers(referenceResponse.data.users);
                setDefaultRoutes(routesResponse.data);
                if (!editing) {
                    setCreateApprovers(routesResponse.data.filter((route) => route.action_type === 'hire').map((route) => route.approver).filter(Boolean));
                }
                if (requestResponse) {
                    const data = requestResponse.data;
                    setItem(data);
                    setForm({
                        employee_name: data.employee_name || '', action_type: data.action_type || 'hire',
                        department_id: data.department_id, position_id: data.position_id,
                        target_department_id: data.target_department_id, target_position_id: data.target_position_id,
                        salary: data.salary || '', work_experience: data.work_experience || '', details: data.details || '',
                    });
                }
            } catch (requestError) {
                setError(errorText(requestError));
            } finally {
                setLoading(false);
            }
        };
        load();
    }, [editing, requestId]);

    const departmentOptions = useMemo(() => departments.map((department) => ({ id: department.id, name: department.name, secondary: department.description || '' })), [departments]);
    const positions = (departmentId) => departments.find((department) => Number(department.id) === Number(departmentId))?.positions || [];
    const positionOptions = useMemo(() => positions(form.department_id).map((position) => ({ id: position.id, name: position.name })), [departments, form.department_id]);
    const targetPositionOptions = useMemo(() => positions(form.target_department_id).map((position) => ({ id: position.id, name: position.name })), [departments, form.target_department_id]);
    const selectedDefaults = useMemo(() => defaultRoutes.filter((route) => route.action_type === form.action_type), [defaultRoutes, form.action_type]);
    const actionChanged = editing && item && item.action_type !== form.action_type;
    const approverOptions = useMemo(() => {
        const used = new Set((editing ? (item?.approvals || []).map((approval) => approval.user_id) : createApprovers.map((user) => user.id)).map(Number));
        return users
            .filter((user) => !used.has(Number(user.id)))
            .map((user) => ({
                id: user.id,
                name: user.name,
                secondary: [user.email, user.phone].filter(Boolean).join(' · '),
            }));
    }, [createApprovers, editing, item?.approvals, users]);

    const updateField = (name, value) => {
        if (name === 'action_type' && value !== form.action_type && !editing) {
            setCreateApprovers(defaultRoutes.filter((route) => route.action_type === value).map((route) => route.approver).filter(Boolean));
            setSelectedApprover(null);
        }
        setForm((current) => {
            const next = { ...current, [name]: value };
            if (name === 'department_id') next.position_id = null;
            if (name === 'target_department_id') next.target_position_id = null;
            if (name === 'action_type' && value !== 'hire') { next.salary = ''; next.work_experience = ''; }
            if (name === 'action_type' && value !== 'transfer') { next.target_department_id = null; next.target_position_id = null; }
            return next;
        });
    };

    const addFiles = (event) => {
        const nextFiles = Array.from(event.target.files || []);
        setFiles((current) => [...current, ...nextFiles].slice(0, 20));
        event.target.value = '';
    };

    const submit = async (event) => {
        event.preventDefault();
        setError('');
        if (!editing && createApprovers.length === 0) {
            setError('Добавьте хотя бы одного участника комиссии.');
            return;
        }
        setSaving(true);
        const payload = new FormData();
        Object.entries(form).forEach(([key, value]) => { if (value !== null && value !== '') payload.append(key, value); });
        if (!editing) createApprovers.forEach((user) => payload.append('approver_user_ids[]', user.id));
        files.forEach((file) => payload.append('documents[]', file));
        try {
            const endpoint = editing ? `/api/admin/personnel/requests/${requestId}/update` : '/api/admin/personnel/requests';
            const { data } = await axios.post(endpoint, payload);
            window.location.assign(`/admin/personnel-requests/${data.request.id}`);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };

    const openDocument = async (document) => {
        const response = await axios.get(`/api/personnel/requests/${requestId}/documents/${document.id}`, { responseType: 'blob' });
        const url = URL.createObjectURL(response.data);
        window.open(url, '_blank', 'noopener');
        window.setTimeout(() => URL.revokeObjectURL(url), 60000);
    };

    const deleteDocument = async (document) => {
        if (!window.confirm(`Удалить документ «${document.original_name}»?`)) return;
        try {
            await axios.delete(`/api/admin/personnel/requests/${requestId}/documents/${document.id}`);
            setItem((current) => ({ ...current, documents: current.documents.filter((candidate) => candidate.id !== document.id) }));
        } catch (requestError) { setError(errorText(requestError)); }
    };

    const addApprover = async () => {
        if (!selectedApprover) return;
        try {
            const { data } = await axios.post(`/api/admin/personnel/requests/${requestId}/approvers`, {
                user_id: selectedApprover,
                approval_order: approvalOrder || null,
            });
            setItem(data.request); setSelectedApprover(null); setApprovalOrder('');
        } catch (requestError) { setError(errorText(requestError)); }
    };

    const addCreateApprover = () => {
        const user = users.find((candidate) => Number(candidate.id) === Number(selectedApprover));
        if (!user || createApprovers.some((candidate) => Number(candidate.id) === Number(user.id))) return;
        setCreateApprovers((current) => [...current, user]);
        setSelectedApprover(null);
    };

    const removeCreateApprover = (userId) => {
        setCreateApprovers((current) => current.filter((user) => Number(user.id) !== Number(userId)));
    };

    const removeApprover = async (approval) => {
        if (!window.confirm(`Удалить ${approval.user?.name} из комиссии?`)) return;
        try {
            const { data } = await axios.delete(`/api/admin/personnel/requests/${requestId}/approvers/${approval.id}`);
            setItem(data.request);
        } catch (requestError) { setError(errorText(requestError)); }
    };

    const title = editing ? `Изменить кадровую заявку №${requestId}` : 'Новая кадровая заявка';
    const actions = <Link href={editing ? `/admin/personnel-requests/${requestId}` : '/admin/personnel-requests'} className={secondaryButtonClass}>{_t(editing ? 'К просмотру' : 'Назад к списку')}</Link>;

    return <Layout admin title={_t(title)} description={_t("Данные сотрудника, документы и состав комиссии.")} actions={actions}>
        <Head title={_t(title)} />
        <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
            <Alert>{_t(error)}</Alert>
            {loading ? <div className="py-16 text-center text-gray-500">{_t("Загрузка...")}</div> : <>
                <form className="space-y-6" onSubmit={submit}>
                    <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 className="font-bold text-[#174a9c]">{_t("Данные заявки")}</h2>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <Field label={_t("ФИО сотрудника")}><input required className={inputClass} value={form.employee_name} onChange={(event) => updateField('employee_name', event.target.value)} /></Field>
                            <Field label={_t("Кадровое действие")}><select required className={inputClass} value={form.action_type} onChange={(event) => updateField('action_type', event.target.value)}>{actionOptions.map((option) => <option key={option.value} value={option.value}>{_t(option.label)}</option>)}</select></Field>
                            <Field label={_t("Подразделение")}><SearchSelect value={form.department_id} onChange={(value) => updateField('department_id', value)} options={departmentOptions} placeholder={_t("Найти подразделение")} /></Field>
                            <Field label={_t("Текущая должность")}><SearchSelect value={form.position_id} onChange={(value) => updateField('position_id', value)} options={positionOptions} disabled={!form.department_id} placeholder={_t("Найти должность")} /></Field>
                            {form.action_type === 'hire' && <><Field label={_t("Заработная плата")}><input required type="number" min="0" step="0.01" className={inputClass} value={form.salary} onChange={(event) => updateField('salary', event.target.value)} /></Field>{editing && <Field label={_t("Стаж работы")}><input className={inputClass} value={form.work_experience} onChange={(event) => updateField('work_experience', event.target.value)} /></Field>}</>}
                            {form.action_type === 'transfer' && <><Field label={_t("Новое подразделение")}><SearchSelect value={form.target_department_id} onChange={(value) => updateField('target_department_id', value)} options={departmentOptions} placeholder={_t("Найти подразделение")} /></Field><Field label={_t("Новая должность")}><SearchSelect value={form.target_position_id} onChange={(value) => updateField('target_position_id', value)} options={targetPositionOptions} disabled={!form.target_department_id} placeholder={_t("Найти должность")} /></Field></>}
                            <div className="md:col-span-2"><Field label={_t("Дополнительная информация")}><textarea rows="4" className={inputClass} value={form.details} onChange={(event) => updateField('details', event.target.value)} /></Field></div>
                        </div>
                    </section>

                    <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 className="font-bold text-[#174a9c]">{_t("Документы")}</h2>
                        <input ref={fileInput} multiple type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" className="mt-4 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm" onChange={addFiles} />
                        {files.length > 0 && <div className="mt-3 divide-y rounded-md border border-gray-200">{files.map((file, index) => <div key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 px-3 py-2 text-sm"><span className="min-w-0 truncate">{file.name} <span className="text-gray-400">({_t(formatFileSize(file.size))})</span></span><button type="button" className="font-semibold text-red-600" onClick={() => setFiles((current) => current.filter((_, candidateIndex) => candidateIndex !== index))}>{_t("Убрать")}</button></div>)}</div>}
                        {editing && item?.documents?.length > 0 && <div className="mt-4"><div className="mb-2 text-sm font-semibold text-gray-700">{_t("Уже загружены")}</div><div className="divide-y rounded-md border border-gray-200">{item.documents.map((document) => <div key={document.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm"><button type="button" className="min-w-0 truncate text-left font-semibold text-[#174a9c]" onClick={() => openDocument(document)}>{_t(document.original_name)}</button><button type="button" className="font-semibold text-red-600" onClick={() => deleteDocument(document)}>{_t("Удалить")}</button></div>)}</div></div>}
                    </section>

                    {!editing && <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 className="font-bold text-[#174a9c]">{_t("Комиссия")}</h2>
                        <p className="mt-1 text-sm text-gray-500">{_t("Найдите сотрудника по ФИО, email или телефону и добавьте в комиссию.")}</p>
                        <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
                            <SearchSelect key={`create-commission-${createApprovers.map((user) => user.id).join('-')}`} value={selectedApprover} onChange={setSelectedApprover} options={approverOptions} placeholder={_t("Начните вводить ФИО, email или телефон")} />
                            <button type="button" className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`} disabled={!selectedApprover} onClick={addCreateApprover}><UserPlus size={17} />{_t("Добавить")}</button>
                        </div>
                        {createApprovers.length > 0 && <div className="mt-4 divide-y divide-gray-200 overflow-hidden rounded-md border border-gray-200">
                            {createApprovers.map((user, index) => <div key={user.id} className="flex items-center justify-between gap-3 bg-white px-3 py-3 text-sm">
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700">{_t(index + 1)}</span>
                                    <span className="min-w-0"><span className="block truncate font-semibold text-gray-900">{user.name}</span><span className="block truncate text-xs text-gray-500">{_t([user.email, user.phone].filter(Boolean).join(' · '))}</span></span>
                                </div>
                                <button type="button" className="shrink-0 rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title={_t("Убрать из комиссии")} aria-label={_t(`Убрать ${user.name} из комиссии`)} onClick={() => removeCreateApprover(user.id)}><X size={17} /></button>
                            </div>)}
                        </div>}
                        {createApprovers.length === 0 && <p className="mt-3 text-sm text-amber-700">{_t("Добавьте хотя бы одного участника комиссии.")}</p>}
                    </section>}

                    <div className="flex justify-end gap-3"><Link href={editing ? `/admin/personnel-requests/${requestId}` : '/admin/personnel-requests'} className={secondaryButtonClass}>{_t("Отмена")}</Link><button className={primaryButtonClass} disabled={saving || !form.department_id || (!editing && createApprovers.length === 0)}>{_t(saving ? 'Сохранение...' : 'Сохранить')}</button></div>
                </form>

                {editing && <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 className="font-bold text-[#174a9c]">{_t("Комиссия по заявке")}</h2>
                    {actionChanged && <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{_t("Комиссия нового кадрового действия:")} {_t(selectedDefaults.length ? selectedDefaults.map((route) => route.approver?.name).join(', ') : 'не настроена')}.</div>}
                    <div className="mt-3 divide-y rounded-md border border-gray-200">{(item?.approvals || []).map((approval) => <div key={approval.id} className="grid gap-2 px-3 py-3 text-sm md:grid-cols-[1fr_140px_auto] md:items-center"><div><div className="font-semibold">{approval.user?.name}</div><div className="text-xs text-gray-500">{approval.user?.email}</div></div><span className="text-gray-600">{_t(decisionLabel(approval.decision))}</span><button type="button" className="text-left font-semibold text-red-600 md:text-right" onClick={() => removeApprover(approval)}>{_t("Удалить")}</button></div>)}</div>
                    <div className="mt-4 grid gap-3 md:grid-cols-[1fr_120px_auto]"><SearchSelect value={selectedApprover} onChange={setSelectedApprover} options={approverOptions} placeholder={_t("Найти пользователя")} /><input type="number" min="1" max="100" className={inputClass.replace('mt-1 ', '')} value={approvalOrder} placeholder={_t("Порядок")} onChange={(event) => setApprovalOrder(event.target.value)} /><button type="button" className={primaryButtonClass} disabled={!selectedApprover} onClick={addApprover}>{_t("Добавить")}</button></div>
                </section>}
            </>}
        </div>
    </Layout>;
}
