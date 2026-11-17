import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
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

export default function DepartmentForm() {
    const { t: _t } = useTextI18n();
    const { props: { departmentId = null, academic = false }, url } = usePage();
    const requestedFaculty = academic && !departmentId ? new URLSearchParams(url.split('?')[1] || '').get('faculty_id') || '' : '';
    const [kind, setKind] = useState(requestedFaculty ? 'chair' : 'faculty');
    const isChair = academic && (departmentId ? kind === 'chair' : Boolean(requestedFaculty));
    const base = academic ? "/admin/academic-units" : "/admin/departments";
    const title = academic
        ? departmentId
            ? isChair ? "Изменить кафедру" : "Изменить факультет"
            : isChair ? "Добавить кафедру" : "Добавить факультет"
        : departmentId
          ? "Изменить департамент"
          : "Добавить департамент";
    const editing = Boolean(departmentId);
    const [items, setItems] = useState([]);
    const [form, setForm] = useState({
        name: "",
        description: "",
        parent_id: requestedFaculty,
    });
    const [loading, setLoading] = useState(editing);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const back = academic && isChair && form.parent_id ? `${base}/${form.parent_id}` : base;
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError('');
        axios
            .get(`/api${base}`, { signal: controller.signal })
            .then(({ data }) => {
                if (controller.signal.aborted) return;
                setItems(data);
                if (editing) {
                    const item = data.find(
                        (row) => Number(row.id) === Number(departmentId),
                    );
                    if (!item) {
                        window.location.assign(base);
                        return;
                    }
                    setForm({
                        name: item.name || "",
                        description: item.description || "",
                        parent_id: item.parent_id || "",
                    });
                    if (academic) setKind(item.kind);
                } else setForm({ name: '', description: '', parent_id: requestedFaculty });
            })
            .catch((requestError) => { if (!controller.signal.aborted) setError(errorText(requestError)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [departmentId, editing, base, requestedFaculty]);
    const excluded = useMemo(() => {
        const result = new Set(departmentId ? [Number(departmentId)] : []);
        let changed = true;
        while (changed) {
            changed = false;
            items.forEach((item) => {
                if (
                    result.has(Number(item.parent_id)) &&
                    !result.has(Number(item.id))
                ) {
                    result.add(Number(item.id));
                    changed = true;
                }
            });
        }
        return result;
    }, [departmentId, items]);
    const submit = async (event) => {
        event.preventDefault();
        if (!form.name.trim()) {
            setError(academic ? isChair ? 'Введите название кафедры.' : 'Введите название факультета.' : "Введите название департамента.");
            return;
        }
        setSaving(true);
        setError("");
        try {
            const payload = { ...form, parent_id: academic && !isChair ? null : form.parent_id || null };
            const response = editing ? await axios.put(`/api${base}/${departmentId}`, payload) : await axios.post(`/api${base}`, payload);
            window.location.assign(academic && response.data.parent_id ? `${base}/${response.data.parent_id}` : base);
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
                    href={back}
                    className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700"
                >{_t("Назад")}</Link>
            }
        >
            <Head title={_t(title)} />
            <DirectoryPage form loading={loading}>
                {loading ? (
                    <div className="text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    <form
                        className={directoryFormClass}
                        onSubmit={submit}
                    >
                        <Alert>{_t(error)}</Alert>
                        {(!academic || isChair) && <Field
                            label={
                                _t(academic
                                    ? "Факультет"
                                    : "Родительское подразделение")
                            }
                        >
                            <select
                                required={isChair}
                                className={inputClass}
                                value={form.parent_id}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        parent_id: event.target.value,
                                    })
                                }
                            >
                                <option value="">
                                    {_t(academic
                                        ? "Выберите факультет"
                                        : "Без родительского подразделения")}
                                </option>
                                {items
                                    .filter(
                                        (item) =>
                                            !excluded.has(Number(item.id)) &&
                                            (!academic ||
                                                item.kind === "faculty"),
                                    )
                                    .map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                            </select>
                        </Field>}
                        <Field label={_t("Название")}>
                            <input
                                className={inputClass}
                                value={form.name}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        name: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        {!academic && (
                            <Field label={_t("Описание")}>
                                <textarea
                                    className={inputClass}
                                    rows="4"
                                    value={form.description}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            description: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        )}
                        <div className="flex flex-wrap justify-end gap-3">
                        <Link href={back} className={secondaryButtonClass}>{_t('Отмена')}</Link>
                        <button
                            className={`${primaryButtonClass} inline-flex items-center gap-2`}
                            disabled={saving}
                        >
                            <Save size={16} />
                            {_t(saving ? "Сохранение..." : "Сохранить")}
                        </button>
                        </div>
                    </form>
                )}
            </DirectoryPage>
        </Layout>
    );
}
