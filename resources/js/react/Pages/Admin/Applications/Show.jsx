import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { ArrowLeft, ChevronDown, FileText, UserPlus, X } from "lucide-react";
import AttachmentChip from "../../../Components/AttachmentChip";
import {
    StatusBadge,
    applicationStageLabels,
} from "../../../Components/ApplicationStatus";
import Layout from "../../../Components/Layout";
import SearchSelect from "../../../Components/SearchSelect";
import UploadDocsModal from "../../../Components/UploadDocsModal";
import TeacherAudit from "../../../Components/TeacherAudit";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

const ppsGroups = [
    [
        "Основные данные",
        [
            ["full_name", "ФИО"],
            ["birth_year", "Год рождения"],
            ["work_experience", "Стаж работы"],
        ],
    ],
    [
        "Образование",
        [
            [
                "basic_education",
                "Базовое образование",
                "basic_education_documents",
            ],
            ["magistracy", "Магистратура", "magistracy_documents"],
            [
                "scientific_degree",
                "Учёная степень",
                "scientific_degree_documents",
            ],
            ["academic_title", "Учёное звание", "academic_title_documents"],
        ],
    ],
    [
        "Научные труды преподавателя",
        [
            [
                "scientific_works",
                "Научные труды преподавателя",
                "scientific_works_documents",
            ],
            ["digital_mooc", "Наличие ЦОР / МООК", "digital_mooc_documents"],
            ["science_conclusion", "Заключение Департамента науки"],
        ],
    ],
    [
        "Повышение квалификации",
        [
            [
                "professional_development",
                "Повышение квалификации",
                "professional_development_documents",
            ],
        ],
    ],
    [
        "Стратегическое развитие",
        [
            ["final_rating_score", "Итоговый рейтинговый балл"],
            ["student_survey_results", "Результаты анкетирования студентов"],
            ["krk", "КМК"],
        ],
    ],
    [
        "Академическое развитие",
        [
            ["open_lesson_quality", "Оценка качества открытого занятия"],
            ["taught_disciplines", "Преподаваемые дисциплины"],
            [
                "educational_methodical_literature",
                "Учебно-методическая литература",
            ],
            ["academic_conclusion", "Заключение"],
        ],
    ],
    [
        "Научная библиотека",
        [["educational_publication_metrics", "Показатели учебных изданий"]],
    ],
    [
        "Комплаенс",
        [
            [
                "anti_corruption_survey_results",
                "Результаты антикоррупционного анкетирования",
            ],
            [
                "disciplinary_actions_info",
                "Сведения о дисциплинарных взысканиях",
            ],
        ],
    ],
];
const categoryFields = new Set([
    "basic_education_documents",
    "magistracy_documents",
    "scientific_degree_documents",
    "academic_title_documents",
    "scientific_works_documents",
    "digital_mooc_documents",
    "professional_development_documents",
]);
const stageConfig = {
    hiring: {
        title: "Принятие на работу",
        options: [
            ["not_started", "Не начато"],
            ["voting", "Голосование"],
            ["hired", "Взять на работу"],
            ["hired_1_year", "Взять на 1 год"],
            ["hired_3_year", "Взять на 3 года"],
            ["rejected", "Не брать"],
        ],
    },
    resume: {
        title: "Статус резюме",
        options: [
            ["pending", "На рассмотрении"],
            ["accepted", "Принято"],
            ["rejected", "Отклонено"],
        ],
    },
    documents: {
        title: "Статус документов",
        options: [
            ["awaiting_upload", "Ожидается загрузка"],
            ["accepted", "Приняты"],
            ["rejected", "Отклонены"],
        ],
    },
    compliance: {
        title: "Статус коррупции",
        options: [
            ["not_started", "Не начато"],
            ["clear", "Не выявлена"],
            ["flagged", "Выявлена"],
        ],
    },
};
const docLabels = {
    diploma: "Дипломы и сертификаты",
    recommendation_letter: "Рекомендательное письмо",
    scientific_works: "Список научных трудов",
    articles: "Список научных трудов",
    other: "Другое",
};
const voteLabel = (vote, isPps) =>
    vote.decision === "pending"
        ? "Не голосовал"
        : vote.decision === "reject"
          ? "Не брать"
          : isPps && vote.hire_term_years
            ? `Взять на ${vote.hire_term_years} ${Number(vote.hire_term_years) === 1 ? "год" : "года"}`
            : "За";

