import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { CheckCircle2, Clock3, ContactRound, Search, XCircle } from "lucide-react";
import Layout from "../../Components/Layout";
import { Alert, errorText, inputClass } from "../../Components/Form";
import { useI18n } from "../../I18nContext";

const copy = {
    ru: {
        title: "Статус кадрового процесса",
        description: "Проверьте этапы согласования кадровой заявки по фамилии, имени или ФИО.",
        label: "ФИО сотрудника",
        placeholder: "Например: Иванов Иван Иванович",
        hint: "Можно ввести фамилию, имя или полное ФИО.",
        search: "Проверить статус",
        searching: "Поиск...",
        empty: "Кадровые процессы по указанному ФИО не найдены.",
        found: "Найденные кадровые процессы",
        department: "Подразделение",
        position: "Должность",
        targetDepartment: "Новое подразделение",
        targetPosition: "Новая должность",
        created: "Дата создания",
        approval: "Маршрут согласования",
        approverPosition: "Должность не указана",
        contact: "Как связаться",
        pending: "На согласовании",
        approved: "Согласовано",
        rejected: "Отклонено",
        waiting: "Ожидает подписи",
        signed: "Подписал",
        declined: "Отказал",
        hire: "Приём на работу",
        dismissal: "Увольнение",
        transfer: "Перевод",
        maternity_leave: "Отпуск по беременности и родам",
    },
    kk: {
        title: "Кадрлық процестің мәртебесі",
        description: "Тегіңіз, атыңыз немесе толық аты-жөніңіз бойынша келісу кезеңдерін тексеріңіз.",
        label: "Қызметкердің аты-жөні",
        placeholder: "Мысалы: Иванов Иван Иванович",
        hint: "Тегіңізді, атыңызды немесе толық аты-жөніңізді енгізуге болады.",
        search: "Мәртебені тексеру",
        searching: "Іздеу...",
        empty: "Көрсетілген аты-жөн бойынша кадрлық процесс табылмады.",
        found: "Табылған кадрлық процестер",
        department: "Бөлімше",
        position: "Лауазым",
        targetDepartment: "Жаңа бөлімше",
        targetPosition: "Жаңа лауазым",
        created: "Құрылған күні",
        approval: "Келісу бағыты",
        approverPosition: "Лауазым көрсетілмеген",
        contact: "Байланыс ақпараты",
        pending: "Келісілуде",
        approved: "Келісілді",
        rejected: "Қабылданбады",
        waiting: "Қол қоюды күтуде",
        signed: "Қол қойды",
        declined: "Бас тартты",
        hire: "Жұмысқа қабылдау",
        dismissal: "Жұмыстан шығару",
        transfer: "Ауыстыру",
        maternity_leave: "Жүктілік және босану демалысы",
    },
};

const statusStyles = {
    pending: "bg-amber-100 text-amber-800",
    approved: "bg-emerald-100 text-emerald-800",
    rejected: "bg-red-100 text-red-700",
};

