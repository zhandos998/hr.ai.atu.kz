<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8] text-center">Голосование комиссии</h1>

      <div v-if="loading" class="text-gray-500 text-center">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-gray-600 text-center">
        Нет заявок для голосования.
      </div>

      <div v-else class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex flex-wrap items-center gap-3">
            <button
              v-for="type in applicationTypeTabs"
              :key="type.value"
              :class="activeVacancyType === type.value
                ? 'bg-[#005eb8] text-white border-[#005eb8]'
                : 'bg-white text-[#005eb8] border-[#005eb8] hover:bg-[#005eb8] hover:text-white'"
              class="inline-flex items-center gap-2 border rounded-xl px-4 py-2 text-sm font-semibold transition"
              @click="activeVacancyType = type.value"
            >
              <span>{{ type.label }}</span>
              <span
                :class="activeVacancyType === type.value ? 'bg-white/20 text-white' : 'bg-blue-50 text-[#005eb8]'"
                class="inline-flex min-w-7 justify-center rounded-full px-2 py-0.5 text-xs"
              >
                {{ type.count }}
              </span>
            </button>
          </div>

          <label class="relative block w-full lg:max-w-md">
            <span class="sr-only">Поиск заявок</span>
            <input
              v-model="searchQuery"
              type="search"
              class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 pr-10 text-sm text-gray-800 shadow-sm outline-none transition focus:border-[#005eb8] focus:ring-2 focus:ring-blue-100"
              placeholder="Поиск по кандидату, должности, кафедре"
            >
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
              aria-label="Очистить поиск"
              @click="searchQuery = ''"
            >
              ×
            </button>
          </label>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-3 shadow-sm">
          <div class="mb-2 text-xs font-semibold uppercase text-gray-500">Дата голосования</div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-lg font-semibold text-gray-500 shadow-sm transition hover:border-[#005eb8] hover:text-[#005eb8]"
              aria-label="Прокрутить даты влево"
              @click="scrollVotingDates('left')"
            >
              ‹
            </button>
            <div class="relative min-w-0 flex-1">
              <div
                ref="votingDateScroller"
                class="scrollbar-none overflow-x-auto scroll-smooth"
              >
                <div class="flex min-w-max gap-2">
                  <button
                    type="button"
                    :class="activeVotingDate === 'all'
                      ? 'bg-[#005eb8] text-white border-[#005eb8]'
                      : 'bg-white text-gray-700 border-gray-300 hover:border-[#005eb8] hover:text-[#005eb8]'"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                    @click="activeVotingDate = 'all'"
                  >
                    <span>Все</span>
                    <span
                      :class="activeVotingDate === 'all' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'"
                      class="inline-flex min-w-7 justify-center rounded-full px-2 py-0.5 text-xs"
                    >
                      {{ selectedTypeApplications.length }}
                    </span>
                  </button>

                  <button
                    v-for="date in votingDateTabs"
                    :key="date.value"
                    type="button"
                    :class="activeVotingDate === date.value
                      ? 'bg-[#005eb8] text-white border-[#005eb8]'
                      : 'bg-white text-gray-700 border-gray-300 hover:border-[#005eb8] hover:text-[#005eb8]'"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                    @click="activeVotingDate = date.value"
                  >
                    <span>{{ date.label }}</span>
                    <span
                      :class="activeVotingDate === date.value ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'"
                      class="inline-flex min-w-7 justify-center rounded-full px-2 py-0.5 text-xs"
                    >
                      {{ date.count }}
                    </span>
                  </button>
                </div>
              </div>
            </div>
            <button
              type="button"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-lg font-semibold text-gray-500 shadow-sm transition hover:border-[#005eb8] hover:text-[#005eb8]"
              aria-label="Прокрутить даты вправо"
              @click="scrollVotingDates('right')"
            >
              ›
            </button>
          </div>
        </div>

        <div
          v-if="filteredApplications.length === 0"
          class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
        >
          {{ emptyStateText }}
        </div>

        <template v-else>
          <div
            v-for="(app, index) in filteredApplications"
            :key="app.id"
            class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-3"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy?.title || 'Заявка' }}</h2>
                <p class="text-sm text-gray-500">Тип вакансии: {{ vacancyTypeLabel(app?.vacancy?.type) }}</p>
                <p class="text-sm text-gray-700">Кандидат: {{ app.user?.name }} ({{ app.user?.email }})</p>
                <p class="text-sm text-gray-700">Телефон: {{ app.user?.phone || 'Не указан' }}</p>
                <p class="text-sm text-gray-500">Дата голосования: {{ formatDate(app.voting_started_at, 'Не назначено') }}</p>
              </div>
              <div class="inline-flex shrink-0 items-center rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-[#005eb8]">
                № {{ votingSequenceLabel(app, index) }}
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <router-link
                :to="detailsRoute(app)"
                class="inline-flex items-center whitespace-nowrap border border-gray-300 text-gray-700 hover:border-[#005eb8] hover:text-[#005eb8] px-3 py-2 rounded-lg text-sm font-medium transition"
              >
                Подробнее
              </router-link>

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

            <div :class="app?.vacancy?.type === 'pps' ? 'md:grid-cols-6' : 'md:grid-cols-5'" class="grid grid-cols-2 gap-2 text-sm">
              <div class="bg-blue-50 rounded-lg p-2 text-center">
                <div class="text-gray-500">Всего членов</div>
                <div class="font-semibold">{{ app.vote_summary?.total_members || 0 }}</div>
              </div>
              <template v-if="app?.vacancy?.type === 'pps'">
                <div class="bg-emerald-50 rounded-lg p-2 text-center">
                  <div class="text-gray-500">На 1 год</div>
                  <div class="font-semibold text-emerald-700">{{ app.vote_summary?.hire_1_year || 0 }}</div>
                </div>
                <div class="bg-sky-50 rounded-lg p-2 text-center">
                  <div class="text-gray-500">На 3 года</div>
                  <div class="font-semibold text-sky-700">{{ app.vote_summary?.hire_3_year || 0 }}</div>
                </div>
              </template>
              <template v-else>
                <div class="bg-emerald-50 rounded-lg p-2 text-center">
                  <div class="text-gray-500">За</div>
                  <div class="font-semibold text-emerald-700">{{ app.vote_summary?.hire || 0 }}</div>
                </div>
              </template>
              <div class="bg-red-50 rounded-lg p-2 text-center">
                <div class="text-gray-500">{{ app?.vacancy?.type === 'pps' ? 'Не брать' : 'Против' }}</div>
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
                :class="commissionVoteResultClass(app.vote_summary?.result, app.vote_summary?.approved_term_years)"
                class="font-semibold"
              >
                {{ commissionVoteResultLabel(app.vote_summary?.result, app.vote_summary?.approved_term_years, app?.vacancy?.type) }}
              </span>
            </div>

            <div class="border border-gray-200 rounded-lg p-3 space-y-2">
              <div class="text-sm font-semibold text-gray-800">Ваш голос</div>
              <div v-if="app.vote_summary?.my_vote" class="text-sm text-gray-700">
                Текущий выбор:
                <span class="font-semibold">
                  {{ commissionVoteDecisionLabel(app.vote_summary.my_vote.decision, app.vote_summary.my_vote.hire_term_years, app?.vacancy?.type) }}
                </span>
              </div>
              <textarea
                v-model="comments[app.id]"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                placeholder="Комментарий (необязательно)"
                rows="2"
              />
              <div class="flex flex-wrap gap-2">
                <template v-if="app?.vacancy?.type === 'pps'">
                  <button
                    @click="vote(app.id, 'hire', 1)"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
                  >
                    Взять на 1 год
                  </button>
                  <button
                    @click="vote(app.id, 'hire', 3)"
                    class="bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
                  >
                    Взять на 3 года
                  </button>
                </template>
                <template v-else>
                  <button
                    @click="vote(app.id, 'hire')"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
                  >
                    Голосовать: За
                  </button>
                </template>
                <button
                  @click="vote(app.id, 'reject')"
                  class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
                >
                  {{ app?.vacancy?.type === 'pps' ? 'Не брать' : 'Голосовать: Против' }}
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import Layout from '../components/Layout.vue';
import {
  vacancyTypeLabel,
  vacancyTypeTabs as vacancyTypeTabValues,
} from '../utils/vacancyTypes';
import {
  voteDecisionLabel as commissionVoteDecisionLabel,
  voteResultClass as commissionVoteResultClass,
  voteResultLabel as commissionVoteResultLabel,
} from '../utils/commissionVotes';

const route = useRoute();
const router = useRouter();

const applications = ref([]);
const comments = ref({});
const loading = ref(true);
const searchQuery = ref('');
const activeVotingDate = ref('all');
const votingDateScroller = ref(null);
const activeVacancyType = ref(
  vacancyTypeTabValues.includes(String(route.query.type))
    ? String(route.query.type)
    : 'pps'
);

const applicationsByVacancyType = computed(() => Object.fromEntries(
  vacancyTypeTabValues.map((type) => [
    type,
    applications.value.filter((app) => app?.vacancy?.type === type),
  ]),
));

const applicationTypeTabs = computed(() => vacancyTypeTabValues.map((type) => ({
  value: type,
  label: vacancyTypeLabel(type),
  count: applicationsByVacancyType.value[type]?.length || 0,
})));

const selectedTypeApplications = computed(() => applicationsByVacancyType.value[activeVacancyType.value] || []);

const scrollVotingDates = (direction) => {
  const scroller = votingDateScroller.value;
  if (!scroller) return;

  const distance = Math.max(220, Math.floor(scroller.clientWidth * 0.75));
  scroller.scrollBy({
    left: direction === 'left' ? -distance : distance,
    behavior: 'smooth',
  });
};

const votingDateKey = (value) => {
  if (!value) return 'none';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'none';

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

const votingDateLabel = (key) => {
  if (key === 'none') return 'Не назначено';

  return new Date(`${key}T00:00:00`).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const votingOrderValue = (app) => {
  const value = Number(app?.voting_order);

  return Number.isInteger(value) && value > 0 ? value : Number.MAX_SAFE_INTEGER;
};

const votingSequenceLabel = (app, index) => {
  const value = Number(app?.voting_order);

  return Number.isInteger(value) && value > 0 ? value : index + 1;
};

const applicationCreatedAtTime = (app) => {
  const value = Date.parse(app?.created_at || '');

  return Number.isNaN(value) ? 0 : value;
};

const sortVotingApplications = (items) => items.slice().sort((left, right) => {
  const leftDate = votingDateKey(left?.voting_started_at);
  const rightDate = votingDateKey(right?.voting_started_at);

  if (leftDate !== rightDate) {
    if (leftDate === 'none') return 1;
    if (rightDate === 'none') return -1;
    return rightDate.localeCompare(leftDate);
  }

  return (
    votingOrderValue(left) - votingOrderValue(right)
    || applicationCreatedAtTime(left) - applicationCreatedAtTime(right)
    || Number(left?.id || 0) - Number(right?.id || 0)
  );
});

const votingDateTabs = computed(() => {
  const counts = new Map();

  selectedTypeApplications.value.forEach((app) => {
    const key = votingDateKey(app.voting_started_at);
    counts.set(key, (counts.get(key) || 0) + 1);
  });

  return Array.from(counts.entries())
    .sort(([left], [right]) => {
      if (left === 'none') return 1;
      if (right === 'none') return -1;
      return right.localeCompare(left);
    })
    .map(([value, count]) => ({
      value,
      label: votingDateLabel(value),
      count,
    }));
});

const dateFilteredApplications = computed(() => {
  if (activeVotingDate.value === 'all') return sortVotingApplications(selectedTypeApplications.value);

  return sortVotingApplications(
    selectedTypeApplications.value.filter((app) =>
      votingDateKey(app.voting_started_at) === activeVotingDate.value
    )
  );
});

const normalizeSearchText = (value) =>
  String(value || '')
    .toLowerCase()
    .replace(/[«»"'`]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

const applicationSearchText = (app) => normalizeSearchText([
  app.id,
  app.vacancy?.title,
  app.vacancy?.position?.name,
  vacancyTypeLabel(app?.vacancy?.type),
  app.user?.name,
  app.user?.email,
  app.user?.phone,
  app.pps_profile?.desired_position,
  app.pps_profile?.faculty_name,
  app.pps_profile?.department_name,
  app.ppsProfile?.desired_position,
  app.ppsProfile?.faculty_name,
  app.ppsProfile?.department_name,
].filter(Boolean).join(' '));

const filteredApplications = computed(() => {
  const query = normalizeSearchText(searchQuery.value);
  if (!query) return dateFilteredApplications.value;

  return dateFilteredApplications.value.filter((app) =>
    applicationSearchText(app).includes(query)
  );
});
const activeVacancyTypeLabel = computed(() => vacancyTypeLabel(activeVacancyType.value));
const activeVotingDateLabel = computed(() =>
  activeVotingDate.value === 'all' ? 'все даты' : votingDateLabel(activeVotingDate.value)
);
const emptyStateText = computed(() => {
  if (searchQuery.value.trim()) {
    return `По запросу "${searchQuery.value.trim()}" в разделе ${activeVacancyTypeLabel.value} за ${activeVotingDateLabel.value} ничего не найдено.`;
  }

  if (activeVotingDate.value !== 'all') {
    return `В разделе ${activeVacancyTypeLabel.value} за ${activeVotingDateLabel.value} заявок для голосования нет.`;
  }

  return `В разделе ${activeVacancyTypeLabel.value} заявок для голосования пока нет.`;
});

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';
const detailsRoute = (application) => ({
  path: `/commission/applications/${application.id}`,
  query: {
    type: application?.vacancy?.type || activeVacancyType.value,
  },
});
const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательное письмо',
  scientific_works: 'Список научных трудов',
  other: 'Другое',
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

const formatDate = (value, fallback = 'Не указана') => {
  if (!value) return fallback;

  return new Date(value).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const syncActiveVacancyType = () => {
  if (applicationsByVacancyType.value[activeVacancyType.value]?.length) {
    return;
  }

  const fallbackType = vacancyTypeTabValues.find((type) => applicationsByVacancyType.value[type]?.length);
  activeVacancyType.value = fallbackType || 'pps';
};

const latestVotingDateValue = () => (
  votingDateTabs.value.find((date) => date.value !== 'none')?.value
  || votingDateTabs.value[0]?.value
  || 'all'
);

const selectLatestVotingDate = () => {
  activeVotingDate.value = latestVotingDateValue();
};

watch(activeVacancyType, (value) => {
  const currentType = route.query.type ? String(route.query.type) : null;
  if (currentType === value) {
    selectLatestVotingDate();
    return;
  }

  router.replace({
    query: {
      ...route.query,
      type: value,
    },
  });
  selectLatestVotingDate();
});

watch(votingDateTabs, (tabs) => {
  if (activeVotingDate.value === 'all' || !tabs.some((date) => date.value === activeVotingDate.value)) {
    selectLatestVotingDate();
  }
});

const fetchQueue = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/commission/applications');
    applications.value = response.data;
    syncActiveVacancyType();
    selectLatestVotingDate();
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const vote = async (applicationId, decision, hireTermYears = null) => {
  try {
    await axios.post(`/api/commission/applications/${applicationId}/vote`, {
      decision,
      hire_term_years: hireTermYears,
      comment: comments.value[applicationId] || null,
    });
    await fetchQueue();
  } catch (error) {
    alert(errorText(error));
  }
};

onMounted(fetchQueue);
</script>

<style scoped>
.scrollbar-none {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.scrollbar-none::-webkit-scrollbar {
  display: none;
}
</style>
