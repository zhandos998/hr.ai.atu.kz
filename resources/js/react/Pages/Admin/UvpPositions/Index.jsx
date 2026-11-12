import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, Field, errorText, inputClass, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
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
    const load = async () => {
        try { const { data } = await axios.get('/api/admin/uvp-positions'); setItems(data); }
        catch (error) { setError(errorText(error)); }
        finally { setLoading(false); }
    };
    useEffect(() => { load(); }, []);
    const save = async event => {
        event.preventDefault(); setSaving(true); setError('');
        try {
            if (editing.id) await axios.put(`/api/admin/uvp-positions/${editing.id}`, { name });
            else await axios.post('/api/admin/uvp-positions', { name });
            setEditing(null); setName(''); await load();
        } catch (error) { setError(errorText(error)); }
        finally { setSaving(false); }
    };
    const remove = async () => {
        setSaving(true); setError('');
        try { await axios.delete(`/api/admin/uvp-positions/${removing.id}`); setRemoving(null); await load(); }
        catch (error) { setRemoving(null); setError(errorText(error)); }
        finally { setSaving(false); }
    };
    const visible = items.filter(item => item.name.toLocaleLowerCase().includes(search.trim().toLocaleLowerCase()));
    return <Layout admin title={t('Должности УВП')} actions={<button className={`${primaryButtonClass} inline-flex items-center gap-2`} onClick={() => { setEditing({}); setName(''); setError(''); }}><Plus size={16} />{t('Добавить должность')}</button>}>
        <Head title={t('Должности УВП')} />
        <div className="mx-auto max-w-5xl space-y-5 px-4 py-8">
            <Alert>{t(error)}</Alert>
            {editing && <form onSubmit={save} className="space-y-4 border-y border-gray-200 py-5">
                <Field label={t('Название должности')}><input autoFocus required maxLength={255} className={inputClass} value={name} onChange={event => setName(event.target.value)} /></Field>
                <div className="flex items-center justify-end gap-2"><button type="button" className={secondaryButtonClass} disabled={saving} onClick={() => setEditing(null)}>{t('Отмена')}</button><button className={primaryButtonClass} disabled={saving || !name.trim()}>{t('Сохранить')}</button></div>
            </form>}
            <input aria-label={t('Поиск по должности')} placeholder={t('Поиск по должности')} className={inputClass} value={search} onChange={event => setSearch(event.target.value)} />
            <section className="overflow-hidden rounded-md border border-gray-200 bg-white">
                <div className="border-b bg-gray-50 px-5 py-3 text-sm text-gray-500">{t('Показано:')} {visible.length}</div>
                {loading ? <p className="p-8 text-sm text-gray-500">{t('Загрузка...')}</p> : visible.length ? visible.map(item => <div key={item.id} className="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 last:border-0">
                    <span className="min-w-0 break-words text-sm font-semibold text-gray-800">{item.name}</span>
                    <div className="flex shrink-0 gap-1">
                        <button title={t('Изменить')} aria-label={`${t('Изменить')}: ${item.name}`} className="rounded p-2 text-[#005eb8] hover:bg-blue-50" disabled={saving} onClick={() => { setEditing(item); setName(item.name); setError(''); }}><Pencil size={17} /></button>
                        <button title={t('Удалить')} aria-label={`${t('Удалить')}: ${item.name}`} className="rounded p-2 text-red-600 hover:bg-red-50" disabled={saving} onClick={() => setRemoving(item)}><Trash2 size={17} /></button>
                    </div>
                </div>) : <p className="p-8 text-sm text-gray-500">{t('Записи не найдены')}</p>}
            </section>
        </div>
        <Dialog open={Boolean(removing)} title={t('Удалить должность?')} message={removing?.name || ''} confirmText={t('Удалить')} danger onClose={() => { if (!saving) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
