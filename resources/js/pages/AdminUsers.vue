<template>
  <Layout>
    <template #adminHeaderActions>
      <button
        type="button"
        class="inline-flex items-center whitespace-nowrap rounded-md bg-[#2f5fb8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#244f9d]"
        @click="showCreateForm = !showCreateForm"
      >
        {{ showCreateForm ? t('admin.actions.hideForm') : t('admin.actions.addUser') }}
      </button>
    </template>

    <div class="mx-auto max-w-7xl px-4 py-8 space-y-6">
        <section
          v-if="showCreateForm"
          class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
        >
          <div class="border-b border-gray-200 bg-[#eef4ff] px-5 py-4">
            <h2 class="font-semibold text-[#174a9c]">{{ t('admin.users.addTitle') }}</h2>
          </div>
          <form class="p-5 space-y-4" @submit.prevent="createUser">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
              <div class="xl:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('admin.users.fullName') }}</label>
                <input
                  v-model="createForm.name"
                  type="text"
                  required
                  class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
              </div>
              <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Email</label>
                <input
                  v-model="createForm.email"
                  type="email"
                  required
                  class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
              </div>
              <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('admin.users.phone') }}</label>
                <input
                  v-model="createForm.phone"
                  type="text"
                  class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
              </div>
              <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('admin.users.password') }}</label>
                <input
                  v-model="createForm.password"
                  type="text"
                  required
                  minlength="4"
                  class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,20rem),auto] md:items-end">
              <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('admin.users.role') }}</label>
                <select
                  v-model="createForm.role"
                  class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
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
                  class="inline-flex h-10 items-center justify-center rounded-md bg-[#2f5fb8] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#244f9d] disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {{ creatingUser ? t('admin.users.createInProgress') : t('admin.users.create') }}
                </button>
                <button
                  type="button"
                  class="inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                  @click="resetCreateForm"
                >
                  {{ t('common.clear') }}
                </button>
              </div>
            </div>
          </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-200 bg-[#eef4ff] px-5 py-4">
            <h2 class="font-semibold text-[#174a9c]">{{ t('admin.users.filterTitle') }}</h2>
          </div>

          <form
            class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_260px_220px_220px_auto] lg:items-end"
            @submit.prevent="fetchUsers"
          >
            <div>
              <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('common.search') }}</label>
              <input
                v-model="filters.q"
                type="text"
                :placeholder="t('admin.users.searchPlaceholder')"
                class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('admin.users.role') }}</label>
              <select
                v-model="filters.role"
                class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                <option value="">{{ t('admin.users.allRoles') }}</option>
                <option
                  v-for="role in roleOptions"
                  :key="`filter-${role.value}`"
                  :value="role.value"
                >
                  {{ role.label }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-gray-700">Email</label>
              <select
                v-model="filters.email_verified"
                class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                <option value="">{{ t('admin.users.allEmail') }}</option>
                <option value="yes">{{ t('admin.users.verified') }}</option>
                <option value="no">{{ t('admin.users.unverified') }}</option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('common.commission') }}</label>
              <select
                v-model="filters.commission"
                class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm focus:border-[#2f5fb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                <option value="">{{ t('common.all') }}</option>
                <option value="yes">{{ t('admin.users.commissionMembers') }}</option>
                <option value="no">{{ t('admin.users.withoutCommission') }}</option>
              </select>
            </div>

            <div class="flex gap-2">
              <button
                type="submit"
                class="inline-flex h-10 items-center justify-center rounded-md bg-[#2f5fb8] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#244f9d]"
              >
                {{ t('common.filter') }}
              </button>
              <button
                type="button"
                class="inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                @click="resetFilters"
              >
                {{ t('common.reset') }}
              </button>
            </div>
          </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-[#eef4ff] px-5 py-4">
            <h2 class="font-semibold text-[#174a9c]">{{ t('admin.users.listTitle') }}</h2>
            <span class="inline-flex min-w-10 items-center justify-center rounded-md border border-blue-200 bg-white px-3 py-1.5 text-sm font-semibold text-[#2f5fb8]">
              {{ users.length }}
            </span>
          </div>

          <div v-if="loading" class="px-5 py-6 text-sm text-gray-500">{{ t('common.loading') }}</div>
          <div v-else-if="users.length === 0" class="px-5 py-6 text-sm text-gray-600">
            {{ t('admin.users.empty') }}
          </div>

          <div v-else class="divide-y divide-gray-100">
            <div
              v-for="user in users"
              :key="user.id"
              class="grid gap-4 px-5 py-4 transition hover:bg-[#f8fbff] lg:grid-cols-[minmax(220px,1.15fr)_minmax(260px,1fr)_minmax(150px,0.65fr)_minmax(180px,0.75fr)_minmax(200px,0.85fr)_auto] lg:items-center"
            >
              <div class="min-w-0">
                <div class="truncate font-semibold text-gray-900" :title="user.name">
                  {{ user.name }}
                </div>
                <div class="mt-1 truncate text-sm text-gray-500" :title="user.email">
                  {{ user.email }}
                </div>
              </div>

              <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap gap-2">
                  <span class="inline-flex items-center rounded-full bg-[#2f5fb8] px-3 py-1 text-xs font-semibold text-white">
                    {{ roleLabel(user.role) }}
                  </span>
                  <span
                    v-if="user.is_commission_member"
                    class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-[#174a9c]"
                  >
                    {{ t('common.commission') }}
                  </span>
                </div>
                <select
                  :value="user.role || 'user'"
                  :disabled="isSaving(user.id) || authStore.user?.id === user.id"
                  @change="onChangeRole(user, $event.target.value)"
                  class="h-9 w-full max-w-[260px] rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm disabled:cursor-not-allowed disabled:bg-gray-100"
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
                  class="text-xs text-amber-700"
                >
                  {{ t('admin.users.ownRoleLocked') }}
                </div>
              </div>

              <div class="space-y-1 text-sm text-gray-700">
                <div>{{ t('admin.users.phone') }}: <span class="text-gray-500">{{ user.phone || t('common.notSpecified') }}</span></div>
                <div>ID: <span class="text-gray-500">{{ user.id }}</span></div>
              </div>

              <div class="space-y-1 text-sm text-gray-700">
                <div>
                  Email:
                  <span
                    :class="user.email_verified_at ? 'text-emerald-700' : 'text-amber-700'"
                    class="font-medium"
                  >
                    {{ user.email_verified_at ? t('admin.users.emailVerified') : t('admin.users.emailNotVerified') }}
                  </span>
                </div>
                <div>{{ t('common.created') }}: <span class="text-gray-500">{{ formatDate(user.created_at) }}</span></div>
              </div>

              <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('admin.users.positions') }}</div>
                <div
                  class="mt-1 truncate text-sm text-gray-700"
                  :title="positionsTitle(user)"
                >
                    {{ positionsSummary(user) }}
                </div>
              </div>

              <div class="flex justify-start lg:justify-end">
                <button
                  type="button"
                  :disabled="authStore.user?.id === user.id || impersonatingUserId === user.id"
                  class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-md bg-[#2f5fb8] px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[#244f9d] disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 disabled:shadow-none"
                  @click="impersonateUser(user)"
                >
                  {{ impersonatingUserId === user.id ? t('admin.users.loggingInAs') : t('admin.users.loginAs') }}
                </button>
              </div>
            </div>
          </div>
        </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import { useAuthStore } from '../stores/useAuthStore';
import { useI18n } from '../i18n';

const authStore = useAuthStore();
const router = useRouter();
const { locale, t } = useI18n();

const users = ref([]);
const loading = ref(false);
const savingUserIds = ref([]);
const impersonatingUserId = ref(null);
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

const roleOptions = computed(() => [
  { value: 'user', label: t('role.user') },
  { value: 'lawyer', label: t('role.lawyer') },
  { value: 'science_director', label: t('role.science_director') },
  { value: 'digital_director', label: t('role.digital_director') },
  { value: 'strategy_director', label: t('role.strategy_director') },
  { value: 'academic_director', label: t('role.academic_director') },
  { value: 'library_director', label: t('role.library_director') },
  { value: 'professional_development_director', label: t('role.professional_development_director') },
  { value: 'admin', label: t('role.admin') },
]);

const roleLabel = (role) => (
  roleOptions.value.find((option) => option.value === (role || 'user'))?.label || role || t('role.user')
);

const formatPosition = (position) => {
  if (!position?.department?.name) return position?.name || '';
  return `${position.name} (${position.department.name})`;
};

const positionsTitle = (user) => {
  const positions = user.positions || [];
  if (!positions.length) return t('admin.users.positionsNotAssignedTitle');

  return positions.map(formatPosition).filter(Boolean).join('\n');
};

const positionsSummary = (user) => {
  const positions = user.positions || [];
  if (!positions.length) return t('admin.users.positionsNotAssigned');

  const labels = positions.map(formatPosition).filter(Boolean);
  if (!labels.length) return t('admin.users.positionsNotAssigned');

  const [firstLabel] = labels;
  const extraCount = labels.length - 1;

  return extraCount > 0 ? `${firstLabel} +${extraCount}` : firstLabel;
};

const errorText = (error) => error?.response?.data?.message || t('validation.genericError');

const isSaving = (userId) => savingUserIds.value.includes(userId);

const resetCreateForm = () => {
  createForm.value = emptyCreateForm();
};

const resetFilters = () => {
  filters.value = {
    q: '',
    role: '',
    email_verified: '',
    commission: '',
  };
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
    alert(t('validation.users.enterName'));
    return;
  }

  if (!createForm.value.email.trim()) {
    alert(t('validation.users.enterEmail'));
    return;
  }

  if (!createForm.value.password || createForm.value.password.length < 4) {
    alert(t('validation.users.passwordLength'));
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

  if (!confirm(t('confirm.users.changeRole', { name: user.name, role: roleLabel(role) }))) return;

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

const defaultRouteForUser = (user) => {
  const role = user?.role || 'user';

  if (role === 'admin') return '/admin';
  if (role === 'lawyer') return '/compliance/applications';
  if (role === 'science_director') return '/science/applications';
  if (role === 'digital_director') return '/digital/applications';
  if (role === 'strategy_director') return '/strategy/applications';
  if (role === 'academic_director') return '/academic/applications';
  if (role === 'library_director') return '/library/applications';
  if (role === 'professional_development_director') return '/professional-development/applications';
  if (user?.is_commission_member) return '/commission/applications';

  return '/profile';
};

const impersonateUser = async (user) => {
  if (!user?.id || authStore.user?.id === user.id || impersonatingUserId.value) return;

  if (!confirm(t('confirm.users.loginAs', { name: user.name }))) return;

  impersonatingUserId.value = user.id;

  try {
    const response = await axios.post(`/api/admin/users/${user.id}/impersonate`);
    authStore.startImpersonation({
      token: response.data?.token,
      user: response.data?.user,
      impersonator: response.data?.impersonator,
    });
    router.push(defaultRouteForUser(response.data?.user));
  } catch (error) {
    alert(errorText(error));
  } finally {
    impersonatingUserId.value = null;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleString(locale.value === 'kk' ? 'kk-KZ' : 'ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
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
