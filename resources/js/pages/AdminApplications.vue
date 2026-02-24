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
            <h2 class="text-lg font-semibold text-[#005eb8]">
              Вакансия: {{ vacancyTitle(app) }}
            </h2>
            <p class="text-gray-700 text-sm">Пользователь: {{ app.user.name }} ({{ app.user.email }})</p>
            <p class="text-gray-700 text-sm">Телефон: {{ app.user.phone || 'Не указан' }}</p>
            <p class="text-gray-500 text-sm">Дата подачи: {{ formatDate(app.created_at) }}</p>
            <p class="text-gray-500 text-sm">ID кандидата: {{ app.user.id }}</p>

            <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
              <div class="bg-blue-50 rounded px-2 py-1">Всего членов: <b>{{ app.vote_summary?.total_members ?? 0 }}</b></div>
              <div class="bg-emerald-50 rounded px-2 py-1">За: <b>{{ app.vote_summary?.hire ?? 0 }}</b></div>
              <div class="bg-red-50 rounded px-2 py-1">Против: <b>{{ app.vote_summary?.reject ?? 0 }}</b></div>
              <div class="bg-gray-50 rounded px-2 py-1">Голосовали: <b>{{ app.vote_summary?.voted ?? 0 }}</b></div>
              <div class="bg-amber-50 rounded px-2 py-1 col-span-2">Ожидают: <b>{{ app.vote_summary?.pending ?? 0 }}</b></div>
            </div>

            <div v-if="(app.vote_details || []).length" class="mt-2 space-y-1">
              <div class="text-xs font-semibold text-gray-600">Детали голосования:</div>
              <div
                v-for="vote in app.vote_details"
                :key="`${app.id}-${vote.user_id}`"
                class="flex items-center justify-between gap-2 text-xs bg-gray-50 border border-gray-100 rounded px-2 py-1"
              >
                <div class="min-w-0">
                  <div class="truncate text-gray-700">{{ vote.name }}</div>
                  <div class="truncate text-gray-400">{{ vote.email }}</div>
                </div>
                <span
                  class="font-semibold whitespace-nowrap px-2 py-0.5 rounded"
                  :class="voteDecisionClass(vote.decision)"
                >
                  {{ voteDecisionLabel(vote.decision) }}
                </span>
              </div>
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

            <div class="border border-gray-200 rounded-lg p-3 space-y-2">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="text-sm font-semibold text-gray-700">AI-анализ кандидата</div>
                <button
                  @click="generateCandidateAI(app)"
                  :disabled="aiLoadingByAppId[app.id]"
                  class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition cursor-pointer"
                >
                  {{ aiLoadingByAppId[app.id] ? 'Генерация...' : 'Сгенерировать' }}
                </button>
              </div>

              <p v-if="aiErrorByAppId[app.id]" class="text-sm text-red-700">{{ aiErrorByAppId[app.id] }}</p>

              <template v-if="aiByAppId[app.id]">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <div class="bg-indigo-50 rounded px-2 py-1">Итоговый балл: <b>{{ aiByAppId[app.id].score ?? '-' }}</b></div>
                  <div class="bg-indigo-50 rounded px-2 py-1">Решение: <b>{{ aiByAppId[app.id].decision || '-' }}</b></div>
                  <div class="bg-indigo-50 rounded px-2 py-1">Образование: <b>{{ aiByAppId[app.id].education_match ?? '-' }}</b></div>
                  <div class="bg-indigo-50 rounded px-2 py-1">Опыт: <b>{{ aiByAppId[app.id].experience_match ?? '-' }}</b></div>
                  <div class="bg-indigo-50 rounded px-2 py-1 col-span-1 sm:col-span-2">Soft skills: <b>{{ aiByAppId[app.id].soft_skills_match ?? '-' }}</b></div>
                </div>
                <div class="text-sm text-gray-700 whitespace-pre-line">{{ aiByAppId[app.id].summary }}</div>
              </template>
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
const aiByAppId = ref({});
const aiLoadingByAppId = ref({});
const aiErrorByAppId = ref({});

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
const voteDecisionLabel = (decision) => {
  if (decision === 'hire') return 'За';
  if (decision === 'reject') return 'Против';
  return 'Не голосовал';
};
const voteDecisionClass = (decision) => {
  if (decision === 'hire') return 'bg-emerald-100 text-emerald-700';
  if (decision === 'reject') return 'bg-red-100 text-red-700';
  return 'bg-gray-200 text-gray-700';
};

const vacancyTitle = (app) => app?.vacancy?.title || 'Без названия';
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
    aiByAppId.value = {};
    aiErrorByAppId.value = {};
    applications.value.forEach((app) => {
      if (app.ai_result) {
        aiByAppId.value[app.id] = app.ai_result;
      }
    });

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

const generateCandidateAI = async (app) => {
  const positionId = app?.vacancy?.position_id;
  const workerId = app?.user?.id;

  aiErrorByAppId.value[app.id] = '';

  if (!workerId) {
    aiErrorByAppId.value[app.id] = 'Не найден ID кандидата.';
    return;
  }

  if (!positionId) {
    aiErrorByAppId.value[app.id] = 'У вакансии не указана должность (position_id).';
    return;
  }

  aiLoadingByAppId.value[app.id] = true;

  try {
    const response = await axios.post('/api/check-candidate', {
      worker_id: Number(workerId),
      position_id: Number(positionId),
      lang: 'ru',
    });

    aiByAppId.value[app.id] = response.data;
  } catch (error) {
    aiErrorByAppId.value[app.id] = error?.response?.data?.message || 'Ошибка при генерации AI-анализа.';
  } finally {
    aiLoadingByAppId.value[app.id] = false;
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

