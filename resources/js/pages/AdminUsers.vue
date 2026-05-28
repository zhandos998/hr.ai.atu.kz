<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Пользователи и роли</h1>
        <button
          type="button"
          class="inline-flex items-center whitespace-nowrap rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
          @click="showCreateForm = !showCreateForm"
        >
          {{ showCreateForm ? 'Скрыть форму' : 'Добавить пользователя' }}
        </button>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <form
          v-if="showCreateForm"
          class="rounded-xl border border-blue-100 bg-blue-50/40 p-4 space-y-4"
          @submit.prevent="createUser"
        >
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
            <div class="xl:col-span-2">
              <label class="block text-xs uppercase tracking-wide text-gray-500 mb-1">ФИО</label>
              <input
                v-model="createForm.name"
                type="text"
                required
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label class="block text-xs uppercase tracking-wide text-gray-500 mb-1">Email</label>
              <input
                v-model="createForm.email"
                type="email"
                required
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label class="block text-xs uppercase tracking-wide text-gray-500 mb-1">Телефон</label>
              <input
                v-model="createForm.phone"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label class="block text-xs uppercase tracking-wide text-gray-500 mb-1">Пароль</label>
              <input
                v-model="createForm.password"
                type="text"
                required
                minlength="4"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[minmax(0,20rem),auto] gap-3 md:items-end">
            <div>
              <label class="block text-xs uppercase tracking-wide text-gray-500 mb-1">Роль</label>
              <select
                v-model="createForm.role"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              >
                <option
                  v-for="role in roleOptions"
                  :key="`create-${role.value}`"
                  :value="role.value"
                >
                  {{ role.label }}
                </option>
              </select>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                type="submit"
                :disabled="creatingUser"
                class="inline-flex items-center justify-center rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {{ creatingUser ? 'Создание...' : 'Создать' }}
              </button>
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                @click="resetCreateForm"
              >
                Очистить
              </button>
            </div>
          </div>
        </form>

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
            <option
              v-for="role in roleOptions"
              :key="`filter-${role.value}`"
              :value="role.value"
            >
              {{ role.label }}
            </option>
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
          Используйте поиск и фильтры, чтобы быстро находить нужных пользователей и назначать им роли.
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
                  <option
                    v-for="role in roleOptions"
                    :key="`user-${user.id}-${role.value}`"
                    :value="role.value"
                  >
                    {{ role.label }}
                  </option>
                </select>
                <div
                  v-if="authStore.user?.id === user.id"
                  class="text-xs text-amber-700 mt-1"
                >
                  Текущему администратору роль изменить нельзя.
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
const showCreateForm = ref(false);
const creatingUser = ref(false);

const filters = ref({
  q: '',
  role: '',
  email_verified: '',
  commission: '',
});

const emptyCreateForm = () => ({
  name: '',
  email: '',
  phone: '',
  role: 'user',
  password: '',
});

const createForm = ref(emptyCreateForm());

const roleOptions = [
  { value: 'user', label: 'Пользователь' },
  { value: 'lawyer', label: 'Юрист / комплаенс' },
  { value: 'science_director', label: 'Директор по науке' },
  { value: 'digital_director', label: 'Директор по цифровизации' },
  { value: 'strategy_director', label: 'Директор по стратегии' },
  { value: 'academic_director', label: 'Академический директор' },
  { value: 'library_director', label: 'Научная библиотека' },
  { value: 'admin', label: 'Администратор' },
];

const roleLabel = (role) => (
  roleOptions.find((option) => option.value === (role || 'user'))?.label || role || 'Пользователь'
);

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const isSaving = (userId) => savingUserIds.value.includes(userId);

const resetCreateForm = () => {
  createForm.value = emptyCreateForm();
};

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

const createUser = async () => {
  if (creatingUser.value) return;

  if (!createForm.value.name.trim()) {
    alert('Введите ФИО пользователя.');
    return;
  }

  if (!createForm.value.email.trim()) {
    alert('Введите email пользователя.');
    return;
  }

  if (!createForm.value.password || createForm.value.password.length < 4) {
    alert('Пароль должен быть не короче 4 символов.');
    return;
  }

  creatingUser.value = true;

  try {
    await axios.post('/api/admin/users', {
      name: createForm.value.name.trim(),
      email: createForm.value.email.trim(),
      phone: createForm.value.phone.trim() || null,
      role: createForm.value.role,
      password: createForm.value.password,
    });

    resetCreateForm();
    showCreateForm.value = false;
    await fetchUsers();
  } catch (error) {
    alert(errorText(error));
  } finally {
    creatingUser.value = false;
  }
};

const onChangeRole = async (user, role) => {
  if ((user.role || 'user') === role) return;

  if (!confirm(`Изменить роль пользователя "${user.name}" на "${roleLabel(role)}"?`)) return;

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
