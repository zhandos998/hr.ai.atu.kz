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
          class="bg-white rounded-xl shadow border border-gray-100 p-4 flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4"
        >
          <div class="space-y-1 lg:max-w-sm">
            <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy_title }}</h2>
            <p class="text-gray-700 text-sm">Пользователь: {{ app.user.name }} ({{ app.user.email }})</p>
            <p class="text-gray-700 text-sm">Телефон: {{ app.user.phone || 'Не указан' }}</p>
            <p class="text-gray-500 text-sm">Дата подачи: {{ formatDate(app.created_at) }}</p>

            <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
              <div class="bg-blue-50 rounded px-2 py-1">Всего членов: <b>{{ app.vote_summary?.total_members ?? 0 }}</b></div>
              <div class="bg-emerald-50 rounded px-2 py-1">За: <b>{{ app.vote_summary?.hire ?? 0 }}</b></div>
              <div class="bg-red-50 rounded px-2 py-1">Против: <b>{{ app.vote_summary?.reject ?? 0 }}</b></div>
              <div class="bg-gray-50 rounded px-2 py-1">Голосовали: <b>{{ app.vote_summary?.voted ?? 0 }}</b></div>
              <div class="bg-amber-50 rounded px-2 py-1 col-span-2">Ожидают: <b>{{ app.vote_summary?.pending ?? 0 }}</b></div>
            </div>
          </div>

          <div class="flex-1 min-w-0 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
            <a
              v-if="app.resume_url"
              :href="app.resume_url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center whitespace-nowrap border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition"
            >
              Резюме
            </a>

            <template v-if="app.documents_map && Object.keys(app.documents_map).length">
              <a
                v-for="(doc, type) in app.documents_map"
                :key="type"
                :href="doc.url"
                target="_blank"
                rel="noopener"
                :download="`${type}-${app.id}`"
                class="inline-flex items-center whitespace-nowrap border border-emerald-600 text-emerald-700 hover:bg-emerald-600 hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition"
              >
                {{ docLabel(type) }}
              </a>
            </template>
            </div>

            <div class="border border-gray-200 rounded-lg p-3 space-y-2">
              <div class="text-sm font-semibold text-gray-700">Доп. комиссия по вакансии</div>

              <div v-if="vacancyMembers(app).length" class="flex flex-wrap gap-2">
                <span
                  v-for="member in vacancyMembers(app)"
                  :key="member.id"
                  class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs"
                >
                  <span>{{ member.name }}</span>
                  <button
                    @click="removeCommissionMember(app, member.id)"
                    class="text-red-600 hover:text-red-700 font-semibold cursor-pointer"
                    title="Удалить"
                  >
                    ×
                  </button>
                </span>
              </div>
              <div v-else class="text-xs text-gray-500">Голосующие не назначены.</div>

              <div class="flex flex-col md:flex-row gap-2">
                <select
                  v-model="memberToAdd[vacancyIdForApp(app)]"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                >
                  <option value="">Выберите пользователя</option>
                  <option
                    v-for="user in vacancyCandidates[vacancyIdForApp(app)] || []"
                    :key="user.id"
                    :value="user.id"
                  >
                    {{ user.name }} ({{ user.email }})
                  </option>
                </select>
                <button
                  @click="addCommissionMember(app)"
                  :disabled="!memberToAdd[vacancyIdForApp(app)]"
                  class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition cursor-pointer"
                >
                  Добавить
                </button>
              </div>
            </div>

            <div class="flex justify-start lg:justify-end">
              <select
                v-model="app.status.code"
                @change="updateStatus(app)"
                class="w-full sm:w-72 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#005eb8] transition"
              >
                <option v-for="status in statuses" :key="status.id" :value="status.code">
                  {{ statusName(status) }}
                </option>
              </select>
            </div>
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
const vacancyCandidates = ref({});
const memberToAdd = ref({});

const docLabels = {
  id_card: 'Уд. личности',
  diploma: 'Диплом',
  articles: 'Статьи/публикации',
  address_certificate: 'Адресная справка',
};

const parseDocType = (type) => {
  const raw = String(type);
  const match = raw.match(/^(.*)_(\d+)$/);
  if (!match) return { base: raw, index: null };
  return { base: match[1], index: Number(match[2]) };
};
const docLabel = (type) => {
  const parsed = parseDocType(type);
  const base = docLabels[parsed.base] || parsed.base;
  return parsed.index ? `${base} #${parsed.index}` : base;
};
const statusName = (status) => {
  if (status.code === 'corruption_not_found') return 'Коррупция: не выявлена';
  if (status.code === 'corruption_found') return 'Коррупция: выявлена';
  if (status.code === 'not_accepted') return 'Не принят на вакансию';
  return status.name;
};

const vacancyIdForApp = (app) => app?.vacancy?.id || app?.vacancy_id || null;
const vacancyMembers = (app) => app?.vacancy?.commission_members || [];

const loadVacancyCandidates = async (vacancyId) => {
  if (!vacancyId) return;
  try {
    const response = await axios.get(`/api/admin/vacancies/${vacancyId}/commission-candidates`);
    vacancyCandidates.value = {
      ...vacancyCandidates.value,
      [vacancyId]: response.data,
    };
  } catch (error) {
    console.error(error);
  }
};

const fetchApplications = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/applications');
    applications.value = response.data;

    const vacancyIds = [...new Set(applications.value.map((a) => vacancyIdForApp(a)).filter(Boolean))];
    await Promise.all(vacancyIds.map((id) => loadVacancyCandidates(id)));
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const addCommissionMember = async (app) => {
  const vacancyId = vacancyIdForApp(app);
  const userId = Number(memberToAdd.value[vacancyId]);
  if (!vacancyId || !userId) return;

  try {
    await axios.post(`/api/admin/vacancies/${vacancyId}/commission-members`, { user_id: userId });
    memberToAdd.value[vacancyId] = '';
    await fetchApplications();
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при добавлении голосующего.');
  }
};

const removeCommissionMember = async (app, userId) => {
  const vacancyId = vacancyIdForApp(app);
  if (!vacancyId) return;
  if (!confirm('Удалить голосующего из вакансии?')) return;

  try {
    await axios.delete(`/api/admin/vacancies/${vacancyId}/commission-members/${userId}`);
    await fetchApplications();
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при удалении голосующего.');
  }
};

const updateStatus = async (app) => {
  try {
    await axios.put(`/api/admin/applications/${app.id}`, {
      status_code: app.status.code,
    });
    alert('Статус обновлен.');
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

