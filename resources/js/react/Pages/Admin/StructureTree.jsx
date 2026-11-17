import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { BriefcaseBusiness, Building2, ChevronDown, ChevronRight, ChevronUp } from 'lucide-react';
import Layout from '../../Components/Layout';
import { Alert, errorText } from '../../Components/Form';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryMetric, DirectoryPage, DirectoryRow } from '../../Components/Directory';
import { useI18n } from '../../I18nContext';
import { buildDepartmentTree, filterDepartmentTree } from '../../lib/directoryStructure';

function Node({ item, query }) {
    const { t } = useI18n();
    const [expanded, setExpanded] = useState(true);
    useEffect(() => { if (query) setExpanded(true); }, [query]);
    const hasContents = Boolean(item.positions?.length || item.children?.length);
    return <div data-structure-node>
        <DirectoryRow name={item.name} secondary={t(item.description || 'Подразделение')} href={'/admin/departments/' + item.id}
            metrics={[
                <DirectoryMetric icon={BriefcaseBusiness}>{t('Должности') + ': ' + (item.positions?.length || 0)}</DirectoryMetric>,
                <DirectoryMetric icon={Building2}>{t('Подразделения') + ': ' + item.children.length}</DirectoryMetric>,
            ]}
            actions={hasContents && <DirectoryAction icon={expanded ? ChevronUp : ChevronDown} label={t(expanded ? 'Свернуть' : 'Развернуть') + ': ' + item.name} aria-expanded={expanded} aria-controls={'structure-' + item.id} onClick={() => setExpanded(value => !value)} />} />
        {hasContents && <div id={'structure-' + item.id} hidden={!expanded} className="ml-2 border-l border-gray-200 pl-2 sm:ml-5 sm:pl-4">
            {(item.positions || []).map(position => <DirectoryRow key={position.id} name={position.name} secondary={t('Должность')} href={'/admin/positions/' + position.id}
                actions={<DirectoryAction icon={ChevronRight} label={t('Открыть') + ': ' + position.name} href={'/admin/positions/' + position.id} />} />)}
            {item.children.map(child => <Node key={child.id} item={child} query={query} />)}
        </div>}
    </div>;
}

export default function StructureTree() {
    const { t } = useI18n();
    const [departments, setDepartments] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    useEffect(() => {
        const controller = new AbortController();
        axios.get('/api/admin/departments', { signal: controller.signal })
            .then(({ data }) => { if (!controller.signal.aborted) setDepartments(data); })
            .catch(error => { if (!controller.signal.aborted) setError(errorText(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, []);
    const roots = useMemo(() => buildDepartmentTree(departments), [departments]);
    const filtered = useMemo(() => filterDepartmentTree(roots, search), [roots, search]);
    return <Layout admin title={t('Структура')} description={t('Иерархия подразделений и должностей.')}>
        <Head title={t('Структура')} />
        <DirectoryPage loading={loading}>
            <Alert>{error}</Alert>
            <DirectoryFilters value={search} onChange={setSearch} placeholder={t('Поиск по подразделениям и должностям')} />
            <DirectoryList count={filtered.length} total={roots.length} loading={loading} error={Boolean(error)}>
                {filtered.map(item => <Node key={item.id} item={item} query={search.trim()} />)}
            </DirectoryList>
        </DirectoryPage>
    </Layout>;
}
