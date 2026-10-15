import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useRef, useState } from "react";
import { Head } from "@inertiajs/react";
import Layout from "../Components/Layout";

export default function Chat() {
    const { t: _t } = useTextI18n();
    const [messages, setMessages] = useState([]);
    const [message, setMessage] = useState("");
    const [loading, setLoading] = useState(false);
    const container = useRef(null);
    useEffect(() => {
        if (container.current)
            container.current.scrollTop = container.current.scrollHeight;
    }, [messages, loading]);
    const send = async (event) => {
        event.preventDefault();
        const text = message.trim();
        if (!text || loading) return;
        setMessages((current) => [...current, { role: "user", content: text }]);
        setMessage("");
        setLoading(true);
        try {
            const response = await fetch("/api/chat/send", {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    Authorization: localStorage.getItem("token")
                        ? `Bearer ${localStorage.getItem("token")}`
                        : "",
                },
                body: JSON.stringify({ message: text }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message);
            setMessages((current) => [
                ...current,
                {
                    role: "assistant",
                    content: data.text || "",
                    buttons: Array.isArray(data.buttons) ? data.buttons : [],
                },
            ]);
        } catch {
            setMessages((current) => [
                ...current,
                {
                    role: "assistant",
                    content: "Извините, сервис временно недоступен.",
                    buttons: [],
                },
            ]);
        } finally {
            setLoading(false);
        }
    };
    return (
        <Layout requireAuth title={_t("HR Chat АТУ")}>
            <Head title={_t("HR Chat")} />
            <div className="mx-auto max-w-2xl px-4 py-8">
                <div
                    ref={container}
                    className="h-[500px] overflow-y-auto rounded-md border border-gray-200 bg-white p-4 shadow-inner"
                >
                    {messages.map((item, index) => (
                        <div
                            key={index}
                            className={`mb-3 flex ${item.role === "user" ? "justify-end" : "justify-start"}`}
                        >
                            <div
                                className={`max-w-[80%] rounded-md px-4 py-2 text-sm ${item.role === "user" ? "bg-[#005eb8] text-white" : "bg-gray-100 text-gray-900"}`}
                            >
                                <div className="whitespace-pre-wrap">
                                    {_t(item.content)}
                                </div>
                                {item.buttons?.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {item.buttons.map(
                                            (button, buttonIndex) => (
                                                <a
                                                    key={buttonIndex}
                                                    href={button.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-md border border-[#005eb8] px-3 py-1.5 text-xs text-[#174a9c]"
                                                >
                                                    {_t(button.label)}
                                                </a>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    {loading && (
                        <div className="text-sm text-gray-500">{_t("Формируется ответ...")}</div>
                    )}
                </div>
                <form className="mt-4 flex gap-2" onSubmit={send}>
                    <input
                        value={message}
                        className="min-w-0 flex-1 rounded-md border border-gray-300 px-4 py-2.5"
                        placeholder={_t("Введите сообщение...")}
                        onChange={(event) => setMessage(event.target.value)}
                    />
                    <button
                        className="rounded-md bg-[#005eb8] px-4 py-2.5 font-semibold text-white"
                        disabled={loading}
                    >{_t("Отправить")}</button>
                </form>
            </div>
        </Layout>
    );
}
