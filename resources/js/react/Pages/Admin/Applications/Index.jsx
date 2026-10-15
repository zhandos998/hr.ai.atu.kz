import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    Archive,
    ArrowLeft,
    Search,
    RotateCcw,
    Pencil,
    UserPlus,
    UserMinus,
} from "lucide-react";
import { useI18n } from "../../../I18nContext";
import { StatusDot } from "../../../Components/ApplicationStatus";
import Layout from "../../../Components/Layout";
import SearchSelect from "../../../Components/SearchSelect";
import Dialog from "../../../Components/Dialog";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

const emptyCreate = {
    full_name: "",
    vacancy_type: "pps",
    faculty_name: "",
    department_name: "",
    department_id: null,
    position_id: null,
};

export default function AdminApplications() {
    const { t: _t } = useTextI18n();
    const {
        url,
        props: { competitionId },
    } = usePage();
    const query = new URLSearchParams(url.split("?")[1] || "");
    const type = query.get("type") === "staff" ? "staff" : "pps";
    const archived = query.get("archived") === "1";
    if (competitionId)
        return (
            <CompetitionApplications
                key={`${competitionId}:${archived}`}
                competitionId={competitionId}
                archived={archived}
            />
        );
    return (
        <ApplicationsList
            key={`${type}:${archived}`}
            type={type}
            archived={archived}
        />
    );
}

function CompetitionApplications({ competitionId, archived }) {
    const { t: _t } = useTextI18n();
    const [competition, setCompetition] = useState(null);
    const [error, setError] = useState("");
    useEffect(() => {
        let active = true;
        axios
            .get(`/api/admin/competitions/${competitionId}`)
            .then(({ data }) => {
                if (active) setCompetition(data);
            })
            .catch((requestError) => {
                if (active) setError(errorText(requestError));
            });
        return () => {
            active = false;
        };
    }, [competitionId]);
    if (!competition)
        return (
            <Layout admin title={_t("Конкурс")}>
                <div className="mx-auto max-w-7xl space-y-4 px-4 py-6">
                    <Alert>{_t(error)}</Alert>
                    {!error && (
                        <p className="text-sm text-gray-500">{_t("Загрузка...")}</p>
                    )}
                    <Link
                        href="/admin/competitions"
                        className="text-sm text-[#005eb8]"
                    >{_t("К списку конкурсов")}</Link>
                </div>
            </Layout>
        );
    return (
        <ApplicationsList
            type={competition.type}
            archived={archived}
            competition={competition}
        />
    );
}

