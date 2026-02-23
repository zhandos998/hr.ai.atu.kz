<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Управление должностями</h1>
        <router-link
          to="/admin/positions/create"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition text-center"
        >
          Добавить должность
        </router-link>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Поиск по должности или отделу"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />
          <select
            v-model="departmentFilter"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          >
            <option value="">Все отделы</option>
            <option v-for="department in departments" :key="department.id" :value="department.id">
              {{ department.name }}
            </option>
          </select>
          <input
            v-model="userSearch"
            type="text"
            placeholder="Поиск пользователей для добавления"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />
        </div>

        <div v-if="loading" class="text-gray-500">Загрузка...</div>
        <div v-else-if="filteredPositions.length === 0" class="text-gray-600">Ничего не найдено.</div>
        <div v-else class="space-y-3">
          <div
            v-for="position in filteredPositions"
            :key="position.id"
            class="border border-gray-200 rounded-lg p-3"
          >
            <div v-if="editingId !== position.id" class="space-y-2">
              <div class="font-semibold text-[#005eb8]">{{ position.name }}</div>
              <div class="text-sm text-gray-600">Отдел: {{ position.department?.name || '-' }}</div>

              <div class="pt-2 border-t border-gray-100 space-y-2">
                <div class="text-sm font-semibold text-gray-700">Пользователи на должности</div>

                <div v-if="position.users && position.users.length" class="flex flex-wrap gap-2">
                  <span
                    v-for="user in position.users"
                    :key="user.id"
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm"
                  >
                    <span>{{ user.name }} <span class="text-gray-500">({{ user.email }})</span></span>
                    <button
                      @click="detachUser(position.id, user.id)"
                      class="text-red-600 hover:text-red-700 font-semibold cursor-pointer"
                      title="Удалить из должности"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div v-else class="text-sm text-gray-500">Пользователи не назначены.</div>

                <div class="flex flex-col md:flex-row gap-2">
                  <select
                    v-model="userToAttach[position.id]"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2"
                  >
                    <option value="">Выберите пользователя</option>
                    <option
                      v-for="user in availableUsersForPosition(position)"
                      :key="user.id"
                      :value="user.id"
                    >
                      {{ user.name }} ({{ user.email }})
                    </option>
                  </select>
                  <button
                    @click="attachUser(position.id)"
                    :disabled="!userToAttach[position.id]"
                    class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
                  >
                    Добавить
                  </button>
                </div>
                <div v-if="userLoading" class="text-xs text-gray-500">Обновляем список пользователей...</div>
              </div>

              <div class="flex gap-3 pt-1">
                <button
                  @click="startEdit(position)"
                  class="text-[#005eb8] text-sm hover:underline cursor-pointer"
                >
                  Редактировать
                </button>
                <button
                  @click="deletePosition(position.id)"
                  class="text-red-600 text-sm hover:underline cursor-pointer"
                >
                  Удалить
                </button>
              </div>
            </div>

            <div v-else class="space-y-2">
              <select
                v-model="editPosition.department_id"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              >
                <option disabled value="">Выберите отдел</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">
                  {{ department.name }}
                </option>
              </select>
              <input
                v-model="editPosition.name"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
              <textarea
                v-model="editPosition.duties"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
              <textarea
                v-model="editPosition.qualification"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
              <div class="flex gap-2">
                <button
                  @click="updatePosition(position.id)"
                  class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer"
                >
                  Сохранить
                </button>
                <button
                  @click="cancelEdit"
                  class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 rounded-lg w-full transition cursor-pointer"
                >
                  Отмена
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';

const departments = ref([]);
const positions = ref([]);
const users = ref([]);

const loading = ref(true);
const userLoading = ref(false);

const search = ref('');
const departmentFilter = ref('');
const userSearch = ref('');
const userToAttach = ref({});

const editingId = ref(null);
const editPosition = ref({ department_id: '', name: '', duties: '', qualification: '' });

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const filteredPositions = computed(() => {
  const q = search.value.trim().toLowerCase();

  return positions.value.filter((position) => {
    const matchesDepartment = !departmentFilter.value || Number(departmentFilter.value) === position.department_id;
    if (!matchesDepartment) return false;

    if (!q) return true;
    const name = (position.name || '').toLowerCase();
    const departmentName = (position.department?.name || '').toLowerCase();
    return name.includes(q) || departmentName.includes(q);
  });
});

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

const fetchUsers = async (q = '') => {
  userLoading.value = true;
  try {
    const response = await axios.get('/api/admin/position-users', { params: { q } });
    users.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    userLoading.value = false;
  }
};

const availableUsersForPosition = (position) => {
  const assigned = new Set((position.users || []).map((u) => u.id));
  return users.value.filter((u) => !assigned.has(u.id));
};

const attachUser = async (positionId) => {
  const userId = Number(userToAttach.value[positionId]);
  if (!userId) return;

  try {
    await axios.post(`/api/admin/positions/${positionId}/users`, { user_id: userId });
    userToAttach.value[positionId] = '';
    await fetchPositions();
  } catch (error) {
    alert(errorText(error));
  }
};

const detachUser = async (positionId, userId) => {
  if (!confirm('Удалить пользователя из должности?')) return;

  try {
    await axios.delete(`/api/admin/positions/${positionId}/users/${userId}`);
    await fetchPositions();
  } catch (error) {
    alert(errorText(error));
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
    alert('Выберите отдел и укажите название должности.');
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
  if (!confirm('Удалить должность?')) return;

  try {
    await axios.delete(`/api/admin/positions/${id}`);
    await fetchPositions();
  } catch (error) {
    alert(errorText(error));
  }
};

let searchTimer = null;
watch(userSearch, (value) => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchUsers(value.trim());
  }, 300);
});

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer);
});

onMounted(async () => {
  await Promise.all([fetchDepartments(), fetchPositions(), fetchUsers()]);
});
</script>
