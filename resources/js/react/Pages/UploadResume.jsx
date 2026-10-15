import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import Layout from "../Components/Layout";
import {
    Alert,
    AuthCard,
    Field,
    errorText,
    primaryButtonClass,
} from "../Components/Form";

export default function UploadResume() {
    const { t: _t } = useTextI18n();
    const [file, setFile] = useState(null);
    const [progress, setProgress] = useState(0);
    const [loading, setLoading] = useState(false);
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [applications, setApplications] = useState([]);
    const [applicationId, setApplicationId] = useState("");
    useEffect(() => {
        axios.get("/api/applications").then(({ data }) => {
            const eligible = data.filter((item) => item.resume_status === "pending" && ["not_started", "voting"].includes(item.hiring_status));
            setApplications(eligible);
            if (eligible.length === 1) setApplicationId(String(eligible[0].id));
        }).catch((error) => setError(errorText(error)));
    }, []);
    const submit = async (event) => {
        event.preventDefault();
        if (!file) return;
        const payload = new FormData();
        payload.append("resume", file);
        payload.append("application_id", applicationId);
        setLoading(true);
        setStatus("");
        setError("");
        try {
            await axios.post("/api/resumes", payload, {
                onUploadProgress: ({ loaded, total }) =>
                    setProgress(total ? Math.round((loaded * 100) / total) : 0),
            });
            setStatus("Резюме успешно загружено.");
            setFile(null);
        } catch (requestError) {
            setError(
                errorText(requestError, "Ошибка загрузки. Попробуйте снова."),
            );
        } finally {
            setLoading(false);
        }
    };
    return (
        <Layout requireAuth>
            <Head title={_t("Загрузить резюме")} />
            <AuthCard title={_t("Загрузить резюме")}>
                <form className="space-y-4" onSubmit={submit}>
                    <Alert type="success">{_t(status)}</Alert>
                    <Alert>{_t(error)}</Alert>
                    <Field label={_t("Заявка")}>
                        <select required className="mt-1 w-full rounded-md border border-gray-300 p-2.5" value={applicationId} onChange={(event) => setApplicationId(event.target.value)}>
                            <option value="">{_t("Выберите заявку")}</option>
                            {applications.map((item) => <option key={item.id} value={item.id}>№{_t(item.id)}: {_t(item.vacancy?.title)}</option>)}
                        </select>
                    </Field>
                    <Field label={_t("Файл")} hint={_t("PDF, DOC или DOCX")}>
                        <input
                            className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            onChange={(event) =>
                                setFile(event.target.files?.[0] || null)
                            }
                        />
                    </Field>
                    {loading && (
                        <div className="h-2 overflow-hidden rounded-full bg-gray-200">
                            <div
                                className="h-full bg-[#005eb8]"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    )}
                    <button
                        className={`${primaryButtonClass} w-full`}
                        disabled={!file || !applicationId || loading}
                    >
                        {_t(loading ? "Загрузка..." : "Отправить резюме")}
                    </button>
                </form>
            </AuthCard>
        </Layout>
    );
}
