<template>
  <div
    v-if="teacherName"
    class="space-y-3 border-t border-gray-100 pt-3 text-sm text-gray-800"
  >
    <div class="flex items-center justify-between gap-3">
      <div class="min-w-0">
        <div class="font-semibold text-[#005eb8]">КМК аудит</div>
        <div class="truncate text-xs text-gray-500">{{ teacherName }}</div>
      </div>
      <button
        type="button"
        class="inline-flex items-center whitespace-nowrap rounded-lg border border-[#005eb8] px-3 py-1.5 text-xs font-medium text-[#005eb8] transition hover:bg-[#005eb8] hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="loading"
        @click="toggleAudit"
      >
        {{ expanded ? 'Скрыть' : 'Раскрыть' }}
      </button>
    </div>

    <div v-if="expanded" class="space-y-4 rounded-xl border border-gray-100 bg-slate-50 p-4">
      <p v-if="loading" class="text-xs text-gray-500">Загрузка...</p>
      <p v-else-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>
      <p v-else-if="!teacher" class="text-xs text-gray-500">Нет данных</p>

      <template v-else>
        <div class="space-y-1">
          <div class="font-semibold leading-tight text-gray-900">{{ teacher.teacher_name || teacherName }}</div>
          <div class="text-gray-600">Кафедра: {{ teacher.department || 'Не указана' }}</div>
          <div class="text-gray-600">Факультет: {{ teacher.faculty || 'Не указан' }}</div>
        </div>

        <div v-if="teacherViolations.length" class="space-y-1">
          <div class="font-semibold text-gray-700">Нарушения</div>
          <ul class="space-y-2">
            <li
              v-for="(violation, index) in teacherViolations"
              :key="`${teacher.teacher_id || teacher.teacher_name}-violation-${index}`"
              class="rounded-lg border border-red-100 bg-red-50 p-3"
            >
              <div v-if="violation.date" class="text-xs text-gray-500">{{ formatDateTime(violation.date) }}</div>
              <div class="mt-1 whitespace-pre-line leading-tight text-gray-800">{{ violationText(violation) }}</div>
              <span
                v-if="violation.status"
                class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800"
              >
                Статус: {{ violation.status }}
              </span>
            </li>
          </ul>
        </div>
        <div v-else class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
          Нарушений нет
        </div>

        <div v-if="teacherAudits.length">
          <div class="font-semibold text-gray-700">Проверки</div>
          <ul class="mt-2 space-y-2">
            <li
              v-for="audit in teacherAudits"
              :key="audit.id || `${audit.date}-${audit.score}`"
              class="rounded-lg border border-gray-100 bg-white px-3 py-2 leading-tight"
            >
              <div class="text-gray-600">{{ formatDateTime(audit.date) }}</div>
              <div class="mt-1 text-gray-700">{{ audit.type || 'Тип не указан' }}</div>
              <div class="mt-1 font-semibold text-[#005eb8]">Оценка: {{ formatScore(audit.score) }}</div>
            </li>
          </ul>
        </div>

        <a
          v-if="teacher.dossier_url"
          :href="teacher.dossier_url"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center rounded-lg bg-[#005eb8] px-3 py-2 text-xs font-medium text-white transition hover:bg-[#004a91]"
        >
          Открыть досье
        </a>
      </template>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  application: {
    type: Object,
    default: null,
  },
  fullName: {
    type: String,
    default: '',
  },
});

const teacherName = computed(() => String(
  props.fullName
    || props.application?.pps_profile?.full_name
    || props.application?.user?.name
    || ''
).trim());

const expanded = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const teacher = ref(null);

const teacherViolations = computed(() => Array.isArray(teacher.value?.violations)
  ? teacher.value.violations
  : []);
const teacherAudits = computed(() => Array.isArray(teacher.value?.audits)
  ? teacher.value.audits
  : []);

const resetAudit = () => {
  expanded.value = false;
  loading.value = false;
  errorMessage.value = '';
  teacher.value = null;
};

const toggleAudit = async () => {
  if (expanded.value) {
    resetAudit();
    return;
  }

  expanded.value = true;
  await loadAudit();
};

const loadAudit = async () => {
  if (!teacherName.value) {
    return;
  }

  loading.value = true;
  errorMessage.value = '';
  teacher.value = null;

  try {
    const response = await axios.get('/api/teacher-audit', {
      params: {
        name: teacherName.value,
      },
    });

    const payload = response.data || {};
    const records = Array.isArray(payload.data) ? payload.data : [];

    if (!payload.success || payload.matches_found === 0 || !records.length) {
      teacher.value = null;
      return;
    }

    teacher.value = records[0];
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Ошибка запроса';
  } finally {
    loading.value = false;
  }
};

watch(teacherName, resetAudit);

const parseDate = (value) => {
  const date = new Date(value);

  return Number.isNaN(date.getTime()) ? null : date;
};

const formatDateTime = (value) => {
  const date = parseDate(value);

  if (!date) {
    return value || '-';
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

const formatScore = (score) => {
  if (score === null || score === undefined || score === '') {
    return '-';
  }

  const numericScore = Number(score);

  if (Number.isNaN(numericScore)) {
    return String(score);
  }

  return numericScore.toFixed(2).replace(/\.00$/, '');
};

const violationText = (violation) => {
  if (typeof violation === 'string') {
    return violation;
  }

  return violation?.observation_text
    || violation?.comment
    || violation?.description
    || violation?.title
    || violation?.name
    || 'Нарушение';
};
</script>
