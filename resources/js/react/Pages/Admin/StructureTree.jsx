import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { inputClass } from '../../Components/Form';

function Node({ item, query }) {
    const { t: _t } = useTextI18n();
    const positions = (item.positions || []).filter((position) => !query || [position.name, position.duties, position.qualification].some((value) => String(value || '').toLowerCase().includes(query)));
    const children = (item.children || []).filter((child) => !query || JSON.stringify(child).toLowerCase().includes(query));
    return <div className="border-l border-blue-200 pl-4"><div className="rounded-md border border-gray-200 bg-white p-4"><h3 className="font-bold text-[#174a9c]">{item.name}</h3>{item.description && <p className="mt-1 text-sm text-gray-600">{_t(item.description)}</p>}{positions.length > 0 && <div className="mt-3 space-y-2">{positions.map((position) => <div key={position.id} className="rounded-md bg-gray-50 px-3 py-2"><div className="font-semibold text-gray-800">{position.name}</div>{position.duties && <p className="mt-1 text-xs text-gray-500">{_t("Обязанности:")}{_t(position.duties)}</p>}{position.qualification && <p className="mt-1 text-xs text-gray-500">{_t("Требования:")}{_t(position.qualification)}</p>}</div>)}</div>}</div>{children.length > 0 && <div className="mt-3 space-y-3">{children.map((child) => <Node key={child.id} item={child} query={query} />)}</div>}</div>;
}

export default function StructureTree() {
    const { t: _t } = useTextI18n();
    const [departments, setDepartments] = useState([]); const [search, setSearch] = useState(''); const [loading, setLoading] = useState(true);
    useEffect(() => { axios.get('/api/admin/departments').then(({ data }) => setDepartments(data)).finally(() => setLoading(false)); }, []);
    const roots = useMemo(() => { const map = new Map(departments.map((item) => [Number(item.id), { ...item, children: [] }])); const result = []; map.forEach((item) => { if (item.parent_id && map.has(Number(item.parent_id))) map.get(Number(item.parent_id)).children.push(item); else result.push(item); }); return result; }, [departments]);
    const query = search.trim().toLowerCase();
    return <Layout admin title={_t("Структура")} description={_t("Иерархия подразделений и должностей.")}><Head title={_t("Структура")} /><div className="mx-auto max-w-6xl space-y-5 px-4 py-8"><input className={inputClass.replace('mt-1 ', '')} value={search} placeholder={_t("Поиск по подразделениям и должностям")} onChange={(event) => setSearch(event.target.value)} />{loading ? <div className="text-center text-gray-500">{_t("Загрузка...")}</div> : <div className="space-y-4">{roots.filter((item) => !query || JSON.stringify(item).toLowerCase().includes(query)).map((item) => <Node key={item.id} item={item} query={query} />)}</div>}</div></Layout>;
}
