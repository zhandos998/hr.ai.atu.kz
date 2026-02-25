<template>
  <div class="max-w-7xl mx-auto mt-12 bg-white shadow-lg rounded-xl p-6 border border-gray-100">
    <h2 class="text-xl font-semibold mb-4 text-[#005eb8] text-center">Мои заявки</h2>

    <div v-if="loading" class="text-center text-gray-500">Загрузка заявок...</div>
    <div v-else-if="applications.length === 0" class="text-center text-gray-600">У вас пока нет заявок.</div>

    <div v-else class="space-y-4">
      <div
        v-for="app in applications"
        :key="app.id"
        class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-3"
      >
        <div>
          <h3 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy.title }}</h3>
          <p class="text-gray-700 mb-2">{{ app.vacancy.description }}</p>
          <p class="text-sm text-gray-500">Тип: {{ app.vacancy.type === 'staff' ? 'Сотрудники' : 'ППС' }}</p>
          <p class="text-sm text-gray-500">Дата подачи: {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}</p>
        </div>

        <div :class="statusClasses(app.status.code) + ' px-3 py-1 rounded-full text-sm text-center w-max'">
          {{ statusText(app.status.code) }}
        </div>

        <div class="flex flex-wrap gap-2">
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
            <span
              v-for="item in orderedDocs(app.documents_map)"
              :key="item.type"
              class="inline-flex items-center gap-1"
            >
              <a
                :href="item.doc.url"
                target="_blank"
                rel="noopener"
                :class="`inline-flex items-center whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition ${docTypeClass(item.base)}`"
                :download="`${item.type}-${app.id}`"
              >
                {{ docLabel(item.type) }}
              </a>
              <button
                v-if="canManageDocs(app) && item.doc.id"
                @click="requestDeleteDocument(app.id, item.doc.id)"
                class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-red-300 text-red-600 hover:bg-red-50 transition"
                title="Удалить документ"
              >
                ×
              </button>
            </span>
          </template>
          <span v-else class="text-xs text-gray-400">Документы не загружены</span>
        </div>

        <template v-if="['resume_accepted', 'docs_rejected'].includes(app.status.code)">
          <button
            @click="openUploadModal(app)"
            class="inline-flex items-center bg-[#005eb8] hover:bg-blue-700 text-white text-center font-semibold px-3 py-2 rounded-lg text-sm transition"
          >
            Загрузить документы
          </button>
        </template>
        <template v-if="canUpdateDocs(app)">
          <button
            @click="openUploadModal(app, 'replace')"
            class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white text-center font-semibold px-3 py-2 rounded-lg text-sm transition"
          >
            Дополнить документы
          </button>
        </template>

        <UploadDocsModal
          v-if="showUploadModal"
          :application="selectedApplication"
          :mode="modalMode"
          @close="closeUploadModal"
          @saved="() => closeUploadModal(true)"
        />
      </div>
    </div>
  </div>

  <AppMessageModal
    v-model="confirmDeleteOpen"
    title="Подтверждение"
    message="Удалить этот документ?"
    :show-cancel="true"
    confirm-text="Удалить"
    cancel-text="Отмена"
    @confirm="performDeleteDocument"
  />

  <AppMessageModal
    v-model="errorModalOpen"
    title="Ошибка"
    :message="errorModalMessage"
    confirm-text="Понятно"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import UploadDocsModal from '../components/UploadDocsModal.vue';
import AppMessageModal from '../components/AppMessageModal.vue';
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

const canUpdateDocs = (app) => ['docs_rejected', 'docs_uploaded'].includes(app.status.code);
const canManageDocs = (app) => ['resume_accepted', 'docs_rejected', 'docs_uploaded'].includes(app.status.code);

const showUploadModal = ref(false);
const selectedApplication = ref(null);
const modalMode = ref('create');
const confirmDeleteOpen = ref(false);
const deletePayload = ref({ applicationId: null, documentId: null });
const errorModalOpen = ref(false);
const errorModalMessage = ref('');

const openUploadModal = (app, mode = 'create') => {
  selectedApplication.value = app;
  modalMode.value = mode;
  showUploadModal.value = true;
};

const closeUploadModal = (refresh = false) => {
  showUploadModal.value = false;
  selectedApplication.value = null;
  if (refresh) fetchApplications();
};

const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательное письмо',
  scientific_works: 'Список научных трудов',
  articles: 'Список научных трудов',
};

const requestDeleteDocument = (applicationId, documentId) => {
  deletePayload.value = { applicationId, documentId };
  confirmDeleteOpen.value = true;
};

const performDeleteDocument = async () => {
  const { applicationId, documentId } = deletePayload.value;
  if (!applicationId || !documentId) return;
  try {
    await axios.delete(`/api/applications/${applicationId}/documents/${documentId}`);
    await fetchApplications();
  } catch (error) {
    errorModalMessage.value = error?.response?.data?.message || 'Не удалось удалить документ.';
    errorModalOpen.value = true;
  } finally {
    deletePayload.value = { applicationId: null, documentId: null };
  }
};
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
};
const orderedDocs = (documentsMap) => {
  return Object.entries(documentsMap || {})
    .map(([type, doc]) => {
      const parsed = parseDocType(type);
      const base = normalizeBase(parsed.base);
      const index = parsed.index || (docOrder[base] ? 1 : 0);
      return { type, doc, base, index };
    })
    .sort((a, b) => (docOrder[a.base] || 99) - (docOrder[b.base] || 99) || a.index - b.index);
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
  if (['diploma', 'recommendation_letter', 'scientific_works'].includes(normalizedBase)) {
    return `${base} #${parsed.index || 1}`;
  }
  return parsed.index ? `${base} #${parsed.index}` : base;
};

const statusText = (code) => {
  switch (code) {
    case 'pending':
      return 'Ваше резюме на рассмотрении';
    case 'resume_rejected':
      return 'Резюме отклонено';
    case 'resume_accepted':
      return 'Резюме принято, загрузите документы';
    case 'docs_uploaded':
      return 'Документы загружены, ожидают проверки';
    case 'docs_rejected':
      return 'Документы отклонены, загрузите заново';
    case 'docs_accepted':
      return 'Документы приняты';
    case 'corruption_not_found':
      return 'Коррупция: не выявлена';
    case 'corruption_found':
      return 'Коррупция: выявлена';
    case 'completed':
      return 'Вы приняты на вакансию';
    case 'not_accepted':
      return 'Не приняты на вакансию';
    default:
      return code;
  }
};

const statusClasses = (code) => {
  switch (code) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'resume_rejected':
    case 'docs_rejected':
      return 'bg-red-100 text-red-800';
    case 'resume_accepted':
    case 'docs_uploaded':
      return 'bg-blue-100 text-blue-800';
    case 'docs_accepted':
      return 'bg-green-100 text-green-800';
    case 'corruption_not_found':
      return 'bg-emerald-100 text-emerald-800';
    case 'corruption_found':
      return 'bg-red-100 text-red-800';
    case 'completed':
      return 'bg-green-200 text-green-900 font-semibold';
    case 'not_accepted':
      return 'bg-red-200 text-red-900 font-semibold';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

onMounted(() => {
  fetchApplications();
});
</script>


