import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { Check, FileUp } from 'lucide-react';
import Layout from '../../Components/Layout';
import SearchSelect from '../../Components/SearchSelect';
import Dialog from '../../Components/Dialog';
import { Alert, errorText, primaryButtonClass, secondaryButtonClass } from '../../Components/Form';
import { useI18n } from '../../I18nContext';

export default function UserImport() {
    const { t } = useI18n();
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [rows, setRows] = useState([]);
    const [positions, setPositions] = useState([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [result, setResult] = useState(null);
    const [confirming, setConfirming] = useState(false);
    const input = useRef(null);

    useEffect(() => {
        axios.get('/api/admin/user-position-options').then(({ data }) => setPositions(data))
            .catch((error) => setError(errorText(error)));
    }, []);
    const userOptions = useMemo(() => (preview?.users || []).map((user) => ({ id: user.id, name: user.name, secondary: `${user.email || ''}${user.technical_email ? ` · ${t('Техническая почта')}` : ''}` })), [preview?.users, t]);
    const positionOptions = useMemo(() => positions.map((position) => ({ id: position.id, name: position.name, secondary: [position.parent_name, position.unit_name].filter(Boolean).join(' / ') })), [positions]);
    const summary = rows.reduce((counts, row) => ({ ...counts, [row.action]: counts[row.action] + 1 }), { create: 0, existing: 0, skip: 0 });
    const ready = rows.some((row) => row.action !== 'skip') && rows.every((row) => row.action === 'skip' || (!row.errors.length && (row.action !== 'existing' || row.user_id) && (row.action !== 'create' || row.has_usable_password)));
    const updateRow = (number, changes) => setRows((current) => current.map((row) => row.row_number === number ? { ...row, ...changes } : row));

    const check = async (event) => {
        event.preventDefault();
        if (!file || busy) return;
        setBusy(true); setError(''); setResult(null); setPreview(null); setRows([]);
        const payload = new FormData(); payload.append('file', file);
        try {
            const { data } = await axios.post('/api/admin/users/import/preview', payload);
            setPreview(data); setRows(data.rows);
        } catch (error) { setError(errorText(error)); }
        finally { setBusy(false); }
    };
    const apply = async () => {
        if (busy || !ready) return;
        setConfirming(false); setBusy(true); setError('');
        try {
            const { data } = await axios.post('/api/admin/users/import/apply', { batch_id: preview.batch_id, rows: rows.map(({ row_number, action, user_id, position_id }) => ({ row_number, action, user_id: action === 'existing' ? user_id : null, position_id: action === 'skip' ? null : position_id })) });
            setResult(data.result); setPreview(null); setRows([]); setFile(null);
            if (input.current) input.current.value = '';
        } catch (error) { setError(errorText(error)); }
        finally { setBusy(false); }
    };

    return <Layout admin title={t('Импорт пользователей')} actions={<Link href="/admin/users" className={secondaryButtonClass}>{t('Пользователи')}</Link>}>
        <Head title={t('Импорт пользователей')} />
        <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
            <Alert>{t(error)}</Alert>
            {result && <section className="rounded-md border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900"><h2 className="flex items-center gap-2 font-bold"><Check size={18} />{t('Импорт завершён')}</h2><div className="mt-3 flex flex-wrap gap-x-6 gap-y-2"><span>{t('Создано')}: {result.created.length}</span><span>{t('Обновлено')}: {result.updated.length}</span><span>{t('Назначено должностей')}: {result.positions_assigned}</span><span>{t('Пропущено')}: {result.skipped}</span></div><Link className="mt-4 inline-block font-semibold text-[#005eb8]" href="/admin/users/technical">{t('Пользователи с технической почтой')}</Link></section>}
            <form onSubmit={check} className="flex flex-wrap items-center gap-4 border-b border-gray-200 pb-5">
                <label className="min-w-0 flex-1 rounded-md border border-dashed border-gray-300 bg-white p-4 text-sm"><span className="mb-2 block font-semibold">{t('Файл Excel')}</span><input ref={input} type="file" accept=".xlsx" disabled={busy} className="block w-full min-w-0 text-sm file:mr-3 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-[#005eb8]" onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null); setRows([]); setResult(null); setError(''); }} /></label>
                <button type="submit" disabled={!file || busy} className={`${primaryButtonClass} inline-flex items-center gap-2`}><FileUp size={17} />{t(busy ? 'Обработка...' : 'Проверить файл')}</button>
            </form>
            {preview && <>
                <div className="flex flex-wrap items-center justify-between gap-4"><div className="flex flex-wrap gap-x-5 gap-y-2 text-sm"><span>{t('Новые пользователи')}: <b>{summary.create}</b></span><span>{t('Обновить')}: <b>{summary.existing}</b></span><span>{t('Пропустить')}: <b>{summary.skip}</b></span><span className="text-gray-500">{t('Пароли существующих пользователей сохраняются')}</span></div><button type="button" className={primaryButtonClass} disabled={busy || !ready} onClick={() => setConfirming(true)}>{t('Импортировать выбранные')}</button></div>
                <div className="overflow-x-auto rounded-md border border-gray-200 bg-white">
                    <table className="w-full min-w-[1100px] text-left text-sm">
                        <thead className="border-b border-gray-200 bg-[#edf4ff] text-xs uppercase text-gray-500"><tr>{['Строка', 'ФИО / сведения из файла', 'Идентификатор из файла', 'Действие', 'Учётная запись', 'Должность'].map((label) => <th key={label} className="px-4 py-3">{t(label)}</th>)}</tr></thead>
                        <tbody className="divide-y divide-gray-100">{rows.map((row) => {
                            const options = [...userOptions].sort((a,b) => Number(row.suggestions.includes(b.id)) - Number(row.suggestions.includes(a.id)));
                            return <tr key={row.row_number} className={row.action === 'skip' ? 'bg-gray-50' : ''}>
                                <td className="px-4 py-4 align-top text-gray-400">{row.row_number}</td>
                                <td className="w-[300px] px-4 py-4 align-top"><div className="font-semibold">{row.name}</div><p className="mt-1 whitespace-pre-wrap break-words text-xs text-gray-500">{row.details || '—'}</p>{row.errors.map((error) => <p key={error} className="mt-2 text-xs text-red-700">{t(error)}</p>)}{row.warnings.map((warning) => <p key={warning} className="mt-2 text-xs text-amber-700">{t(warning)}</p>)}</td>
                                <td className="max-w-[180px] break-all px-4 py-4 align-top"><span className="font-mono text-xs">{row.username}</span><p className="mt-2 text-xs text-gray-500">{t(row.action === 'create' ? 'Пароль из Excel' : row.action === 'existing' ? 'Текущий пароль сохранится' : 'Не импортируется')}</p></td>
                                <td className="px-4 py-4 align-top"><select aria-label={`${t('Действие')}: ${row.row_number}`} disabled={busy} className="rounded-md border border-gray-300 bg-white px-2 py-2.5" value={row.action} onChange={(event) => updateRow(row.row_number, { action: event.target.value })}><option value="skip">{t('Пропустить')}</option><option value="existing" disabled={row.errors.length > 0}>{t('Обновить')}</option><option value="create" disabled={!row.has_usable_password || row.errors.length > 0}>{t('Создать')}</option></select></td>
                                <td className="w-[260px] px-4 py-4 align-top">{row.action === 'existing' ? <SearchSelect value={row.user_id} onChange={(value) => updateRow(row.row_number, { user_id: value })} options={options} disabled={busy} placeholder={t('Выберите пользователя')} /> : row.action === 'create' ? <span className="text-xs text-gray-500">{t('Техническая почта')}<br />@hr-ai.invalid</span> : '—'}</td>
                                <td className="w-[260px] px-4 py-4 align-top"><SearchSelect value={row.position_id} onChange={(value) => updateRow(row.row_number, { position_id: value })} options={positionOptions} disabled={busy || row.action === 'skip'} placeholder={t('Должность не выбрана')} /></td>
                            </tr>;
                        })}</tbody>
                    </table>
                </div>
                <div className="flex justify-end"><button type="button" className={primaryButtonClass} disabled={busy || !ready} onClick={() => setConfirming(true)}>{t('Импортировать выбранные')}</button></div>
            </>}
        </div>
        <Dialog open={confirming} title={t('Импортировать выбранные')} message={`${t('Новые пользователи')}: ${summary.create}\n${t('Обновить')}: ${summary.existing}\n${t('Пропустить')}: ${summary.skip}\n\n${t('Пароли существующих пользователей сохраняются')}`} confirmText={t('Импортировать')} onConfirm={apply} onClose={() => setConfirming(false)} />
    </Layout>;
}
