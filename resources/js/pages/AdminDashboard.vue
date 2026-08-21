<template>
    <Layout>
        <div class="max-w-6xl mx-auto py-8 px-4 space-y-8">
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ t('admin.dashboard.recruitment') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-1 gap-1">
                    <router-link
                        to="/admin/applications"
                        class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
                    >
                        <div class="text-lg font-semibold">{{ t('admin.dashboard.applications.title') }}</div>
                        <div class="text-sm text-blue-100 mt-2">{{ t('admin.dashboard.applications.description') }}</div>
                    </router-link>
                </div>
            </section>

            <div class="border-t border-gray-200"></div>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ t('admin.dashboard.structure') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <router-link
                        to="/admin/departments"
                        class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
                    >
                        <div class="text-lg font-semibold">{{ t('admin.nav.departments') }}</div>
                        <div class="text-sm text-blue-100 mt-2">{{ t('admin.dashboard.departments.description') }}</div>
                    </router-link>

                    <router-link
                        to="/admin/positions"
                        class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
                    >
                        <div class="text-lg font-semibold">{{ t('admin.nav.positions') }}</div>
                        <div class="text-sm text-blue-100 mt-2">{{ t('admin.dashboard.positions.description') }}</div>
                    </router-link>

                    <router-link
                        to="/admin/structure-tree"
                        class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
                    >
                        <div class="text-lg font-semibold">{{ t('admin.dashboard.structureTree.title') }}</div>
                        <div class="text-sm text-blue-100 mt-2">{{ t('admin.dashboard.structureTree.description') }}</div>
                    </router-link>
                </div>
            </section>

            <div class="border-t border-gray-200"></div>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ t('admin.dashboard.access') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <router-link
                        to="/admin/commission-members"
                        class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
                    >
                        <div class="text-lg font-semibold">{{ t('admin.dashboard.commissionMembers.title') }}</div>
                        <div class="text-sm text-blue-100 mt-2">{{ t('admin.dashboard.commissionMembers.description') }}</div>
                    </router-link>

                    <router-link
                        to="/admin/users"
                        class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
                    >
                        <div class="text-lg font-semibold">{{ t('admin.dashboard.users.title') }}</div>
                        <div class="text-sm text-blue-100 mt-2">{{ t('admin.dashboard.users.description') }}</div>
                    </router-link>
                </div>
            </section>
        </div>
    </Layout>
</template>

<script setup>
import Layout from "../components/Layout.vue";
import { useI18n } from "../i18n";

const { t } = useI18n();
</script>
