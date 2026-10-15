import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import ApplicationSummary from "../../Components/ApplicationSummary";
import TeacherAudit from "../../Components/TeacherAudit";
import { Alert, errorText, inputClass } from "../../Components/Form";

const sections = [
    [
        "Научные труды и ЦОР / МООК",
        [
            [
                "scientific_works",
                "Научные труды преподавателя",
                "scientific_works_documents",
            ],
            [
                "digital_mooc",
                "Наличие ЦОР / МООК и подтверждающих документов",
                "digital_mooc_documents",
            ],
            ["science_conclusion", "Заключение"],
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
            [
                "open_lesson_quality",
                "Оценка качества проведения открытого занятия",
            ],
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
                "compliance_documents",
            ],
            [
                "disciplinary_actions_info",
                "Сведения о дисциплинарных взысканиях",
            ],
        ],
    ],
];
const decisionLabel = (vote, type) =>
    !vote
        ? "Не голосовал"
        : vote.decision === "reject"
          ? type === "pps"
              ? "Не брать"
              : "Против"
          : type === "pps"
            ? `Взять на ${vote.hire_term_years || "—"} ${Number(vote.hire_term_years) === 1 ? "год" : "года"}`
            : "За";

export default function CommissionShow() {
    const { t: _t } = useTextI18n();
    const { props: { applicationId }, url } = usePage();
    const competitionFilter = new URLSearchParams(url.split("?")[1] || "").get("competition_id");
    const [item, setItem] = useState(null);
    const [comment, setComment] = useState("");
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const load = async () => {
        setLoading(true);
        setError("");
        try {
            const { data } = await axios.get(
                `/api/commission/applications/${applicationId}`,
            );
            setItem(data);
            setComment(data.vote_summary?.my_vote?.comment || "");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, [applicationId]);
    const vote = async (decision, years = null) => {
        setSaving(true);
        setError("");
        try {
            const { data } = await axios.post(
                `/api/commission/applications/${applicationId}/vote`,
                {
                    decision,
                    hire_term_years: years,
                    comment: comment.trim() || null,
                },
            );
            setItem(data.application);
            setComment(data.application?.vote_summary?.my_vote?.comment || "");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const profile = item?.pps_profile || {};
    const type = item?.vacancy?.type;
    const canVote = ["not_started", "voting"].includes(item?.hiring_status);
    return (
        <Layout
            requireAuth
            title={_t(`Заявка комиссии №${applicationId}`)}
            description={_t("Данные кандидата и форма голосования.")}
        >
            <Head title={_t(`Заявка комиссии №${applicationId}`)} />
            <div className="mx-auto max-w-6xl space-y-5 px-4 py-8">
                <Link
                    href={`/commission/applications?type=${type || "pps"}${competitionFilter ? `&competition_id=${encodeURIComponent(competitionFilter)}` : ""}`}
                    className="text-sm font-semibold text-[#174a9c]"
                >{_t("← Вернуться к списку")}</Link>
                <Alert>{_t(error)}</Alert>
                {loading ? (
                    <div className="rounded-md border border-gray-200 bg-white py-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    item && (
                        <>
                            <ApplicationSummary application={item} />
                            {type === "pps" && (
                                <>
                                    {sections.map(([title, fields]) => (
                                        <section
                                            key={title}
                                            className="rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                                        >
                                            <h2 className="font-bold text-[#174a9c]">
                                                {_t(title)}
                                            </h2>
                                            <div className="mt-4 space-y-4">
                                                {fields.map(
                                                    ([
                                                        key,
                                                        label,
                                                        documentsField,
                                                    ]) => (
                                                        <div
                                                            key={key}
                                                            className="rounded-md bg-gray-50 p-4"
                                                        >
                                                            <div className="text-xs font-bold uppercase text-gray-400">
                                                                {_t(label)}
                                                            </div>
                                                            <p className="mt-2 whitespace-pre-wrap text-sm text-gray-700">
                                                                {_t(profile[key] ||
                                                                    "Не заполнено")}
                                                            </p>
                                                            {documentsField &&
                                                                profile[
                                                                    documentsField
                                                                ]?.length >
                                                                    0 && (
                                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                                        {profile[
                                                                            documentsField
                                                                        ].map(
                                                                            (
                                                                                document,
                                                                            ) => (
                                                                                <a
                                                                                    key={
                                                                                        document.id
                                                                                    }
                                                                                    href={
                                                                                        document.url
                                                                                    }
                                                                                    target="_blank"
                                                                                    rel="noreferrer"
                                                                                    className="rounded-md border border-[#005eb8] px-3 py-2 text-xs font-semibold text-[#174a9c]"
                                                                                >
                                                                                    {
                                                                                        document.name
                                                                                    }
                                                                                </a>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                )}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </section>
                                    ))}
                                    <TeacherAudit
                                        name={
                                            profile.full_name || item.user?.name
                                        }
                                    />
                                </>
                            )}
                            <section className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                                <h2 className="font-bold text-[#174a9c]">{_t("Ваш голос")}</h2>
                                {item.vote_summary?.my_vote && (
                                    <p className="mt-2 text-sm text-gray-600">{_t("Текущий выбор:")}{" "}
                                        <strong>
                                            {_t(decisionLabel(
                                                item.vote_summary.my_vote,
                                                type,
                                            ))}
                                        </strong>
                                    </p>
                                )}
                                <textarea
                                    className={inputClass}
                                    rows="4"
                                    placeholder={_t("Комментарий (необязательно)")}
                                    value={comment}
                                    onChange={(event) =>
                                        setComment(event.target.value)
                                    }
                                />
                                {!canVote && (
                                    <p className="mt-2 text-sm text-amber-700">{_t("Голосование по этой заявке завершено.")}</p>
                                )}
                                <div className="mt-4 flex flex-wrap justify-end gap-2">
                                    {type === "pps" ? (
                                        <>
                                            <button
                                                disabled={saving || !canVote}
                                                className="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                                onClick={() => vote("hire", 1)}
                                            >{_t("Взять на 1 год")}</button>
                                            <button
                                                disabled={saving || !canVote}
                                                className="rounded-md bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                                onClick={() => vote("hire", 3)}
                                            >{_t("Взять на 3 года")}</button>
                                        </>
                                    ) : (
                                        <button
                                            disabled={saving || !canVote}
                                            className="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                            onClick={() => vote("hire")}
                                        >{_t("Голосовать: За")}</button>
                                    )}
                                    <button
                                        disabled={saving || !canVote}
                                        className="rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                        onClick={() => vote("reject")}
                                    >
                                        {_t(type === "pps"
                                            ? "Не брать"
                                            : "Голосовать: Против")}
                                    </button>
                                </div>
                            </section>
                        </>
                    )
                )}
            </div>
        </Layout>
    );
}
