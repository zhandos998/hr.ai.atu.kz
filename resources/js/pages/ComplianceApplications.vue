<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Право и комплаенс</h1>
          <p class="text-sm text-gray-500">Очередь директора Департамента правового обеспечения и комплаенса по заявкам ППС и АУП.</p>
        </div>
        <div class="text-sm text-gray-500">
          Всего заявок: <span class="font-semibold text-gray-700">{{ applications.length }}</span>
        </div>
      </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-center text-gray-600">Заявок для департамента комплаенса пока нет.</div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <router-link
          v-for="app in applications"
          :key="app.id"
          :to="{ name: 'ComplianceApplicationDetails', params: { id: app.id } }"
          class="group block rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-xs font-semibold uppercase tracking-wide text-[#005eb8]">{{ vacancyTypeLabel(app.vacancy?.type) }}</div>
              <h2 class="mt-1 truncate text-lg font-semibold text-gray-900">{{ app.vacancy?.title || 'Без названия' }}</h2>
              <p class="mt-1 truncate text-sm text-gray-700">{{ app.user?.name || 'Кандидат не указан' }}</p>
              <p class="truncate text-xs text-gray-400">{{ app.user?.email || 'Email не указан' }}</p>
            </div>

            <div
              :class="complianceReady(app) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
              class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium text-center"
            >
              {{ complianceStatusLabel(app) }}
            </div>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl bg-slate-50 px-3 py-2">
              <div class="text-gray-400">Резюме</div>
              <div class="mt-1 font-medium text-gray-700">{{ stageLabel('resume', app.resume_status) }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2">
              <div class="text-gray-400">Документы</div>
              <div class="mt-1 font-medium text-gray-700">{{ stageLabel('documents', app.documents_status) }}</div>
            </div>
          </div>

          <div class="mt-4 space-y-2 text-sm text-gray-600 min-h-[96px]">
            <template v-if="app.vacancy?.type === 'pps'">
              <div class="line-clamp-2">
                <span class="font-medium text-gray-700">Анкетирование:</span>
                {{ app.pps_profile?.anti_corruption_survey_results || 'не заполнено' }}
              </div>
              <div class="line-clamp-2">
                <span class="font-medium text-gray-700">Взыскания:</span>
                {{ app.pps_profile?.disciplinary_actions_info || 'не заполнено' }}
              </div>
            </template>
            <template v-else>
              <div>
                <span class="font-medium text-gray-700">Юридическая проверка:</span>
                {{ stageLabel('compliance', app.compliance_status) }}
              </div>
              <div v-if="app.compliance_comment" class="line-clamp-2">
                <span class="font-medium text-gray-700">Комментарий:</span>
                {{ app.compliance_comment }}
              </div>
            </template>
          </div>

          <div class="mt-4 flex items-center justify-between text-sm">
            <span class="text-gray-400">ID заявки: {{ app.id }}</span>
            <span class="font-semibold text-[#005eb8] transition group-hover:translate-x-0.5">Подробнее</span>
          </div>
        </router-link>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import { stageLabel } from '../utils/applicationStages';
import { vacancyTypeLabel } from '../utils/vacancyTypes';

const applications = ref([]);
const loading = ref(true);

const complianceReady = (application) => Boolean(
  application?.vacancy?.type === 'pps'
    ? application?.pps_profile?.anti_corruption_survey_results
      || application?.pps_profile?.disciplinary_actions_info
    : ['clear', 'flagged'].includes(application?.compliance_status),
);

const complianceStatusLabel = (application) => {
  if (application?.vacancy?.type === 'pps') {
    return complianceReady(application) ? 'Заполнено' : 'Нужно заполнить';
  }

  return complianceReady(application) ? 'Проверено' : 'Нужно проверить';
};

const fetchApplications = async () => {
  loading.value = true;

  try {
    const response = await axios.get('/api/compliance/applications');
    applications.value = response.data;
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при загрузке заявок.');
  } finally {
    loading.value = false;
  }
};

onMounted(fetchApplications);
</script>
