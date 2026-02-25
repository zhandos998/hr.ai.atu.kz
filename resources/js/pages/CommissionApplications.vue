<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8] text-center">Голосование комиссии</h1>

      <div v-if="loading" class="text-gray-500 text-center">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-gray-600 text-center">
        Нет заявок для голосования.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="app in applications"
          :key="app.id"
          class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-3"
        >
          <div>
            <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy?.title || 'Заявка' }}</h2>
            <p class="text-sm text-gray-700">Кандидат: {{ app.user?.name }} ({{ app.user?.email }})</p>
            <p class="text-sm text-gray-700">Телефон: {{ app.user?.phone || 'Не указан' }}</p>
          </div>

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
                v-for="item in orderedDocs(app.documents_map)"
                :key="item.type"
                :href="item.doc.url"
                target="_blank"
                rel="noopener"
                :download="`${item.type}-${app.id}`"
                :class="`inline-flex items-center whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition border ${docTypeClass(item.base)}`"
              >
                {{ docLabel(item.type) }}
              </a>
            </template>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
            <div class="bg-blue-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Всего членов</div>
              <div class="font-semibold">{{ app.vote_summary?.total_members || 0 }}</div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">За</div>
              <div class="font-semibold text-emerald-700">{{ app.vote_summary?.hire || 0 }}</div>
            </div>
            <div class="bg-red-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Против</div>
              <div class="font-semibold text-red-700">{{ app.vote_summary?.reject || 0 }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Проголосовали</div>
              <div class="font-semibold">{{ app.vote_summary?.voted || 0 }}</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Ожидается</div>
              <div class="font-semibold">{{ app.vote_summary?.pending || 0 }}</div>
            </div>
          </div>

          <div class="text-sm">
            Итог голосования:
            <span
              v-if="app.vote_summary?.result === 'approved'"
              class="font-semibold text-emerald-700"
            >
              Взять на работу
            </span>
            <span
              v-else-if="app.vote_summary?.result === 'rejected'"
              class="font-semibold text-red-700"
            >
              Не брать
            </span>
            <span v-else class="font-semibold text-gray-700">Ожидание голосов</span>
          </div>

          <div class="border border-gray-200 rounded-lg p-3 space-y-2">
            <div class="text-sm font-semibold text-gray-800">Ваш голос</div>
            <div v-if="app.vote_summary?.my_vote" class="text-sm text-gray-700">
              Текущий выбор:
              <span class="font-semibold">{{ app.vote_summary.my_vote.decision === 'hire' ? 'За' : 'Против' }}</span>
            </div>
            <textarea
              v-model="comments[app.id]"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              placeholder="Комментарий (необязательно)"
              rows="2"
            />
            <div class="flex flex-wrap gap-2">
              <button
                @click="vote(app.id, 'hire')"
                class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
              >
                Голосовать: За
              </button>
              <button
                @click="vote(app.id, 'reject')"
                class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
              >
                Голосовать: Против
              </button>
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

const applications = ref([]);
const comments = ref({});
const loading = ref(true);

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';
const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательное письмо',
  scientific_works: 'Список научных трудов',
  articles: 'Список научных трудов',
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

const fetchQueue = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/commission/applications');
    applications.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const vote = async (applicationId, decision) => {
  try {
    await axios.post(`/api/commission/applications/${applicationId}/vote`, {
      decision,
      comment: comments.value[applicationId] || null,
    });
    await fetchQueue();
  } catch (error) {
    alert(errorText(error));
  }
};

onMounted(fetchQueue);
</script>