function ApplicationsList({ type, archived, competition = null }) {
    const { t: _t } = useTextI18n();
    const { t } = useI18n();
    const pageQuery = new URLSearchParams(usePage().url.split("?")[1] || "");
    const sectionTitle = t(
        type === "pps" ? "teacherApplications" : "staffApplications",
    );
    const title = `${competition?.title || sectionTitle}${archived ? ": архив" : ""}`;
    const listUrl = competition
        ? `/admin/competitions/${competition.id}`
        : `/admin/applications?type=${type}`;
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState({
        current_page: 1,
        last_page: 1,
        total: 0,
    });
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({
        q: pageQuery.get("q") || "",
        resume_status: pageQuery.get("resume_status") || "",
        documents_status: pageQuery.get("documents_status") || "",
        compliance_status: pageQuery.get("compliance_status") || "",
        hiring_status: pageQuery.get("hiring_status") || "",
        per_page: Number(pageQuery.get("per_page")) || 25,
    });
    const [applied, setApplied] = useState(filters);
    const [departments, setDepartments] = useState([]);
    const [academicUnits, setAcademicUnits] = useState([]);
    const [positions, setPositions] = useState([]);
    const [showCreate, setShowCreate] = useState(false);
    const [createForm, setCreateForm] = useState({
        ...emptyCreate,
        vacancy_type: type,
    });
    const [resume, setResume] = useState(null);
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState("");
    const [confirm, setConfirm] = useState(null);
    const loadReferences = async () => {
        if (departments.length) return;
        const [departmentResponse, positionResponse, academicResponse] =
            await Promise.all([
                axios.get("/api/admin/departments"),
                axios.get("/api/admin/positions"),
                axios.get("/api/admin/academic-units"),
            ]);
        setDepartments(departmentResponse.data);
        setAcademicUnits(academicResponse.data);
        setPositions(positionResponse.data);
    };
    const load = async (page = 1, next = {}) => {
        setLoading(true);
        setError("");
        const params = {
            ...applied,
            type,
            archived: archived ? 1 : 0,
            competition_id: competition?.id,
            page,
            ...next,
        };
        try {
            const { data } = await axios.get("/api/admin/applications", {
                params,
            });
            setItems(data.data);
            setMeta(data.meta);
            const visibleQuery = new URLSearchParams();
            Object.entries({ ...params, competition_id: undefined, archived: archived ? 1 : undefined }).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== "") visibleQuery.set(key, String(value));
            });
            router.replace({ url: `${listUrl.split("?")[0]}?${visibleQuery}`, preserveState: true, preserveScroll: true });
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load(Math.max(1, Number(pageQuery.get("page")) || 1));
    }, [type, archived]);
    const roots = useMemo(
        () => academicUnits.filter((item) => item.kind === "faculty"),
        [academicUnits],
    );
    const selectedFaculty = academicUnits.find(
        (item) => item.name === createForm.faculty_name,
    );
    const ppsDepartments = academicUnits.filter(
        (item) => Number(item.parent_id) === Number(selectedFaculty?.id),
    );
    const selectedDepartment =
        createForm.vacancy_type === "pps"
            ? academicUnits.find(
                  (item) => item.name === createForm.department_name,
              )
            : departments.find(
                  (item) =>
                      Number(item.id) === Number(createForm.department_id),
              );
    const availablePositions = positions.filter(
        (item) =>
            Number(
                createForm.vacancy_type === "pps"
                    ? item.academic_unit_id
                    : item.department_id,
            ) === Number(selectedDepartment?.id),
    );
    const departmentOptions = departments.map((item) => ({
        id: item.id,
        name: item.name,
        secondary: item.parent_id
            ? departments.find(
                  (parent) => Number(parent.id) === Number(item.parent_id),
              )?.name
            : "",
    }));
    const positionOptions = availablePositions.map((item) => ({
        id: item.id,
        name: item.name,
    }));
    const applyFilters = (event) => {
        event.preventDefault();
        setApplied(filters);
        load(1, filters);
    };
    const resetFilters = () => {
        const next = {
            q: "",
            resume_status: "",
            documents_status: "",
            compliance_status: "",
            hiring_status: "",
            per_page: 25,
        };
        setFilters(next);
        setApplied(next);
        load(1, next);
    };
    const create = async (event) => {
        event.preventDefault();
        setCreating(true);
        setError("");
        const payload = new FormData();
        Object.entries({
            ...createForm,
            vacancy_type: createForm.vacancy_type,
        }).forEach(
            ([key, value]) =>
                value !== null && value !== "" && payload.append(key, value),
        );
        if (resume) payload.append("resume", resume);
        if (competition) payload.append("competition_id", competition.id);
        try {
            await axios.post("/api/admin/applications", payload);
            setCreateForm({ ...emptyCreate, vacancy_type: type });
            setResume(null);
            setShowCreate(false);
            await load(1);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setCreating(false);
        }
    };
    const archiveToggle = async () => {
        const item = confirm?.item;
        if (!item) return;
        try {
            if (confirm.action === "detach") {
                await axios.delete(
                    `/api/admin/competitions/${competition.id}/applications/${item.id}`,
                );
            } else
                await axios.put(
                    `/api/admin/applications/${item.id}/${archived ? "unarchive" : "archive"}`,
                );
            setConfirm(null);
            await load(
                items.length === 1 && meta.current_page > 1
                    ? meta.current_page - 1
                    : meta.current_page,
            );
        } catch (requestError) {
            setConfirm(null);
            setError(errorText(requestError));
        }
    };
    const setHiring = async (item, status) => {
        if (!status) return;
        try {
            const { data } = await axios.put(
                `/api/admin/applications/${item.id}`,
                { stage: "hiring", stage_status: status },
            );
            setItems((current) =>
                current.map((row) =>
                    row.id === item.id ? data.application : row,
                ),
            );
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    const actions = (
        <>
            <span className="text-sm text-gray-500">{_t("Всего заявок:")}<strong>{_t(meta.total)}</strong>
            </span>
            <Link
                href={`${listUrl}${archived ? "" : `${competition ? "?" : "&"}archived=1`}`}
                className={`${secondaryButtonClass} inline-flex items-center gap-2`}
            >
                {archived ? (
                    <ArrowLeft size={16} aria-hidden="true" />
                ) : (
                    <Archive size={16} aria-hidden="true" />
                )}
                {_t(archived ? "К активным заявкам" : "Архивные")}
            </Link>
            {!archived && (
                <button
                    className={primaryButtonClass}
                    onClick={async () => {
                        try {
                            await loadReferences();
                            setCreateForm({
                                ...emptyCreate,
                                vacancy_type: type,
                            });
                            setShowCreate((value) => !value);
                        } catch (requestError) {
                            setError(errorText(requestError));
                        }
                    }}
                >
                    {_t(showCreate
                        ? "Закрыть форму"
                        : competition
                          ? "Новый конкурсант"
                          : "Добавить заявку")}
                </button>
            )}
        </>
    );
    return (
        <Layout
            admin
            title={_t(title)}
            description={
                _t(competition
                    ? `${type === "pps" ? "ППС" : "АУП"} · ${archived ? "Архивные конкурсанты" : "Конкурсанты"}`
                    : archived
                      ? "Архив конкурсных заявок."
                      : "Заявки кандидатов, статусы и этапы рассмотрения.")
            }
            actions={actions}
        >
            <Head title={_t(title)} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-8">
                <Alert>{_t(error)}</Alert>
                {competition && (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <Link
                            href="/admin/competitions"
                            className="inline-flex items-center gap-2 text-[#005eb8]"
                        >
                            <ArrowLeft size={15} />{_t("К списку конкурсов")}</Link>
                        <div className="flex flex-wrap gap-2">
                            <Link
                                href={`/admin/competitions/${competition.id}/edit`}
                                className={`${secondaryButtonClass} inline-flex items-center gap-2`}
                            >
                                <Pencil size={15} />{_t("Изменить конкурс")}</Link>
                            {!archived && (
                                <Link
                                    href={`/admin/competitions/${competition.id}/add`}
                                    className={`${secondaryButtonClass} inline-flex items-center gap-2`}
                                >
                                    <UserPlus size={16} />{_t("Добавить из заявок")}</Link>
                            )}
                        </div>
                    </div>
                )}
                {showCreate && (
                    <form
                        className="space-y-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                        onSubmit={create}
                    >
                        <h2 className="font-bold text-[#174a9c]">
                            {_t(competition
                                ? "Новый конкурсант"
                                : "Добавить заявку вручную")}
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label={_t("ФИО кандидата")}>
                                <input
                                    required
                                    className={inputClass}
                                    value={createForm.full_name}
                                    onChange={(e) =>
                                        setCreateForm({
                                            ...createForm,
                                            full_name: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                            {createForm.vacancy_type === "pps" ? (
                                <>
                                    <Field label={_t("Факультет")}>
                                        <select
                                            required
                                            className={inputClass}
                                            value={createForm.faculty_name}
                                            onChange={(e) =>
                                                setCreateForm({
                                                    ...createForm,
                                                    faculty_name:
                                                        e.target.value,
                                                    department_name: "",
                                                    position_id: null,
                                                })
                                            }
                                        >
                                            <option value="">{_t("Выберите факультет")}</option>
                                            {roots.map((item) => (
                                                <option
                                                    key={item.id}
                                                    value={item.name}
                                                >
                                                    {item.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label={_t("Кафедра")}>
                                        <select
                                            required
                                            className={inputClass}
                                            value={createForm.department_name}
                                            onChange={(e) =>
                                                setCreateForm({
                                                    ...createForm,
                                                    department_name:
                                                        e.target.value,
                                                    position_id: null,
                                                })
                                            }
                                        >
                                            <option value="">{_t("Выберите кафедру")}</option>
                                            {ppsDepartments.map((item) => (
                                                <option
                                                    key={item.id}
                                                    value={item.name}
                                                >
                                                    {item.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label={_t("Позиция")}>
                                        <SearchSelect
                                            value={createForm.position_id}
                                            onChange={(value) =>
                                                setCreateForm({
                                                    ...createForm,
                                                    position_id: value,
                                                })
                                            }
                                            options={positionOptions}
                                            disabled={
                                                !createForm.department_name
                                            }
                                            placeholder={_t("Начните вводить должность")}
                                        />
                                    </Field>
                                </>
                            ) : (
                                <>
                                    <Field label={_t("Департамент")}>
                                        <SearchSelect
                                            value={createForm.department_id}
                                            onChange={(value) =>
                                                setCreateForm({
                                                    ...createForm,
                                                    department_id: value,
                                                    position_id: null,
                                                })
                                            }
                                            options={departmentOptions}
                                            placeholder={_t("Начните вводить департамент")}
                                        />
                                    </Field>
                                    <Field label={_t("Должность")}>
                                        <SearchSelect
                                            value={createForm.position_id}
                                            onChange={(value) =>
                                                setCreateForm({
                                                    ...createForm,
                                                    position_id: value,
                                                })
                                            }
                                            options={positionOptions}
                                            disabled={!createForm.department_id}
                                            placeholder={_t("Начните вводить должность")}
                                        />
                                    </Field>
                                </>
                            )}
                            <Field label={_t("Резюме")} hint={_t("Необязательно, до 2 МБ")}>
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    onChange={(e) =>
                                        setResume(e.target.files?.[0] || null)
                                    }
                                />
                            </Field>
                        </div>
                        <div className="flex justify-end">
                            <button
                                className={primaryButtonClass}
                                disabled={creating}
                            >
                                {_t(creating ? "Создание..." : "Создать заявку")}
                            </button>
                        </div>
                    </form>
                )}

                <form
                    className="flex flex-wrap items-center gap-3"
                    onSubmit={applyFilters}
                >
                    <div className="relative min-w-0 flex-[1_1_300px]">
                        <Search
                            size={17}
                            aria-hidden="true"
                            className="pointer-events-none absolute left-3 top-3 text-gray-400"
                        />
                        <input
                            aria-label={_t("Поиск заявок")}
                            className={`${inputClass.replace("mt-1 ", "")} pl-10`}
                            value={filters.q}
                            placeholder={_t("Кандидат, должность, факультет")}
                            onChange={(e) =>
                                setFilters({ ...filters, q: e.target.value })
                            }
                        />
                    </div>
                    <button className={primaryButtonClass}>{_t("Поиск")}</button>
                    {[
                        [
                            "resume_status",
                            "Резюме",
                            [
                                ["pending", "На рассмотрении"],
                                ["accepted", "Принято"],
                                ["rejected", "Отклонено"],
                            ],
                        ],
                        [
                            "documents_status",
                            "Документы",
                            [
                                ["awaiting_upload", "Ожидается загрузка"],
                                ["accepted", "Приняты"],
                                ["rejected", "Отклонены"],
                            ],
                        ],
                        [
                            "hiring_status",
                            "Приём",
                            [
                                ["not_started", "Не начато"],
                                ["voting", "Голосование"],
                                ...(type === "pps"
                                    ? [
                                          ["hired_1_year", "На 1 год"],
                                          ["hired_3_year", "На 3 года"],
                                      ]
                                    : [["hired", "Принят"]]),
                                ["rejected", "Не брать"],
                            ],
                        ],
                        [
                            "compliance_status",
                            "Коррупция",
                            [
                                ["not_started", "Не начато"],
                                ["clear", "Не выявлена"],
                                ["flagged", "Выявлена"],
                            ],
                        ],
                    ].map(([key, label, options]) => (
                        <select
                            key={key}
                            aria-label={_t(label)}
                            className="min-w-[160px] flex-[1_1_160px] rounded-md border border-gray-300 bg-transparent px-3 py-2.5 text-sm xl:max-w-40"
                            value={filters[key]}
                            onChange={(e) =>
                                setFilters({
                                    ...filters,
                                    [key]: e.target.value,
                                })
                            }
                        >
                            <option value="">{_t(label)}{_t(": все")}</option>
                            {options.map(([value, text]) => (
                                <option key={value} value={value}>
                                    {_t(text)}
                                </option>
                            ))}
                        </select>
                    ))}
                    <button
                        type="button"
                        aria-label={_t("Сбросить фильтры")}
                        title={_t("Сбросить фильтры")}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50"
                        onClick={resetFilters}
                    >
                        <RotateCcw size={17} />
                    </button>
                </form>
                <div className="flex flex-wrap items-center justify-between gap-3 border-y border-gray-200 py-3 text-sm">
                    <span className="text-gray-500">{_t("Показано:")}{" "}
                        <strong className="font-medium text-gray-700">
                            {meta.from || 0}-{meta.to || 0}
                        </strong>{" "}
                        / {_t(meta.total)}
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="mr-2 flex items-center gap-2 text-xs text-gray-500">{_t("На странице")}<select
                                className="rounded-md border border-gray-300 bg-white px-2 py-2 text-gray-700"
                                value={applied.per_page}
                                disabled={loading}
                                onChange={(event) => {
                                    const per_page = Number(event.target.value);
                                    setFilters({ ...filters, per_page });
                                    setApplied({ ...applied, per_page });
                                    load(1, { per_page });
                                }}
                            >
                                {[10, 25, 50, 100].map((count) => (
                                    <option key={count}>{_t(count)}</option>
                                ))}
                            </select>
                        </label>
                        <button
                            disabled={loading || meta.current_page <= 1}
                            className={secondaryButtonClass}
                            onClick={() => load(meta.current_page - 1)}
                        >{_t("Назад")}</button>
                        <span className="px-1 text-xs tabular-nums text-gray-500">
                            {_t(meta.current_page)} / {_t(meta.last_page)}
                        </span>
                        <button
                            disabled={
                                loading || meta.current_page >= meta.last_page
                            }
                            className={secondaryButtonClass}
                            onClick={() => load(meta.current_page + 1)}
                        >{_t("Вперёд")}</button>
                    </div>
                </div>
                <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    {loading ? (
                        <div className="p-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1120px] table-fixed divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50 text-left text-xs font-medium uppercase text-gray-500">
                                    <tr>
                                        <th className="w-[16%] px-4 py-4">{_t("Кандидат")}</th>
                                        <th className="w-[17%] px-4 py-4">{_t("Претендуемая должность")}</th>
                                        <th className="w-[19%] px-4 py-4">
                                            {_t(type === "pps"
                                                ? "Факультет / кафедра"
                                                : "Подразделение")}
                                        </th>
                                        <th className="w-[12%] px-3 py-4">{_t("Статусы")}</th>
                                        <th className="w-[7%] px-3 py-4">{_t("Голоса")}</th>
                                        <th className="w-[14%] px-3 py-4">{_t("Дата")}</th>
                                        <th className="w-[15%] px-4 py-4 text-right">{_t("Действия")}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {items.map((item) => {
                                        const profile = item.pps_profile || {};
                                        const hiringStatus =
                                            item.hiring_status === "hired" &&
                                            item.hiring_term_years
                                                ? `hired_${item.hiring_term_years}_year`
                                                : item.hiring_status;
                                        return (
                                            <tr
                                                key={item.id}
                                                className="align-top hover:bg-gray-50/70"
                                            >
                                                <td className="break-words px-4 py-4">
                                                    <div className="font-semibold leading-5">
                                                        {_t(item.user?.name ||
                                                            "Кандидат не указан")}
                                                    </div>
                                                    <div className="mt-1 text-xs text-gray-400">{_t("ID заявки:")}{_t(item.id)}
                                                    </div>
                                                    {!competition &&
                                                        item.competition && (
                                                            <Link
                                                                href={`/admin/competitions/${item.competition.id}`}
                                                                className="mt-1 block text-xs text-[#005eb8] hover:underline"
                                                            >
                                                                {
                                                                    _t(item
                                                                        .competition
                                                                        .title)
                                                                }
                                                            </Link>
                                                        )}
                                                </td>
                                                <td className="break-words px-4 py-4">
                                                    <div className="font-medium leading-5">
                                                        {_t(profile.desired_position ||
                                                            item.vacancy
                                                                ?.position
                                                                ?.name ||
                                                            item.vacancy
                                                                ?.title ||
                                                            "Не указана")}
                                                    </div>
                                                    <span className="mt-1 inline-block rounded bg-blue-50 px-2 py-0.5 text-xs text-[#174a9c]">
                                                        {_t(type === "pps"
                                                            ? "ППС"
                                                            : "АУП")}
                                                    </span>
                                                </td>
                                                <td className="break-words px-4 py-4 text-xs leading-5 text-gray-600">
                                                    {_t(type === "pps" ? (
                                                        <>
                                                            {_t(profile.faculty_name ||
                                                                "Факультет не указан")}
                                                            <div className="mt-1 text-gray-400">
                                                                {_t(profile.department_name ||
                                                                    "Кафедра не указана")}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        item.vacancy?.position
                                                            ?.department
                                                            ?.name ||
                                                        "Не указано"
                                                    ))}
                                                </td>
                                                <td className="px-3 py-4">
                                                    <div className="flex gap-1">
                                                        {[
                                                            [
                                                                "Резюме",
                                                                item.resume_status,
                                                            ],
                                                            [
                                                                "Документы",
                                                                item.documents_status,
                                                            ],
                                                            [
                                                                "Комплаенс",
                                                                item.compliance_status,
                                                            ],
                                                            [
                                                                "Принятие",
                                                                hiringStatus,
                                                            ],
                                                        ].map(
                                                            ([
                                                                label,
                                                                value,
                                                            ]) => (
                                                                <StatusDot
                                                                    key={label}
                                                                    label={
                                                                        _t(label)
                                                                    }
                                                                    value={
                                                                        value
                                                                    }
                                                                />
                                                            ),
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-5 text-xs tabular-nums">
                                                    <span className="font-medium text-[#174a9c]">
                                                        {item.vote_summary
                                                            ?.voted ?? 0}
                                                    </span>
                                                    <span className="text-gray-400">
                                                        {" "}
                                                        /{" "}
                                                        {item.vote_summary
                                                            ?.total_members ??
                                                            0}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4">
                                                    <div className="text-xs text-gray-500">
                                                        {_t(item.created_at
                                                            ? new Date(
                                                                  item.created_at,
                                                              ).toLocaleDateString(
                                                                  "ru-RU",
                                                              )
                                                            : "")}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <select
                                                        aria-label={_t(`Принятие на работу: ${item.user?.name || item.id}`)}
                                                        disabled={
                                                            item.compliance_status !==
                                                                "clear" ||
                                                            archived
                                                        }
                                                        className="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-xs disabled:bg-gray-50 disabled:text-gray-400"
                                                        value={
                                                            hiringStatus ||
                                                            "not_started"
                                                        }
                                                        onChange={(e) =>
                                                            setHiring(
                                                                item,
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        <option value="not_started">{_t("Не начато")}</option>
                                                        <option value="voting">{_t("Голосование")}</option>
                                                        {type === "pps" ? (
                                                            <>
                                                                <option value="hired_1_year">{_t("Взять на 1 год")}</option>
                                                                <option value="hired_3_year">{_t("Взять на 3 года")}</option>
                                                            </>
                                                        ) : (
                                                            <option value="hired">{_t("Взять на работу")}</option>
                                                        )}
                                                        <option value="rejected">{_t("Не брать")}</option>
                                                    </select>
                                                    <div className="mt-2 flex flex-wrap items-center justify-end gap-2 text-xs">
                                                        <Link
                                                            href={`/admin/applications/${item.id}?type=${type}${archived ? "&archived=1" : ""}${competition ? `&competition_id=${competition.id}` : ""}&return=${encodeURIComponent(window.location.pathname + window.location.search)}`}
                                                            className="py-1.5 font-medium text-[#174a9c] hover:underline"
                                                        >{_t("Открыть")}</Link>
                                                        {competition && (
                                                            <button
                                                                title={_t("Исключить из конкурса")}
                                                                aria-label={_t(`Исключить из конкурса: ${item.user?.name}`)}
                                                                className="rounded p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600"
                                                                onClick={() =>
                                                                    setConfirm({
                                                                        item,
                                                                        action: "detach",
                                                                    })
                                                                }
                                                            >
                                                                <UserMinus
                                                                    size={16}
                                                                />
                                                            </button>
                                                        )}
                                                        <button
                                                            className={`rounded-md border px-2 py-1.5 ${archived ? "border-emerald-200 text-emerald-700 hover:bg-emerald-50" : "border-red-200 text-red-600 hover:bg-red-50"}`}
                                                            onClick={() =>
                                                                setConfirm({
                                                                    item,
                                                                })
                                                            }
                                                        >
                                                            {_t(archived
                                                                ? "Разархивировать"
                                                                : "Архивировать")}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {!items.length && (
                                        <tr>
                                            <td
                                                colSpan="7"
                                                className="p-12 text-center text-gray-500"
                                            >{_t("Заявки не найдены.")}</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {meta.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm">
                            <span className="text-gray-500">{_t("Страница")}{_t(meta.current_page)}{_t("из")}{_t(meta.last_page)}
                            </span>
                            <div className="flex gap-2">
                                <button
                                    disabled={loading || meta.current_page <= 1}
                                    className={secondaryButtonClass}
                                    onClick={() => load(meta.current_page - 1)}
                                >{_t("Назад")}</button>
                                <button
                                    disabled={
                                        loading ||
                                        meta.current_page >= meta.last_page
                                    }
                                    className={secondaryButtonClass}
                                    onClick={() => load(meta.current_page + 1)}
                                >{_t("Вперёд")}</button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
            <Dialog
                open={Boolean(confirm)}
                title={
                    _t(confirm?.action === "detach"
                        ? "Исключить из конкурса"
                        : archived
                          ? "Разархивировать заявку"
                          : "Архивировать заявку")
                }
                message={
                    confirm?.action === "detach"
                        ? `Исключить ${confirm?.item?.user?.name || "кандидата"} из конкурса? Заявка и документы останутся в общем списке.`
                        : archived
                          ? `Вернуть ${confirm?.item?.user?.name || "заявку"} в активный список?`
                          : `Архивировать ${confirm?.item?.user?.name || "заявку"}? Заявка исчезнет из активного списка.`
                }
                confirmText={
                    confirm?.action === "detach"
                        ? "Исключить"
                        : archived
                          ? "Разархивировать"
                          : "Архивировать"
                }
                danger={!archived}
                onClose={() => setConfirm(null)}
                onConfirm={archiveToggle}
            />
        </Layout>
    );
}
