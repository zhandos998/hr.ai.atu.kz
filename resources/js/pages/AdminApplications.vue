<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Управление заявками</h1>
          <p class="text-sm text-gray-500">Откройте карточку, чтобы перейти к полной информации по кандидату и этапам.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
          <div>
            {{ applicationArchiveMode === 'archived' ? 'В архиве' : 'Всего заявок' }}:
            <span class="font-semibold text-gray-700">{{ applications.length }}</span>
          </div>
          <button
            v-if="applicationArchiveMode === 'active'"
            type="button"
            class="inline-flex items-center rounded-xl bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
            @click="toggleCreateForm"
          >
            {{ showCreateForm ? 'Скрыть форму' : 'Добавить заявку' }}
          </button>
        </div>
      </div>

      <section
        v-if="showCreateForm && applicationArchiveMode === 'active'"
        class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm space-y-4"
      >
        <div>
          <h2 class="text-xl font-semibold text-[#005eb8]">Создать заявку вручную</h2>
          <p class="text-sm text-gray-500">Введите ФИО кандидата, выберите тип вакансии, а для ППС дополнительно укажите факультет и кафедру.</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">ФИО кандидата</label>
            <input
              v-model="createForm.full_name"
              type="text"
              placeholder="Например: Иванов Иван Иванович"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Тип вакансии</label>
            <select
              v-model="createForm.vacancy_type"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
              <option
                v-for="type in vacancyTypeOptions"
                :key="type.value"
                :value="type.value"
              >
                {{ type.label }}
              </option>
            </select>
          </div>

          <div v-if="createForm.vacancy_type === 'pps'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Факультет</label>
            <select
              v-model="createForm.faculty_name"
              :disabled="facultyOptions.length === 0"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
            >
              <option value="">Выберите факультет</option>
              <option
                v-for="faculty in facultyOptions"
                :key="faculty.id"
                :value="faculty.name"
              >
                {{ faculty.name }}
              </option>
            </select>
            <p v-if="facultyOptions.length === 0" class="text-xs text-amber-700">
              В справочнике нет факультетов.
            </p>
          </div>

          <div v-if="createForm.vacancy_type === 'pps'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Кафедра</label>
            <select
              v-model="createForm.department_name"
              :disabled="!createForm.faculty_name || departmentOptions.length === 0"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
            >
              <option value="">Выберите кафедру</option>
              <option
                v-for="department in departmentOptions"
                :key="department.id"
                :value="department.name"
              >
                {{ department.name }}
              </option>
            </select>
            <p v-if="createForm.faculty_name && departmentOptions.length === 0" class="text-xs text-amber-700">
              Для выбранного факультета в справочнике нет кафедр.
            </p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Вакансия</label>
            <select
              v-model="createForm.vacancy_id"
              :disabled="filteredVacancies.length === 0"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
            >
              <option value="">Выберите вакансию</option>
              <option
                v-for="vacancy in filteredVacancies"
                :key="vacancy.id"
                :value="String(vacancy.id)"
              >
                {{ vacancy.title }}
              </option>
            </select>
            <p v-if="filteredVacancies.length === 0" class="text-xs text-amber-700">
              Для выбранного типа вакансий пока нет записей.
            </p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Резюме</label>
            <input
              type="file"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-medium file:text-[#005eb8] hover:file:bg-blue-100"
              @change="handleCreateResumeUpload"
            />
            <p class="text-xs text-gray-400">
              {{ createForm.resume?.name || 'Необязательно. PDF, DOC, DOCX, JPG, PNG до 2 МБ' }}
            </p>
          </div>
        </div>

        <div v-if="createForm.full_name || selectedVacancy || createForm.faculty_name || createForm.department_name" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-sm">
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Кандидат</div>
            <div class="mt-1 font-medium text-gray-800">{{ createForm.full_name || 'Не указан' }}</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Тип</div>
            <div class="mt-1 font-medium text-gray-800">{{ vacancyTypeLabel(createForm.vacancy_type) }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'pps'" class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Факультет</div>
            <div class="mt-1 font-medium text-gray-800">{{ createForm.faculty_name || 'Не указан' }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'pps'" class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Кафедра</div>
            <div class="mt-1 font-medium text-gray-800">{{ createForm.department_name || 'Не указана' }}</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3 md:col-span-2 xl:col-span-2">
            <div class="text-xs uppercase tracking-wide text-gray-400">Вакансия</div>
            <div class="mt-1 font-medium text-gray-800">{{ selectedVacancy?.title || 'Не выбрана' }}</div>
          </div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
            @click="resetCreateForm"
          >
            Очистить
          </button>
          <button
            type="button"
            :disabled="creatingApplication"
            class="rounded-xl bg-[#005eb8] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
            @click="createApplication"
          >
            {{ creatingApplication ? 'Создание...' : 'Создать заявку' }}
          </button>
        </div>
      </section>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else class="space-y-5">
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            :class="applicationArchiveMode === 'active'
              ? 'bg-[#005eb8] text-white border-[#005eb8]'
              : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold transition"
            @click="setApplicationArchiveMode('active')"
          >
            Активные
          </button>
          <button
            type="button"
            :class="applicationArchiveMode === 'archived'
              ? 'bg-[#005eb8] text-white border-[#005eb8]'
              : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold transition"
            @click="setApplicationArchiveMode('archived')"
          >
            Архив
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            v-for="type in applicationTypeTabs"
            :key="type.value"
            :class="activeVacancyType === type.value
              ? 'bg-[#005eb8] text-white border-[#005eb8]'
              : 'bg-white text-[#005eb8] border-[#005eb8] hover:bg-[#005eb8] hover:text-white'"
            class="inline-flex items-center gap-2 border rounded-xl px-4 py-2 text-sm font-semibold transition"
            @click="setActiveVacancyType(type.value)"
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

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-7">
          <div class="text-sm text-gray-500 md:self-center">
            Показано: <span class="font-semibold text-gray-700">{{ filteredApplications.length }}</span>
            <span class="text-gray-400"> / {{ applicationsByVacancyType[activeVacancyType]?.length || 0 }}</span>
          </div>
          <input
            v-model="applicationSearch"
            type="text"
            placeholder="Поиск по кандидату, вакансии, факультету"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 xl:col-span-2"
          />
          <select
            v-model="stageFilters.resume"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Резюме: все</option>
            <option
              v-for="option in stageFilterOptions.resume"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <select
            v-model="stageFilters.documents"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Документы: все</option>
            <option
              v-for="option in stageFilterOptions.documents"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <select
            v-model="stageFilters.hiring"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Прием: все</option>
            <option
              v-for="option in stageFilterOptions.hiring"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <select
            v-model="stageFilters.compliance"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Коррупция: все</option>
            <option
              v-for="option in stageFilterOptions.compliance"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div
          v-if="filteredApplications.length === 0"
          class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
        >
          {{ emptyApplicationsMessage }}
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Кандидат"
                      column="candidate"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Вакансия"
                      column="vacancy"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Факультет / кафедра"
                      column="profile"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Статусы"
                      column="statuses"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Голоса"
                      column="votes"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Дата"
                      column="created_at"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3 text-right">Действие</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr
                  v-for="app in filteredApplications"
                  :key="app.id"
                  class="cursor-pointer transition hover:bg-blue-50/50 focus-within:bg-blue-50/50"
                  tabindex="0"
                  role="link"
                  @click="goToDetails(app.id)"
                  @keydown.enter.self.prevent="goToDetails(app.id)"
                  @keydown.space.self.prevent="goToDetails(app.id)"
                >
                  <td class="px-4 py-3 align-top">
                    <div class="max-w-[260px]">
                      <div class="font-semibold text-gray-900 whitespace-normal break-words">
                        {{ app.user?.name || 'Кандидат не указан' }}
                      </div>
                      <div class="mt-0.5 truncate text-xs text-gray-400">
                        {{ visibleUserEmail(app.user) || 'Email не указан' }}
                      </div>
                      <div class="mt-1 text-xs text-gray-400">ID заявки: {{ app.id }}</div>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="max-w-[320px]">
                      <div class="font-medium text-gray-900 whitespace-normal break-words">
                        {{ vacancyTitle(app) }}
                      </div>
                      <div class="mt-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-[#005eb8]">
                        {{ vacancyTypeLabel(app?.vacancy?.type) }}
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <template v-if="app?.vacancy?.type === 'pps'">
                      <div class="max-w-[260px] whitespace-normal break-words">
                        {{ app.pps_profile?.faculty_name || 'Факультет не указан' }}
                      </div>
                      <div class="mt-1 max-w-[260px] whitespace-normal break-words text-xs text-gray-400">
                        {{ app.pps_profile?.department_name || 'Кафедра не указана' }}
                      </div>
                    </template>
                    <template v-else>
                      <div class="max-w-[220px] whitespace-normal break-words">
                        {{ app?.vacancy?.position?.name || 'Должность не указана' }}
                      </div>
                    </template>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="flex items-center gap-2">
                      <span
                        v-for="stage in applicationStageOrder"
                        :key="`${app.id}-${stage}`"
                        class="group relative flex h-7 w-7 items-center justify-center"
                        :title="stageTooltip(stage, app)"
                      >
                        <span :class="stageDotClass(stage, app)"></span>
                        <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs font-medium normal-case tracking-normal text-white shadow-lg group-hover:block">
                          {{ stageTooltip(stage, app) }}
                        </span>
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <span class="font-medium text-gray-900">{{ app.vote_summary?.voted ?? 0 }}</span>
                    <span class="text-gray-400"> / {{ app.vote_summary?.total_members ?? 0 }}</span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-gray-600">
                    <div>{{ formatDate(app.created_at) }}</div>
                    <div v-if="applicationArchiveMode === 'archived'" class="mt-1 text-xs text-gray-400">
                      Архив: {{ formatDate(app.archived_at) }}
                    </div>
                    <div v-if="applicationArchiveMode === 'archived' && app.archived_by?.name" class="mt-1 text-xs text-gray-400">
                      {{ app.archived_by.name }}
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                    <div class="flex flex-wrap justify-end gap-2">
                      <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#005eb8] transition hover:bg-blue-50"
                        @click.stop="goToDetails(app.id)"
                      >
                        Открыть
                      </button>
                      <button
                        v-if="applicationArchiveMode === 'active'"
                        type="button"
                        :disabled="isArchivingApplication(app.id)"
                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                        @click.stop="archiveApplication(app)"
                      >
                        {{ isArchivingApplication(app.id) ? 'Архив...' : 'Архивировать' }}
                      </button>
                      <button
                        v-else
                        type="button"
                        :disabled="isUnarchivingApplication(app.id)"
                        class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
                        @click.stop="unarchiveApplication(app)"
                      >
                        {{ isUnarchivingApplication(app.id) ? 'Возврат...' : 'Разархивировать' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import TableSortButton from '../components/TableSortButton.vue';
import { useDialogStore } from '../stores/useDialogStore';
import {
  applicationStageOrder,
  stageLabel,
  stageOptions,
  stageStatusField,
  normalizedStageStatus,
} from '../utils/applicationStages';
import {
  vacancyTypeLabel,
  vacancyTypeTabs as vacancyTypeTabValues,
} from '../utils/vacancyTypes';
import { sortRows } from '../utils/tableSort';

const route = useRoute();
const router = useRouter();
const dialogStore = useDialogStore();

const applications = ref([]);
const vacancies = ref([]);
const departments = ref([]);
const loading = ref(true);
const creatingApplication = ref(false);
const archivingApplicationIds = ref([]);
const unarchivingApplicationIds = ref([]);
const applicationArchiveMode = ref(String(route.query.archived) === '1' ? 'archived' : 'active');
const showCreateForm = ref(false);
const applicationSearch = ref('');
const stageFilters = ref({
  resume: '',
  documents: '',
  compliance: '',
  hiring: '',
});
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const createForm = ref({
  full_name: '',
  vacancy_type: vacancyTypeTabValues.includes(String(route.query.type)) ? String(route.query.type) : 'pps',
  faculty_name: '',
  department_name: '',
  vacancy_id: '',
  resume: null,
});

const normalizeVacancyType = (value) => (
  vacancyTypeTabValues.includes(String(value)) ? String(value) : 'pps'
);

const activeVacancyType = ref(normalizeVacancyType(route.query.type));

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

const vacancyTypeOptions = computed(() => vacancyTypeTabValues.map((type) => ({
  value: type,
  label: vacancyTypeLabel(type),
})));

const activeVacancyTypeLabel = computed(() => vacancyTypeLabel(activeVacancyType.value));
const emptyApplicationsMessage = computed(() => (
  applicationArchiveMode.value === 'archived'
    ? `В архиве ${activeVacancyTypeLabel.value} заявок пока нет.`
    : `В разделе ${activeVacancyTypeLabel.value} заявок пока нет.`
));

const stageFilterOptions = computed(() => Object.fromEntries(
  applicationStageOrder.map((stage) => [stage, stageOptions(stage)]),
));

const filteredApplications = computed(() => {
  const q = applicationSearch.value.trim().toLowerCase();
  const rows = (applicationsByVacancyType.value[activeVacancyType.value] || []).filter((app) => {
    const matchesStages = applicationStageOrder.every((stage) => {
      const selectedStatus = stageFilters.value[stage];
      return !selectedStatus || stageStatusValue(stage, app) === selectedStatus;
    });

    if (!matchesStages) return false;
    if (!q) return true;

    return applicationSearchValues(app).some((value) => value.includes(q));
  });

  return sortRows(rows, applicationSortValue, sortDirection.value);
});
const normalizeVacancyMatchText = (value) => String(value || '')
  .trim()
  .toLowerCase()
  .replace(/[«»"'`]/g, '')
  .replace(/\s+/g, ' ');
const ppsVacancyTitles = [
  'Ассистент',
  'Ассистент-профессор',
  'Ассистент-профессор-исследователь',
  'Ассоциированный профессор',
  'Ассоциированный профессор – исследователь',
  'Заведующий кафедрой (для выпускающих кафедр)',
  'Заведующий кафедрой (для не выпускающих кафедр)',
  'Сеньор-лектор',
  'Профессор',
  'Профессор-исследователь',
  'Исполняющий обязанности (и.о.) ассоциированного профессора',
  'Исполняющий обязанности (и.о.) ассоциированного профессора – исследователя',
  'Исполняющий обязанности (и.о.) профессора',
  'Исполняющий обязанности (и.о.) профессора-исследователя',
];
const ppsVacancyTitleOrder = new Map(
  ppsVacancyTitles.map((title, index) => [normalizeVacancyMatchText(title), index]),
);
const filteredVacancies = computed(() => {
  const typeVacancies = vacancies.value.filter((vacancy) => vacancy?.type === createForm.value.vacancy_type);

  if (createForm.value.vacancy_type !== 'pps') {
    return typeVacancies
      .slice()
      .sort((left, right) => String(left?.title || '').localeCompare(String(right?.title || ''), 'ru'));
  }

  const uniqueByTitle = new Map();

  for (const vacancy of typeVacancies) {
    const titleKey = normalizeVacancyMatchText(vacancy?.title);
    if (!ppsVacancyTitleOrder.has(titleKey)) {
      continue;
    }

    const current = uniqueByTitle.get(titleKey);
    if (!current || (!vacancy?.position_id && current?.position_id)) {
      uniqueByTitle.set(titleKey, vacancy);
    }
  }

  return Array.from(uniqueByTitle.values())
    .sort((left, right) => (
      ppsVacancyTitleOrder.get(normalizeVacancyMatchText(left?.title))
      - ppsVacancyTitleOrder.get(normalizeVacancyMatchText(right?.title))
    ));
});

const selectedVacancy = computed(() => vacancies.value.find((vacancy) => String(vacancy.id) === createForm.value.vacancy_id) || null);
const departmentChildrenByParentId = computed(() => {
  const grouped = new Map();

  for (const department of departments.value || []) {
    const parentId = Number(department?.parent_id || 0);
    if (!parentId) continue;

    if (!grouped.has(parentId)) {
      grouped.set(parentId, []);
    }

    grouped.get(parentId).push(department);
  }

  for (const items of grouped.values()) {
    items.sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'));
  }

  return grouped;
});
const hasDescribedFaculties = computed(() => (
  (departments.value || []).some((department) => String(department?.description || '').trim().toLowerCase() === 'факультет')
));
const facultyOptions = computed(() => (
  (departments.value || [])
    .filter((department) => (
      !department?.parent_id
      && (hasDescribedFaculties.value
        ? String(department?.description || '').trim().toLowerCase() === 'факультет'
        : departmentChildrenByParentId.value.has(Number(department?.id)))
    ))
    .slice()
    .sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'))
));
const selectedFaculty = computed(() => (
  facultyOptions.value.find((faculty) => faculty.name === createForm.value.faculty_name) || null
));
const hasDescribedChairs = computed(() => (
  (departments.value || []).some((department) => String(department?.description || '').trim().toLowerCase() === 'кафедра')
));
const isChairDepartment = (department) => {
  const description = String(department?.description || '').trim().toLowerCase();
  const name = String(department?.name || '').trim().toLowerCase();

  return description === 'кафедра' || name.startsWith('кафедра');
};
const departmentOptions = computed(() => {
  if (!selectedFaculty.value) {
    return [];
  }

  return (departmentChildrenByParentId.value.get(Number(selectedFaculty.value.id)) || [])
    .filter((department) => (hasDescribedChairs.value ? isChairDepartment(department) : true))
    .slice()
    .sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'));
});
const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const isArchivingApplication = (applicationId) => archivingApplicationIds.value.includes(applicationId);
const isUnarchivingApplication = (applicationId) => unarchivingApplicationIds.value.includes(applicationId);

const archiveApplication = async (app) => {
  if (!app?.id) return;

  const candidateName = app.user?.name || `заявку #${app.id}`;
  const confirmed = await dialogStore.openConfirm(
    `Архивировать ${candidateName}? Заявка исчезнет из активного списка.`,
    {
      title: 'Архивировать заявку',
      confirmText: 'Архивировать',
      cancelText: 'Отмена',
    },
  );

  if (!confirmed) {
    return;
  }

  archivingApplicationIds.value = [...archivingApplicationIds.value, app.id];

  try {
    const response = await axios.put(`/api/admin/applications/${app.id}/archive`);
    applications.value = applications.value.filter((item) => item.id !== app.id);
    syncActiveVacancyType();
    alert(response.data?.message || 'Заявка архивирована.');
  } catch (error) {
    alert(errorText(error));
  } finally {
    archivingApplicationIds.value = archivingApplicationIds.value.filter((id) => id !== app.id);
  }
};

const unarchiveApplication = async (app) => {
  if (!app?.id) return;

  const candidateName = app.user?.name || `заявку #${app.id}`;
  const confirmed = await dialogStore.openConfirm(
    `Разархивировать ${candidateName}? Заявка вернется в активный список.`,
    {
      title: 'Разархивировать заявку',
      confirmText: 'Разархивировать',
      cancelText: 'Отмена',
    },
  );

  if (!confirmed) {
    return;
  }

  unarchivingApplicationIds.value = [...unarchivingApplicationIds.value, app.id];

  try {
    const response = await axios.put(`/api/admin/applications/${app.id}/unarchive`);
    applications.value = applications.value.filter((item) => item.id !== app.id);
    syncActiveVacancyType();
    alert(response.data?.message || 'Заявка возвращена в активный список.');
  } catch (error) {
    alert(errorText(error));
  } finally {
    unarchivingApplicationIds.value = unarchivingApplicationIds.value.filter((id) => id !== app.id);
  }
};

const isTechnicalCandidateEmail = (email) => String(email || '').toLowerCase().endsWith('@hr-ai.invalid');
const visibleUserEmail = (user) => {
  const email = String(user?.email || '').trim();
  return email && !isTechnicalCandidateEmail(email) ? email : '';
};

const applicationSearchValues = (app) => [
  app.user?.name,
  visibleUserEmail(app.user),
  vacancyTitle(app),
  vacancyTypeLabel(app?.vacancy?.type),
  app.pps_profile?.faculty_name,
  app.pps_profile?.department_name,
  app?.vacancy?.position?.name,
].map((value) => String(value || '').toLowerCase());

const stageShortTitle = (stage) => {
  if (stage === 'resume') return 'Резюме';
  if (stage === 'documents') return 'Документы';
  if (stage === 'compliance') return 'Коррупция';
  if (stage === 'hiring') return 'Найм';
  return stage;
};

const stageStatusValue = (stage, app) => normalizedStageStatus(
  stage,
  app?.[stageStatusField(stage)],
  app,
);

const stageTooltipLabel = (stage, app) => {
  const status = stageStatusValue(stage, app);
  if (stage === 'resume' && status === 'accepted') return 'Принята';

  return stageLabel(stage, app?.[stageStatusField(stage)], app);
};

const stageTooltip = (stage, app) => `${stageShortTitle(stage)}: ${stageTooltipLabel(stage, app)}`;

const stageDotClass = (stage, app) => {
  const status = stageStatusValue(stage, app);
  const base = 'block h-2.5 w-2.5 rounded-full ring-4 transition group-hover:scale-110';

  if (['accepted', 'clear', 'hired_1_year', 'hired_3_year'].includes(status)) {
    return `${base} bg-emerald-500 ring-emerald-100`;
  }

  if (['rejected', 'flagged'].includes(status)) {
    return `${base} bg-red-500 ring-red-100`;
  }

  if (['pending', 'voting'].includes(status)) {
    return `${base} bg-amber-400 ring-amber-100`;
  }

  if (status === 'awaiting_upload') {
    return `${base} bg-sky-500 ring-sky-100`;
  }

  return `${base} bg-slate-300 ring-slate-100`;
};

const vacancyTitle = (app) => app?.vacancy?.title || 'Без названия';

const applicationProfileLabel = (app) => {
  if (app?.vacancy?.type === 'pps') {
    return `${app.pps_profile?.faculty_name || ''} ${app.pps_profile?.department_name || ''}`.trim();
  }

  return app?.vacancy?.position?.name || '';
};

const applicationSortValue = (app) => {
  if (sortBy.value === 'candidate') return app.user?.name || '';
  if (sortBy.value === 'vacancy') return vacancyTitle(app);
  if (sortBy.value === 'profile') return applicationProfileLabel(app);
  if (sortBy.value === 'statuses') return applicationStageOrder.map((stage) => stageTooltipLabel(stage, app)).join(' ');
  if (sortBy.value === 'votes') return Number(app.vote_summary?.voted || 0);
  if (sortBy.value === 'created_at') return Date.parse(app.created_at || '') || 0;
  return app.id || 0;
};

const setSort = (column) => {
  if (sortBy.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortBy.value = column;
  sortDirection.value = ['created_at', 'votes'].includes(column) ? 'desc' : 'asc';
};

const formatDate = (value) => {
  if (!value) return 'Не указана';

  return new Date(value).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const syncApplicationsQuery = (overrides = {}) => {
  const query = {
    ...route.query,
    type: activeVacancyType.value,
    ...overrides,
  };

  if (applicationArchiveMode.value === 'archived') {
    query.archived = '1';
  } else {
    delete query.archived;
  }

  router.replace({
    query,
  });
};

const syncVacancyTypeQuery = (type) => {
  syncApplicationsQuery({ type });
};

const setActiveVacancyType = (type) => {
  if (activeVacancyType.value === type) return;

  activeVacancyType.value = type;
  syncVacancyTypeQuery(type);
};

const setApplicationArchiveMode = async (mode) => {
  if (applicationArchiveMode.value === mode) return;

  applicationArchiveMode.value = mode;
  showCreateForm.value = false;
  applicationSearch.value = '';
  syncApplicationsQuery();
  await fetchApplications();
};

const syncActiveVacancyType = () => {
  if (applications.value.length === 0) {
    return;
  }

  if (applicationsByVacancyType.value[activeVacancyType.value]?.length) {
    return;
  }

  const fallbackType = vacancyTypeTabValues.find((type) => applicationsByVacancyType.value[type]?.length);
  activeVacancyType.value = fallbackType || 'pps';
  syncVacancyTypeQuery(activeVacancyType.value);
};

const detailsRoute = (applicationId) => ({
  name: 'AdminApplicationDetails',
  params: { id: applicationId },
  query: {
    type: activeVacancyType.value,
    ...(applicationArchiveMode.value === 'archived' ? { archived: '1' } : {}),
  },
});

const goToDetails = (applicationId) => {
  router.push(detailsRoute(applicationId));
};

const resetCreateForm = () => {
  createForm.value = {
    full_name: '',
    vacancy_type: activeVacancyType.value,
    faculty_name: '',
    department_name: '',
    vacancy_id: '',
    resume: null,
  };
};

const toggleCreateForm = () => {
  showCreateForm.value = !showCreateForm.value;

  if (showCreateForm.value) {
    resetCreateForm();
  }
};

const handleCreateResumeUpload = (event) => {
  createForm.value.resume = event.target.files?.[0] || null;
};

const fetchApplications = async () => {
  loading.value = true;

  try {
    const response = await axios.get('/api/admin/applications', {
      params: {
        archived: applicationArchiveMode.value === 'archived' ? 1 : undefined,
      },
    });
    applications.value = response.data;
    syncActiveVacancyType();
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const fetchVacancies = async () => {
  try {
    const response = await axios.get('/api/admin/vacancies');
    vacancies.value = response.data;
  } catch (error) {
    alert(errorText(error));
  }
};

const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/admin/departments');
    departments.value = response.data || [];
  } catch (error) {
    departments.value = [];
    alert(errorText(error));
  }
};

const createApplication = async () => {
  if (!createForm.value.full_name.trim()) {
    alert('Введите ФИО кандидата.');
    return;
  }

  if (createForm.value.vacancy_type === 'pps' && !createForm.value.faculty_name.trim()) {
    alert('Выберите факультет.');
    return;
  }

  if (createForm.value.vacancy_type === 'pps' && !createForm.value.department_name.trim()) {
    alert('Выберите кафедру.');
    return;
  }

  if (!createForm.value.vacancy_id) {
    alert('Выберите вакансию.');
    return;
  }

  creatingApplication.value = true;

  const formData = new FormData();
  formData.append('full_name', createForm.value.full_name.trim());
  formData.append('vacancy_id', createForm.value.vacancy_id);

  if (createForm.value.vacancy_type === 'pps') {
    formData.append('faculty_name', createForm.value.faculty_name.trim());
    formData.append('department_name', createForm.value.department_name.trim());
  }

  if (createForm.value.resume) {
    formData.append('resume', createForm.value.resume);
  }

  try {
    const response = await axios.post('/api/admin/applications', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    const createdApplication = response.data?.application;
    if (createdApplication) {
      applications.value = [
        createdApplication,
        ...applications.value.filter((app) => app.id !== createdApplication.id),
      ];

      if (vacancyTypeTabValues.includes(String(createdApplication?.vacancy?.type))) {
        activeVacancyType.value = String(createdApplication.vacancy.type);
        syncVacancyTypeQuery(activeVacancyType.value);
      }
    }

    resetCreateForm();
    showCreateForm.value = false;
    alert(response.data?.message || 'Заявка создана.');
  } catch (error) {
    alert(errorText(error));
  } finally {
    creatingApplication.value = false;
  }
};

watch(() => createForm.value.vacancy_type, (type) => {
  const isSelectedVacancyVisible = filteredVacancies.value.some(
    (vacancy) => String(vacancy.id) === createForm.value.vacancy_id,
  );

  if (!isSelectedVacancyVisible) {
    createForm.value.vacancy_id = '';
  }

  if (type !== 'pps') {
    createForm.value.faculty_name = '';
    createForm.value.department_name = '';
  }

  if (activeVacancyType.value !== type) {
    activeVacancyType.value = type;
    syncVacancyTypeQuery(type);
  }
});

watch(() => createForm.value.faculty_name, () => {
  const isSelectedDepartmentVisible = departmentOptions.value.some(
    (department) => department.name === createForm.value.department_name,
  );

  if (!isSelectedDepartmentVisible) {
    createForm.value.department_name = '';
  }
});

onMounted(async () => {
  await Promise.all([
    fetchApplications(),
    fetchVacancies(),
    fetchDepartments(),
  ]);
});
</script>
