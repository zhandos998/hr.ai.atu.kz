import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, Field, errorText, inputClass, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
import { useAuth } from '../../../AuthContext';
import { useI18n } from '../../../I18nContext';
import { registers, formatRegisterDate } from './config';

const emptyFilters = { q: '', date_from: '', date_to: '' };
const paginationButtonClass = 'flex size-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 disabled:opacity-40';

export default function PersonnelRegister() {
    const { props: { registerType }, url } = usePage();
    const config = registers[registerType];
    const { t, locale } = useI18n();
    const { loading: authLoading, role } = useAuth();
    const queryString = url.split('?')[1] || '';
    const query = new URLSearchParams(queryString);
    const page = Math.max(1, Number(query.get('page')) || 1);
    const perPage = Number(query.get('per_page')) || 25;
    const [filters, setFilters] = useState(emptyFilters);
    const [result, setResult] = useState({ data: [], total: 0, current_page: 1, last_page: 1 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [deleting, setDeleting] = useState(null);
    const [deletingBusy, setDeletingBusy] = useState(false);
    const [version, setVersion] = useState(0);

    useEffect(() => {
        if (authLoading || role !== 'admin') return;
        const controller = new AbortController();
        const params = new URLSearchParams(queryString);
        setFilters(Object.fromEntries(Object.keys(emptyFilters).map(key => [key, params.get(key) || ''])));
        setLoading(true);
        setError('');
        axios.get(`/api/admin/personnel-register/${registerType}`, { params, signal: controller.signal })
            .then(({ data }) => setResult(data))
            .catch(requestError => { if (!axios.isCancel(requestError)) setError(errorText(requestError)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [registerType, queryString, version, authLoading, role]);

    const navigate = (values) => router.get(config.path, Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '')), { preserveState: true, preserveScroll: true, replace: true });
    const paginate = (nextPage) => navigate({ ...Object.fromEntries(query), page: nextPage, per_page: perPage });
    const remove = async () => {
        if (!deleting || deletingBusy) return;
        setDeletingBusy(true);
        setError('');
        try {
            await axios.delete(`/api/admin/personnel-register/${registerType}/${deleting.id}`);
            setDeleting(null);
            if (result.data.length === 1 && page > 1) paginate(page - 1);
            else setVersion(value => value + 1);
        } catch (requestError) {
            setError(errorText(requestError));
            setDeleting(null);
        } finally { setDeletingBusy(false); }
    };

    return <Layout admin title={t(config.title)} actions={<>
        <span className="text-sm text-gray-500">{t('Всего записей:')} <strong className="font-semibold text-gray-700">{loading ? '...' : result.total}</strong></span>
        <Link href={`${config.path}/create`} className={`${primaryButtonClass} inline-flex items-center gap-2`}><Plus size={16} aria-hidden="true" />{t(config.add)}</Link>
    </>}>
        <Head title={t(config.title)} />
        <div className="mx-auto max-w-7xl space-y-5 px-4 py-6">
            <Alert>{error}</Alert>
            <form className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_170px_170px_auto_auto]" onSubmit={event => { event.preventDefault(); navigate({ ...filters, page: 1, per_page: perPage }); }}>
                <div className="min-w-0 sm:col-span-2 xl:col-span-1"><Field label={t('Поиск')}><input type="search" className={inputClass} maxLength={255} placeholder={t('ФИО, должность, номер документа')} value={filters.q} onChange={event => setFilters({ ...filters, q: event.target.value })} /></Field></div>
                <Field label={t('Период с')}><input type="date" className={`${inputClass} min-w-0`} value={filters.date_from} onChange={event => setFilters({ ...filters, date_from: event.target.value })} /></Field>
                <Field label={t('Период по')}><input type="date" className={`${inputClass} min-w-0`} min={filters.date_from || undefined} value={filters.date_to} onChange={event => setFilters({ ...filters, date_to: event.target.value })} /></Field>
                <button type="submit" className={`${primaryButtonClass} inline-flex min-h-11 items-center justify-center gap-2`} disabled={loading}><Search size={16} aria-hidden="true" />{t('Поиск')}</button>
                <button type="button" title={t('Сброс')} className={`${secondaryButtonClass} inline-flex min-h-11 items-center justify-center gap-2`} disabled={loading} onClick={() => { setFilters(emptyFilters); navigate({ per_page: perPage }); }}><RotateCcw size={16} aria-hidden="true" /><span className="xl:hidden">{t('Сброс')}</span><span className="sr-only hidden xl:inline">{t('Сброс')}</span></button>
            </form>
            <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div className="overflow-x-auto" aria-busy={loading}>
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead className="border-b border-gray-200 bg-[#edf3ff] text-xs text-gray-500"><tr>
                            {['№', 'ФИО сотрудника', 'Должность', config.documentLabel, 'Период', config.detailLabel, 'Действия'].map(label => <th key={label} className="px-4 py-3 font-medium">{t(label)}</th>)}
                        </tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? <tr><td colSpan={7} className="px-4 py-14 text-center text-gray-500">{t('Загрузка...')}</td></tr> : result.data.length === 0 ? <tr><td colSpan={7} className="px-4 py-14 text-center text-gray-500">{t('Записи не найдены')}</td></tr> : result.data.map(entry => <tr key={entry.id} className="align-top hover:bg-gray-50/70">
                                <td className="px-4 py-4 text-gray-400">{entry.id}</td>
                                <td className="w-56 min-w-44 break-words px-4 py-4"><Link href={`${config.path}/${entry.id}`} className="font-semibold leading-6 text-[#174a9c] hover:underline">{entry.employee_name}</Link></td>
                                <td className="min-w-44 max-w-64 break-words px-4 py-4 leading-6 text-gray-600">{entry.position_name}</td>
                                <td className="max-w-40 break-words px-4 py-4 text-gray-600">{entry.document_number}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-gray-600"><time dateTime={entry.starts_on}>{formatRegisterDate(entry.starts_on, locale)}</time><span className="mt-1 block"><time dateTime={entry.ends_on}>{formatRegisterDate(entry.ends_on, locale)}</time></span></td>
                                <td className="max-w-52 break-words px-4 py-4 leading-6 text-gray-600">{entry[config.detailField] || '-'}</td>
                                <td className="px-3 py-3"><div className="flex items-center justify-end gap-1">
                                    <Link href={`${config.path}/${entry.id}`} title={t('Открыть')} aria-label={t('Открыть')} className="flex size-9 items-center justify-center rounded-md text-[#005eb8] hover:bg-blue-50"><Eye size={17} /></Link>
                                    <Link href={`${config.path}/${entry.id}/edit`} title={t('Изменить')} aria-label={t('Изменить')} className="flex size-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100"><Pencil size={16} /></Link>
                                    <button type="button" title={t('Удалить запись')} aria-label={t('Удалить запись')} className="flex size-9 items-center justify-center rounded-md text-red-500 hover:bg-red-50" onClick={() => setDeleting(entry)}><Trash2 size={16} /></button>
                                </div></td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
                <span>{t('Страница')} {result.current_page} / {result.last_page}</span>
                <div className="flex flex-wrap items-center gap-2">
                    <label className="flex items-center gap-2">{t('На странице')}<select className="rounded-md border border-gray-300 bg-white px-2 py-2 text-gray-700" value={perPage} disabled={loading} onChange={event => navigate({ ...Object.fromEntries(query), page: 1, per_page: Number(event.target.value) })}>{[10, 25, 50, 100].map(count => <option key={count}>{count}</option>)}</select></label>
                    <button type="button" title={t('Назад')} aria-label={t('Назад')} disabled={loading || result.current_page <= 1} onClick={() => paginate(page - 1)} className={paginationButtonClass}><ChevronLeft size={18} className="shrink-0" /></button>
                    <button type="button" title={t('Вперёд')} aria-label={t('Вперёд')} disabled={loading || result.current_page >= result.last_page} onClick={() => paginate(page + 1)} className={paginationButtonClass}><ChevronRight size={18} className="shrink-0" /></button>
                </div>
            </div>
        </div>
        <Dialog open={Boolean(deleting)} title={t('Удалить запись?')} message={deleting?.employee_name || ''} confirmText={t(deletingBusy ? 'Удаление...' : 'Удалить')} danger onClose={() => { if (!deletingBusy) setDeleting(null); }} onConfirm={remove} />
    </Layout>;
}
