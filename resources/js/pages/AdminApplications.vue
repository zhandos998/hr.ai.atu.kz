<template>
  <Layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
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
