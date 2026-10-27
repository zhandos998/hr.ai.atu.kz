import React, { useState } from 'react';
import axios from 'axios';
import { Archive, ArchiveRestore } from 'lucide-react';
import Dialog from './Dialog';
import { useI18n } from '../I18nContext';
import { errorText } from '../personnel';

export default function PersonnelArchiveButton({ item, onChanged, onError }) {
    const { t } = useI18n();
    const [confirming, setConfirming] = useState(false);
    const [saving, setSaving] = useState(false);
    const archived = Boolean(item.archived_at);
    const label = t(archived ? 'Восстановить' : 'Архивировать');
    const Icon = archived ? ArchiveRestore : Archive;

    const save = async () => {
        if (saving) return;
        setSaving(true);
        try {
            const { data } = await axios.post(`/api/admin/personnel/requests/${item.id}/${archived ? 'restore' : 'archive'}`);
            setConfirming(false);
            await onChanged(data.request);
        } catch (error) {
            setConfirming(false);
            onError(errorText(error));
        } finally {
            setSaving(false);
        }
    };

    return <>
        <button type="button" disabled={saving} onClick={() => setConfirming(true)} className="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-50">
            <Icon size={16} />{label}
        </button>
        <Dialog open={confirming} title={label} message={`${item.employee_name}\n\n${t(archived ? 'Восстановить кадровый процесс? Согласование снова станет доступно.' : 'Архивировать кадровый процесс? Документы и решения сохранятся, согласование будет приостановлено.')}`} confirmText={t(saving ? 'Сохранение...' : archived ? 'Восстановить' : 'Архивировать')} onConfirm={save} onClose={() => !saving && setConfirming(false)} />
    </>;
}
