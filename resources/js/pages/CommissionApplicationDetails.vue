<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
          <router-link
            :to="backRoute"
            class="inline-flex items-center gap-2 text-sm font-medium text-[#005eb8] hover:underline"
          >
            <span>←</span>
            <span>К списку заявок комиссии</span>
          </router-link>
          <div>
            <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Заявка №{{ application?.id || route.params.id }}</h1>
            <p v-if="application" class="text-sm text-gray-500">
              {{ vacancyTypeLabel(application?.vacancy?.type) }} • {{ application?.vacancy?.title || 'Без названия' }}
            </p>
          </div>
        </div>

        <div
          v-if="application"
          :class="commissionVoteResultClass(application.vote_summary?.result, application.vote_summary?.approved_term_years)"
          class="inline-flex rounded-full px-3 py-1 text-sm font-medium"
        >
          {{ commissionVoteResultLabel(application.vote_summary?.result, application.vote_summary?.approved_term_years, application?.vacancy?.type) }}
        </div>
      </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div
        v-else-if="!application"
        class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
      >
        {{ errorMessage || 'Заявка не найдена.' }}
      </div>

      <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-[320px,1fr]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow space-y-4">
            <ApplicationVacancySummary :application="application" />

            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">Кандидат</h2>
              <p class="text-sm text-gray-700">{{ application.user?.name || 'Не указано' }}</p>
              <p class="text-sm text-gray-600">{{ application.user?.email || 'Не указано' }}</p>
              <p class="text-sm text-gray-600">Телефон: {{ application.user?.phone || 'Не указан' }}</p>
              <p
                v-if="application.hiring_status === 'hired' && application.hiring_term_years"
                class="text-sm text-gray-600"
              >
                Срок найма: {{ formatHiringTermLabel(application.hiring_term_years) }}
              </p>
              <p class="mt-2 text-sm text-gray-500">Дата подачи: {{ formatDate(application.created_at) }}</p>
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

            <div class="grid grid-cols-2 gap-2 text-sm">
              <div class="rounded-xl bg-blue-50 p-3 text-center">
                <div class="text-gray-500">Всего членов</div>
                <div class="font-semibold">{{ application.vote_summary?.total_members || 0 }}</div>
              </div>
              <template v-if="isPpsApplication">
                <div class="rounded-xl bg-emerald-50 p-3 text-center">
                  <div class="text-gray-500">На 1 год</div>
                  <div class="font-semibold text-emerald-700">{{ application.vote_summary?.hire_1_year || 0 }}</div>
                </div>
                <div class="rounded-xl bg-sky-50 p-3 text-center">
                  <div class="text-gray-500">На 3 года</div>
                  <div class="font-semibold text-sky-700">{{ application.vote_summary?.hire_3_year || 0 }}</div>
                </div>
              </template>
              <template v-else>
                <div class="rounded-xl bg-emerald-50 p-3 text-center">
                  <div class="text-gray-500">За</div>
                  <div class="font-semibold text-emerald-700">{{ application.vote_summary?.hire || 0 }}</div>
                </div>
              </template>
              <div class="rounded-xl bg-red-50 p-3 text-center">
                <div class="text-gray-500">{{ isPpsApplication ? 'Не брать' : 'Против' }}</div>
                <div class="font-semibold text-red-700">{{ application.vote_summary?.reject || 0 }}</div>
              </div>
              <div class="rounded-xl bg-gray-50 p-3 text-center">
                <div class="text-gray-500">Проголосовали</div>
                <div class="font-semibold">{{ application.vote_summary?.voted || 0 }}</div>
              </div>
            </div>

            <div class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Файлы кандидата</div>
              <div class="flex flex-wrap gap-2">
                <a
                  v-if="application.resume_url"
                  :href="application.resume_url"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center whitespace-nowrap rounded-lg border border-[#005eb8] px-3 py-2 text-sm font-medium text-[#005eb8] transition hover:bg-[#005eb8] hover:text-white"
                >
                  Резюме
                </a>
                <a
                  v-for="item in orderedDocuments"
                  :key="item.type"
                  :href="item.doc.url"
                  target="_blank"
                  rel="noopener"
                  :class="`inline-flex items-center whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-medium transition ${docTypeClass(item.base)}`"
                >
                  {{ docLabel(item.type) }}
                </a>
              </div>
              <div v-if="!application.resume_url && !orderedDocuments.length" class="text-sm text-gray-500">
                Дополнительные файлы не загружены.
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow space-y-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-[#005eb8]">Ваш голос</h2>
                <p class="text-sm text-gray-500">Можно голосовать прямо на этой странице.</p>
              </div>
              <div v-if="application.vote_summary?.my_vote" class="text-sm text-gray-600">
                Текущий выбор:
                <span class="font-semibold">
                  {{ commissionVoteDecisionLabel(application.vote_summary.my_vote.decision, application.vote_summary.my_vote.hire_term_years, application?.vacancy?.type) }}
                </span>
              </div>
            </div>

            <textarea
              v-model="voteComment"
              rows="3"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              placeholder="Комментарий к голосу (необязательно)"
            />

            <div class="flex flex-wrap gap-2">
              <button
                :disabled="voteSaving || !canVote"
                @click="submitVote('hire', isPpsApplication ? 1 : null)"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:bg-gray-300"
              >
                {{ voteSaving && pendingVoteKey === (isPpsApplication ? 'hire:1' : 'hire:') ? 'Сохранение...' : (isPpsApplication ? 'Взять на 1 год' : 'Голосовать: За') }}
              </button>
              <button
                v-if="isPpsApplication"
                :disabled="voteSaving || !canVote"
                @click="submitVote('hire', 3)"
                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:bg-gray-300"
              >
                {{ voteSaving && pendingVoteKey === 'hire:3' ? 'Сохранение...' : 'Взять на 3 года' }}
              </button>
              <button
                :disabled="voteSaving || !canVote"
                @click="submitVote('reject')"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:bg-gray-300"
              >
                {{ voteSaving && pendingVoteKey === 'reject:' ? 'Сохранение...' : (isPpsApplication ? 'Не брать' : 'Голосовать: Против') }}
              </button>
            </div>
            <div v-if="!canVote" class="text-sm text-gray-500">
              Голосование по этой заявке уже завершено.
            </div>
          </section>
        </div>

        <div class="space-y-5">
          <section v-if="isPpsApplication" class="rounded-2xl border border-gray-100 bg-white p-5 shadow space-y-4">
            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">Основные данные преподавателя</h2>
              <p class="text-sm text-gray-500">Профиль, который заполняется по ППС-заявке.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div
                v-for="field in ppsInfoFields"
                :key="field.key"
                class="rounded-xl bg-slate-50 px-4 py-3"
              >
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ field.label }}</div>
                <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ ppsValue(field.key) }}</div>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div
                v-for="field in ppsSupportingFields"
                :key="field.key"
                class="rounded-xl border border-gray-200 px-4 py-3 space-y-3"
              >
                <div>
                  <div class="text-sm font-medium text-gray-700">{{ field.label }}</div>
                  <div class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ ppsValue(field.key) }}</div>
                </div>

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
          </section>

          <section
            v-for="section in ppsSections"
            :key="section.title"
            v-show="isPpsApplication"
            class="rounded-2xl border border-gray-100 bg-white p-5 shadow space-y-4"
          >
            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">{{ section.title }}</h2>
              <p v-if="section.description" class="text-sm text-gray-500">{{ section.description }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4" :class="section.columnsClass">
              <div
                v-for="field in section.fields"
                :key="field.key"
                class="rounded-xl border border-gray-200 px-4 py-3 space-y-3"
              >
                <div>
                  <div class="text-sm font-medium text-gray-700">{{ field.label }}</div>
                  <div class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ ppsValue(field.key) }}</div>
                </div>

                <div v-if="field.documentsField && ppsDocuments(field.documentsField).length" class="flex flex-wrap gap-2">
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
              </div>
            </div>
          </section>

          <section v-if="application.vote_details?.length" class="rounded-2xl border border-gray-100 bg-white p-5 shadow space-y-4">
            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">Голоса комиссии</h2>
              <p class="text-sm text-gray-500">Текущее состояние голосования по заявке.</p>
            </div>

            <div class="space-y-2">
              <div
                v-for="vote in application.vote_details"
                :key="`${application.id}-${vote.user_id}`"
                class="flex flex-col gap-2 rounded-xl border border-gray-200 px-4 py-3 md:flex-row md:items-center md:justify-between"
              >
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium text-gray-700">{{ vote.name }}</div>
                  <div class="truncate text-xs text-gray-400">{{ vote.email }}</div>
                  <div v-if="vote.comment" class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ vote.comment }}</div>
                </div>

                <div class="text-right">
                  <div :class="commissionVoteDecisionClass(vote.decision, vote.hire_term_years)" class="inline-flex rounded-full px-3 py-1 text-xs font-semibold">
                    {{ commissionVoteDecisionLabel(vote.decision, vote.hire_term_years, application?.vacancy?.type) }}
                  </div>
                  <div v-if="vote.updated_at" class="mt-1 text-xs text-gray-400">{{ formatDateTime(vote.updated_at) }}</div>
                </div>
              </div>
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
import { vacancyTypeLabel } from '../utils/vacancyTypes';
import {
  hiringTermLabel as formatHiringTermLabel,
  voteDecisionClass as commissionVoteDecisionClass,
  voteDecisionLabel as commissionVoteDecisionLabel,
  voteResultClass as commissionVoteResultClass,
  voteResultLabel as commissionVoteResultLabel,
} from '../utils/commissionVotes';

