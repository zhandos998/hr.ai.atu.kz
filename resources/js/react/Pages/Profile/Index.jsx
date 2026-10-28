import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import {
    BadgeCheck,
    ChevronRight,
    KeyRound,
    LogOut,
    Mail,
    Phone,
    RefreshCw,
    Save,
    ShieldCheck,
    UserRound,
} from "lucide-react";
import Layout from "../../Components/Layout";
import UserApplications from "../../Components/UserApplications";
import { Alert, errorText } from "../../Components/Form";
import { useAuth } from "../../AuthContext";
import { isTechnicalEmail } from "../../lib/email";

const roleLabels = {
    admin: "Администратор",
    lawyer: "Юрист / комплаенс",
    compliance: "Юрист / комплаенс",
    science_director: "Департамент науки",
    digital_director: "Департамент цифровизации",
    strategy_director: "Стратегическое развитие",
    academic_director: "Академическое развитие",
    library_director: "Научная библиотека",
    professional_development_director: "Повышение квалификации",
    economist: "Экономист",
    commission: "Член комиссии",
    commission_member: "Член комиссии",
    user: "Пользователь",
};

const userInitials = (name) =>
    String(name || "")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toLocaleUpperCase())
        .join("") || "АТ";

export default function Profile() {
    const { t: _t } = useTextI18n();
    const { user, logout, refreshUser } = useAuth();
    const technicalEmail = isTechnicalEmail(user?.email);
    const [loading, setLoading] = useState(false);
    const [savingContact, setSavingContact] = useState(false);
    const [phone, setPhone] = useState("");
    const [contactInfo, setContactInfo] = useState("");
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    useEffect(() => {
        setPhone(user?.phone || "");
        setContactInfo(user?.contact_info || "");
    }, [user?.contact_info, user?.phone]);

    const saveContactInfo = async (event) => {
        event.preventDefault();
        setSavingContact(true);
        setStatus("");
        setError("");
        try {
            const { data } = await axios.put("/api/user/contact-info", {
                phone,
                contact_info: contactInfo,
            });
            await refreshUser();
            setStatus(data.message);
        } catch (requestError) {
            setError(
                errorText(
                    requestError,
                    "Не удалось сохранить контактную информацию.",
                ),
            );
        } finally {
            setSavingContact(false);
        }
    };
    const resend = async () => {
        setLoading(true);
        setStatus("");
        setError("");
        try {
            const { data } = await axios.post(
                "/api/email/verification-notification",
                { email: user.email },
            );
            setStatus(data.message);
        } catch (requestError) {
            setError(errorText(requestError, "Не удалось отправить письмо."));
        } finally {
            setLoading(false);
        }
    };
    return (
        <Layout
            requireAuth
            title={_t("Профиль")}
            description={_t("Личные данные и безопасность учётной записи.")}
        >
            <Head title={_t("Профиль")} />
            {user && (
                <>
                    <div className="mx-auto max-w-7xl space-y-4 px-4 pt-8">
                        <Alert type="success">{_t(status)}</Alert>
                        <Alert>{_t(error)}</Alert>
                        <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div className="flex flex-col justify-between gap-5 bg-[#f8fbff] px-5 py-6 sm:flex-row sm:items-center sm:px-6">
                                <div className="flex min-w-0 items-center gap-4">
                                    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-[#005eb8] text-xl font-bold text-white">
                                        {_t(userInitials(user.name))}
                                    </div>
                                    <div className="min-w-0">
                                        <h1 className="break-words text-xl font-bold text-gray-950">
                                            {user.name}
                                        </h1>
                                        <p className="mt-1 text-sm text-gray-500">
                                            {_t(roleLabels[user.role] ||
                                                roleLabels.user)}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    className={`inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${
                                        user.email_verified_at && !technicalEmail
                                            ? "bg-emerald-100 text-emerald-800"
                                            : "bg-amber-100 text-amber-800"
                                    }`}
                                >
                                    {user.email_verified_at && !technicalEmail ? (
                                        <BadgeCheck size={16} />
                                    ) : (
                                        <ShieldCheck size={16} />
                                    )}
                                    {_t(technicalEmail
                                        ? "Техническая почта"
                                        : user.email_verified_at
                                          ? "Email подтверждён"
                                          : "Email не подтверждён")}
                                </span>
                            </div>

                            <div className="grid border-t border-gray-200 lg:grid-cols-[minmax(0,1fr)_360px]">
                                <div className="px-5 py-5 sm:px-6">
                                    <h2 className="text-sm font-bold text-[#174a9c]">{_t("Контактные данные")}</h2>
                                    <dl className="mt-3 divide-y divide-gray-100">
                                        {[
                                            [Mail, "Email", user.email],
                                            [
                                                Phone,
                                                "Телефон",
                                                user.phone || "Не указан",
                                            ],
                                            [
                                                UserRound,
                                                "ID пользователя",
                                                `№${user.id}`,
                                            ],
                                        ].map(([Icon, label, value]) => (
                                            <div
                                                key={label}
                                                className="grid gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center"
                                            >
                                                <dt className="flex items-center gap-2 text-sm text-gray-500">
                                                    <Icon
                                                        size={17}
                                                        className="shrink-0 text-gray-400"
                                                    />
                                                    {_t(label)}
                                                </dt>
                                                <dd className="min-w-0 break-words text-sm font-semibold text-gray-900 sm:text-right">
                                                    {_t(value)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                    <form
                                        className="mt-4 border-t border-gray-200 pt-5"
                                        onSubmit={saveContactInfo}
                                    >
                                        <label className="block text-sm font-semibold text-gray-800">{_t("Телефон")}<input
                                                type="tel"
                                                maxLength="255"
                                                autoComplete="tel"
                                                className="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#005eb8] focus:ring-2 focus:ring-blue-100"
                                                placeholder={_t("Например: +7 700 000 00 00")}
                                                value={phone}
                                                onChange={(event) =>
                                                    setPhone(event.target.value)
                                                }
                                            />
                                        </label>
                                        <label className="mt-4 block text-sm font-semibold text-gray-800">{_t("Как со мной связаться")}<span className="mt-1 block text-xs font-normal leading-5 text-gray-500">{_t("Укажите телефон, внутренний номер, кабинет, адрес или рабочую почту. Эти данные будут видны на публичной странице статуса кадрового процесса.")}</span>
                                            <textarea
                                                rows="4"
                                                maxLength="1000"
                                                className="mt-3 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#005eb8] focus:ring-2 focus:ring-blue-100"
                                                placeholder={_t("Например: каб. 214, вн. 123, +7 700 000 00 00")}
                                                value={contactInfo}
                                                onChange={(event) =>
                                                    setContactInfo(event.target.value)
                                                }
                                            />
                                        </label>
                                        <div className="mt-3 flex justify-end">
                                            <button
                                                type="submit"
                                                disabled={savingContact}
                                                className="inline-flex items-center gap-2 rounded-md bg-[#005eb8] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#244f9d] disabled:cursor-wait disabled:opacity-60"
                                            >
                                                <Save size={16} />
                                                {_t(savingContact
                                                    ? "Сохранение..."
                                                    : "Сохранить контакты")}
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <div className="border-t border-gray-200 px-5 py-5 sm:px-6 lg:border-l lg:border-t-0">
                                    <h2 className="text-sm font-bold text-[#174a9c]">{_t("Безопасность")}</h2>
                                    <div className="mt-3 divide-y divide-gray-100">
                                        <Link
                                            href="/profile/change-password"
                                            className="flex items-center justify-between gap-3 py-4 text-sm font-semibold text-gray-800 hover:text-[#005eb8]"
                                        >
                                            <span className="flex items-center gap-3">
                                                <KeyRound
                                                    size={18}
                                                    className="text-[#005eb8]"
                                                />{_t("Сменить пароль")}</span>
                                            <ChevronRight
                                                size={18}
                                                className="text-gray-400"
                                            />
                                        </Link>
                                        <Link
                                            href="/profile/change-email"
                                            className="flex items-center justify-between gap-3 py-4 text-sm font-semibold text-gray-800 hover:text-[#005eb8]"
                                        >
                                            <span className="flex items-center gap-3">
                                                <Mail
                                                    size={18}
                                                    className="text-[#005eb8]"
                                                />{_t("Сменить email")}</span>
                                            <ChevronRight
                                                size={18}
                                                className="text-gray-400"
                                            />
                                        </Link>
                                        {!user.email_verified_at && !technicalEmail && (
                                            <button
                                                type="button"
                                                disabled={loading}
                                                className="flex w-full items-center gap-3 py-4 text-left text-sm font-semibold text-[#005eb8] disabled:opacity-60"
                                                onClick={resend}
                                            >
                                                <RefreshCw
                                                    size={18}
                                                    className={
                                                        loading
                                                            ? "animate-spin"
                                                            : ""
                                                    }
                                                />
                                                {_t(loading
                                                    ? "Отправка..."
                                                    : "Подтвердить email")}
                                            </button>
                                        )}
                                    </div>
                                    <button
                                        type="button"
                                        className="mt-4 inline-flex items-center gap-2 rounded-md border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                                        onClick={logout}
                                    >
                                        <LogOut size={17} />{_t("Выйти из аккаунта")}</button>
                                </div>
                            </div>
                        </section>
                    </div>
                    <UserApplications />
                </>
            )}
        </Layout>
    );
}