function formatDate(value, locale) {
    if (!value) return "—";
    return new Intl.DateTimeFormat(locale === "kk" ? "kk-KZ" : "ru-RU", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

function ApprovalIcon({ decision }) {
    const { t: _t } = useTextI18n();
    if (decision === "approved")
        return <CheckCircle2 aria-hidden="true" size={20} className="text-emerald-600" />;
    if (decision === "rejected")
        return <XCircle aria-hidden="true" size={20} className="text-red-600" />;
    return <Clock3 aria-hidden="true" size={20} className="text-amber-600" />;
}

export default function PublicPersonnelStatus() {
    const { t: _t } = useTextI18n();
    const { locale } = useI18n();
    const text = copy[locale] || copy.ru;
    const [name, setName] = useState("");
    const [items, setItems] = useState([]);
    const [searched, setSearched] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const submit = async (event) => {
        event.preventDefault();
        setLoading(true);
        setError("");
        setSearched(false);

        try {
            const { data } = await axios.post("/api/personnel/status", {
                employee_name: name.trim().replace(/\s+/g, " "),
            });
            setItems(data.data || []);
            setSearched(true);
        } catch (requestError) {
            setItems([]);
            setError(errorText(requestError));
        } finally {
            setLoading(false);
        }
    };

    const decisionText = (decision) => ({
        approved: text.signed,
        rejected: text.declined,
        pending: text.waiting,
    })[decision] || decision;

    return (
        <Layout title={_t(text.title)} description={_t(text.description)}>
            <Head title={_t(text.title)} />
            <main className="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
                <form
                    className="rounded-md border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
                    onSubmit={submit}
                >
                    <Alert>{_t(error)}</Alert>
                    <label className="block text-sm font-semibold text-gray-800">
                        {_t(text.label)}
                        <span className="mt-1 block text-xs font-normal text-gray-500">
                            {_t(text.hint)}
                        </span>
                        <div className="mt-3 flex flex-col gap-3 sm:flex-row">
                            <input
                                required
                                minLength="2"
                                maxLength="255"
                                autoComplete="name"
                                className={inputClass.replace("mt-1 ", "")}
                                placeholder={_t(text.placeholder)}
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                            />
                            <button
                                type="submit"
                                disabled={loading}
                                className="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-[#005eb8] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#244f9d] disabled:cursor-wait disabled:opacity-60"
                            >
                                <Search aria-hidden="true" size={17} />
                                {_t(loading ? text.searching : text.search)}
                            </button>
                        </div>
                    </label>
                </form>

                {searched && items.length === 0 && (
                    <div className="rounded-md border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500 shadow-sm">
                        {_t(text.empty)}
                    </div>
                )}

                {items.length > 0 && (
                    <section className="space-y-4">
                        <h2 className="text-lg font-bold text-gray-900">{_t(text.found)}</h2>
                        {items.map((item) => (
                            <article
                                key={item.id}
                                className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm"
                            >
                                <header className="flex flex-col gap-3 border-b border-blue-100 bg-[#edf4ff] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 className="font-bold text-[#174a9c]">
                                            {_t(text[item.action_type] || item.action_type)}
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600">{item.employee_name}</p>
                                    </div>
                                    <span className={`inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-bold ${statusStyles[item.status] || "bg-gray-100 text-gray-700"}`}>
                                        {_t(text[item.status] || item.status)}
                                    </span>
                                </header>

                                <dl className="grid gap-4 border-b border-gray-100 px-5 py-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    {item.employee_type === 'pps' ? <>
                                        <div><dt className="text-xs text-gray-500">{_t('Факультет')}</dt><dd className="mt-1 font-semibold">{item.faculty?.name || '—'}</dd></div>
                                        <div><dt className="text-xs text-gray-500">{_t('Кафедра')}</dt><dd className="mt-1 font-semibold">{item.academic_unit?.name || '—'}</dd></div>
                                    </> : <div><dt className="text-xs text-gray-500">{_t(text.department)}</dt><dd className="mt-1 font-semibold">{_t(item.department?.name || "—")}</dd></div>}
                                    <div><dt className="text-xs text-gray-500">{_t(text.position)}</dt><dd className="mt-1 font-semibold">{_t(item.position?.name || "—")}</dd></div>
                                    {item.action_type === "transfer" && (item.employee_type === 'pps' ? <>
                                        <div><dt className="text-xs text-gray-500">{_t('Новый факультет')}</dt><dd className="mt-1 font-semibold">{item.target_faculty?.name || '—'}</dd></div>
                                        <div><dt className="text-xs text-gray-500">{_t('Новая кафедра')}</dt><dd className="mt-1 font-semibold">{item.target_academic_unit?.name || '—'}</dd></div>
                                    </> : <div><dt className="text-xs text-gray-500">{_t(text.targetDepartment)}</dt><dd className="mt-1 font-semibold">{_t(item.target_department?.name || "—")}</dd></div>)}
                                    {item.action_type === "transfer" && <div><dt className="text-xs text-gray-500">{_t(text.targetPosition)}</dt><dd className="mt-1 font-semibold">{_t(item.target_position?.name || "—")}</dd></div>}
                                    <div><dt className="text-xs text-gray-500">{_t(text.created)}</dt><dd className="mt-1 font-semibold">{_t(formatDate(item.created_at, locale))}</dd></div>
                                </dl>

                                <div className="px-5 py-4">
                                    <h4 className="text-sm font-bold text-gray-900">{_t(text.approval)}</h4>
                                    <div className="mt-3 divide-y divide-gray-100">
                                        {item.approvals.map((approval) => (
                                            <div key={approval.id} className="grid gap-3 py-3 sm:grid-cols-[32px_1fr_auto] sm:items-center">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-50">
                                                    <ApprovalIcon decision={approval.decision} />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="font-semibold text-gray-900">
                                                        {_t(approval.approval_order)}. {_t(approval.user?.name || "—")}
                                                    </div>
                                                    <div className="mt-1 space-y-0.5 text-xs text-gray-600">
                                                        {approval.user?.positions?.length ? approval.user.positions.map((position) => (
                                                            <div key={position.id}>
                                                                {position.name}
                                                                {_t(position.unit_name && ` · ${[position.parent_name, position.unit_name].filter(Boolean).join(" / ")}`)}
                                                            </div>
                                                        )) : <div className="text-gray-400">{_t(text.approverPosition)}</div>}
                                                    </div>
                                                    {approval.decided_at && <div className="mt-0.5 text-xs text-gray-500">{_t(formatDate(approval.decided_at, locale))}</div>}
                                                    {approval.user?.contact_info && (
                                                        <div className="mt-2 flex items-start gap-2 rounded-md bg-blue-50 px-3 py-2 text-xs leading-5 text-[#174a9c]">
                                                            <ContactRound aria-hidden="true" size={15} className="mt-0.5 shrink-0" />
                                                            <div>
                                                                <span className="font-semibold">{_t(text.contact)}:</span>{" "}
                                                                <span className="whitespace-pre-wrap">{approval.user.contact_info}</span>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                                <span className={`w-fit rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[approval.decision] || "bg-gray-100 text-gray-700"}`}>
                                                    {_t(decisionText(approval.decision))}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </section>
                )}
            </main>
        </Layout>
    );
}