const route = useRoute();

const application = ref(null);
const loading = ref(true);
const errorMessage = ref('');
const voteComment = ref('');
const voteSaving = ref(false);
const pendingVoteKey = ref('');

const backRoute = computed(() => ({
  name: 'CommissionApplications',
  query: route.query.type ? { type: String(route.query.type) } : {},
}));

const isPpsApplication = computed(() => application.value?.vacancy?.type === 'pps');
const canVote = computed(() => ['not_started', 'voting'].includes(application.value?.hiring_status));

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

const ppsSections = [
  {
    title: 'Научные труды и ЦОР / МООК',
    description: 'Сведения от департамента науки и центра цифрового развития.',
    columnsClass: 'md:grid-cols-1',
    fields: [
      { key: 'scientific_works', label: 'Научные труды преподавателя', documentsField: 'scientific_works_documents' },
      { key: 'digital_mooc', label: 'Наличие ЦОР / МООК', documentsField: 'digital_mooc_documents' },
    ],
  },
  {
    title: 'Стратегическое развитие',
    description: 'Итоговый рейтинг, анкетирование студентов, индивидуальный план и КРК.',
    columnsClass: 'md:grid-cols-1',
    fields: [
      { key: 'final_rating_score', label: 'Итоговый рейтинговый балл' },
      { key: 'student_survey_results', label: 'Результаты анкетирования студентов о деятельности ППС' },
      { key: 'individual_plan_nonfulfillment', label: 'Невыполнение индивидуального плана' },
      { key: 'krk', label: 'КРК' },
    ],
  },
  {
    title: 'Академическое развитие',
    description: 'Открытое занятие, дисциплины и учебно-методическая литература.',
    columnsClass: 'md:grid-cols-1',
    fields: [
      { key: 'open_lesson_quality', label: 'Оценка качества проведения открытого занятия' },
      { key: 'taught_disciplines', label: 'Преподаваемые дисциплины' },
      { key: 'educational_methodical_literature', label: 'Учебно-методическая литература' },
    ],
  },
  {
    title: 'Научная библиотека',
    description: 'Показатели учебных изданий.',
    columnsClass: 'md:grid-cols-1',
    fields: [
      { key: 'educational_publication_metrics', label: 'Показатели учебных изданий' },
    ],
  },
  {
    title: 'Комплаенс',
    description: 'Анкетирование по противодействию коррупции и дисциплинарные взыскания.',
    columnsClass: 'md:grid-cols-1',
    fields: [
      { key: 'anti_corruption_survey_results', label: 'Результаты анкетирования по вопросам противодействия коррупции', documentsField: 'compliance_documents' },
      { key: 'disciplinary_actions_info', label: 'Сведения о дисциплинарных взысканиях' },
    ],
  },
];

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

