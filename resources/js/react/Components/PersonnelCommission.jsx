import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { UserPlus, X } from 'lucide-react';
import SearchSelect from './SearchSelect';
import Dialog from './Dialog';
import { useI18n } from '../I18nContext';
import { errorText } from '../personnel';

export default function PersonnelCommission({ item, onChanged }) {
    const { t } = useI18n();
    const [users, setUsers] = useState([]);
    const [selected, setSelected] = useState(null);
    const [order, setOrder] = useState('');
    const [removing, setRemoving] = useState(null);
    const [saving, setSaving] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;
        axios.get('/api/admin/personnel/reference-data')
            .then(({ data }) => { if (active) setUsers(data.users); })
            .catch((error) => { if (active) setError(errorText(error)); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, []);

    const used = new Set(item.approvals.map((approval) => Number(approval.user_id)));
    const options = users.filter((user) => !used.has(Number(user.id))).map((user) => ({
        id: user.id, name: user.name, secondary: [user.email, user.phone].filter(Boolean).join(' · '),
    }));
    const save = async (approval = null) => {
        if (saving || (!approval && !selected)) return;
        setSaving(true);
        setError('');
        try {
            const endpoint = `/api/admin/personnel/requests/${item.id}/approvers`;
            const { data } = approval
                ? await axios.delete(`${endpoint}/${approval.id}`)
                : await axios.post(endpoint, { user_id: selected, approval_order: order || null });
            onChanged(data.request);
            setSelected(null);
            setOrder('');
            setRemoving(null);
        } catch (error) {
            setRemoving(null);
            setError(errorText(error));
        } finally {
            setSaving(false);
        }
    };

    return <section className="rounded-md border border-gray-200 bg-white shadow-sm">
        <div className="rounded-t-md border-b border-blue-100 bg-[#edf4ff] px-5 py-4"><h2 className="font-bold text-[#174a9c]">{t('Комиссия по заявке')}</h2></div>
        <div className="space-y-4 p-5">
            {error && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{t(error)}</p>}
            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_100px_auto]">
                <SearchSelect key={item.approvals.map((approval) => approval.id).join('-')} value={selected} onChange={setSelected} options={options} disabled={saving || loading} placeholder="Начните вводить ФИО, email или телефон" />
                <input type="number" min="1" max="100" value={order} disabled={saving} onChange={(event) => setOrder(event.target.value)} aria-label={t('Порядок')} placeholder={t('Порядок')} className="min-w-0 rounded-md border border-gray-300 px-3 py-2.5 text-sm" />
                <button type="button" disabled={saving || !selected} onClick={() => save()} className="inline-flex items-center justify-center gap-2 rounded-md bg-[#005eb8] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"><UserPlus size={17} />{t('Добавить')}</button>
            </div>
            <div className="divide-y divide-gray-100">
                {item.approvals.map((approval) => {
                    const canRemove = approval.decision === 'pending' && !approval.decided_at && item.approvals.length > 1;
                    return <div key={approval.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                        <div className="flex min-w-0 items-center gap-3"><span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700">{approval.approval_order}</span><div className="min-w-0"><div className="break-words font-semibold">{approval.user?.name}</div><div className="break-all text-xs text-gray-500">{approval.user?.email}</div></div></div>
                        {canRemove ? <button type="button" disabled={saving} title={t('Убрать из комиссии')} aria-label={`${t('Убрать из комиссии')}: ${approval.user?.name}`} className="shrink-0 rounded p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50" onClick={() => setRemoving(approval)}><X size={17} /></button> : <span className="text-right text-xs text-gray-500">{t(approval.decision === 'pending' ? 'Единственный участник' : 'Решение сохранено')}</span>}
                    </div>;
                })}
            </div>
        </div>
        <Dialog open={Boolean(removing)} title={t('Убрать из комиссии')} message={removing?.user?.name} confirmText={t(saving ? 'Сохранение...' : 'Удалить')} danger onConfirm={() => save(removing)} onClose={() => !saving && setRemoving(null)} />
    </section>;
}
