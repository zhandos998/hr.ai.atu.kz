import { useI18n as useTextI18n } from "../I18nContext";
import React, { useState } from "react";
import axios from "axios";

const statusLabels = {
    pending: "Ожидает рассмотрения",
    open: "Открыто",
    active: "Активно",
    in_progress: "В работе",
    processing: "В работе",
    reviewed: "Рассмотрено",
    resolved: "Устранено",
    closed: "Закрыто",
    rejected: "Отклонено",
    cancelled: "Отменено",
};
const formatDate = (value) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value || "—"
        : new Intl.DateTimeFormat("ru-RU", {
              dateStyle: "short",
              timeStyle: "short",
          }).format(date);
};

export default function TeacherAudit({ name, compact = false }) {
    const { t: _t } = useTextI18n();
    const [expanded, setExpanded] = useState(false);
    const [loading, setLoading] = useState(false);
    const [teacher, setTeacher] = useState(null);
    const [error, setError] = useState("");
    const toggle = async () => {
        if (expanded) {
            setExpanded(false);
            setTeacher(null);
            setError("");
            return;
        }
        setExpanded(true);
        setLoading(true);
        try {
            const { data } = await axios.get("/api/teacher-audit", {
                params: { name },
            });
            const records = Array.isArray(data.data) ? data.data : [];
            setTeacher(data.success && records.length ? records[0] : null);
        } catch (requestError) {
            setError(requestError?.response?.data?.message || "Ошибка запроса");
        } finally {
            setLoading(false);
        }
    };
    if (!name) return null;
    return (
        <section
            className={
                compact
                    ? "border-b border-gray-200 pb-5"
                    : "rounded-md border border-gray-200 bg-white p-5 shadow-sm"
            }
        >
            <div className="flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-[#174a9c]">{_t("КМК аудит")}</h2>
                    <p className="mt-1 text-xs text-gray-500">{_t(name)}</p>
                </div>
                <button
                    type="button"
                    className="rounded-md border border-[#005eb8] px-3 py-2 text-sm font-semibold text-[#174a9c]"
                    onClick={toggle}
                >
                    {_t(expanded ? "Скрыть" : "Раскрыть")}
                </button>
            </div>
            {expanded && (
                <div className="mt-4 rounded-md bg-gray-50 p-4 text-sm">
                    {_t(loading ? (
                        "Загрузка..."
                    ) : error ? (
                        <span className="text-red-600">{_t(error)}</span>
                    ) : !teacher ? (
                        "Нет данных"
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <div className="font-bold">
                                    {_t(teacher.teacher_name || name)}
                                </div>
                                <div className="mt-1 text-gray-600">{_t("Кафедра:")}{" "}
                                    {_t(teacher.department || "Не указана")}
                                </div>
                                <div className="text-gray-600">{_t("Факультет:")}{_t(teacher.faculty || "Не указан")}
                                </div>
                            </div>
                            {teacher.violations?.length ? (
                                <div>
                                    <h3 className="font-semibold">{_t("Нарушения")}</h3>
                                    <div className="mt-2 space-y-2">
                                        {teacher.violations.map(
                                            (violation, index) => (
                                                <div
                                                    key={index}
                                                    className="rounded-md border border-red-100 bg-red-50 p-3"
                                                >
                                                    <div className="text-xs text-gray-500">
                                                        {_t(formatDate(
                                                            violation.date,
                                                        ))}
                                                    </div>
                                                    <p className="mt-1 whitespace-pre-wrap">
                                                        {_t(violation.observation_text ||
                                                            violation.comment ||
                                                            violation.description ||
                                                            "Нарушение")}
                                                    </p>
                                                    {violation.status && (
                                                        <span className="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-800">{_t("Статус:")}{" "}
                                                            {_t(statusLabels[
                                                                String(
                                                                    violation.status,
                                                                ).toLowerCase()
                                                            ] ||
                                                                violation.status)}
                                                        </span>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <span className="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-700">{_t("Нарушений нет")}</span>
                            )}
                            {teacher.audits?.length > 0 && (
                                <div>
                                    <h3 className="font-semibold">{_t("Проверки")}</h3>
                                    <div className="mt-2 space-y-2">
                                        {teacher.audits.map((audit) => (
                                            <div
                                                key={audit.id || audit.date}
                                                className="rounded-md border border-gray-200 bg-white p-3"
                                            >
                                                <div>
                                                    {_t(formatDate(audit.date))} (
                                                    {_t(audit.type ||
                                                        "Тип не указан")}
                                                    )
                                                </div>
                                                <div className="mt-1 font-bold text-[#174a9c]">{_t("Оценка:")}{_t(audit.score ?? "—")}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {teacher.dossier_url && (
                                <a
                                    href={teacher.dossier_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex rounded-md bg-[#005eb8] px-3 py-2 text-xs font-semibold text-white"
                                >{_t("Открыть досье")}</a>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
