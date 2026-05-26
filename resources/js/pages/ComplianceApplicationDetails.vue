<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
          <router-link
            :to="{ name: 'ComplianceApplications' }"
            class="inline-flex items-center gap-2 text-sm font-medium text-[#005eb8] hover:underline"
          >
            <span>←</span>
            <span>К очереди права и комплаенса</span>
          </router-link>
          <div>
            <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Заявка №{{ application?.id || route.params.id }}</h1>
            <p v-if="application" class="text-sm text-gray-500">
              {{ application?.vacancy?.title || 'Без названия' }} • {{ application?.user?.name || 'Кандидат не указан' }}
            </p>
          </div>
        </div>

        <div
          v-if="application"
          :class="pageReady ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
          class="inline-flex px-3 py-1 rounded-full text-sm font-medium"
        >
          {{ pageReadyLabel }}
        </div>
      </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="!application" class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600">
        {{ errorMessage || 'Заявка не найдена.' }}
      </div>

      <div v-else class="grid grid-cols-1 xl:grid-cols-[320px,1fr] gap-5">
        <div class="bg-white rounded-2xl shadow border border-gray-100 p-5 space-y-4">
          <ApplicationVacancySummary :application="application" />

          <div>
            <h2 class="text-lg font-semibold text-[#005eb8]">Кандидат</h2>
            <p class="text-sm text-gray-700">{{ application.user?.name }}</p>
            <p class="text-sm text-gray-600">{{ application.user?.email }}</p>
            <p class="text-sm text-gray-600">Телефон: {{ application.user?.phone || 'Не указан' }}</p>
            <p class="text-sm text-gray-500 mt-2">Дата подачи: {{ formatDate(application.created_at) }}</p>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl bg-slate-50 px-3 py-2">
              <div class="text-gray-400">Резюме</div>
              <div class="mt-1 font-medium text-gray-700">{{ stageLabel('resume', application.resume_status) }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2">
              <div class="text-gray-400">Документы</div>
              <div class="mt-1 font-medium text-gray-700">{{ stageLabel('documents', application.documents_status) }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2">
              <div class="text-gray-400">Проверка</div>
              <div class="mt-1 font-medium text-gray-700">{{ stageLabel('compliance', application.compliance_status) }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2">
              <div class="text-gray-400">Найм</div>
              <div class="mt-1 font-medium text-gray-700">{{ stageLabel('hiring', application.hiring_status, application) }}</div>
            </div>
          </div>

          <div class="space-y-2">
            <div class="text-sm font-semibold text-gray-700">Файлы кандидата</div>
            <a
              v-if="application.resume_url"
              :href="application.resume_url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition"
            >
              Открыть резюме
            </a>

            <div v-if="orderedDocuments.length" class="flex flex-wrap gap-2">
              <a
                v-for="item in orderedDocuments"
                :key="item.type"
                :href="item.doc.url"
                target="_blank"
                rel="noopener"
                :class="`inline-flex items-center px-3 py-2 rounded-lg text-xs font-medium transition border ${docTypeClass(item.base)}`"
              >
                {{ docLabel(item.type) }}
              </a>
            </div>
            <div v-else class="text-sm text-gray-500">Дополнительные документы не загружены.</div>
          </div>
        </div>

        <div class="space-y-5">
          <section v-if="isPpsApplication" class="bg-white rounded-2xl shadow border border-gray-100 p-5 space-y-4">
            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">Профиль преподавателя</h2>
              <p class="text-sm text-gray-500">Базовые данные, которые заполнил администратор.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="field in ppsInfoFields" :key="field.key" class="rounded-xl bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ field.label }}</div>
                <div class="mt-2 text-sm text-gray-700 whitespace-pre-line">{{ ppsValue(field.key) }}</div>
              </div>
            </div>
          </section>

          <section class="bg-white rounded-2xl shadow border border-gray-100 p-5 space-y-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-[#005eb8]">Юридическая проверка</h2>
                <p class="text-sm text-gray-500">Проверка на коррупционные риски и комментарий юриста.</p>
              </div>
              <div :class="stageClass('compliance', application.compliance_status) + ' inline-flex px-3 py-1 rounded-full text-sm font-medium'">
                {{ stageLabel('compliance', application.compliance_status) }}
              </div>
            </div>

            <label class="space-y-2 block">
              <span class="text-sm font-medium text-gray-700">Комментарий юриста</span>
              <textarea
                v-model="corruptionCommentDraft"
                rows="4"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                placeholder="Укажите комментарий к юридической проверке"
              ></textarea>
            </label>

            <div class="flex flex-wrap gap-2">
              <button
                :disabled="corruptionSaving || !canSetCorruptionStatus"
                @click="setCorruptionStatus('clear')"
                class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-semibold py-2 px-3 rounded-lg transition"
              >
                {{ corruptionSaving ? 'Сохранение...' : 'Не выявлена' }}
              </button>
              <button
                :disabled="corruptionSaving || !canSetCorruptionStatus"
                @click="setCorruptionStatus('flagged')"
                class="bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white font-semibold py-2 px-3 rounded-lg transition"
              >
                {{ corruptionSaving ? 'Сохранение...' : 'Выявлена' }}
              </button>
              <button
                :disabled="!canGenerateLawyerPdf || pdfLoading"
                @click="openLawyerResponsePdf"
                class="bg-slate-700 hover:bg-slate-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold py-2 px-3 rounded-lg transition"
              >
                {{ pdfLoading ? 'Генерация PDF...' : 'Открыть PDF ответа юриста' }}
              </button>
            </div>

            <div v-if="!canSetCorruptionStatus" class="text-xs text-amber-700">
              Юридическая проверка доступна только после принятия документов.
            </div>
            <div v-if="application.compliance_comment" class="text-xs text-gray-500">
              Последний комментарий:
              <span class="text-gray-700">{{ application.compliance_comment }}</span>
            </div>
          </section>

          <section v-if="isPpsApplication" class="bg-white rounded-2xl shadow border border-gray-100 p-5 space-y-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-[#005eb8]">Правовое обеспечение и комплаенс</h2>
                <p class="text-sm text-gray-500">Этот блок редактирует директор Департамента правового обеспечения и комплаенса.</p>
              </div>
              <button
                :disabled="saving"
                @click="saveComplianceDepartment"
                class="inline-flex items-center justify-center bg-[#005eb8] hover:bg-blue-700 disabled:bg-gray-300 text-white text-sm font-semibold px-4 py-2 rounded-lg transition"
              >
                {{ saving ? 'Сохранение...' : 'Сохранить данные' }}
              </button>
            </div>

            <label class="space-y-2 block">
              <span class="text-sm font-medium text-gray-700">Результаты анкетирования по вопросам противодействия коррупции</span>
              <textarea
                v-model="complianceDraft.anti_corruption_survey_results"
                rows="6"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                placeholder="Укажите результаты анкетирования"
              ></textarea>
            </label>

            <label class="space-y-2 block">
              <span class="text-sm font-medium text-gray-700">Сведения о дисциплинарных взысканиях</span>
              <textarea
                v-model="complianceDraft.disciplinary_actions_info"
                rows="6"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                placeholder="Укажите сведения о дисциплинарных взысканиях"
              ></textarea>
            </label>
          </section>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import ApplicationVacancySummary from '../components/ApplicationVacancySummary.vue';
import { stageClass, stageLabel } from '../utils/applicationStages';

const route = useRoute();

const application = ref(null);
const loading = ref(true);
const saving = ref(false);
const corruptionSaving = ref(false);
const pdfLoading = ref(false);
const errorMessage = ref('');
const corruptionCommentDraft = ref('');
const complianceDraft = ref({
  anti_corruption_survey_results: '',
  disciplinary_actions_info: '',
});

const isPpsApplication = computed(() => application.value?.vacancy?.type === 'pps');

const ppsInfoFields = [
  { key: 'full_name', label: 'ФИО' },
  { key: 'desired_position', label: 'Претендуемая должность' },
  { key: 'birth_year', label: 'Год рождения' },
  { key: 'work_experience', label: 'Стаж работы' },
];

const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательные письма',
  scientific_works: 'Научные труды кандидата',
  other: 'Другое',
  articles: 'Научные труды кандидата',
};

const complianceReady = computed(() => Boolean(
  application.value?.pps_profile?.anti_corruption_survey_results
  || application.value?.pps_profile?.disciplinary_actions_info
));
const legalCheckReady = computed(() => ['clear', 'flagged'].includes(application.value?.compliance_status));
const pageReady = computed(() => (isPpsApplication.value ? complianceReady.value : legalCheckReady.value));
const pageReadyLabel = computed(() => {
  if (isPpsApplication.value) {
    return complianceReady.value ? 'Блок комплаенса заполнен' : 'Блок комплаенса не заполнен';
  }

  return legalCheckReady.value ? 'Юридическая проверка завершена' : 'Юридическая проверка не завершена';
});

const canSetCorruptionStatus = computed(() => application.value?.documents_status === 'accepted');
const canGenerateLawyerPdf = computed(() => ['clear', 'flagged'].includes(application.value?.compliance_status));

const orderedDocuments = computed(() => Object.entries(application.value?.documents_map || {})
  .map(([type, doc]) => {
    const parsed = parseDocType(type);
    const base = normalizeBase(parsed.base);
    const index = parsed.index || (docOrder[base] ? 1 : 0);
    return { type, doc, base, index };
  })
  .sort((a, b) => (docOrder[a.base] || 99) - (docOrder[b.base] || 99) || a.index - b.index));

const parseDocType = (type) => {
  const raw = String(type);
  const match = raw.match(/^(.*)_(\d+)$/);
  if (!match) return { base: raw, index: null };
  return { base: match[1], index: Number(match[2]) };
};

const normalizeBase = (type) => {
  const base = String(type).replace(/_\d+$/, '');
  return base === 'articles' ? 'scientific_works' : base;
};

const docOrder = {
  diploma: 1,
  recommendation_letter: 2,
  scientific_works: 3,
  other: 4,
};

const docTypeClass = (base) => {
  if (base === 'diploma') return 'border-sky-600 text-sky-700 hover:bg-sky-600 hover:text-white';
  if (base === 'recommendation_letter') return 'border-amber-600 text-amber-700 hover:bg-amber-600 hover:text-white';
  if (base === 'scientific_works') return 'border-cyan-600 text-cyan-700 hover:bg-cyan-600 hover:text-white';
  return 'border-emerald-600 text-emerald-700 hover:bg-emerald-600 hover:text-white';
};

const docLabel = (type) => {
  const parsed = parseDocType(type);
  const normalizedBase = normalizeBase(parsed.base);
  const base = docLabels[normalizedBase] || parsed.base;
  return ['diploma', 'recommendation_letter', 'scientific_works', 'other'].includes(normalizedBase)
    ? `${base} #${parsed.index || 1}`
    : parsed.index ? `${base} #${parsed.index}` : base;
};

const formatDate = (value) => {
  if (!value) return 'Не указана';

  return new Date(value).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const ppsValue = (key) => application.value?.pps_profile?.[key] || 'Не указано';

const syncDraft = () => {
  corruptionCommentDraft.value = application.value?.compliance_comment || '';
  complianceDraft.value = {
    anti_corruption_survey_results: application.value?.pps_profile?.anti_corruption_survey_results || '',
    disciplinary_actions_info: application.value?.pps_profile?.disciplinary_actions_info || '',
  };
};

const fetchApplication = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get(`/api/compliance/applications/${route.params.id}`);
    application.value = response.data;
    syncDraft();
  } catch (error) {
    application.value = null;
    errorMessage.value = error?.response?.data?.message || 'Ошибка при загрузке заявки.';
  } finally {
    loading.value = false;
  }
};

const setCorruptionStatus = async (statusCode) => {
  if (!application.value) return;

  corruptionSaving.value = true;

  try {
    await axios.put(`/api/lawyer/applications/${application.value.id}/corruption-status`, {
      status_code: statusCode,
      comment: corruptionCommentDraft.value.trim() || null,
    });

    await fetchApplication();
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при сохранении юридической проверки.');
  } finally {
    corruptionSaving.value = false;
  }
};

const saveComplianceDepartment = async () => {
  if (!application.value) return;

  saving.value = true;

  try {
    const formData = new FormData();
    formData.append('anti_corruption_survey_results', complianceDraft.value.anti_corruption_survey_results || '');
    formData.append('disciplinary_actions_info', complianceDraft.value.disciplinary_actions_info || '');

    await axios.post(`/api/compliance/applications/${application.value.id}/department-review`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    await fetchApplication();
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при сохранении данных комплаенса.');
  } finally {
    saving.value = false;
  }
};

const openLawyerResponsePdf = async () => {
  if (!application.value || !canGenerateLawyerPdf.value) {
    alert('PDF ответа юриста доступен только после завершения юридической проверки.');
    return;
  }

  const previewWindow = window.open('', '_blank');
  if (previewWindow) {
    previewWindow.opener = null;
    previewWindow.document.title = 'PDF ответа юриста';
    previewWindow.document.body.innerHTML = '<p style="font-family:sans-serif;padding:16px">Подготовка PDF...</p>';
  }

  pdfLoading.value = true;

  try {
    const response = await axios.get(`/api/compliance/applications/${application.value.id}/lawyer-response-pdf`, {
      responseType: 'blob',
    });

    const blobUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));

    if (previewWindow) {
      previewWindow.location.href = blobUrl;
    } else {
      window.open(blobUrl, '_blank');
    }

    window.setTimeout(() => {
      window.URL.revokeObjectURL(blobUrl);
    }, 60_000);
  } catch (error) {
    if (previewWindow) {
      previewWindow.close();
    }

    alert(error?.response?.data?.message || 'Ошибка при генерации PDF ответа юриста.');
  } finally {
    pdfLoading.value = false;
  }
};

onMounted(fetchApplication);
</script>
