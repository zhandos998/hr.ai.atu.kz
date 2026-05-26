import { useDialogStore } from '../stores/useDialogStore';

export const installAlertOverride = (pinia) => {
    const dialogStore = useDialogStore(pinia);

    if (!window.__nativeAlert) {
        window.__nativeAlert = window.alert.bind(window);
    }

    window.alert = (message) => {
        dialogStore.openAlert(message);
    };
};
