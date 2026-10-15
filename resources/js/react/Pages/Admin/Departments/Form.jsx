import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import Layout from "../../../Components/Layout";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
} from "../../../Components/Form";

export default function DepartmentForm() {
    const { t: _t } = useTextI18n();
    const { departmentId = null, academic = false } = usePage().props;
    const base = academic ? "/admin/academic-units" : "/admin/departments";
    const title = academic
        ? departmentId
            ? "Изменить факультет / кафедру"
            : "Добавить факультет / кафедру"
        : departmentId
          ? "Изменить департамент"
          : "Добавить департамент";
    const editing = Boolean(departmentId);
    const [items, setItems] = useState([]);
    const [form, setForm] = useState({
        name: "",
        description: "",
        parent_id: "",
    });
    const [loading, setLoading] = useState(editing);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        axios
            .get(`/api${base}`)
            .then(({ data }) => {
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
                }
            })
            .catch((requestError) => setError(errorText(requestError)))
            .finally(() => setLoading(false));
    }, [departmentId, editing, base]);
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
            setError("Введите название департамента.");
            return;
        }
        setSaving(true);
        setError("");
        try {
            if (editing)
                await axios.put(`/api${base}/${departmentId}`, {
                    ...form,
                    parent_id: form.parent_id || null,
                });
            else
                await axios.post(`/api${base}`, {
                    ...form,
                    parent_id: form.parent_id || null,
                });
            window.location.assign(base);
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
                    href={base}
                    className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700"
                >{_t("Назад")}</Link>
            }
        >
            <Head title={_t(title)} />
            <div className="mx-auto max-w-3xl px-4 py-8">
                {loading ? (
                    <div className="text-center text-sm text-gray-500">{_t("Загрузка...")}</div>
                ) : (
                    <form
                        className="space-y-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                        onSubmit={submit}
                    >
                        <Alert>{_t(error)}</Alert>
                        <Field
                            label={
                                _t(academic
                                    ? "Факультет"
                                    : "Родительское подразделение")
                            }
                        >
                            <select
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
                                        ? "Самостоятельный факультет"
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
                        </Field>
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
                        <button
                            className={`${primaryButtonClass} w-full`}
                            disabled={saving}
                        >
                            {_t(saving ? "Сохранение..." : "Сохранить")}
                        </button>
                    </form>
                )}
            </div>
        </Layout>
    );
}
