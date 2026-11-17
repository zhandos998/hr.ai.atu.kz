import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, BriefcaseBusiness, Building2, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, errorText, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryMetric, DirectoryPage, DirectoryRow } from '../../../Components/Directory';
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
        <DirectoryPage loading={loading}>
            {facultyId && <Link href={base} className="inline-flex items-center gap-2 text-sm font-semibold text-[#005eb8]"><ArrowLeft size={16} aria-hidden="true" />{t('К списку факультетов')}</Link>}
            <Alert>{error}</Alert>
            <DirectoryFilters value={search} onChange={setSearch} placeholder={t(facultyId ? 'Поиск кафедры' : 'Поиск факультета')} />
            <DirectoryList count={filtered.length} total={items.length} loading={loading} label={t(facultyId ? 'Кафедры' : 'Факультеты')} error={Boolean(error)} emptyMessage={t(facultyId ? 'Кафедры не найдены.' : 'Факультеты не найдены.')}>
                {filtered.map(item => <DirectoryRow key={item.id} data-academic-unit-row name={item.name} secondary={facultyId ? faculty?.name : t('Факультет')} href={facultyId ? undefined : `${base}/${item.id}`}
                    metrics={[<DirectoryMetric icon={facultyId ? BriefcaseBusiness : Building2}>{`${t(facultyId ? 'Должности' : 'Кафедры')}: ${facultyId ? item.positions_count : item.children_count}`}</DirectoryMetric>]}
                    actions={<>
                        {!facultyId && <DirectoryAction href={`${base}/${item.id}`} label={`${t('Открыть')}: ${item.name}`} icon={ChevronRight} />}
                        <DirectoryAction href={`${base}/${item.id}/edit`} label={`${t('Изменить')}: ${item.name}`} icon={Pencil} />
                        <DirectoryAction onClick={() => setRemoving(item)} label={`${t('Удалить')}: ${item.name}`} icon={Trash2} danger />
                    </>} />)}
            </DirectoryList>
        </DirectoryPage>
        <Dialog open={Boolean(removing)} title={t(facultyId ? 'Удалить кафедру?' : 'Удалить факультет?')} message={removing?.name || ''} confirmText={t('Удалить')} danger onClose={() => { if (!deleting) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
