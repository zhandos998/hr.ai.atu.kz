<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4">
      <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Проверка заявок юристом</h1>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-center text-gray-600">
        Заявок с принятыми документами нет.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="app in applications"
          :key="app.id"
          class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4"
        >
          <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy?.title || 'Заявка' }}</h2>
              <p class="text-sm text-gray-700">Кандидат: {{ app.user?.name }} ({{ app.user?.email }})</p>
              <p class="text-sm text-gray-700">Телефон: {{ app.user?.phone || 'Не указан' }}</p>
              <p class="text-sm text-gray-500">Дата подачи: {{ formatDate(app.created_at) }}</p>
            </div>

            <div
              :class="stageClass('compliance', app.compliance_status) + ' inline-flex px-3 py-1 rounded-full text-sm font-medium'"
            >
              {{ stageLabel('compliance', app.compliance_status) }}
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <a
              v-if="app.resume_url"
              :href="app.resume_url"
              target="_blank"
              rel="noopener"
              class="border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white px-3 py-1 rounded transition"
            >
              Резюме
            </a>

            <template v-if="app.documents_map && Object.keys(app.documents_map).length">
              <a
                v-for="item in orderedDocs(app.documents_map)"
                :key="item.type"
                :href="item.doc.url"
                target="_blank"
                rel="noopener"
                :class="`px-3 py-1 rounded transition border ${docTypeClass(item.base)}`"
                :download="`${item.type}-${app.id}`"
              >
                {{ docLabel(item.type) }}
              </a>
            </template>
          </div>

          <div class="rounded-xl border border-gray-200 bg-slate-50 p-4 space-y-3">
            <div class="text-sm font-semibold text-gray-800">Комментарий юриста</div>
            <textarea
              v-model="complianceComments[app.id]"
              rows="4"
              placeholder="Укажите комментарий к юридической проверке"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
            />

            <div class="flex flex-wrap gap-2">
              <button
                @click="setStatus(app.id, 'clear')"
                :disabled="Boolean(savingByAppId[app.id])"
                class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-semibold py-2 px-3 rounded-lg transition"
              >
                {{ savingByAppId[app.id] ? 'Сохранение...' : 'Не выявлена' }}
              </button>
              <button
                @click="setStatus(app.id, 'flagged')"
                :disabled="Boolean(savingByAppId[app.id])"
                class="bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white font-semibold py-2 px-3 rounded-lg transition"
              >
                {{ savingByAppId[app.id] ? 'Сохранение...' : 'Выявлена' }}
              </button>
            </div>

            <div v-if="app.compliance_comment" class="text-xs text-gray-500">
              Последний комментарий:
              <span class="text-gray-700">{{ app.compliance_comment }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import { stageClass, stageLabel } from '../utils/applicationStages';

const applications = ref([]);
const loading = ref(true);
const savingByAppId = ref({});
const complianceComments = ref({});

const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательное письмо',
  scientific_works: 'Список научных трудов',
  other: 'Другое',
  articles: 'Список научных трудов',
};

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

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
  if (['diploma', 'recommendation_letter', 'scientific_works', 'other'].includes(normalizedBase)) {
    return `${base} #${parsed.index || 1}`;
  }
  return parsed.index ? `${base} #${parsed.index}` : base;
};

const fetchQueue = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/lawyer/applications');
    applications.value = response.data;
    complianceComments.value = Object.fromEntries(
      applications.value.map((app) => [app.id, app.compliance_comment || '']),
    );
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const setStatus = async (id, statusCode) => {
  savingByAppId.value[id] = true;
  try {
    await axios.put(`/api/lawyer/applications/${id}/corruption-status`, {
      status_code: statusCode,
      comment: complianceComments.value[id]?.trim() || null,
    });
    await fetchQueue();
  } catch (error) {
    alert(errorText(error));
  } finally {
    savingByAppId.value[id] = false;
  }
};

const formatDate = (value) => new Date(value).toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });

onMounted(fetchQueue);
</script>
