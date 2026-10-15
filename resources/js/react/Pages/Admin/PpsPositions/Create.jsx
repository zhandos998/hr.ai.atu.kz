import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";
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

export default function PpsPositionCreate() {
    const { t: _t } = useTextI18n();
    const [name, setName] = useState("");
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError("");
        try {
            await axios.post("/api/admin/pps-positions", { name });
            window.location.assign("/admin/pps-positions");
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Layout
            admin
            title={_t("Добавить позицию ППС")}
            actions={
                <Link
                    href="/admin/pps-positions"
                    className={secondaryButtonClass}
                >{_t("Назад")}</Link>
            }
        >
            <Head title={_t("Добавить позицию ППС")} />
            <div className="mx-auto max-w-3xl px-4 py-8">
                <form
                    className="space-y-5 rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                    onSubmit={submit}
                >
                    <Alert>{_t(error)}</Alert>
                    <Field label={_t("Название позиции")}>
                        <input
                            required
                            autoFocus
                            className={inputClass}
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    </Field>
                    <div className="flex justify-end gap-3">
                        <Link
                            href="/admin/pps-positions"
                            className={secondaryButtonClass}
                        >{_t("Отмена")}</Link>
                        <button
                            className={primaryButtonClass}
                            disabled={saving || !name.trim()}
                        >
                            {_t(saving ? "Сохранение..." : "Сохранить позицию")}
                        </button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
