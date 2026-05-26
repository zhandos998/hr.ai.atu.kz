<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[60] px-4 py-8 sm:px-6">
      <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" @click="onCancel"></div>

      <div class="relative mx-auto flex min-h-full w-full max-w-md items-center justify-center">
        <div class="w-full overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_28px_70px_-24px_rgba(15,23,42,0.45)]">
          <div class="border-b border-slate-200 bg-gradient-to-r from-[#005eb8] via-[#0b6fcd] to-[#4ea7ff] px-5 py-4 text-white">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/16 ring-1 ring-white/20">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M12 8v4m0 4h.01M10.29 3.86l-7.5 13A1 1 0 003.67 18h16.66a1 1 0 00.88-1.14l-7.5-13a1 1 0 00-1.74 0z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-base font-semibold leading-6">{{ title }}</h3>
                <p class="mt-1 text-sm text-white/80">{{ '\u0421\u0438\u0441\u0442\u0435\u043C\u043D\u043E\u0435 \u0443\u0432\u0435\u0434\u043E\u043C\u043B\u0435\u043D\u0438\u0435' }}</p>
              </div>
            </div>
          </div>

          <div class="px-5 py-5 text-sm leading-6 text-slate-700 whitespace-pre-line">
            {{ message }}
          </div>

          <div class="flex items-center justify-end gap-2 bg-slate-50 px-5 py-4">
            <button
              v-if="showCancel"
              class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white"
              @click="onCancel"
            >
              {{ cancelText }}
            </button>
            <button
              class="rounded-xl bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#005eb8]/90"
              @click="onConfirm"
            >
              {{ confirmText }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '\u0421\u043E\u043E\u0431\u0449\u0435\u043D\u0438\u0435' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'OK' },
  cancelText: { type: String, default: '\u041E\u0442\u043C\u0435\u043D\u0430' },
  showCancel: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

const onConfirm = () => {
  emit('confirm');
  emit('update:modelValue', false);
};

const onCancel = () => {
  emit('cancel');
  emit('update:modelValue', false);
};
</script>
