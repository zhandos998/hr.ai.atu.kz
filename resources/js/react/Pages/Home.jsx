import { useI18n as useTextI18n } from "../I18nContext";
import React from "react";
import { Head, Link } from "@inertiajs/react";
import Layout from "../Components/Layout";
import { useAuth } from "../AuthContext";

export default function Home() {
    const { t: _t } = useTextI18n();
    const { user } = useAuth();
    return (
        <Layout>
            <Head title={_t("Главная")} />
            <div className="mx-auto max-w-3xl px-4 py-16 text-center">
                <h1 className="text-3xl font-bold text-[#174a9c] md:text-4xl">{_t("HR-портал Алматинского технологического университета")}</h1>
                <p className="mx-auto mt-5 max-w-2xl text-lg leading-8 text-gray-600">{_t("Подача заявок, загрузка документов и отслеживание решений по конкурсу в одном месте.")}</p>
                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Link
                        href="/vacancies"
                        className="rounded-md bg-[#005eb8] px-6 py-3 font-semibold text-white hover:bg-[#244f9d]"
                    >{_t("Смотреть вакансии")}</Link>
                    <Link
                        href={user ? "/profile" : "/login"}
                        className="rounded-md border border-[#005eb8] px-6 py-3 font-semibold text-[#174a9c] hover:bg-blue-50"
                    >
                        {_t(user ? "Перейти в профиль" : "Войти")}
                    </Link>
                </div>
            </div>
        </Layout>
    );
}
