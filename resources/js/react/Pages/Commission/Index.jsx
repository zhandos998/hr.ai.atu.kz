import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useRef, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { ChevronLeft, ChevronRight } from "lucide-react";
import Layout from "../../Components/Layout";
import { errorText, inputClass } from "../../Components/Form";

const competitionKey = (item) => String(item.competition_id || "none");
const order = (item) =>
    Number(item.voting_order) > 0
        ? Number(item.voting_order)
        : Number.MAX_SAFE_INTEGER;
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

export default function CommissionApplications() {
    const { t: _t } = useTextI18n();
    const query = new URLSearchParams(window.location.search);
    const queryType = query.get("type");
    const [items, setItems] = useState([]);
    const [type, setType] = useState(
        ["pps", "staff"].includes(queryType) ? queryType : "pps",
    );
    const [activeCompetition, setActiveCompetition] = useState(
        query.get("competition_id") || "",
    );
    const [search, setSearch] = useState("");
    const [comments, setComments] = useState({});
    const [loading, setLoading] = useState(true);
    const [savingId, setSavingId] = useState(null);
    const scroller = useRef(null);
    const load = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get("/api/commission/applications");
            setItems(data);
            setComments(
                Object.fromEntries(
                    data.map((item) => [
                        item.id,
                        item.vote_summary?.my_vote?.comment || "",
                    ]),
                ),
            );
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => {
        load();
    }, []);
    const typed = useMemo(
        () => items.filter((item) => item.vacancy?.type === type),
        [items, type],
    );
    const competitions = useMemo(() => {
        const groups = new Map();
        typed.forEach((item) => {
            const value = competitionKey(item);
            if (!groups.has(value)) {
                groups.set(value, {
                    value,
                    count: 0,
                    label: item.competition?.title || "Без конкурса",
                });
            }
            groups.get(value).count++;
        });
        return Array.from(groups.values()).sort((a, b) =>
            a.value === "none"
                ? 1
                : b.value === "none"
                  ? -1
                  : Number(b.value) - Number(a.value),
        );
    }, [typed]);
    useEffect(() => {
        if (loading || activeCompetition === "all") return;
        if (!competitions.some((competition) => competition.value === activeCompetition))
            setActiveCompetition(competitions[0]?.value || "all");
    }, [competitions, activeCompetition, loading]);
    useEffect(() => {
        const container = scroller.current;
        const selected = container?.querySelector('[aria-pressed="true"]');
        if (!selected) return;
        const align = () => container.scrollTo({
            left: selected.offsetLeft - (container.clientWidth - selected.offsetWidth) / 2,
        });
        align();
        const observer = new ResizeObserver(align);
        observer.observe(container);
        return () => observer.disconnect();
    }, [activeCompetition, type, competitions.length]);
    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        return typed
            .filter(
                (item) =>
                    (activeCompetition === "all" ||
                        competitionKey(item) === activeCompetition) &&
                    (!query ||
                        [
                            item.id,
                            item.user?.name,
                            item.user?.email,
                            item.vacancy?.title,
                            item.pps_profile?.desired_position,
                            item.pps_profile?.faculty_name,
                            item.pps_profile?.department_name,
                        ].some((value) =>
                            String(value || "")
                                .toLowerCase()
                                .includes(query),
                        )),
            )
            .sort((a, b) => order(a) - order(b) || Number(a.id) - Number(b.id));
    }, [activeCompetition, search, typed]);
    const vote = async (item, decision, years = null) => {
        setSavingId(item.id);
        try {
            await axios.post(`/api/commission/applications/${item.id}/vote`, {
                decision,
                hire_term_years: years,
                comment: comments[item.id]?.trim() || null,
            });
            await load();
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setSavingId(null);
        }
    };
    return (
        <Layout
            requireAuth
            title={_t("Голосование комиссии")}
            description={_t("Заявки с завершённой проверкой комплаенса.")}
        >
            <Head title={_t("Голосование комиссии")} />
            <div className="mx-auto max-w-6xl space-y-5 px-4 py-8">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex gap-2">
                        {[
                            ["pps", "ППС"],
                            ["staff", "АУП"],
                        ].map(([value, label]) => (
                            <button
                                key={value}
                                className={`rounded-md border px-4 py-2 text-sm font-semibold ${type === value ? "border-[#005eb8] bg-[#005eb8] text-white" : "border-gray-300 bg-white text-gray-700"}`}
                                onClick={() => setType(value)}
                            >
                                {_t(label)} (
                                {
                                    _t(items.filter(
                                        (item) => item.vacancy?.type === value,
                                    ).length)
                                }
                                )
                            </button>
                        ))}
                    </div>
                    <input
                        className={`${inputClass.replace("mt-1 ", "")} lg:max-w-md`}
                        type="search"
                        value={search}
                        placeholder={_t("Кандидат, должность или кафедра")}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                {typed.length > 0 && (
                    <div className="flex items-center gap-2 rounded-md border border-gray-200 bg-white p-3">
                        <button
                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50"
                            type="button"
                            title={_t("Предыдущие конкурсы")}
                            aria-label={_t("Предыдущие конкурсы")}
                            onClick={() =>
                                scroller.current?.scrollBy({
                                    left: -300,
                                    behavior: "smooth",
                                })
                            }
                        >
                            <ChevronLeft size={18} aria-hidden="true" />
                        </button>
                        <div
                            ref={scroller}
                            className="relative min-w-0 flex-1 overflow-x-auto [container-type:inline-size]"
                        >
                            <div className="flex min-w-max gap-2">
                                <button
                                    aria-pressed={activeCompetition === "all"}
                                    className={`rounded-md border px-3 py-2 text-sm font-semibold ${activeCompetition === "all" ? "border-[#005eb8] bg-[#005eb8] text-white" : "border-gray-300 bg-white text-gray-700"}`}
                                    onClick={() => setActiveCompetition("all")}
                                >{_t("Все")} ({typed.length})
                                </button>
                                {competitions.map((competition) => (
                                    <button
                                        key={competition.value}
                                        aria-pressed={activeCompetition === competition.value}
                                        className={`max-w-[min(18rem,100cqi)] whitespace-normal break-words rounded-md border px-3 py-2 text-left text-sm font-semibold ${activeCompetition === competition.value ? "border-[#005eb8] bg-[#005eb8] text-white" : "border-gray-300 bg-white text-gray-700"}`}
                                        onClick={() =>
                                            setActiveCompetition(competition.value)
                                        }
                                    >
                                        {_t(competition.label)} ({competition.count})
                                    </button>
                                ))}
                            </div>
                        </div>
                        <button
                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50"
                            type="button"
                            title={_t("Следующие конкурсы")}
                            aria-label={_t("Следующие конкурсы")}
                            onClick={() =>
                                scroller.current?.scrollBy({
                                    left: 300,
                                    behavior: "smooth",
                                })
                            }
                        >
                            <ChevronRight size={18} aria-hidden="true" />
                        </button>
                    </div>
                )}
                {loading ? (
                    <div className="py-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : filtered.length === 0 ? (
                    <div className="rounded-md border border-gray-200 bg-white py-12 text-center text-gray-500">{_t("Нет заявок для голосования.")}</div>
                ) : (
                    <div className="space-y-4">
                        {filtered.map((item, index) => (
                            <article
                                key={item.id}
                                className="rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="font-bold text-[#174a9c]">
                                            {_t(item.pps_profile
                                                ?.desired_position ||
                                                item.vacancy?.title ||
                                                "Заявка")}
                                        </h2>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {item.user?.name} ·{" "}
                                            {item.user?.email}
                                        </p>
                                        <p className="text-xs text-gray-400">
                                            {_t(item.competition?.title || "Без конкурса")}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-blue-50 px-3 py-1 text-sm font-bold text-[#174a9c]">
                                        №{" "}
                                        {_t(Number(item.voting_order) > 0
                                            ? item.voting_order
                                            : index + 1)}
                                    </span>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Link
                                        href={`/commission/applications/${item.id}?type=${type}&competition_id=${encodeURIComponent(activeCompetition)}`}
                                        className="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold"
                                    >{_t("Подробнее")}</Link>
                                    {item.resume_url && (
                                        <a
                                            href={item.resume_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="rounded-md border border-[#005eb8] px-3 py-2 text-sm font-semibold text-[#174a9c]"
                                        >{_t("Резюме")}</a>
                                    )}
                                </div>
                                <div className="mt-4 rounded-md border border-gray-200 p-4">
                                    <div className="text-sm font-semibold">{_t("Ваш голос")}</div>
                                    {item.vote_summary?.my_vote && (
                                        <p className="mt-1 text-sm text-gray-600">{_t("Текущий выбор:")}{" "}
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
                                        rows="2"
                                        placeholder={_t("Комментарий (необязательно)")}
                                        value={comments[item.id] || ""}
                                        onChange={(event) =>
                                            setComments({
                                                ...comments,
                                                [item.id]: event.target.value,
                                            })
                                        }
                                    />
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {type === "pps" ? (
                                            <>
                                                <button
                                                    disabled={
                                                        savingId === item.id
                                                    }
                                                    className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"
                                                    onClick={() =>
                                                        vote(item, "hire", 1)
                                                    }
                                                >{_t("Взять на 1 год")}</button>
                                                <button
                                                    disabled={
                                                        savingId === item.id
                                                    }
                                                    className="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white"
                                                    onClick={() =>
                                                        vote(item, "hire", 3)
                                                    }
                                                >{_t("Взять на 3 года")}</button>
                                            </>
                                        ) : (
                                            <button
                                                disabled={savingId === item.id}
                                                className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"
                                                onClick={() =>
                                                    vote(item, "hire")
                                                }
                                            >{_t("Голосовать: За")}</button>
                                        )}
                                        <button
                                            disabled={savingId === item.id}
                                            className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white"
                                            onClick={() => vote(item, "reject")}
                                        >
                                            {_t(type === "pps"
                                                ? "Не брать"
                                                : "Голосовать: Против")}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </Layout>
    );
}
