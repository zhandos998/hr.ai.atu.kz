import { useI18n as useTextI18n } from "../I18nContext";
import React from 'react';
import { Head } from '@inertiajs/react';
import Layout from '../Components/Layout';
import UserApplications from '../Components/UserApplications';

export default function MyApplications() {
    const { t: _t } = useTextI18n(); return <Layout requireAuth title={_t("Мои заявки")}><Head title={_t("Мои заявки")} /><UserApplications /></Layout>; }

