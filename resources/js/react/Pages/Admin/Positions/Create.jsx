import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    loadPositionUnits,
    positionOwnerFields,
} from "../../../lib/positionUnits";
import Layout from "../../../Components/Layout";
import {
    Alert,
    Field,
    errorText,
    inputClass,
    primaryButtonClass,
} from "../../../Components/Form";

export default function PositionCreate() {
    const { t: _t } = useTextI18n();
    const { url } = usePage();
    const selectedDepartmentId = new URLSearchParams(
        String(url).split("?")[1] || "",
    ).get("department_id");
    const [departments, setDepartments] = useState([]);
    const [form, setForm] = useState({
        unit_key: "",
        name: "",
        duties: "",
        qualification: "",
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        loadPositionUnits({ includeAcademic: false })
            .then((data) => {
                setDepartments(data);
                const selectedKey = `department:${selectedDepartmentId}`;
                if (
                    selectedDepartmentId &&
                    data.some((item) => item.id === selectedKey)
                ) {
                    setForm((current) => ({
                        ...current,
                        unit_key: selectedKey,
                    }));
                }
            })
            .catch((requestError) => setError(errorText(requestError)));
    }, [selectedDepartmentId]);
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError("");
        try {
            await axios.post("/api/admin/positions", {
                ...form,
                ...positionOwnerFields(form.unit_key),
            });
            window.location.assign(
                selectedDepartmentId
                    ? `/admin/departments/${selectedDepartmentId}`
                    : "/admin/positions",
            );
        } catch (requestError) {
            setError(errorText(requestError));
        } finally {
            setSaving(false);
        }
    };
    return (
        <Layout
            admin
            title={_t("Добавить должность")}
            actions={
                <Link
                    href={
                        selectedDepartmentId
                            ? `/admin/departments/${selectedDepartmentId}`
                            : "/admin/positions"
                    }
                    className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold"
                >{_t("Назад")}</Link>
            }
        >
            <Head title={_t("Добавить должность")} />
            <div className="mx-auto max-w-3xl px-4 py-8">
                <form
                    className="space-y-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm"
                    onSubmit={submit}
                >
                    <Alert>{_t(error)}</Alert>
                    <Field label={_t("Подразделение")}>
                        <select
                            required
                            className={inputClass}
                            value={form.unit_key}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    unit_key: event.target.value,
                                })
                            }
                        >
                            <option value="">{_t("Выберите подразделение")}</option>
                            {departments.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label={_t("Название должности")}>
                        <input
                            required
                            className={inputClass}
                            value={form.name}
                            onChange={(event) =>
                                setForm({ ...form, name: event.target.value })
                            }
                        />
                    </Field>
                    <Field label={_t("Должностные обязанности")}>
                        <textarea
                            rows="5"
                            className={inputClass}
                            value={form.duties}
                            onChange={(event) =>
                                setForm({ ...form, duties: event.target.value })
                            }
                        />
                    </Field>
                    <Field label={_t("Квалификационные требования")}>
                        <textarea
                            rows="5"
                            className={inputClass}
                            value={form.qualification}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    qualification: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <button
                        className={`${primaryButtonClass} w-full`}
                        disabled={saving}
                    >
                        {_t(saving ? "Сохранение..." : "Сохранить должность")}
                    </button>
                </form>
            </div>
        </Layout>
    );
}
