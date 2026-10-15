import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../../Components/Layout";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
    secondaryButtonClass,
} from "../../../Components/Form";

export default function CompetitionForm() {
    const { t: _t } = useTextI18n();
    const { competitionId } = usePage().props;
    const [form, setForm] = useState({ type: "pps", title: "" });
    const [defaults, setDefaults] = useState({});
    const [customTitle, setCustomTitle] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const returnUrl = competitionId
        ? `/admin/competitions/${competitionId}`
        : "/admin/competitions";
    useEffect(() => {
        let active = true;
        setLoading(true);
        axios
            .get(`/api/admin/competitions/${competitionId || "defaults"}`)
            .then(({ data }) => {
                if (!active) return;
                if (competitionId) {
                    setForm({ title: data.title, type: data.type });
                    setCustomTitle(true);
                } else {
                    setDefaults(data);
                    setForm({ type: "pps", title: data.pps });
                    setCustomTitle(false);
                }
            })
            .catch((requestError) => {
                if (active) setError(errorText(requestError));
            })
            .finally(() => {
                if (active) setLoading(false);
            });
        return () => {
            active = false;
        };
    }, [competitionId]);
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError("");
        try {
            const payload = {
                type: form.type,
                title: customTitle ? form.title : null,
            };
            const { data } = competitionId
                ? await axios.put(
                      `/api/admin/competitions/${competitionId}`,
                      payload,
                  )
                : await axios.post("/api/admin/competitions", payload);
            router.visit(`/admin/competitions/${data.id}`);
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    const title = competitionId ? "Изменить конкурс" : "Создать конкурс";
    return (
        <Layout
            admin
            title={_t(title)}
            actions={
                <Link href={returnUrl} className={secondaryButtonClass}>{_t("Назад")}</Link>
            }
        >
            <Head title={_t(title)} />
            <div className="mx-auto max-w-3xl space-y-5 px-4 py-6">
                <Alert>{_t(error)}</Alert>
                {loading ? (
                    <div className="py-12 text-center text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    <form onSubmit={submit} className="space-y-5">
                        <Field label={_t("Тип конкурса")}>
                            <select
                                className={inputClass}
                                value={form.type}
                                disabled={Boolean(competitionId) || saving}
                                onChange={(event) =>
                                    setForm({
                                        type: event.target.value,
                                        title: customTitle
                                            ? form.title
                                            : defaults[event.target.value],
                                    })
                                }
                            >
                                <option value="pps">{_t("ППС")}</option>
                                <option value="staff">{_t("АУП")}</option>
                            </select>
                        </Field>
                        <Field label={_t("Название конкурса")}>
                            <input
                                required
                                maxLength={255}
                                className={inputClass}
                                value={form.title}
                                disabled={saving}
                                onChange={(event) => {
                                    setCustomTitle(true);
                                    setForm({
                                        ...form,
                                        title: event.target.value,
                                    });
                                }}
                            />
                        </Field>
                        <div className="flex justify-end gap-2">
                            <Link
                                href={returnUrl}
                                className={secondaryButtonClass}
                            >{_t("Отмена")}</Link>
                            <button
                                disabled={saving || !form.title.trim()}
                                className={primaryButtonClass}
                            >
                                {_t(saving
                                    ? "Сохранение..."
                                    : competitionId
                                      ? "Сохранить"
                                      : "Создать конкурс")}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </Layout>
    );
}
