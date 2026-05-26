<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
          <router-link
            :to="{ name: 'ScienceApplications' }"
            class="inline-flex items-center gap-2 text-sm font-medium text-[#005eb8] hover:underline"
          >
            <span>←</span>
            <span>К очереди науки</span>
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
          :class="scientificWorksReady ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
          class="inline-flex px-3 py-1 rounded-full text-sm font-medium"
        >
          {{ scientificWorksReady ? 'Научные труды заполнены' : 'Научные труды не заполнены' }}
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
          <section class="bg-white rounded-2xl shadow border border-gray-100 p-5 space-y-4">
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

            <div class="space-y-3">
              <div class="text-sm font-semibold text-gray-700">Подтверждающие документы профиля ППС</div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                  v-for="field in ppsSupportingFields"
                  :key="field.key"
                  class="rounded-xl border border-gray-200 px-4 py-3 space-y-2"
                >
                  <div class="text-sm font-medium text-gray-700">{{ field.label }}</div>
                  <div class="text-sm text-gray-500 whitespace-pre-line">{{ ppsValue(field.key) }}</div>

                  <div v-if="ppsDocuments(field.documentsField).length" class="flex flex-wrap gap-2">
                    <a
                      v-for="document in ppsDocuments(field.documentsField)"
                      :key="document.id"
                      :href="document.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-[#005eb8] hover:underline"
                    >
                      {{ document.name }}
                    </a>
                  </div>
                  <div v-else class="text-sm text-gray-400">Документы не прикреплены.</div>
                </div>
              </div>
            </div>
          </section>

          <section class="bg-white rounded-2xl shadow border border-gray-100 p-5 space-y-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-[#005eb8]">Научные труды преподавателя</h2>
                <p class="text-sm text-gray-500">Этот блок редактирует директор Департамента науки.</p>
              </div>
              <button
                :disabled="saving"
                @click="saveScientificWorks"
                class="inline-flex items-center justify-center bg-[#005eb8] hover:bg-blue-700 disabled:bg-gray-300 text-white text-sm font-semibold px-4 py-2 rounded-lg transition"
              >
                {{ saving ? 'Сохранение...' : 'Сохранить научные труды' }}
              </button>
            </div>

            <label class="space-y-2 block">
              <span class="text-sm font-medium text-gray-700">Описание / список научных трудов</span>
              <textarea
                v-model="scientificWorksDraft"
                rows="8"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                placeholder="Укажите публикации, статьи, монографии, индексируемые работы и другие научные труды"
              ></textarea>
            </label>

            <div class="rounded-xl border border-dashed border-gray-300 bg-slate-50 px-4 py-4 space-y-3">
              <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                  <div class="text-sm font-medium text-gray-700">Подтверждающие документы</div>
                  <div class="text-sm text-gray-500">Можно добавлять несколько файлов и удалять их по одному.</div>
                </div>

                <input
                  type="file"
                  multiple
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  class="block text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#005eb8] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                  @change="setScientificWorksFiles"
                />
              </div>

              <div v-if="pendingFiles.length" class="flex flex-wrap gap-2">
                <span
                  v-for="(file, index) in pendingFiles"
                  :key="`${file.name}-${file.size}-${index}`"
                  class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                >
                  <span class="max-w-[220px] truncate">{{ file.name }}</span>
                  <button
                    type="button"
                    class="font-semibold text-amber-700 hover:text-red-600"
                    @click="removePendingFile(index)"
                  >
                    x
                  </button>
                </span>
              </div>
            </div>

            <div class="space-y-3">
              <div class="text-sm font-semibold text-gray-700">Текущие документы</div>
              <div v-if="scientificWorksDocuments.length" class="space-y-2">
                <div
                  v-for="document in scientificWorksDocuments"
                  :key="document.id"
                  class="flex flex-col gap-2 rounded-xl border border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <a
                    :href="document.url"
                    target="_blank"
                    rel="noopener"
                    class="text-sm font-medium text-[#005eb8] hover:underline break-all"
                  >
                    {{ document.name }}
                  </a>
                  <button
                    @click="deleteScientificWorksDocument(document.id)"
                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                  >
                    Удалить
                  </button>
                </div>
              </div>
              <div v-else class="text-sm text-gray-500">Документы по научным трудам не прикреплены.</div>
            </div>
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
import { stageLabel } from '../utils/applicationStages';

const route = useRoute();

const application = ref(null);
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref('');
const scientificWorksDraft = ref('');
const pendingFiles = ref([]);

const ppsInfoFields = [
  { key: 'full_name', label: 'ФИО' },
  { key: 'desired_position', label: 'Претендуемая должность' },
  { key: 'birth_year', label: 'Год рождения' },
  { key: 'work_experience', label: 'Стаж работы' },
];

const ppsSupportingFields = [
  { key: 'basic_education', label: 'Базовое образование', documentsField: 'basic_education_documents' },
  { key: 'magistracy', label: 'Магистратура', documentsField: 'magistracy_documents' },
  { key: 'scientific_degree', label: 'Ученая степень', documentsField: 'scientific_degree_documents' },
  { key: 'academic_title', label: 'Ученое звание', documentsField: 'academic_title_documents' },
];

const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательные письма',
  scientific_works: 'Научные труды кандидата',
  other: 'Другое',
  articles: 'Научные труды кандидата',
};

const scientificWorksDocuments = computed(() => application.value?.pps_profile?.scientific_works_documents || []);

const scientificWorksReady = computed(() => Boolean(
  application.value?.pps_profile?.scientific_works || scientificWorksDocuments.value.length,
));

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
const ppsDocuments = (key) => application.value?.pps_profile?.[key] || [];

const syncDraft = () => {
  scientificWorksDraft.value = application.value?.pps_profile?.scientific_works || '';
  pendingFiles.value = [];
};

const fetchApplication = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get(`/api/science/applications/${route.params.id}`);
    application.value = response.data;
    syncDraft();
  } catch (error) {
    application.value = null;
    errorMessage.value = error?.response?.data?.message || 'Ошибка при загрузке заявки.';
  } finally {
    loading.value = false;
  }
};

const setScientificWorksFiles = (event) => {
  const files = Array.from(event.target.files || []);
  if (!files.length) {
    return;
  }

  pendingFiles.value = [
    ...pendingFiles.value,
    ...files,
  ];

  event.target.value = '';
};

const removePendingFile = (index) => {
  pendingFiles.value = pendingFiles.value.filter((_, fileIndex) => fileIndex !== index);
};

const saveScientificWorks = async () => {
  if (!application.value) return;

  saving.value = true;

  try {
    const formData = new FormData();
    formData.append('scientific_works', scientificWorksDraft.value || '');

    pendingFiles.value.forEach((file) => {
      formData.append('documents[]', file);
    });

    await axios.post(`/api/science/applications/${application.value.id}/scientific-works`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    await fetchApplication();
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при сохранении научных трудов.');
  } finally {
    saving.value = false;
  }
};

const deleteScientificWorksDocument = async (documentId) => {
  if (!application.value) return;
  if (!confirm('Удалить документ научных трудов?')) return;

  try {
    await axios.delete(`/api/science/applications/${application.value.id}/scientific-works-documents/${documentId}`);
    await fetchApplication();
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при удалении документа научных трудов.');
  }
};

onMounted(fetchApplication);
</script>
