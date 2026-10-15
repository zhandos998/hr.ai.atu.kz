import { useI18n as useTextI18n } from "../I18nContext";
import React from "react";

const docLabels = {
    diploma: "Дипломы и сертификаты",
    recommendation_letter: "Рекомендательное письмо",
    scientific_works: "Список научных трудов",
    articles: "Список научных трудов",
    other: "Другое",
};
const infoFields = [
    ["full_name", "ФИО"],
    ["desired_position", "Претендуемая должность"],
    ["birth_year", "Год рождения"],
    ["work_experience", "Стаж работы"],
    ["faculty_name", "Факультет"],
    ["department_name", "Кафедра"],
];
const supporting = [
    ["basic_education", "Базовое образование", "basic_education_documents"],
    ["magistracy", "Магистратура", "magistracy_documents"],
    ["scientific_degree", "Учёная степень", "scientific_degree_documents"],
    ["academic_title", "Учёное звание", "academic_title_documents"],
];
const stageLabel = (value) =>
    ({
        pending: "На рассмотрении",
        accepted: "Принято",
        rejected: "Отклонено",
        awaiting_upload: "Ожидается загрузка",
        uploaded: "Загружены",
        not_started: "Не начато",
        clear: "Не выявлена",
        flagged: "Выявлена",
        voting: "Голосование",
        hired: "Принят",
        hired_1_year: "Принят на 1 год",
        hired_3_year: "Принят на 3 года",
    })[value] ||
    value ||
    "—";

export default function ApplicationSummary({ application }) {
    const { t: _t } = useTextI18n();
    const profile = application.pps_profile || {};
    return (
        <div className="space-y-5">
            <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                <div className="border-b bg-[#edf4ff] px-5 py-4">
                    <h2 className="text-lg font-bold text-[#174a9c]">
                        {_t(profile.desired_position ||
                            application.vacancy?.title ||
                            "Заявка")}
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">{_t("Кандидат:")}{" "}
                        {_t(application.user?.name ||
                            profile.full_name ||
                            "Не указан")}{" "}
                        · ID: {_t(application.id)}
                    </p>
                </div>
                <dl className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    {infoFields.map(([key, label]) => (
                        <div key={key}>
                            <dt className="text-xs font-bold uppercase text-gray-400">
                                {_t(label)}
                            </dt>
                            <dd className="mt-1 text-sm font-medium text-gray-800">
                                {_t(profile[key] || "Не указано")}
                            </dd>
                        </div>
                    ))}
                    <div>
                        <dt className="text-xs font-bold uppercase text-gray-400">{_t("Дата подачи")}</dt>
                        <dd className="mt-1 text-sm font-medium">
                            {_t(new Date(
                                application.created_at,
                            ).toLocaleDateString("ru-RU"))}
                        </dd>
                    </div>
                </dl>
                <div className="grid gap-2 border-t border-gray-100 p-5 sm:grid-cols-4">
                    {[
                        ["Резюме", application.resume_status],
                        ["Документы", application.documents_status],
                        ["Комплаенс", application.compliance_status],
                        ["Приём", application.hiring_status],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-md bg-gray-50 p-3">
                            <div className="text-xs text-gray-400">{_t(label)}</div>
                            <div className="mt-1 text-sm font-semibold">
                                {_t(stageLabel(value))}
                            </div>
                        </div>
                    ))}
                </div>
            </section>
            <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="font-bold text-[#174a9c]">{_t("Документы кандидата")}</h2>
                <div className="mt-4 flex flex-wrap gap-2">
                    {application.resume_url && (
                        <a
                            href={application.resume_url}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-md border border-[#005eb8] px-3 py-2 text-sm font-semibold text-[#174a9c]"
                        >{_t("Резюме")}</a>
                    )}
                    {Object.entries(application.documents_map || {}).map(
                        ([type, document]) => {
                            const match = type.match(/^(.*)_(\d+)$/);
                            const base = match?.[1] || type;
                            return (
                                <a
                                    key={type}
                                    href={document.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-md border border-emerald-500 px-3 py-2 text-sm font-semibold text-emerald-700"
                                >
                                    {_t(docLabels[base] || base)}
                                    {_t(match ? ` #${match[2]}` : "")}
                                </a>
                            );
                        },
                    )}
                </div>
                <div className="mt-5 grid gap-4 md:grid-cols-2">
                    {supporting.map(([key, label, documentsKey]) => (
                        <div
                            key={key}
                            className="rounded-md border border-gray-100 bg-gray-50 p-3"
                        >
                            <div className="text-xs font-bold uppercase text-gray-400">
                                {_t(label)}
                            </div>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-gray-700">
                                {_t(profile[key] || "Не указано")}
                            </p>
                            {(profile[documentsKey] || []).length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {profile[documentsKey].map((document) => (
                                        <a
                                            key={document.id}
                                            href={document.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-xs font-semibold text-[#174a9c] hover:underline"
                                        >
                                            {document.name}
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
