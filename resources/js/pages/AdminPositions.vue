<template>
  <Layout>
    <template #adminHeaderActions>
      <router-link
        to="/admin/positions/create"
        class="inline-flex items-center justify-center rounded-md bg-[#2f5fb8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#244f9d]"
      >
        {{ t('admin.actions.addPosition') }}
      </router-link>
    </template>

    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <section class="space-y-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="text-sm text-gray-500 md:self-center">
            {{ t('admin.positions.shown') }}: <span class="font-semibold text-gray-700">{{ filteredPositions.length }}</span>
            <span class="text-gray-400"> / {{ positions.length }}</span>
          </div>
          <input
            v-model="search"
            type="text"
            :placeholder="t('admin.positions.searchPlaceholder')"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          />

          <select
            v-model="departmentFilter"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">{{ t('admin.positions.allDepartments') }}</option>
            <option
              v-for="department in departmentOptions"
              :key="department.id"
              :value="department.id"
            >
              {{ department.full_name }}
            </option>
          </select>

          <select
            v-model="contentFilter"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">{{ t('admin.positions.allDescriptions') }}</option>
            <option value="complete">{{ t('admin.positions.complete') }}</option>
            <option value="missing_duties">{{ t('admin.positions.missingDuties') }}</option>
            <option value="missing_qualification">{{ t('admin.positions.missingQualification') }}</option>
          </select>
        </div>

        <div v-if="loading" class="text-center text-gray-500">{{ t('common.loading') }}</div>

        <div
          v-else-if="filteredPositions.length === 0"
          class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
        >
          {{ t('admin.positions.empty') }}
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.positions.position')"
                      column="name"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.positions.department')"
                      column="department"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.positions.duties')"
                      column="duties"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.positions.qualification')"
                      column="qualification"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3 text-right">{{ t('admin.positions.actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <template
                  v-for="position in filteredPositions"
                  :key="position.id"
                >
                  <tr
                    v-if="editingId !== position.id"
                    class="transition hover:bg-blue-50/50"
                  >
                    <td class="px-4 py-3 align-top">
                      <div class="max-w-[280px]">
                        <div class="font-semibold text-gray-900 whitespace-normal break-words">
                          {{ position.name }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">ID: {{ position.id }}</div>
                      </div>
                    </td>
                    <td class="px-4 py-3 align-top text-gray-600">
                      <div class="max-w-[320px] whitespace-normal break-words">
                        {{ departmentName(position.department_id) }}
                      </div>
                    </td>
                    <td class="px-4 py-3 align-top text-gray-600">
                      <div class="max-w-[340px] line-clamp-3 whitespace-normal break-words">
                        {{ position.duties || t('admin.positions.notSpecifiedPlural') }}
                      </div>
                    </td>
                    <td class="px-4 py-3 align-top text-gray-600">
                      <div class="max-w-[340px] line-clamp-3 whitespace-normal break-words">
                        {{ position.qualification || t('admin.positions.notSpecifiedPlural') }}
                      </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                      <div class="flex justify-end gap-3">
                        <button
                          type="button"
                          class="font-semibold text-[#005eb8] hover:text-blue-700"
                          @click="startEdit(position)"
                        >
                          {{ t('common.edit') }}
                        </button>
                        <button
                          type="button"
                          class="font-semibold text-red-600 hover:text-red-700"
                          @click="deletePosition(position.id)"
                        >
                          {{ t('common.delete') }}
                        </button>
                      </div>
                    </td>
                  </tr>

                  <tr v-else>
                    <td colspan="5" class="bg-slate-50 px-4 py-4">
                      <form class="space-y-4" @submit.prevent="updatePosition(position.id)">
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                          <label class="space-y-2">
                            <span class="text-sm font-medium text-gray-700">{{ t('admin.positions.department') }}</span>
                            <select
                              v-model="editPosition.department_id"
                              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            >
                              <option disabled value="">{{ t('admin.positions.chooseDepartment') }}</option>
                              <option
                                v-for="department in departmentOptions"
                                :key="department.id"
                                :value="department.id"
                              >
                                {{ department.full_name }}
                              </option>
                            </select>
                          </label>

                          <label class="space-y-2">
                            <span class="text-sm font-medium text-gray-700">{{ t('admin.positions.name') }}</span>
                            <input
                              v-model="editPosition.name"
                              type="text"
                              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            />
                          </label>

                          <label class="space-y-2 xl:col-span-2">
                            <span class="text-sm font-medium text-gray-700">{{ t('admin.positions.duties') }}</span>
                            <textarea
                              v-model="editPosition.duties"
                              rows="4"
                              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                          </label>

                          <label class="space-y-2 xl:col-span-2">
                            <span class="text-sm font-medium text-gray-700">{{ t('admin.positions.qualification') }}</span>
                            <textarea
                              v-model="editPosition.qualification"
                              rows="4"
                              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                          </label>
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                          <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100"
                            @click="cancelEdit"
                          >
                            {{ t('common.cancel') }}
                          </button>
                          <button
                            type="submit"
                            class="rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                          >
                            {{ t('common.save') }}
                          </button>
                        </div>
                      </form>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import TableSortButton from '../components/TableSortButton.vue';
import { useI18n } from '../i18n';
import { collectDepartmentSubtreeIds, decorateDepartments } from '../utils/departments';
import { sortRows } from '../utils/tableSort';

const { t } = useI18n();

const departments = ref([]);
const positions = ref([]);
const loading = ref(true);
const search = ref('');
const departmentFilter = ref('');
const contentFilter = ref('');
const sortBy = ref('department');
const sortDirection = ref('asc');
const editingId = ref(null);
const editPosition = ref({ department_id: '', name: '', duties: '', qualification: '' });

const errorText = (error) => error?.response?.data?.message || t('validation.genericError');
const departmentOptions = computed(() => decorateDepartments(departments.value));

const departmentName = (departmentId) => {
  const department = departmentOptions.value.find((item) => item.id === Number(departmentId));
  return department?.full_name || department?.name || '-';
};

const filteredPositions = computed(() => {
  const q = search.value.trim().toLowerCase();
  const subtreeIds = departmentFilter.value
    ? collectDepartmentSubtreeIds(departmentFilter.value, departments.value)
    : null;

  const rows = positions.value.filter((position) => {
    const matchesDepartment = !subtreeIds || subtreeIds.has(Number(position.department_id));
    if (!matchesDepartment) return false;

    if (contentFilter.value === 'complete' && (!position.duties || !position.qualification)) return false;
    if (contentFilter.value === 'missing_duties' && position.duties) return false;
    if (contentFilter.value === 'missing_qualification' && position.qualification) return false;

    if (!q) return true;
    const name = (position.name || '').toLowerCase();
    const departmentPath = departmentName(position.department_id).toLowerCase();
    const duties = (position.duties || '').toLowerCase();
    const qualification = (position.qualification || '').toLowerCase();
    return [name, departmentPath, duties, qualification].some((value) => value.includes(q));
  });

  return sortRows(rows, positionSortValue, sortDirection.value);
});

const positionSortValue = (position) => {
  if (sortBy.value === 'name') return position.name || '';
  if (sortBy.value === 'department') return `${departmentName(position.department_id)} ${position.name || ''}`;
  if (sortBy.value === 'duties') return position.duties || '';
  if (sortBy.value === 'qualification') return position.qualification || '';
  return position.id || 0;
};

const setSort = (column) => {
  if (sortBy.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortBy.value = column;
  sortDirection.value = 'asc';
};

const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/admin/departments');
    departments.value = response.data;
  } catch (error) {
    alert(errorText(error));
  }
};

const fetchPositions = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/positions');
    positions.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const startEdit = (position) => {
  editingId.value = position.id;
  editPosition.value = {
    department_id: position.department_id || '',
    name: position.name || '',
    duties: position.duties || '',
    qualification: position.qualification || '',
  };
};

const cancelEdit = () => {
  editingId.value = null;
  editPosition.value = { department_id: '', name: '', duties: '', qualification: '' };
};

const updatePosition = async (id) => {
  if (!editPosition.value.department_id || !editPosition.value.name.trim()) {
    alert(t('validation.positions.required'));
    return;
  }

  try {
    await axios.put(`/api/admin/positions/${id}`, editPosition.value);
    cancelEdit();
    await fetchPositions();
  } catch (error) {
    alert(errorText(error));
  }
};

const deletePosition = async (id) => {
  if (!confirm(t('confirm.positions.delete'))) return;

  try {
    await axios.delete(`/api/admin/positions/${id}`);
    await fetchPositions();
  } catch (error) {
    alert(errorText(error));
  }
};

onMounted(async () => {
  await Promise.all([fetchDepartments(), fetchPositions()]);
});
</script>
