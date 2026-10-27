import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useMemo, useRef, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { ChevronLeft, ChevronRight } from "lucide-react";
import Layout from "../../Components/Layout";
import { Alert, errorText, inputClass } from "../../Components/Form";
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
    const [filters, setFilters] = useState(() => ({
        type: ["pps", "staff"].includes(queryType) ? queryType : "pps",
        competition_id: query.get("competition_id") || "",
        q: query.get("q") || "",
        page: Math.max(1, Number(query.get("page")) || 1),
        per_page: [10, 25, 50, 100].includes(Number(query.get("per_page"))) ? Number(query.get("per_page")) : 25,
    }));
    const { type, competition_id: activeCompetition } = filters;
    const [search, setSearch] = useState(filters.q);
    const [counts, setCounts] = useState({ pps: 0, staff: 0 });
    const [groups, setGroups] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, from: null, to: null, total: 0 });
    const [error, setError] = useState("");
    const [comments, setComments] = useState({});
    const [loading, setLoading] = useState(true);
    const [savingId, setSavingId] = useState(null);
    const scroller = useRef(null);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError("");
        axios.get("/api/commission/applications", { params: filters, signal: controller.signal })
            .then(({ data }) => {
                if (controller.signal.aborted) return;
                const page = Math.min(data.meta.current_page, data.meta.last_page);
                const competition_id = data.filters.competition_id;
                setItems(data.data); setMeta(data.meta); setCounts(data.counts); setGroups(data.competitions);
                setComments((previous) => {
                    const next = { ...previous };
                    data.data.forEach((item) => { if (!(item.id in next)) next[item.id] = item.vote_summary?.my_vote?.comment || ""; });
                    return next;
                });
                const nextFilters = { ...filters, page, competition_id };
                const url = new URL(window.location.href);
                Object.entries(nextFilters).forEach(([key, value]) => value === "" ? url.searchParams.delete(key) : url.searchParams.set(key, String(value)));
                window.history.replaceState(window.history.state, "", url);
                if (page !== filters.page || competition_id !== filters.competition_id) setFilters(nextFilters);
            })
            .catch((requestError) => { if (!controller.signal.aborted) { setError(errorText(requestError)); setItems([]); } })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [filters]);
    useEffect(() => {
        if (search === filters.q) return;
        const timeout = window.setTimeout(() => setFilters((previous) => ({ ...previous, q: search, page: 1 })), 300);
        return () => window.clearTimeout(timeout);
    }, [search, filters.q]);
    const competitions = useMemo(() => groups.filter((group) => group.type === type), [groups, type]);
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
    const vote = async (item, decision, years = null) => {
        setSavingId(item.id);
        try {
            const { data } = await axios.post(`/api/commission/applications/${item.id}/vote`, {
                decision,
                hire_term_years: years,
                comment: comments[item.id]?.trim() || null,
            });
            setItems((previous) => previous.map((row) => row.id === item.id ? data.application : row));
            setComments((previous) => ({ ...previous, [item.id]: data.application.vote_summary?.my_vote?.comment || "" }));
        } catch (error) {
            window.alert(errorText(error));
        } finally {
            setSavingId(null);
        }
    };
    const pagination = {
        meta, loading, perPage: filters.per_page,
        onPage: (page) => setFilters((previous) => ({ ...previous, page })),
        onPerPage: (per_page) => setFilters((previous) => ({ ...previous, per_page, page: 1 })),
    };
    return (
        <Layout
            requireAuth
            title={_t("Голосование комиссии")}
            description={_t("Заявки с завершённой проверкой комплаенса.")}
        >
            <Head title={_t("Голосование комиссии")} />
            <div className="mx-auto max-w-6xl space-y-5 px-4 py-8">
                <Alert>{_t(error)}</Alert>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex gap-2">
                        {[
                            ["pps", "ППС"],
                            ["staff", "АУП"],
                        ].map(([value, label]) => (
                            <button
                                key={value}
                                className={`rounded-md border px-4 py-2 text-sm font-semibold ${type === value ? "border-[#005eb8] bg-[#005eb8] text-white" : "border-gray-300 bg-white text-gray-700"}`}
                                disabled={loading}
                                onClick={() => setFilters((previous) => ({ ...previous, type: value, competition_id: "", page: 1 }))}
                            >
                                {_t(label)} (
                                {
                                    _t(counts[value])
                                }
                                )
                            </button>
                        ))}
                    </div>
                    <input
                        className={`${inputClass.replace("mt-1 ", "")} lg:max-w-md`}
                        type="search"
                        maxLength={255}
                        value={search}
                        placeholder={_t("Кандидат, должность или кафедра")}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                {counts[type] > 0 && (
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
                                    disabled={loading}
                                    className={`rounded-md border px-3 py-2 text-sm font-semibold ${activeCompetition === "all" ? "border-[#005eb8] bg-[#005eb8] text-white" : "border-gray-300 bg-white text-gray-700"}`}
                                    onClick={() => setFilters((previous) => ({ ...previous, competition_id: "all", page: 1 }))}
                                >{_t("Все")} ({counts[type]})
                                </button>
                                {competitions.map((competition) => (
                                    <button
                                        key={competition.value}
                                        aria-pressed={activeCompetition === competition.value}
                                        disabled={loading}
                                        className={`max-w-[min(18rem,100cqi)] whitespace-normal break-words rounded-md border px-3 py-2 text-left text-sm font-semibold ${activeCompetition === competition.value ? "border-[#005eb8] bg-[#005eb8] text-white" : "border-gray-300 bg-white text-gray-700"}`}
                                        onClick={() =>
                                            setFilters((previous) => ({ ...previous, competition_id: competition.value, page: 1 }))
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
                <CommissionPagination {...pagination} />
                {loading ? (
                    <div className="py-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : items.length === 0 ? (
                    <div className="rounded-md border border-gray-200 bg-white py-12 text-center text-gray-500">{_t("Нет заявок для голосования.")}</div>
                ) : (
                    <div className="space-y-4">
                        {items.map((item, index) => (
                            <article
                                key={item.id}
                                className="rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <h2 className="font-bold text-[#174a9c]">
                                            {_t(item.pps_profile
                                                ?.desired_position ||
                                                item.vacancy?.title ||
                                                "Заявка")}
                                        </h2>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {item.user?.name} ·{" "}
                                            <span className="break-all">{item.user?.email}</span>
                                        </p>
                                        <p className="text-xs text-gray-400">
                                            {_t(item.competition?.title || "Без конкурса")}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-blue-50 px-3 py-1 text-sm font-bold text-[#174a9c]">
                                        №{" "}
                                        {_t(Number(item.voting_order) > 0
                                            ? item.voting_order
                                            : (meta.from || 1) + index)}
                                    </span>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Link
                                        href={`/commission/applications/${item.id}?${new URLSearchParams({ ...filters, competition_id: activeCompetition }).toString()}`}
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
                {meta.last_page > 1 && <CommissionPagination {...pagination} />}
            </div>
        </Layout>
    );
}

function CommissionPagination({ meta, loading, perPage, onPage, onPerPage }) {
    const { t: _t } = useTextI18n();
    return <nav aria-label={_t('Страницы заявок')} className="flex flex-wrap items-center justify-between gap-3 border-y border-gray-200 py-3 text-sm">
        <span className="text-gray-500">{_t('Показано:')} <strong className="font-medium text-gray-700">{meta.from || 0}-{meta.to || 0}</strong> / {meta.total}</span>
        <div className="flex flex-wrap items-center gap-2">
            <label className="mr-2 flex items-center gap-2 text-xs text-gray-500">{_t('На странице')}
                <select className="rounded-md border border-gray-300 bg-white px-2 py-2 text-gray-700" value={perPage} disabled={loading} onChange={(event) => onPerPage(Number(event.target.value))}>
                    {[10, 25, 50, 100].map((count) => <option key={count} value={count}>{count}</option>)}
                </select>
            </label>
            <button type="button" title={_t('Назад')} aria-label={_t('Назад')} disabled={loading || meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} className="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 disabled:opacity-40"><ChevronLeft size={18} /></button>
            <span className="min-w-12 px-1 text-center text-xs tabular-nums text-gray-500">{meta.current_page} / {meta.last_page}</span>
            <button type="button" title={_t('Вперёд')} aria-label={_t('Вперёд')} disabled={loading || meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} className="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 disabled:opacity-40"><ChevronRight size={18} /></button>
        </div>
    </nav>;
}