const orderedDocuments = computed(() => Object.entries(application.value?.documents_map || {})
  .map(([type, doc]) => {
    const parsed = parseDocType(type);
    const base = normalizeBase(parsed.base);
    const index = parsed.index || (docOrder[base] ? 1 : 0);
    return { type, doc, base, index };
  })
  .sort((a, b) => (docOrder[a.base] || 99) - (docOrder[b.base] || 99) || a.index - b.index));

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

const voteResultLabel = (result) => {
  if (result === 'approved') return 'Взять на работу';
  if (result === 'rejected') return 'Не брать';
  return 'Ожидание голосов';
};

const voteResultClass = (result) => {
  if (result === 'approved') return 'bg-emerald-100 text-emerald-700';
  if (result === 'rejected') return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
};

const voteDecisionLabel = (decision) => {
  if (decision === 'hire') return 'За';
  if (decision === 'reject') return 'Против';
  return 'Не голосовал';
};

const voteDecisionClass = (decision) => {
  if (decision === 'hire') return 'bg-emerald-100 text-emerald-700';
  if (decision === 'reject') return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
};

const formatDate = (value) => {
  if (!value) return 'Не указана';

  return new Date(value).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const formatDateTime = (value) => {
  if (!value) return '';

  return new Date(value).toLocaleString('ru-RU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const ppsValue = (key) => application.value?.pps_profile?.[key] || 'Не указано';
const ppsDocuments = (key) => application.value?.pps_profile?.[key] || [];

const syncVoteDraft = () => {
  voteComment.value = application.value?.vote_summary?.my_vote?.comment || '';
};

const fetchApplication = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get(`/api/commission/applications/${route.params.id}`);
    application.value = response.data;
    syncVoteDraft();
  } catch (error) {
    application.value = null;
    errorMessage.value = error?.response?.data?.message || 'Ошибка при загрузке заявки.';
  } finally {
    loading.value = false;
  }
};

const submitVote = async (decision, hireTermYears = null) => {
  if (!application.value) return;

  voteSaving.value = true;
  pendingVoteKey.value = `${decision}:${hireTermYears || ''}`;

  try {
    const response = await axios.post(`/api/commission/applications/${application.value.id}/vote`, {
      decision,
      hire_term_years: hireTermYears,
      comment: voteComment.value || null,
    });

    if (response.data?.application) {
      application.value = response.data.application;
      syncVoteDraft();
    } else {
      await fetchApplication();
    }
  } catch (error) {
    alert(error?.response?.data?.message || 'Ошибка при сохранении голоса.');
  } finally {
    voteSaving.value = false;
    pendingVoteKey.value = '';
  }
};

onMounted(fetchApplication);
</script>
