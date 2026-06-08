<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Академическое развитие ППС</h1>
          <p class="text-sm text-gray-500">Очередь директора Департамента академического развития по преподавательским заявкам.</p>
        </div>
        <div class="text-sm text-gray-500">
          Всего заявок: <span class="font-semibold text-gray-700">{{ applications.length }}</span>
        </div>
      </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-center text-gray-600">Заявок ППС для академического развития пока нет.</div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <router-link
          v-for="app in applications"
          :key="app.id"
          :to="{ name: 'AcademicApplicationDetails', params: { id: app.id } }"
          class="group block rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-xs font-semibold uppercase tracking-wide text-[#005eb8]">ППС</div>
              <h2 class="mt-1 truncate text-lg font-semibold text-gray-900">{{ app.vacancy?.title || 'Без названия' }}</h2>
              <p class="mt-1 truncate text-sm text-gray-700">{{ app.user?.name || 'Кандидат не указан' }}</p>
              <p class="truncate text-xs text-gray-400">{{ app.user?.email || 'Email не указан' }}</p>
            </div>

            <div
              :class="academicReady(app) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
              class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium text-center"
            >
              {{ academicReady(app) ? 'Заполнено' : 'Нужно заполнить' }}
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
            <div class="line-clamp-2">
              <span class="font-medium text-gray-700">Открытое занятие:</span>
              {{ app.pps_profile?.open_lesson_quality || 'не заполнено' }}
            </div>
            <div class="line-clamp-2">
              <span class="font-medium text-gray-700">Дисциплины:</span>
              {{ app.pps_profile?.taught_disciplines || 'не заполнено' }}
            </div>
            <div class="line-clamp-2">
              <span class="font-medium text-gray-700">Учебно-методическая литература:</span>
              {{ app.pps_profile?.educational_methodical_literature || 'не заполнено' }}
            </div>
            <div class="line-clamp-2">
              <span class="font-medium text-gray-700">Индивидуальный план:</span>
              {{ app.pps_profile?.individual_plan_nonfulfillment || 'не заполнено' }}
            </div>
            <div class="line-clamp-2">
              <span class="font-medium text-gray-700">Заключение:</span>
              {{ app.pps_profile?.academic_conclusion || 'не заполнено' }}
            </div>
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

const applications = ref([]);
const loading = ref(true);

const academicReady = (application) => Boolean(
  application?.pps_profile?.open_lesson_quality
  || application?.pps_profile?.taught_disciplines
  || application?.pps_profile?.educational_methodical_literature
  || application?.pps_profile?.individual_plan_nonfulfillment
  || application?.pps_profile?.academic_conclusion,
);

const fetchApplications = async () => {
  loading.value = true;

  try {
    const response = await axios.get('/api/academic/applications');
    applications.value = response.data;
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при загрузке заявок.');
  } finally {
    loading.value = false;
  }
};

onMounted(fetchApplications);
</script>
