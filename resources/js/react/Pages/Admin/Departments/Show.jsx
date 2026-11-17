import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    BriefcaseBusiness,
    ChevronDown,
    ChevronUp,
    ChevronRight,
    Pencil,
    Plus,
    Users,
} from "lucide-react";
import Layout from "../../../Components/Layout";
import {
    Alert,
    errorText,
    primaryButtonClass,
} from "../../../Components/Form";
import { groupPositionsByDepartment } from './positionGroups';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryMetric, DirectoryPage, DirectoryRow } from '../../../Components/Directory';

export default function DepartmentShow() {
    const { t: _t, locale } = useTextI18n();
    const { departmentId } = usePage().props;
    const [department, setDepartment] = useState(null);
    const [search, setSearch] = useState("");
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [collapsed, setCollapsed] = useState(() => new Set());

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setDepartment(null);
        setError("");
        setSearch("");
        setCollapsed(new Set());
        axios
            .get(`/api/admin/departments/${departmentId}`, { signal: controller.signal })
            .then(({ data }) => setDepartment(data))
            .catch((requestError) => { if (!axios.isCancel(requestError)) setError(errorText(requestError)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [departmentId]);

    const positions = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return (department?.positions || []).filter(
            (position) =>
                !query ||
                [
                    position.name,
                    position.department?.name,
                    ...(position.users || []).map((user) => user.name),
                ]
                    .filter(Boolean)
                    .some((value) => value.toLocaleLowerCase().includes(query)),
        );
    }, [department, search]);

    const groups = useMemo(() => groupPositionsByDepartment(positions, department, locale), [positions, department, locale]);
    const toggle = (id) => setCollapsed(current => {
        const next = new Set(current);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        return next;
    });

    const actions = department ? (
        <div className="flex flex-wrap items-center gap-2">
            <Link
                href={`/admin/departments/${department.id}/edit`}
                className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700"
            >
                <Pencil size={16} />{_t("Изменить")}</Link>
            <Link
                href={`/admin/positions/create?department_id=${department.id}`}
                className={`${primaryButtonClass} inline-flex items-center gap-2`}
            >
                <Plus size={16} />{_t("Добавить должность")}</Link>
        </div>
    ) : null;

    return (
        <Layout
            admin
            title={_t(department?.name || "Департамент")}
            description={_t("Департамент и дочерние подразделения.")}
            actions={actions}
        >
            <Head title={_t(department?.name || "Департамент")} />
            <DirectoryPage loading={loading}>
                <Link
                    href="/admin/departments"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-[#005eb8]"
                >
                    <ArrowLeft size={16} />{_t("К списку департаментов")}</Link>
                <Alert>{_t(error)}</Alert>

                {loading ? (
                    <div className="rounded-md border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                ) : department ? (
                    <>
                        <section className="grid gap-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm md:grid-cols-3">
                            <div>
                                <div className="text-xs font-semibold uppercase text-gray-400">{_t("Подразделение")}</div>
                                <div className="mt-1 font-semibold text-gray-900">
                                    {department.name}
                                </div>
                                <div className="mt-1 text-sm text-gray-500">
                                    {_t(department.description ||
                                        "Описание не указано")}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs font-semibold uppercase text-gray-400">{_t("Родитель")}</div>
                                <div className="mt-1 text-sm text-gray-700">
                                    {_t(department.parent?.name || "Нет")}
                                </div>
                            </div>
                            <div className="flex items-center gap-3 md:justify-end">
                                <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-50 text-[#005eb8]">
                                    <BriefcaseBusiness size={20} />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold text-gray-900">
                                        {_t(department.positions.length)}
                                    </div>
                                    <div className="text-xs text-gray-500">{_t("должностей")}</div>
                                </div>
                            </div>
                        </section>

                        <DirectoryFilters value={search} placeholder={_t("Поиск по должности, подразделению или сотруднику")} onChange={value => { setSearch(value); setCollapsed(new Set()); }} />
                        <DirectoryList count={positions.length} total={department.positions.length} heading={_t('Должности департамента и подразделений')}
                            emptyMessage={_t(department.positions.length ? 'По вашему запросу ничего не найдено.' : 'В этом департаменте пока нет должностей.')}>
                            <div className="divide-y divide-gray-200">
                                {groups.map((group) => <div key={group.id}>
                                    <DirectoryRow name={group.name} href={`/admin/departments/${group.id}`} secondary={_t('Подразделение')}
                                        metrics={[<DirectoryMetric icon={BriefcaseBusiness}>{`${_t('Должности')}: ${group.positions.length}`}</DirectoryMetric>]}
                                        actions={<DirectoryAction icon={collapsed.has(group.id) ? ChevronDown : ChevronUp} label={`${_t(collapsed.has(group.id) ? 'Развернуть' : 'Свернуть')}: ${group.name}`} aria-expanded={!collapsed.has(group.id)} aria-controls={`department-positions-${group.id}`} onClick={() => toggle(group.id)} />} />
                                    <div id={`department-positions-${group.id}`} hidden={collapsed.has(group.id)} aria-label={group.name} className="divide-y divide-gray-100">
                                        {group.positions.map((position) => (
                                            <DirectoryRow key={position.id} name={position.name} href={`/admin/positions/${position.id}`} secondary={_t('Должность')}
                                                metrics={[<DirectoryMetric icon={Users}>{`${_t('Сотрудники')}: ${position.users?.length || 0}`}</DirectoryMetric>]}
                                                actions={<DirectoryAction href={`/admin/positions/${position.id}`} icon={ChevronRight} label={`${_t('Открыть')}: ${position.name}`} />} />
                                        ))}
                                    </div>
                                </div>)}
                            </div>
                        </DirectoryList>
                    </>
                ) : null}
            </DirectoryPage>
        </Layout>
    );
}
