<template>
  <div v-if="modelValue" class="fixed inset-0 z-[60]">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="onCancel"></div>

    <div class="relative mx-auto mt-24 w-[92%] max-w-md">
      <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b">
          <h3 class="text-base font-semibold text-[#005eb8]">{{ title }}</h3>
        </div>

        <div class="px-5 py-4 text-sm text-gray-700 whitespace-pre-line">
          {{ message }}
        </div>

        <div class="px-5 py-4 bg-gray-50 flex items-center justify-end gap-2">
          <button
            v-if="showCancel"
            class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-white transition"
            @click="onCancel"
          >
            {{ cancelText }}
          </button>
          <button
            class="px-3 py-2 rounded-lg text-white bg-[#005eb8] hover:bg-[#005eb8]/90 transition"
            @click="onConfirm"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Сообщение' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Ок' },
  cancelText: { type: String, default: 'Отмена' },
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
