import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, BriefcaseBusiness, Building2, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, errorText, inputClass, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
import { useI18n } from '../../../I18nContext';

export default function AcademicUnits() {
    const { t } = useI18n();
    const { facultyId = null } = usePage().props;
    const [faculty, setFaculty] = useState(null);
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [search, setSearch] = useState('');
    const [revision, setRevision] = useState(0);
    const [removing, setRemoving] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const base = '/admin/academic-units';

    useEffect(() => { setSearch(''); setRemoving(null); }, [facultyId]);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setError(''); setFaculty(null); setItems([]);
        axios.get(facultyId ? `/api${base}/${facultyId}` : `/api${base}?kind=faculty`, { signal: controller.signal })
            .then(({ data }) => {
                if (controller.signal.aborted) return;
                setFaculty(facultyId ? data : null);
                setItems(facultyId ? data.children : data);
            })
            .catch(error => { if (!controller.signal.aborted) setError(errorText(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [facultyId, revision]);

    const filtered = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return items.filter(item => item.name.toLocaleLowerCase().includes(query));
    }, [items, search]);
    const remove = async () => {
        if (!removing || deleting) return;
        setDeleting(true); setError('');
        try {
            await axios.delete(`/api${base}/${removing.id}`);
            setRevision(value => value + 1);
        } catch (error) { setError(errorText(error)); }
        finally { setDeleting(false); setRemoving(null); }
    };
    const title = facultyId ? faculty?.name || t('Кафедры') : t('Факультеты');
    const actions = (!facultyId || faculty) && <div className="flex flex-wrap gap-2">
        {faculty && <Link href={`${base}/${faculty.id}/edit`} className={`${secondaryButtonClass} inline-flex items-center gap-2`}><Pencil size={16} aria-hidden="true" />{t('Изменить факультет')}</Link>}
        <Link href={`${base}/create${facultyId ? `?faculty_id=${facultyId}` : ''}`} className={`${primaryButtonClass} inline-flex items-center gap-2`}><Plus size={16} aria-hidden="true" />{t(facultyId ? 'Добавить кафедру' : 'Добавить факультет')}</Link>
    </div>;

    return <Layout admin title={title} description={t(facultyId ? 'Кафедры факультета.' : 'Факультеты университета.')} actions={actions}>
        <Head title={title} />
        <div data-guide-busy={loading} className="mx-auto max-w-7xl space-y-5 px-4 py-8">
            {facultyId && <Link href={base} className="inline-flex items-center gap-2 text-sm font-semibold text-[#005eb8]"><ArrowLeft size={16} aria-hidden="true" />{t('К списку факультетов')}</Link>}
            <Alert>{error}</Alert>
            <section className="rounded-md border border-gray-200 bg-white p-4 shadow-sm">
                <input type="search" aria-label={t(facultyId ? 'Поиск кафедры' : 'Поиск факультета')} placeholder={t(facultyId ? 'Поиск кафедры' : 'Поиск факультета')} value={search} onChange={event => setSearch(event.target.value)} className={inputClass.replace('mt-1 ', '')} />
            </section>
            <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-500">
                    {t('Показано:')}{' '}<span className="font-semibold text-gray-800">{filtered.length}</span>{' '}{t('из')}{' '}{items.length}
                </div>
                {loading ? <div className="p-10 text-center text-sm text-gray-500">{t('Загрузка...')}</div> : <>
                    <ul aria-label={t(facultyId ? 'Кафедры' : 'Факультеты')} className="divide-y divide-gray-100">
                        {filtered.map(item => <li key={item.id} data-academic-unit-row className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-5 py-4 text-sm hover:bg-gray-50 md:grid-cols-[minmax(0,1fr)_160px_auto]">
                            <div className="col-span-2 min-w-0 md:col-span-1">
                                {facultyId ? <div className="font-semibold text-gray-900 [overflow-wrap:anywhere]">{item.name}</div> : <Link href={`${base}/${item.id}`} className="font-semibold text-gray-900 hover:text-[#005eb8] hover:underline [overflow-wrap:anywhere]">{item.name}</Link>}
                                <div className="mt-1 text-xs text-gray-400 [overflow-wrap:anywhere]">{facultyId ? faculty?.name : t('Факультет')}</div>
                            </div>
                            <div className="flex min-w-0 items-center gap-2 text-sm text-gray-600">
                                {facultyId ? <BriefcaseBusiness size={16} className="shrink-0 text-gray-400" aria-hidden="true" /> : <Building2 size={16} className="shrink-0 text-gray-400" aria-hidden="true" />}
                                <span>{`${t(facultyId ? 'Должности' : 'Кафедры')}: ${facultyId ? item.positions_count : item.children_count}`}</span>
                            </div>
                            <div className="flex items-center justify-end gap-1">
                                {!facultyId && <Link href={`${base}/${item.id}`} title={t('Открыть')} aria-label={`${t('Открыть')}: ${item.name}`} className="flex h-8 w-8 shrink-0 items-center justify-center rounded text-[#005eb8] hover:bg-blue-50"><ChevronRight size={17} aria-hidden="true" /></Link>}
                                <Link href={`${base}/${item.id}/edit`} title={t('Изменить')} aria-label={`${t('Изменить')}: ${item.name}`} className="flex h-8 w-8 shrink-0 items-center justify-center rounded text-gray-500 hover:bg-blue-50 hover:text-[#174a9c]"><Pencil size={16} aria-hidden="true" /></Link>
                                <button type="button" onClick={() => setRemoving(item)} title={t('Удалить')} aria-label={`${t('Удалить')}: ${item.name}`} className="flex h-8 w-8 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-600"><Trash2 size={16} aria-hidden="true" /></button>
                            </div>
                        </li>)}
                    </ul>
                    {!filtered.length && !error && <p className="p-10 text-center text-sm text-gray-500">{t(facultyId ? 'Кафедры не найдены.' : 'Факультеты не найдены.')}</p>}
                </>}
            </section>
        </div>
        <Dialog open={Boolean(removing)} title={t(facultyId ? 'Удалить кафедру?' : 'Удалить факультет?')} message={removing?.name || ''} confirmText={t('Удалить')} danger onClose={() => { if (!deleting) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
