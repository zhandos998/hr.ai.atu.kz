import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { BriefcaseBusiness, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, errorText, inputClass } from '../../../Components/Form';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryMetric, DirectoryPage, DirectoryRow } from '../../../Components/Directory';
import { useI18n } from '../../../I18nContext';

const decorate = items => {
    const map = new Map(items.map(item => [Number(item.id), item]));
    return items.map(item => {
        const names = [item.name];
        const seen = new Set([Number(item.id)]);
        let current = item;
        while (current.parent_id && map.has(Number(current.parent_id)) && !seen.has(Number(current.parent_id))) {
            current = map.get(Number(current.parent_id));
            seen.add(Number(current.id));
            names.unshift(current.name);
        }
        return { ...item, full_name: names.join(' / ') };
    });
};

export default function Departments() {
    const { t } = useI18n();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [parent, setParent] = useState('none');
    const [error, setError] = useState('');
    const [removing, setRemoving] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [revision, setRevision] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios.get('/api/admin/departments', { signal: controller.signal })
            .then(({ data }) => { if (!controller.signal.aborted) setItems(decorate(data)); })
            .catch(error => { if (!controller.signal.aborted) setError(errorText(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [revision]);
    const filtered = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return items.filter(item => (!parent || (parent === 'none' ? !item.parent_id : Number(item.parent_id) === Number(parent)))
            && [item.name, item.description, item.full_name].some(value => String(value || '').toLocaleLowerCase().includes(query)));
    }, [items, parent, search]);
    const remove = async () => {
        if (!removing || deleting) return;
        setDeleting(true); setError('');
        try { await axios.delete('/api/admin/departments/' + removing.id); setRevision(value => value + 1); }
        catch (error) { setError(errorText(error)); }
        finally { setDeleting(false); setRemoving(null); }
    };
    return <Layout admin title={t('Департаменты')} description={t('Структурные подразделения университета.')} actions={<DirectoryAction primary href="/admin/departments/create" icon={Plus} label={t('Добавить департамент')} />}>
        <Head title={t('Департаменты')} />
        <DirectoryPage loading={loading}>
            <Alert>{error}</Alert>
            <DirectoryFilters value={search} onChange={setSearch} placeholder={t('Поиск по названию или описанию')}>
                <select value={parent} aria-label={t('Родительское подразделение')} className={inputClass.replace('mt-1 ', '')} onChange={event => setParent(event.target.value)}>
                    <option value="">{t('Все родительские подразделения')}</option>
                    <option value="none">{t('Без родителя')}</option>
                    {items.map(item => <option key={item.id} value={item.id}>{item.full_name}</option>)}
                </select>
            </DirectoryFilters>
            <DirectoryList count={filtered.length} total={items.length} loading={loading} error={Boolean(error)}>
                {filtered.map(item => <DirectoryRow key={item.id} name={item.name} href={'/admin/departments/' + item.id}
                    secondary={items.find(parent => Number(parent.id) === Number(item.parent_id))?.full_name || t(item.description || 'Подразделение')}
                    metrics={[<DirectoryMetric icon={BriefcaseBusiness}>{t('Должности') + ': ' + (item.subtree_positions_count ?? item.positions?.length ?? 0)}</DirectoryMetric>]}
                    actions={<>
                        <DirectoryAction href={'/admin/departments/' + item.id} label={t('Открыть') + ': ' + item.name} icon={ChevronRight} />
                        <DirectoryAction href={'/admin/departments/' + item.id + '/edit'} label={t('Изменить') + ': ' + item.name} icon={Pencil} />
                        <DirectoryAction onClick={() => setRemoving(item)} label={t('Удалить') + ': ' + item.name} icon={Trash2} danger />
                    </>} />)}
            </DirectoryList>
        </DirectoryPage>
        <Dialog open={Boolean(removing)} title={t('Удалить подразделение?')} message={removing?.name || ''} confirmText={t('Удалить')} danger onClose={() => { if (!deleting) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