export default function AdminApplicationShow() {
    const { t: _t } = useTextI18n();
    const {
        props: { applicationId },
        url: pageUrl,
    } = usePage();
    const [item, setItem] = useState(null);
    const [departments, setDepartments] = useState([]);
    const [academicUnits, setAcademicUnits] = useState([]);
    const [positions, setPositions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [ppsDraft, setPpsDraft] = useState({});
    const [categoryFiles, setCategoryFiles] = useState({});
    const [staffDraft, setStaffDraft] = useState({
        full_name: "",
        phone: "",
        department_id: null,
        position_id: null,
    });
    const [stageDraft, setStageDraft] = useState({});
    const [votingDraft, setVotingDraft] = useState({
        voting_date: "",
        voting_order: "",
    });
    const [showDocs, setShowDocs] = useState(false);
    const [resumeFile, setResumeFile] = useState(null);
    const [commissionCandidates, setCommissionCandidates] = useState([]);
    const [selectedCommissionUser, setSelectedCommissionUser] = useState(null);
    const [aiLoading, setAiLoading] = useState(false);
    const sync = (application, reset = "all") => {
        setItem(application);
        if (reset === "all" || reset === "profile") {
            setPpsDraft({ ...(application.pps_profile || {}) });
            setCategoryFiles({});
        }
        if (reset === "all" || reset === "staff") setStaffDraft({
            full_name: application.user?.name || "",
            phone: application.user?.phone || "",
            department_id: application.vacancy?.position?.department_id || null,
            position_id: application.vacancy?.position_id || null,
        });
        if (reset === "all") setStageDraft(
            Object.fromEntries(
                Object.keys(stageConfig).map((stage) => [
                    stage,
                    {
                        status:
                            stage === "hiring" &&
                            application.hiring_status === "hired" &&
                            application.hiring_term_years
                                ? `hired_${application.hiring_term_years}_year`
                                : application[`${stage}_status`] || "",
                        comment: application[`${stage}_comment`] || "",
                    },
                ]),
            ),
        );
        if (reset === "all") setVotingDraft({
            voting_date: application.voting_date || "",
            voting_order: application.voting_order || "",
        });
    };
    const load = async () => {
        setLoading(true);
        setError("");
        try {
            const [
                applicationResponse,
                departmentResponse,
                positionResponse,
                academicResponse,
            ] = await Promise.all([
                axios.get(`/api/admin/applications/${applicationId}`),
                axios.get("/api/admin/departments"),
                axios.get("/api/admin/positions"),
                axios.get("/api/admin/academic-units"),
            ]);
            sync(applicationResponse.data);
            setDepartments(departmentResponse.data);
            setAcademicUnits(academicResponse.data);
            setPositions(positionResponse.data);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, [applicationId]);
    const isPps = item?.vacancy?.type === "pps";
    const roots = academicUnits.filter(
        (department) => department.kind === "faculty",
    );
    const selectedFaculty = academicUnits.find(
        (department) => department.name === ppsDraft.faculty_name,
    );
    const facultyDepartments = academicUnits.filter(
        (department) =>
            Number(department.parent_id) === Number(selectedFaculty?.id),
    );
    const staffPositions = positions.filter(
        (position) =>
            position.department_id &&
            Number(position.department_id) === Number(staffDraft.department_id),
    );
    const positionNames = useMemo(() => {
        const names = new Set(
            positions
                .filter((position) => position.academic_unit_id)
                .map((position) => position.name)
                .filter(Boolean),
        );
        if (ppsDraft.desired_position) names.add(ppsDraft.desired_position);
        return Array.from(names)
            .sort((a, b) => a.localeCompare(b, "ru"))
            .map((name) => ({ id: name, name }));
    }, [positions, ppsDraft.desired_position]);
    const savePps = async () => {
        setSaving(true);
        setError("");
        const payload = new FormData();
        const allowed = ppsGroups.flatMap(([, fields]) =>
            fields.map(([key]) => key),
        );
        [
            ...allowed,
            "desired_position",
            "faculty_name",
            "department_name",
        ].forEach((key) => payload.append(key, ppsDraft[key] ?? ""));
        Object.entries(categoryFiles).forEach(([key, files]) =>
            files.forEach((file) => payload.append(`${key}[]`, file)),
        );
        try {
            const { data } = await axios.post(
                `/api/admin/applications/${applicationId}/pps-profile`,
                payload,
            );
            sync(data.application, "profile");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const saveStaff = async () => {
        setSaving(true);
        setError("");
        try {
            const { data } = await axios.put(
                `/api/admin/applications/${applicationId}/staff-details`,
                staffDraft,
            );
            sync(data.application, "staff");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const saveStage = async (stage) => {
        setSaving(true);
        setError("");
        try {
            const { data } = await axios.put(
                `/api/admin/applications/${applicationId}`,
                {
                    stage,
                    stage_status: stageDraft[stage].status,
                    comment: stageDraft[stage].comment.trim() || null,
                },
            );
            setItem(data.application);
            setStageDraft((current) => ({ ...current, [stage]: { status: data.application[`${stage}_status`] === "hired" && data.application.hiring_term_years ? `hired_${data.application.hiring_term_years}_year` : data.application[`${stage}_status`], comment: data.application[`${stage}_comment`] || "" } }));
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const saveVoting = async () => {
        setSaving(true);
        try {
            const { data } = await axios.put(
                `/api/admin/applications/${applicationId}/voting-date`,
                {
                    voting_date: votingDraft.voting_date || null,
                    voting_order: votingDraft.voting_order || null,
                },
            );
            sync(data.application, "item");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const replaceResume = async () => {
        if (!resumeFile) return;
        setSaving(true);
        const payload = new FormData();
        payload.append("resume", resumeFile);
        try {
            const { data } = await axios.post(
                `/api/admin/applications/${applicationId}/resume`,
                payload,
            );
            sync(data.application, "item");
            setResumeFile(null);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const deleteDocument = async (documentId) => {
        if (!window.confirm("Удалить документ?")) return;
        try {
            const { data } = await axios.delete(
                `/api/admin/applications/${applicationId}/documents/${documentId}`,
            );
            sync(data.application, "item");
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    const deleteProfileDocument = async (documentId) => {
        if (!window.confirm("Удалить документ профиля ППС?")) return;
        try {
            const { data } = await axios.delete(
                `/api/admin/applications/${applicationId}/pps-profile/documents/${documentId}`,
            );
            sync(data.application, "item");
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    const loadCommissionCandidates = async (query = "") => {
        if (!item?.vacancy_id) return;
        try {
            const { data } = await axios.get(
                `/api/admin/vacancies/${item.vacancy_id}/commission-candidates`,
                { params: { q: query } },
            );
            setCommissionCandidates(data);
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    useEffect(() => {
        if (item?.vacancy_id) loadCommissionCandidates();
    }, [item?.vacancy_id, item?.vacancy?.commission_members?.length]);
    const addCommission = async () => {
        if (!selectedCommissionUser) return;
        try {
            const { data } = await axios.post(
                `/api/admin/vacancies/${item.vacancy_id}/commission-members`,
                { user_id: selectedCommissionUser },
            );
            setItem((current) => ({ ...current, vacancy: data.vacancy }));
            setSelectedCommissionUser(null);
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    const removeCommission = async (userId) => {
        if (!window.confirm("Удалить пользователя из комиссии по вакансии?"))
            return;
        try {
            const { data } = await axios.delete(
                `/api/admin/vacancies/${item.vacancy_id}/commission-members/${userId}`,
            );
            setItem((current) => ({ ...current, vacancy: data.vacancy }));
        } catch (requestError) {
            setError(errorText(requestError));
        }
    };
    const analyze = async () => {
        setAiLoading(true);
        setError("");
        try {
            const { data } = await axios.post("/api/check-candidate", {
                application_id: applicationId,
                lang: "ru",
                force: true,
            });
            setItem((current) => ({ ...current, ai_result: data }));
        } catch (requestError) {
            setError(
                errorText(requestError, "Ошибка при генерации AI-анализа."),
            );
        } finally {
            setAiLoading(false);
        }
    };
    const openPdf = async (suffix) => {
        const preview = window.open("", "_blank");
        try {
            const { data } = await axios.get(
                `/api/admin/applications/${applicationId}/${suffix}`,
                { responseType: "blob" },
            );
            const url = URL.createObjectURL(
                new Blob([data], { type: "application/pdf" }),
            );
            if (preview) preview.location.href = url;
            else window.open(url, "_blank");
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (requestError) {
            preview?.close();
            setError(errorText(requestError, "Ошибка при открытии PDF."));
        }
    };
    const displayPosition =
        item?.pps_profile?.desired_position ||
        item?.vacancy?.position?.name ||
        item?.vacancy?.title;
    const fromCompetition =
        item?.competition_id &&
        new URLSearchParams(pageUrl.split("?")[1] || "").get(
            "competition_id",
        ) === String(item.competition_id);
    const requestedReturn = new URLSearchParams(pageUrl.split("?")[1] || "").get("return");
    const safeReturn = requestedReturn && /^\/admin\/(applications(?:\?|$)|competitions\/\d+(?:\?|$))/.test(requestedReturn);
    const returnUrl = safeReturn ? requestedReturn : fromCompetition
        ? `/admin/competitions/${item.competition_id}${item.archived_at ? "?archived=1" : ""}`
        : `/admin/applications?type=${isPps ? "pps" : "staff"}${item?.archived_at ? "&archived=1" : ""}`;
    const setProfileField = (key, value) =>
        setPpsDraft((current) => ({ ...current, [key]: value }));
    const profileFiles = (field, label) => (
        <div className="document-upload mt-3 rounded-md border border-dashed border-gray-300 bg-gray-50 p-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="text-xs text-gray-500">{_t("Подтверждающие документы")}</span>
                <input
                    aria-label={_t(`Документы: ${label}`)}
                    type="file"
                    multiple
                    disabled={saving}
                    onChange={(event) => {
                        const files = Array.from(event.target.files || []);
                        setCategoryFiles((current) => ({
                            ...current,
                            [field]: [...(current[field] || []), ...files],
                        }));
                        event.target.value = "";
                    }}
                />
            </div>
            {((item.pps_profile?.[field] || []).length > 0 ||
                (categoryFiles[field] || []).length > 0) && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {(item.pps_profile?.[field] || []).map((document) => (
                        <AttachmentChip
                            key={document.id}
                            name={document.name || "Документ"}
                            url={document.url}
                            disabled={saving}
                            onRemove={() => deleteProfileDocument(document.id)}
                        />
                    ))}
                    {(categoryFiles[field] || []).map((file, index) => (
                        <AttachmentChip
                            key={`${file.name}-${index}`}
                            name={file.name}
                            disabled={saving}
                            onRemove={() =>
                                setCategoryFiles((current) => ({
                                    ...current,
                                    [field]: current[field].filter(
                                        (_, fileIndex) => fileIndex !== index,
                                    ),
                                }))
                            }
                        />
                    ))}
                </div>
            )}
        </div>
    );
    return (
        <Layout
            admin
            title={_t(`Заявка №${applicationId}`)}
            description={_t("Полная информация по кандидату и этапам рассмотрения.")}
        >
            <Head title={_t(`Заявка №${applicationId}`)} />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-6">
                <Alert>{_t(error)}</Alert>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={returnUrl}
                        className="inline-flex items-center gap-2 py-2 text-sm font-medium text-[#005eb8] hover:underline"
                    >
                        <ArrowLeft size={15} aria-hidden="true" />
                        {_t(fromCompetition ? "К конкурсу" : "К списку заявок")}
                    </Link>
                    {item && (
                        <StatusBadge value={item.compliance_status}>
                            {_t(item.compliance_status === "clear"
                                ? "Коррупция не выявлена"
                                : item.compliance_status === "flagged"
                                  ? "Коррупция выявлена"
                                  : "Комплаенс: " +
                                    (applicationStageLabels[
                                        item.compliance_status
                                    ] || "Не начато"))}
                        </StatusBadge>
                    )}
                </div>
                {loading ? (
                    <div className="py-16 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    item && (
                        <div className="grid min-w-0 gap-5 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[280px_minmax(0,1fr)]">
                            <aside className="min-w-0 space-y-4 border-b border-gray-100 pb-5 lg:border-b-0 lg:pb-0">
                                <section className="space-y-1 text-sm">
                                    <span className="inline-block rounded bg-blue-50 px-2 py-1 text-xs font-medium text-[#174a9c]">
                                        {_t(isPps ? "ППС" : "АУП")}
                                    </span>
                                    <h2 className="break-words text-lg font-semibold leading-6 text-[#174a9c]">
                                        {_t(displayPosition)}
                                    </h2>
                                    <p className="font-semibold">
                                        {item.user?.name}
                                    </p>
                                    <dl className="text-xs leading-5 text-gray-600">
                                        <div>
                                            <dt className="inline text-gray-400">{_t("Телефон:")}{" "}
                                            </dt>
                                            <dd className="inline">
                                                {_t(item.user?.phone ||
                                                    "Не указан")}
                                            </dd>
                                        </div>
                                        {isPps && (
                                            <>
                                                <div>
                                                    <dt className="inline text-gray-400">{_t("Факультет:")}{" "}
                                                    </dt>
                                                    <dd className="inline">
                                                        {_t(item.pps_profile
                                                            ?.faculty_name ||
                                                            "Не указан")}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="inline text-gray-400">{_t("Кафедра:")}{" "}
                                                    </dt>
                                                    <dd className="inline">
                                                        {_t(item.pps_profile
                                                            ?.department_name ||
                                                            "Не указана")}
                                                    </dd>
                                                </div>
                                            </>
                                        )}
                                        <div>
                                            <dt className="inline text-gray-400">{_t("Дата подачи:")}{" "}
                                            </dt>
                                            <dd className="inline">
                                                {_t(new Date(
                                                    item.created_at,
                                                ).toLocaleDateString("ru-RU"))}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline text-gray-400">{_t("ID кандидата:")}{" "}
                                            </dt>
                                            <dd className="inline">
                                                {_t(item.user?.id)}
                                            </dd>
                                        </div>
                                    </dl>
                                </section>
                                <section className="rounded-md border border-gray-200 bg-gray-50 p-3">
                                    <h3 className="mb-2 text-xs font-medium uppercase text-gray-500">{_t("Дата голосования")}</h3>
                                    <div className="grid grid-cols-[minmax(0,1fr)_65px] gap-2">
                                        <input
                                            aria-label={_t("Дата голосования")}
                                            type="date"
                                            className={`${inputClass} min-w-0 px-2`}
                                            value={votingDraft.voting_date}
                                            onChange={(e) =>
                                                setVotingDraft({
                                                    ...votingDraft,
                                                    voting_date: e.target.value,
                                                })
                                            }
                                        />
                                        <input
                                            aria-label={_t("Порядок голосования")}
                                            type="number"
                                            min="1"
                                            placeholder={_t("№")}
                                            className={`${inputClass} min-w-0 px-2`}
                                            value={votingDraft.voting_order}
                                            onChange={(e) =>
                                                setVotingDraft({
                                                    ...votingDraft,
                                                    voting_order:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <button
                                        className={`${secondaryButtonClass} mt-3 w-full`}
                                        disabled={saving}
                                        onClick={saveVoting}
                                    >{_t("Сохранить дату")}</button>
                                </section>
                                <TeacherAudit
                                    name={
                                        isPps
                                            ? item.pps_profile?.full_name ||
                                              item.user?.name
                                            : ""
                                    }
                                    compact
                                />
                                <section className="space-y-3">
                                    <h3 className="text-sm font-semibold">{_t("Голоса комиссии")}</h3>
                                    <div className="grid grid-cols-2 gap-2 text-xs">
                                        {[
                                            [
                                                "Всего",
                                                "total_members",
                                                "bg-blue-50",
                                            ],
                                            [
                                                "Голосовали",
                                                "voted",
                                                "bg-emerald-50",
                                            ],
                                            ["Не брать", "reject", "bg-red-50"],
                                            [
                                                "Ожидают",
                                                "pending",
                                                "bg-amber-50",
                                            ],
                                        ].map(([label, key, color]) => (
                                            <div
                                                key={key}
                                                className={`rounded px-2 py-2 ${color}`}
                                            >
                                                {_t(label)}:{" "}
                                                <strong>
                                                    {item.vote_summary?.[key] ??
                                                        0}
                                                </strong>
                                            </div>
                                        ))}
                                        {isPps && (
                                            <>
                                                <div className="rounded bg-emerald-50 px-2 py-2">{_t("На 1 год:")}{" "}
                                                    <strong>
                                                        {item.vote_summary
                                                            ?.hire_1_year ?? 0}
                                                    </strong>
                                                </div>
                                                <div className="rounded bg-sky-50 px-2 py-2">{_t("На 3 года:")}{" "}
                                                    <strong>
                                                        {item.vote_summary
                                                            ?.hire_3_year ?? 0}
                                                    </strong>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        {(item.vote_details || []).map(
                                            (vote) => (
                                                <div
                                                    key={vote.user_id}
                                                    className="rounded border border-gray-100 bg-gray-50 px-2 py-2 text-xs"
                                                >
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <span className="font-medium">
                                                            {vote.name}
                                                        </span>
                                                        <span
                                                            className={`rounded px-2 py-1 ${vote.decision === "pending" ? "bg-gray-100 text-gray-500" : vote.decision === "reject" ? "bg-red-50 text-red-700" : "bg-emerald-50 text-emerald-700"}`}
                                                        >
                                                            {_t(voteLabel(
                                                                vote,
                                                                isPps,
                                                            ))}
                                                        </span>
                                                    </div>
                                                    {vote.email && (
                                                        <div className="mt-1 break-all text-gray-400">
                                                            {vote.email}
                                                        </div>
                                                    )}
                                                    {vote.comment && (
                                                        <p className="mt-2 whitespace-pre-wrap break-words text-gray-600">
                                                            {_t(vote.comment)}
                                                        </p>
                                                    )}
                                                    {vote.voted_at && (
                                                        <div className="mt-1 text-gray-400">
                                                            {_t(new Date(
                                                                vote.voted_at,
                                                            ).toLocaleString(
                                                                "ru-RU",
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </section>
                            </aside>
                            <div className="min-w-0 space-y-4">
                                <section className="space-y-3">
                                    <div className="application-resume-upload flex flex-wrap items-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 p-2">
                                        <div className="min-w-0 flex-[1_1_200px]">
                                            <input
                                                aria-label={_t("Резюме")}
                                                key={item.resume_url}
                                                type="file"
                                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                onChange={(e) =>
                                                    setResumeFile(
                                                        e.target.files?.[0] ||
                                                            null,
                                                    )
                                                }
                                            />
                                        </div>
                                        <button
                                            className={primaryButtonClass}
                                            disabled={!resumeFile || saving}
                                            onClick={replaceResume}
                                        >{_t("Загрузить резюме")}</button>
                                        {item.resume_url && (
                                            <a
                                                href={item.resume_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className={`${secondaryButtonClass} inline-flex items-center gap-2`}
                                            >
                                                <FileText size={16} />{_t("Открыть")}</a>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {Object.entries(
                                            item.documents_map || {},
                                        ).map(([type, document]) => (
                                            <AttachmentChip
                                                key={type}
                                                name={
                                                    document.name ||
                                                    docLabels[
                                                        type.replace(
                                                            /_\d+$/,
                                                            "",
                                                        )
                                                    ] ||
                                                    type
                                                }
                                                url={document.url}
                                                onRemove={() =>
                                                    deleteDocument(document.id)
                                                }
                                            />
                                        ))}
                                        <button
                                            className="rounded-md px-2 py-2 text-xs font-semibold text-[#174a9c] hover:bg-blue-50"
                                            onClick={() => setShowDocs(true)}
                                        >{_t("Загрузить документы")}</button>
                                    </div>
                                </section>
                                <section className="border-y border-gray-200 py-4">
                                    <h2 className="mb-3 text-sm font-semibold">{_t("Статусы заявки")}</h2>
                                    <div className="grid gap-4 xl:grid-cols-2">
                                        {[
                                            "resume",
                                            "documents",
                                            "compliance",
                                            "hiring",
                                        ].map((stage) => {
                                            const config = stageConfig[stage];
                                            const options =
                                                config.options.filter(
                                                    ([value]) =>
                                                        stage !== "hiring" ||
                                                        (isPps
                                                            ? value !== "hired"
                                                            : !value.startsWith(
                                                                  "hired_",
                                                              )),
                                                );
                                            const currentStatus =
                                                stage === "hiring" &&
                                                item.hiring_status ===
                                                    "hired" &&
                                                item.hiring_term_years
                                                    ? `hired_${item.hiring_term_years}_year`
                                                    : item[`${stage}_status`];
                                            const locked =
                                                stage === "hiring" &&
                                                item.compliance_status !==
                                                    "clear";
                                            const latestLog =
                                                item.stage_logs?.find(
                                                    (log) =>
                                                        log.stage === stage,
                                                );
                                            return (
                                                <div
                                                    key={stage}
                                                    className="min-w-0 rounded-lg bg-gray-50 p-4 ring-1 ring-inset ring-gray-200"
                                                >
                                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                        <h3 className="text-sm font-medium">
                                                            {_t(config.title)}
                                                        </h3>
                                                        <StatusBadge
                                                            value={
                                                                stage ===
                                                                    "hiring" &&
                                                                item.hiring_status ===
                                                                    "hired" &&
                                                                item.hiring_term_years
                                                                    ? `hired_${item.hiring_term_years}_year`
                                                                    : item[
                                                                          `${stage}_status`
                                                                      ]
                                                            }
                                                        >
                                                            {
                                                                _t(config.options.find(
                                                                    ([value]) =>
                                                                        value ===
                                                                        currentStatus,
                                                                )?.[1])
                                                            }
                                                        </StatusBadge>
                                                    </div>
                                                    <select
                                                        aria-label={
                                                            _t(config.title)
                                                        }
                                                        className={inputClass}
                                                        disabled={
                                                            locked || saving
                                                        }
                                                        value={
                                                            stageDraft[stage]
                                                                ?.status || ""
                                                        }
                                                        onChange={(e) =>
                                                            setStageDraft({
                                                                ...stageDraft,
                                                                [stage]: {
                                                                    ...stageDraft[
                                                                        stage
                                                                    ],
                                                                    status: e
                                                                        .target
                                                                        .value,
                                                                },
                                                            })
                                                        }
                                                    >
                                                        {!options.some(
                                                            ([value]) =>
                                                                value ===
                                                                stageDraft[
                                                                    stage
                                                                ]?.status,
                                                        ) && (
                                                            <option
                                                                disabled
                                                                value={
                                                                    stageDraft[
                                                                        stage
                                                                    ]?.status ||
                                                                    ""
                                                                }
                                                            >
                                                                {_t(applicationStageLabels[
                                                                    stageDraft[
                                                                        stage
                                                                    ]?.status
                                                                ] ||
                                                                    "Выберите статус")}
                                                            </option>
                                                        )}
                                                        {options.map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <option
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {_t(label)}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <textarea
                                                        aria-label={_t(`Комментарий: ${config.title}`)}
                                                        className={`${inputClass} mt-2`}
                                                        rows="3"
                                                        placeholder={
                                                            _t(stage === "hiring"
                                                                ? "Комментарий по финальному решению"
                                                                : stage ===
                                                                    "resume"
                                                                  ? "Комментарий по резюме"
                                                                  : stage ===
                                                                      "documents"
                                                                    ? "Комментарий по документам"
                                                                    : "Комментарий по юридической проверке")
                                                        }
                                                        disabled={
                                                            locked || saving
                                                        }
                                                        value={
                                                            stageDraft[stage]
                                                                ?.comment || ""
                                                        }
                                                        onChange={(e) =>
                                                            setStageDraft({
                                                                ...stageDraft,
                                                                [stage]: {
                                                                    ...stageDraft[
                                                                        stage
                                                                    ],
                                                                    comment:
                                                                        e.target
                                                                            .value,
                                                                },
                                                            })
                                                        }
                                                    />
                                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                                        <div className="min-w-0 flex-1 text-xs leading-5 text-gray-500">
                                                            {latestLog && (
                                                                <>
                                                                    {_t(new Date(
                                                                        latestLog.created_at,
                                                                    ).toLocaleString(
                                                                        "ru-RU",
                                                                    ))}
                                                                    {_t(latestLog
                                                                        .author
                                                                        ?.name &&
                                                                        `, ${latestLog.author.name}`)}
                                                                </>
                                                            )}
                                                        </div>
                                                        <button
                                                            className={
                                                                primaryButtonClass
                                                            }
                                                            disabled={
                                                                saving || locked
                                                            }
                                                            onClick={() =>
                                                                saveStage(stage)
                                                            }
                                                        >{_t("Сохранить")}</button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </section>
                                {isPps ? (
                                    <section className="space-y-3">
                                        <h2 className="text-sm font-semibold">{_t("Данные преподавателя")}</h2>
                                        {ppsGroups.map(
                                            ([title, fields], groupIndex) => (
                                                <details
                                                    key={title}
                                                    open={
                                                        groupIndex === 0
                                                            ? true
                                                            : undefined
                                                    }
                                                    className="application-accordion rounded-lg border border-gray-200 bg-white"
                                                >
                                                    <summary className="flex cursor-pointer items-center justify-between gap-3 rounded-lg bg-gray-50 px-4 py-4 text-sm font-medium hover:bg-blue-50/50">
                                                        {_t(title)}
                                                        <ChevronDown
                                                            size={17}
                                                            aria-hidden="true"
                                                            className="accordion-chevron shrink-0 text-[#174a9c] transition-transform"
                                                        />
                                                    </summary>
                                                    <div className="space-y-4 border-t border-gray-100 p-4">
                                                        {groupIndex === 0 && (
                                                            <div className="grid gap-4 xl:grid-cols-2">
                                                                <Field label={_t("Претендуемая должность")}>
                                                                    <SearchSelect
                                                                        value={
                                                                            ppsDraft.desired_position ||
                                                                            null
                                                                        }
                                                                        onChange={(
                                                                            value,
                                                                        ) =>
                                                                            setProfileField(
                                                                                "desired_position",
                                                                                value ||
                                                                                    "",
                                                                            )
                                                                        }
                                                                        options={
                                                                            positionNames
                                                                        }
                                                                        placeholder={_t("Начните вводить должность")}
                                                                    />
                                                                </Field>
                                                                <Field label={_t("Факультет")}>
                                                                    <select
                                                                        className={
                                                                            inputClass
                                                                        }
                                                                        value={
                                                                            ppsDraft.faculty_name ||
                                                                            ""
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setPpsDraft(
                                                                                {
                                                                                    ...ppsDraft,
                                                                                    faculty_name:
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    department_name:
                                                                                        "",
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <option value="">{_t("Выберите факультет")}</option>
                                                                        {roots.map(
                                                                            (
                                                                                department,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        department.id
                                                                                    }
                                                                                    value={
                                                                                        department.name
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        department.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                </Field>
                                                                <Field label={_t("Кафедра")}>
                                                                    <select
                                                                        className={
                                                                            inputClass
                                                                        }
                                                                        value={
                                                                            ppsDraft.department_name ||
                                                                            ""
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setProfileField(
                                                                                "department_name",
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                    >
                                                                        <option value="">{_t("Выберите кафедру")}</option>
                                                                        {facultyDepartments.map(
                                                                            (
                                                                                department,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        department.id
                                                                                    }
                                                                                    value={
                                                                                        department.name
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        department.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                </Field>
                                                            </div>
                                                        )}
                                                        {fields.map(
                                                            ([
                                                                key,
                                                                label,
                                                                documentsField,
                                                            ]) => (
                                                                <div key={key}>
                                                                    <Field
                                                                        label={
                                                                            _t(label)
                                                                        }
                                                                    >
                                                                        {[
                                                                            "birth_year",
                                                                            "full_name",
                                                                        ].includes(
                                                                            key,
                                                                        ) ? (
                                                                            <input
                                                                                type={
                                                                                    key ===
                                                                                    "birth_year"
                                                                                        ? "number"
                                                                                        : "text"
                                                                                }
                                                                                className={
                                                                                    inputClass
                                                                                }
                                                                                value={
                                                                                    ppsDraft[
                                                                                        key
                                                                                    ] ??
                                                                                    ""
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    setProfileField(
                                                                                        key,
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                            />
                                                                        ) : (
                                                                            <textarea
                                                                                rows={
                                                                                    key.includes(
                                                                                        "conclusion",
                                                                                    )
                                                                                        ? 4
                                                                                        : 3
                                                                                }
                                                                                className={
                                                                                    inputClass
                                                                                }
                                                                                value={
                                                                                    ppsDraft[
                                                                                        key
                                                                                    ] ??
                                                                                    ""
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    setProfileField(
                                                                                        key,
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                            />
                                                                        )}
                                                                    </Field>
                                                                    {_t(documentsField &&
                                                                        categoryFields.has(
                                                                            documentsField,
                                                                        ) &&
                                                                        profileFiles(
                                                                            documentsField,
                                                                            label,
                                                                        ))}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </details>
                                            ),
                                        )}
                                        <div className="flex justify-end pt-1">
                                            <button
                                                className={primaryButtonClass}
                                                disabled={saving}
                                                onClick={savePps}
                                            >
                                                {_t(saving
                                                    ? "Сохранение..."
                                                    : "Сохранить данные ППС")}
                                            </button>
                                        </div>
                                    </section>
                                ) : (
                                    <section className="space-y-4 border-y border-gray-200 py-5">
                                        <h2 className="text-sm font-semibold">{_t("Департамент и должность АУП")}</h2>
                                        <div className="grid gap-4 xl:grid-cols-2">
                                            <Field label={_t("ФИО")}>
                                                <input
                                                    className={inputClass}
                                                    value={staffDraft.full_name}
                                                    onChange={(e) =>
                                                        setStaffDraft({
                                                            ...staffDraft,
                                                            full_name:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                            <Field label={_t("Телефон")}>
                                                <input
                                                    className={inputClass}
                                                    value={staffDraft.phone}
                                                    onChange={(e) =>
                                                        setStaffDraft({
                                                            ...staffDraft,
                                                            phone: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                            <Field label={_t("Департамент")}>
                                                <SearchSelect
                                                    value={
                                                        staffDraft.department_id
                                                    }
                                                    onChange={(value) =>
                                                        setStaffDraft({
                                                            ...staffDraft,
                                                            department_id:
                                                                value,
                                                            position_id: null,
                                                        })
                                                    }
                                                    options={departments.map(
                                                        (department) => ({
                                                            id: department.id,
                                                            name: department.name,
                                                        }),
                                                    )}
                                                    placeholder={_t("Начните вводить департамент")}
                                                />
                                            </Field>
                                            <Field label={_t("Должность")}>
                                                <SearchSelect
                                                    value={
                                                        staffDraft.position_id
                                                    }
                                                    onChange={(value) =>
                                                        setStaffDraft({
                                                            ...staffDraft,
                                                            position_id: value,
                                                        })
                                                    }
                                                    options={staffPositions.map(
                                                        (position) => ({
                                                            id: position.id,
                                                            name: position.name,
                                                        }),
                                                    )}
                                                    disabled={
                                                        !staffDraft.department_id
                                                    }
                                                    placeholder={_t("Начните вводить должность")}
                                                />
                                            </Field>
                                        </div>
                                        <div className="flex justify-end">
                                            <button
                                                className={primaryButtonClass}
                                                disabled={saving}
                                                onClick={saveStaff}
                                            >{_t("Сохранить данные АУП")}</button>
                                        </div>
                                    </section>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        [
                                            "lawyer-response-pdf",
                                            "PDF ответа юриста",
                                        ],
                                        ...(isPps
                                            ? [
                                                  [
                                                      "academic-response-pdf",
                                                      "Академическое заключение",
                                                  ],
                                                  [
                                                      "science-response-pdf",
                                                      "Научное заключение",
                                                  ],
                                              ]
                                            : []),
                                    ].map(([suffix, label]) => (
                                        <button
                                            key={suffix}
                                            className={`${secondaryButtonClass} inline-flex items-center gap-2`}
                                            onClick={() => openPdf(suffix)}
                                        >
                                            <FileText
                                                size={16}
                                                className="shrink-0"
                                            />
                                            {_t(label)}
                                        </button>
                                    ))}
                                </div>
                                <section className="space-y-3 border-y border-gray-200 py-5">
                                    <h2 className="text-sm font-semibold">{_t("Комиссия по вакансии")}</h2>
                                    <p className="text-sm text-gray-500">{_t("Найдите сотрудника по ФИО, email или телефону и добавьте в комиссию.")}</p>
                                    <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                                        <div className="min-w-0">
                                            <SearchSelect
                                                key={`vacancy-commission-${(item.vacancy?.commission_members || []).map((member) => member.id).join("-")}`}
                                                value={selectedCommissionUser}
                                                onChange={
                                                    setSelectedCommissionUser
                                                }
                                                options={commissionCandidates.map(
                                                    (user) => ({
                                                        id: user.id,
                                                        name: user.name,
                                                        secondary: [
                                                            user.email,
                                                            user.phone,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(" · "),
                                                    }),
                                                )}
                                                placeholder={_t("Начните вводить ФИО, email или телефон")}
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}
                                            disabled={!selectedCommissionUser}
                                            onClick={addCommission}
                                        >
                                            <UserPlus size={17} />{_t("Добавить")}</button>
                                    </div>
                                    {(item.vacancy?.commission_members || [])
                                        .length > 0 ? (
                                        <div className="divide-y divide-gray-200 overflow-hidden rounded-md border border-gray-200">
                                            {item.vacancy.commission_members.map(
                                                (member, index) => (
                                                    <div
                                                        key={member.id}
                                                        className="flex items-center justify-between gap-3 bg-white px-3 py-3 text-sm"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-3">
                                                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700">
                                                                {_t(index + 1)}
                                                            </span>
                                                            <span className="min-w-0">
                                                                <span className="block truncate font-semibold text-gray-900">
                                                                    {member.name}
                                                                </span>
                                                                <span className="block truncate text-xs text-gray-500">
                                                                    {_t([
                                                                        member.email,
                                                                        member.phone,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            " · ",
                                                                        ))}
                                                                </span>
                                                            </span>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            title={_t(`Удалить из комиссии: ${member.name}`)}
                                                            aria-label={_t(`Удалить из комиссии: ${member.name}`)}
                                                            className="shrink-0 rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                            onClick={() =>
                                                                removeCommission(
                                                                    member.id,
                                                                )
                                                            }
                                                        >
                                                            <X size={17} />
                                                        </button>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-amber-700">{_t("Добавьте хотя бы одного участника комиссии.")}</p>
                                    )}
                                </section>
                                <section className="space-y-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <h2 className="text-sm font-semibold">{_t("AI-анализ кандидата")}</h2>
                                        <button
                                            className={primaryButtonClass}
                                            disabled={aiLoading}
                                            onClick={analyze}
                                        >
                                            {_t(aiLoading
                                                ? "Анализ..."
                                                : "Сгенерировать")}
                                        </button>
                                    </div>
                                    {item.ai_result ? (
                                        <div className="space-y-3 text-sm">
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {[
                                                    ["score", "Итоговый балл"],
                                                    [
                                                        "education_match",
                                                        "Образование",
                                                    ],
                                                    [
                                                        "experience_match",
                                                        "Опыт",
                                                    ],
                                                    [
                                                        "soft_skills_match",
                                                        "Soft skills",
                                                    ],
                                                ].map(([key, label]) => (
                                                    <div
                                                        key={key}
                                                        className="rounded bg-blue-50 px-3 py-2 text-xs"
                                                    >
                                                        {_t(label)}:{" "}
                                                        <strong>
                                                            {_t(item.ai_result[
                                                                key
                                                            ] ?? "Не указано")}
                                                        </strong>
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="font-medium">
                                                {_t(item.ai_result.decision)}
                                            </div>
                                            <p className="whitespace-pre-wrap break-words leading-6 text-gray-600">
                                                {_t(item.ai_result.summary)}
                                            </p>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-400">{_t("Анализ ещё не выполнен.")}</p>
                                    )}
                                </section>
                            </div>
                            {showDocs && (
                                <UploadDocsModal
                                    application={item}
                                    mode="replace"
                                    uploadUrl={`/api/admin/applications/${applicationId}/upload-docs`}
                                    onClose={() => setShowDocs(false)}
                                    onSaved={async () => {
                                        setShowDocs(false);
                                        await load();
                                    }}
                                />
                            )}
                        </div>
                    )
                )}
            </div>
        </Layout>
    );
}
