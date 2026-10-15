import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../Components/Layout";
import {
    Alert,
    AuthCard,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
} from "../../Components/Form";
import { useI18n } from "../../I18nContext";

const copy = {
    ru: {
        title: "Сменить email",
        description: "Подтверждение отправится на новый email.",
        email: "Новый email",
        password: "Текущий пароль",
        submit: "Отправить подтверждение",
        sending: "Отправка...",
        back: "Назад в профиль",
        error: "Не удалось отправить подтверждение.",
    },
    kk: {
        title: "Email өзгерту",
        description: "Растау хаты жаңа email мекенжайына жіберіледі.",
        email: "Жаңа email",
        password: "Ағымдағы құпиясөз",
        submit: "Растауды жіберу",
        sending: "Жіберілуде...",
        back: "Профильге оралу",
        error: "Растауды жіберу мүмкін болмады.",
    },
};

export default function ChangeEmail() {
    const { t: _t } = useTextI18n();
    const { locale } = useI18n();
    const text = copy[locale] || copy.ru;
    const [form, setForm] = useState({ new_email: "", password: "" });
    const [loading, setLoading] = useState(false);
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const update = (key) => (event) =>
        setForm((current) => ({ ...current, [key]: event.target.value }));

    const submit = async (event) => {
        event.preventDefault();
        setLoading(true);
        setStatus("");
        setError("");
        try {
            const { data } = await axios.post(
                "/api/user/email/change-request",
                form,
            );
            setStatus(data.message);
            setForm({ new_email: "", password: "" });
        } catch (requestError) {
            setError(errorText(requestError, text.error));
        } finally {
            setLoading(false);
        }
    };

    return (
        <Layout requireAuth>
            <Head title={_t(text.title)} />
            <AuthCard title={_t(text.title)}>
                <form className="space-y-4" onSubmit={submit}>
                    <p className="text-sm text-gray-600">{_t(text.description)}</p>
                    <Alert type="success">{_t(status)}</Alert>
                    <Alert>{_t(error)}</Alert>
                    <Field label={text.email}>
                        <input
                            className={inputClass}
                            type="email"
                            required
                            value={form.new_email}
                            onChange={update("new_email")}
                        />
                    </Field>
                    <Field label={_t(text.password)}>
                        <input
                            className={inputClass}
                            type="password"
                            required
                            value={form.password}
                            onChange={update("password")}
                        />
                    </Field>
                    <button
                        className={`${primaryButtonClass} w-full`}
                        disabled={loading}
                    >
                        {_t(loading ? text.sending : text.submit)}
                    </button>
                    <Link
                        href="/profile"
                        className="block text-center text-sm text-[#174a9c]"
                    >
                        {_t(text.back)}
                    </Link>
                </form>
            </AuthCard>
        </Layout>
    );
}
