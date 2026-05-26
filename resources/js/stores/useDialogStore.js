import { defineStore } from 'pinia';

const DEFAULT_ALERT_TITLE = '\u0421\u043E\u043E\u0431\u0449\u0435\u043D\u0438\u0435';
const DEFAULT_CONFIRM_TITLE = '\u041F\u043E\u0434\u0442\u0432\u0435\u0440\u0436\u0434\u0435\u043D\u0438\u0435';
const DEFAULT_CANCEL_TEXT = '\u041E\u0442\u043C\u0435\u043D\u0430';

const normalizeMessage = (message) => {
    if (message === null || message === undefined) {
        return '';
    }

    return typeof message === 'string' ? message : String(message);
};

export const useDialogStore = defineStore('dialog', {
    state: () => ({
        current: null,
        queue: [],
    }),

    getters: {
        isOpen: (state) => Boolean(state.current),
    },

    actions: {
        enqueue(dialog) {
            if (this.current) {
                this.queue.push(dialog);
                return;
            }

            this.current = dialog;
        },

        openAlert(message, options = {}) {
            return new Promise((resolve) => {
                this.enqueue({
                    type: 'alert',
                    title: options.title ?? DEFAULT_ALERT_TITLE,
                    message: normalizeMessage(message),
                    confirmText: options.confirmText ?? 'OK',
                    cancelText: options.cancelText ?? DEFAULT_CANCEL_TEXT,
                    showCancel: false,
                    resolve,
                });
            });
        },

        openConfirm(message, options = {}) {
            return new Promise((resolve) => {
                this.enqueue({
                    type: 'confirm',
                    title: options.title ?? DEFAULT_CONFIRM_TITLE,
                    message: normalizeMessage(message),
                    confirmText: options.confirmText ?? 'OK',
                    cancelText: options.cancelText ?? DEFAULT_CANCEL_TEXT,
                    showCancel: true,
                    resolve,
                });
            });
        },

        settleCurrent(result) {
            const current = this.current;

            if (!current) {
                return;
            }

            this.current = null;
            current.resolve(result);
            this.current = this.queue.shift() ?? null;
        },

        confirmCurrent() {
            this.settleCurrent(true);
        },

        cancelCurrent() {
            this.settleCurrent(false);
        },

        dismissCurrent() {
            if (!this.current) {
                return;
            }

            if (this.current.type === 'confirm') {
                this.cancelCurrent();
                return;
            }

            this.confirmCurrent();
        },
    },
});
