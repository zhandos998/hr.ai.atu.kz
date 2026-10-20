import { useI18n as useTextI18n } from '../../I18nContext';
import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { ArrowDown, ArrowUp, BriefcaseBusiness, Save, UserPlus, X } from 'lucide-react';
import Layout from '../../Components/Layout';
import SearchSelect from '../../Components/SearchSelect';
import { Alert, primaryButtonClass, secondaryButtonClass } from '../../Components/Form';
import { actionOptions, errorText } from '../../personnel';

export default function PersonnelApprovalRoutes() {
    const { t: _t } = useTextI18n();
    const [users, setUsers] = useState([]);
    const [positions, setPositions] = useState([]);
    const [positionUserId, setPositionUserId] = useState(null);
    const [selectedPosition, setSelectedPosition] = useState(null);
    const [savingPosition, setSavingPosition] = useState(false);
    const [positionError, setPositionError] = useState('');
    const [members, setMembers] = useState({});
    const [savedMembers, setSavedMembers] = useState({});
    const [action, setAction] = useState('hire');
    const [selected, setSelected] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        let active = true;
        Promise.all([
            axios.get('/api/admin/personnel/reference-data'),
            axios.get('/api/admin/personnel/approval-routes'),
            axios.get('/api/admin/user-position-options'),
        ]).then(([reference, routes, positionResponse]) => {
            if (!active) return;
            const grouped = Object.fromEntries(actionOptions.map(({ value }) => [value, routes.data
                .filter((route) => route.action_type === value)
                .map((route) => route.approver || { id: route.approver_user_id, name: 'Недоступный пользователь' })]));
            setUsers(reference.data.users);
            setPositions(positionResponse.data);
            setMembers(grouped);
            setSavedMembers(grouped);
        }).catch((requestError) => { if (active) setError(errorText(requestError)); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, []);

    const current = members[action] || [];
    const dirty = JSON.stringify(current.map((user) => user.id)) !== JSON.stringify((savedMembers[action] || []).map((user) => user.id));
    const full = current.length >= 100;
    const busy = saving || savingPosition;
    const managedUser = current.find((user) => Number(user.id) === Number(positionUserId));
    const positionOptions = useMemo(() => positions
        .filter((position) => !(managedUser?.positions || []).some((assigned) => Number(assigned.id) === Number(position.id)))
        .map((position) => ({ id: position.id, name: position.name, secondary: [position.parent_name, position.unit_name].filter(Boolean).join(' / ') })), [positions, managedUser?.positions]);
    const options = useMemo(() => users.filter((user) => !(members[action] || []).some((member) => Number(member.id) === Number(user.id)))
        .map((user) => ({ id: user.id, name: user.name, secondary: user.email })), [users, members, action]);

    const changeMembers = (next) => {
        setMembers((previous) => ({ ...previous, [action]: next }));
        setError(''); setSuccess('');
    };
    const add = () => {
        const user = users.find((candidate) => Number(candidate.id) === Number(selected));
        if (!user || full || current.some((member) => Number(member.id) === Number(user.id))) return;
        changeMembers([...current, user]); setSelected(null);
    };
    const move = (index, direction) => {
        const next = [...current];
        [next[index], next[index + direction]] = [next[index + direction], next[index]];
        changeMembers(next);
    };
    const save = async () => {
        setSaving(true); setError(''); setSuccess('');
        try {
            await axios.put(`/api/admin/personnel/approval-routes/${action}`, { approver_user_ids: current.map((user) => user.id) });
            setSavedMembers((previous) => ({ ...previous, [action]: current }));
            setSuccess('Комиссия по умолчанию сохранена.');
        } catch (requestError) { setError(errorText(requestError)); }
        finally { setSaving(false); }
    };
    const assignPosition = async () => {
        if (!managedUser || !selectedPosition) return;
        setSavingPosition(true); setPositionError(''); setSuccess('');
        try {
            const { data } = await axios.post(`/api/admin/users/${managedUser.id}/positions`, { position_id: selectedPosition });
            const updateUsers = (list) => list.map((user) => Number(user.id) === Number(data.user.id) ? { ...user, positions: data.user.positions } : user);
            setUsers(updateUsers);
            setMembers((previous) => Object.fromEntries(Object.entries(previous).map(([type, list]) => [type, updateUsers(list)])));
            setSavedMembers((previous) => Object.fromEntries(Object.entries(previous).map(([type, list]) => [type, updateUsers(list)])));
            setSelectedPosition(null);
            setSuccess('Должность назначена пользователю.');
        } catch (requestError) { setPositionError(errorText(requestError)); }
        finally { setSavingPosition(false); }
    };

    return <Layout admin title={_t('Комиссия по умолчанию')} actions={<Link href="/admin/personnel-requests" className={secondaryButtonClass}>{_t('К заявкам')}</Link>}>
        <Head title={_t('Комиссия по умолчанию')} />
        <div className="mx-auto max-w-5xl space-y-5 px-4 py-8">
            <div role="tablist" aria-label={_t('Кадровое действие')} className="flex flex-wrap gap-2 border-b border-gray-200 pb-4">
                {actionOptions.map((option) => <button key={option.value} type="button" role="tab" aria-selected={action === option.value} disabled={busy} onClick={() => { setAction(option.value); setSelected(null); setPositionUserId(null); setSelectedPosition(null); setPositionError(''); setError(''); setSuccess(''); }} className={action === option.value ? primaryButtonClass : secondaryButtonClass}>{_t(option.label)}</button>)}
            </div>
            <Alert>{_t(error)}</Alert>
            {success && <p role="status" className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{_t(success)}</p>}
            {loading ? <div className="py-12 text-center text-gray-500">{_t('Загрузка...')}</div> : <div role="tabpanel" className="space-y-4">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="font-semibold text-gray-900">{_t(actionOptions.find((option) => option.value === action).label)}</h2>
                    <span className="shrink-0 text-sm text-gray-500">{_t('Участники')}: {current.length}</span>
                </div>
                <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <SearchSelect key={`${action}:${current.map((user) => user.id).join(',')}`} value={selected} onChange={setSelected} options={options} disabled={full || busy} placeholder={_t('Найти пользователя')} />
                    <button type="button" onClick={add} disabled={!selected || full || busy} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}><UserPlus size={17} />{_t('Добавить')}</button>
                </div>
                {current.length === 0 ? <p className="py-8 text-center text-sm text-gray-500">{_t('Комиссия не настроена.')}</p> : <ol className="divide-y divide-gray-200 rounded-md border border-gray-200 bg-white">
                    {current.map((user, index) => <li key={user.id} className="px-3 py-3 sm:px-4">
                        <div className="flex flex-wrap items-center gap-3">
                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700">{index + 1}</span>
                        <span className="min-w-0 flex-1"><span className="block break-words text-sm font-semibold text-gray-900">{_t(user.name)}</span><span className="block break-all text-xs text-gray-500">{user.email}</span>{(user.positions || []).map((position) => <span key={position.id} className="mt-1 block break-words text-xs text-gray-600">{position.name}{(position.academic_unit || position.department)?.name ? ` · ${(position.academic_unit || position.department).name}` : ''}</span>)}</span>
                        <div className="flex w-full flex-wrap items-center justify-end gap-1 sm:w-auto">
                            <button type="button" title={_t('Добавить должность')} aria-expanded={Number(positionUserId) === Number(user.id)} disabled={busy} onClick={() => { setPositionUserId(Number(positionUserId) === Number(user.id) ? null : user.id); setSelectedPosition(null); setPositionError(''); }} className="mr-auto inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2 py-1.5 text-xs font-semibold text-[#174a9c] hover:bg-blue-50 sm:mr-2"><BriefcaseBusiness size={16} />{_t('Добавить должность')}</button>
                            <button type="button" title={_t('Выше')} aria-label={_t('Выше')} disabled={busy || index === 0} onClick={() => move(index, -1)} className="rounded p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30"><ArrowUp size={16} /></button>
                            <button type="button" title={_t('Ниже')} aria-label={_t('Ниже')} disabled={busy || index === current.length - 1} onClick={() => move(index, 1)} className="rounded p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30"><ArrowDown size={16} /></button>
                            <button type="button" title={_t('Убрать из комиссии')} aria-label={_t('Убрать из комиссии')} disabled={busy} onClick={() => { changeMembers(current.filter((member) => member.id !== user.id)); if (Number(positionUserId) === Number(user.id)) setPositionUserId(null); }} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><X size={17} /></button>
                        </div>
                        </div>
                        {Number(positionUserId) === Number(user.id) && <div className="mt-3 space-y-3 border-t border-gray-100 pt-3">
                            <Alert>{_t(positionError)}</Alert>
                            <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                                <SearchSelect key={`${user.id}:${(user.positions || []).map((position) => position.id).join(',')}`} value={selectedPosition} onChange={setSelectedPosition} options={positionOptions} disabled={busy} placeholder={_t('Найти должность')} />
                                <button type="button" className={primaryButtonClass} disabled={busy || !selectedPosition} onClick={assignPosition}>{_t(savingPosition ? 'Назначение...' : 'Назначить')}</button>
                            </div>
                        </div>}
                    </li>)}
                </ol>}
                <div className="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
                    {dirty && <span className="text-sm text-amber-700">{_t('Изменения не сохранены')}</span>}
                    <button type="button" onClick={save} disabled={busy || !dirty || current.length === 0} className={`${primaryButtonClass} inline-flex items-center gap-2`}><Save size={17} />{_t(saving ? 'Сохранение...' : 'Сохранить')}</button>
                </div>
            </div>}
        </div>
    </Layout>;
}
