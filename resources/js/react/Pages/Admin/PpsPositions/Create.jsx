import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../../Components/Layout";
import { Save } from 'lucide-react';
import { DirectoryPage, directoryFormClass } from '../../../Components/Directory';
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
    const { positionId = null } = usePage().props;
    const title = positionId ? 'Изменить должность ППС' : 'Добавить позицию ППС';
    const [name, setName] = useState("");
    const [loading, setLoading] = useState(Boolean(positionId));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        const controller = new AbortController();
        setName('');
        setError('');
        setLoading(Boolean(positionId));
        if (positionId) {
            axios.get('/api/admin/pps-positions/' + positionId, { signal: controller.signal })
                .then(({ data }) => { if (!controller.signal.aborted) setName(data.name); })
                .catch(error => { if (!controller.signal.aborted) setError(errorText(error)); })
                .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }
        return () => controller.abort();
    }, [positionId]);

    const submit = async (event) => {
        event.preventDefault();
        if (loading || saving || !name.trim()) return;
        setSaving(true);
        setError("");
        try {
            if (positionId) await axios.put('/api/admin/pps-positions/' + positionId, { name });
            else await axios.post("/api/admin/pps-positions", { name });
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
            title={_t(title)}
            actions={
                <Link
                    href="/admin/pps-positions"
                    className={secondaryButtonClass}
                >{_t("Назад")}</Link>
            }
        >
            <Head title={_t(title)} />
            <DirectoryPage form loading={loading}>
                <form
                    className={directoryFormClass}
                    onSubmit={submit}
                >
                    <Alert>{_t(error)}</Alert>
                    <Field label={_t("Название позиции")}>
                        <input
                            required
                            autoFocus
                            maxLength={255}
                            disabled={loading || saving}
                            className={inputClass}
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    </Field>
                    <div className="flex flex-wrap justify-end gap-3">
                        <Link
                            href="/admin/pps-positions"
                            className={secondaryButtonClass}
                        >{_t("Отмена")}</Link>
                        <button
                            className={`${primaryButtonClass} inline-flex items-center gap-2`}
                            disabled={loading || saving || !name.trim()}
                        >
                            <Save size={16} />
                            {_t(saving ? "Сохранение..." : "Сохранить")}
                        </button>
                    </div>
                </form>
            </DirectoryPage>
        </Layout>
    );
}
