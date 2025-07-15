<template>
        <div class="max-w-3xl mx-auto mt-12 bg-white shadow-lg rounded-xl p-6 border border-gray-100">
            <h2 class="text-xl font-semibold mb-4 text-[#005eb8] text-center">Мои заявки</h2>

            <div v-if="loading" class="text-center text-gray-500">Загрузка заявок...</div>
            <div v-else-if="applications.length === 0" class="text-center text-gray-600">У вас пока нет заявок.</div>

            <div v-else class="space-y-4">
                <div
                    v-for="app in applications"
                    :key="app.id"
                    class="bg-white rounded-xl shadow border border-gray-100 p-4 flex flex-col md:flex-row md:justify-between md:items-center"
                >

                    <div>
                        <h3 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy.title }}</h3>
                            <p class="text-gray-700 mb-2">{{ app.vacancy.description }}</p>
                            <p class="text-sm text-gray-500">Тип: {{ app.vacancy.type === 'staff' ? 'Сотрудники' : 'ППС' }}</p>
                        <p class="text-sm text-gray-500">Дата подачи: {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}</p>
                    </div>
                    <div
                        :class="statusClasses(app.status) + ' mt-2 md:mt-0 px-3 py-1 rounded-full text-sm text-center w-max'"
                    >
                        {{ statusText(app.status.name) }}
                    </div>
                    <template v-if="app.status.code === 'resume_accepted'">
                        <button
                                @click="openUploadModal(app)"
                                class="mt-2 bg-[#005eb8] hover:bg-blue-700 text-white text-center font-semibold py-1 px-3 rounded transition"
                        >
                                Загрузить документы
                        </button>
                    </template>
                    <UploadDocsModal
                        v-if="showUploadModal"
                        :application="selectedApplication"
                        @close="closeUploadModal"
                        />

                </div>
            </div>
        </div>
    </template>

    <script setup>
    import { ref, onMounted } from 'vue';
    import UploadDocsModal from '../components/UploadDocsModal.vue';
    import axios from 'axios';

    const applications = ref([]);
    const loading = ref(true);

    const fetchApplications = async () => {
        loading.value = true;
        try {
            const response = await axios.get('/api/applications');
            applications.value = response.data;
        } catch (error) {
            console.error('Ошибка загрузки заявок:', error);
        } finally {
            loading.value = false;
        }
    };

    const showUploadModal = ref(false);
    const selectedApplication = ref(null);

    const openUploadModal = (app) => {
        selectedApplication.value = app;
        showUploadModal.value = true;
    };

    const closeUploadModal = () => {
        showUploadModal.value = false;
        selectedApplication.value = null;
    };

    const statusText = (status) => {
            switch (status) {
                    case 'pending': return 'Ваше резюме на рассмотрении';
                    case 'resume_rejected': return 'Резюме отклонено';
                    case 'resume_accepted': return 'Резюме принято, загрузите документы';
                    case 'docs_uploaded': return 'Документы загружены, ожидают проверки';
                    case 'docs_rejected': return 'Документы отклонены, загрузите заново';
                    case 'docs_accepted': return 'Документы приняты';
                    case 'completed': return 'Вы приняты на вакансию';
                    default: return status;
            }
    };

    const statusClasses = (status) => {
            switch (status) {
                    case 'pending': return 'bg-yellow-100 text-yellow-800';
                    case 'resume_rejected':
                    case 'docs_rejected': return 'bg-red-100 text-red-800';
                    case 'resume_accepted':
                    case 'docs_uploaded': return 'bg-blue-100 text-blue-800';
                    case 'docs_accepted': return 'bg-green-100 text-green-800';
                    case 'completed': return 'bg-green-200 text-green-900 font-semibold';
                    default: return 'bg-gray-100 text-gray-800';
            }
    };

    onMounted(() => {
            fetchApplications();
    });
</script>
