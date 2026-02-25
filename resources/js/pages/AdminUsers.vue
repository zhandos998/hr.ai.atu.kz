<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Пользователи и роли</h1>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
          <input
            v-model="filters.q"
            type="text"
            placeholder="Поиск: ФИО, email, телефон"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />

          <select
            v-model="filters.role"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          >
            <option value="">Все роли</option>
            <option value="user">user</option>
            <option value="lawyer">lawyer</option>
            <option value="admin">admin</option>
          </select>

          <select
            v-model="filters.email_verified"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          >
            <option value="">Email: все</option>
            <option value="yes">Только подтвержденные</option>
            <option value="no">Только неподтвержденные</option>
          </select>

          <select
            v-model="filters.commission"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          >
            <option value="">Комиссия: все</option>
            <option value="yes">Только члены комиссии</option>
            <option value="no">Без комиссии</option>
          </select>
        </div>

        <div class="text-xs text-gray-500">
          Фильтры обязательны для работы со списком: используйте поиск и параметры выше для быстрого отбора пользователей.
        </div>

        <div v-if="loading" class="text-gray-500">Загрузка...</div>
        <div v-else-if="users.length === 0" class="text-gray-600">По выбранным фильтрам пользователи не найдены.</div>

        <div v-else class="space-y-3">
          <div
            v-for="user in users"
            :key="user.id"
            class="border border-gray-200 rounded-lg p-3 space-y-3"
          >
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
              <div class="space-y-1 min-w-0">
                <div class="font-semibold text-[#005eb8] break-words">{{ user.name }}</div>
                <div class="text-sm text-gray-700 break-all">{{ user.email }}</div>
                <div class="text-sm text-gray-600">Телефон: {{ user.phone || 'Не указан' }}</div>
                <div class="text-sm text-gray-600">ID: {{ user.id }}</div>
                <div class="text-sm text-gray-600">Email: {{ user.email_verified_at ? 'подтвержден' : 'не подтвержден' }}</div>
                <div class="text-sm text-gray-600">Комиссия: {{ user.is_commission_member ? 'да' : 'нет' }}</div>
                <div class="text-sm text-gray-600">Регистрация: {{ formatDate(user.created_at) }}</div>
              </div>

              <div class="w-full lg:w-72">
                <label class="block text-xs uppercase tracking-wide text-gray-500 mb-1">Роль</label>
                <select
                  :value="user.role || 'user'"
                  :disabled="isSaving(user.id) || authStore.user?.id === user.id"
                  @change="onChangeRole(user, $event.target.value)"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
                >
                  <option value="user">user</option>
                  <option value="lawyer">lawyer</option>
                  <option value="admin">admin</option>
                </select>
                <div
                  v-if="authStore.user?.id === user.id"
                  class="text-xs text-amber-700 mt-1"
                >
                  Текущему админу роль изменить нельзя.
                </div>
              </div>
            </div>

            <div class="pt-2 border-t border-gray-100 space-y-1">
              <div class="text-sm font-semibold text-gray-700">Должности</div>
              <div v-if="user.positions?.length" class="flex flex-wrap gap-2">
                <span
                  v-for="position in user.positions"
                  :key="position.id"
                  class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-xs"
                >
                  {{ position.name }}
                  <span v-if="position.department?.name" class="text-blue-500 ml-1">({{ position.department.name }})</span>
                </span>
              </div>
              <div v-else class="text-sm text-gray-500">Должности не назначены.</div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import { useAuthStore } from '../stores/useAuthStore';

const authStore = useAuthStore();

const users = ref([]);
const loading = ref(false);
const savingUserIds = ref([]);

const filters = ref({
  q: '',
  role: '',
  email_verified: '',
  commission: '',
});

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const isSaving = (userId) => savingUserIds.value.includes(userId);

const fetchUsers = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/users', {
      params: {
        q: filters.value.q.trim(),
        role: filters.value.role,
        email_verified: filters.value.email_verified,
        commission: filters.value.commission,
      },
    });
    users.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const onChangeRole = async (user, role) => {
  if ((user.role || 'user') === role) return;

  if (!confirm(`Изменить роль пользователя "${user.name}" на "${role}"?`)) return;

  savingUserIds.value = [...savingUserIds.value, user.id];
  try {
    await axios.put(`/api/admin/users/${user.id}/role`, { role });
    user.role = role;
    if (authStore.user?.id === user.id) {
      authStore.role = role;
    }
  } catch (error) {
    alert(errorText(error));
  } finally {
    savingUserIds.value = savingUserIds.value.filter((id) => id !== user.id);
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });
};

let filterTimer = null;
watch(filters, () => {
  if (filterTimer) clearTimeout(filterTimer);
  filterTimer = setTimeout(() => {
    fetchUsers();
  }, 350);
}, { deep: true });

onUnmounted(() => {
  if (filterTimer) clearTimeout(filterTimer);
});

onMounted(async () => {
  await fetchUsers();
});
</script>
