<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Управление заявками</h1>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-center text-gray-600">Заявок пока нет.</div>

      <div v-else class="space-y-4">
        <div
          v-for="app in applications"
          :key="app.id"
          class="bg-white rounded-xl shadow border border-gray-100 p-4 flex flex-col md:flex-row md:justify-between md:items-center gap-2"
        >
          <div class="space-y-1">
            <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy_title }}</h2>
            <p class="text-gray-700 text-sm">Пользователь: {{ app.user.name }} ({{ app.user.email }})</p>
            <p class="text-gray-500 text-sm">Дата подачи: {{ formatDate(app.created_at) }}</p>
          </div>

          <div class="flex items-center gap-2">
            <a
                v-if="app.resume_url"
                :href="app.resume_url"
                target="_blank"
                rel="noopener"
                class="border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white px-3 py-1 rounded transition h-[60px] content-center"
                >
                Резюме
            </a>

            <!-- Документы -->
            <template v-if="app.documents_map && Object.keys(app.documents_map).length">
                <a
                v-for="(doc, type) in app.documents_map"
                :key="type"
                :href="doc.url"
                target="_blank"
                rel="noopener"
                :download="`${type}-${app.id}`"
                class="border border-emerald-600 text-emerald-700 hover:bg-emerald-600 hover:text-white px-3 py-1 rounded transition h-[60px] content-center"
                >
                {{ docLabel(type) }}
                </a>
            </template>

            <select
              v-model="app.status.code"
              @change="updateStatus(app)"
              class="border border-gray-300 rounded-lg px-3 py-1 focus:ring-2 focus:ring-[#005eb8] transition"
            >
                <option
                    v-for="status in statuses"
                    :key="status.id"
                    :value="status.code"
                >
                    {{ status.name }}
                </option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const applications = ref([]);
const loading = ref(true);
const statuses = ref([]);
const docLabels = {
  id_card: 'Уд. личности',
  diploma: 'Диплом',
  articles: 'Статьи/публикации',
  address_certificate: 'Адресная справка',
};

const docLabel = (type) => docLabels[type] || type;

const fetchApplications = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/applications');
    applications.value = response.data;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// const updateStatus = async (app) => {
//   try {
//     await axios.put(`/api/admin/applications/${app.id}`, {
//       status_id: app.status.id
//     });
//     alert('Статус обновлён');
//   } catch (error) {
//     console.error(error);
//     alert('Ошибка при обновлении статуса');
//   }
// };
const updateStatus = async (app) => {
    try {
        await axios.put(`/api/admin/applications/${app.id}`, {
            status_code: app.status.code,
        });
        alert('Статус обновлён.');
    } catch (error) {
        console.error(error);
        alert('Ошибка при обновлении статуса.');
    }
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });
};

const fetchStatuses = async () => {
    try {
        const response = await axios.get('/api/application-statuses');
        statuses.value = response.data;
    } catch (error) {
        console.error('Ошибка загрузки статусов', error);
    }
};


onMounted(() => {
  fetchApplications();
  fetchStatuses();
});

</script>
