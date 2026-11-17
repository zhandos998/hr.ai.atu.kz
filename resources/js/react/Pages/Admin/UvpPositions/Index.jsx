import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, Field, errorText, inputClass, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryPage, DirectoryRow, directoryFormClass } from '../../../Components/Directory';
import { useI18n } from '../../../I18nContext';

export default function UvpPositions() {
    const { t } = useI18n();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [editing, setEditing] = useState(null);
    const [name, setName] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const [removing, setRemoving] = useState(null);
    const load = async signal => {
        setLoading(true);
        try { const { data } = await axios.get('/api/admin/uvp-positions', { signal }); if (!signal?.aborted) setItems(data); }
        catch (error) { if (!signal?.aborted) setError(errorText(error)); }
        finally { if (!signal?.aborted) setLoading(false); }
    };
    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal);
        return () => controller.abort();
    }, []);
    const save = async event => {
        event.preventDefault();
        if (saving) return;
        setSaving(true); setError('');
        try {
            if (editing.id) await axios.put('/api/admin/uvp-positions/' + editing.id, { name });
            else await axios.post('/api/admin/uvp-positions', { name });
            setEditing(null); setName(''); await load();
        } catch (error) { setError(errorText(error)); }
        finally { setSaving(false); }
    };
    const remove = async () => {
        if (saving || !removing) return;
        setSaving(true); setError('');
        try { await axios.delete('/api/admin/uvp-positions/' + removing.id); setRemoving(null); await load(); }
        catch (error) { setRemoving(null); setError(errorText(error)); }
        finally { setSaving(false); }
    };
    const visible = items.filter(item => item.name.toLocaleLowerCase().includes(search.trim().toLocaleLowerCase()));
    return <Layout admin title={t('Должности УВП')} description={t('Должности учебно-вспомогательного персонала.')} actions={<button type="button" className={primaryButtonClass + ' inline-flex items-center gap-2'} disabled={saving} onClick={() => { setEditing({}); setName(''); setError(''); }}><Plus size={16} aria-hidden="true" />{t('Добавить должность')}</button>}>
        <Head title={t('Должности УВП')} />
        <DirectoryPage loading={loading}>
            <Alert>{error}</Alert>
            {editing && <form onSubmit={save} className={directoryFormClass}>
                <Field label={t('Название должности')}><input autoFocus required maxLength={255} className={inputClass} value={name} onChange={event => setName(event.target.value)} /></Field>
                <div className="flex items-center justify-end gap-2"><button type="button" className={secondaryButtonClass} disabled={saving} onClick={() => setEditing(null)}>{t('Отмена')}</button><button className={primaryButtonClass} disabled={saving || !name.trim()}>{t('Сохранить')}</button></div>
            </form>}
            <DirectoryFilters value={search} onChange={setSearch} placeholder={t('Поиск по должности')} />
            <DirectoryList count={visible.length} total={items.length} loading={loading} error={Boolean(error)} emptyMessage={t('Записи не найдены')}>
                {visible.map(item => <DirectoryRow key={item.id} name={item.name} secondary={t('Должность УВП')} actions={<>
                    <DirectoryAction label={t('Изменить') + ': ' + item.name} icon={Pencil} disabled={saving} onClick={() => { setEditing(item); setName(item.name); setError(''); }} />
                    <DirectoryAction label={t('Удалить') + ': ' + item.name} icon={Trash2} danger disabled={saving} onClick={() => setRemoving(item)} />
                </>} />)}
            </DirectoryList>
        </DirectoryPage>
        <Dialog open={Boolean(removing)} title={t('Удалить должность?')} message={removing?.name || ''} confirmText={t('Удалить')} danger onClose={() => { if (!saving) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
