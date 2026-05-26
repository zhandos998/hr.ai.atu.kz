<template>
  <AppMessageModal
    :model-value="dialogStore.isOpen"
    :title="currentDialog?.title || ''"
    :message="currentDialog?.message || ''"
    :confirm-text="currentDialog?.confirmText || 'OK'"
    :cancel-text="currentDialog?.cancelText || 'Cancel'"
    :show-cancel="Boolean(currentDialog?.showCancel)"
    @confirm="dialogStore.confirmCurrent"
    @cancel="dialogStore.dismissCurrent"
  />
</template>

<script setup>
import { computed } from 'vue';
import AppMessageModal from './AppMessageModal.vue';
import { useDialogStore } from '../stores/useDialogStore';

const dialogStore = useDialogStore();
const currentDialog = computed(() => dialogStore.current);
</script>
