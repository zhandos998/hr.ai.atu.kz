import { defineStore } from 'pinia';
import axios from 'axios';
import { normalizeRole } from '../utils/roles';

const impersonatorTokenKey = 'impersonator_token';
const impersonatorUserKey = 'impersonator_user';

const storage = () => (typeof window !== 'undefined' ? window.localStorage : null);

const readStoredImpersonator = () => {
    const value = storage()?.getItem(impersonatorUserKey);
    if (!value) return null;

    try {
        return JSON.parse(value);
    } catch (error) {
        storage()?.removeItem(impersonatorUserKey);
        return null;
    }
};

const applyAuthToken = (token) => {
    if (!token) {
        storage()?.removeItem('token');
        storage()?.removeItem('role');
        delete axios.defaults.headers.common.Authorization;
        return;
    }

    storage()?.setItem('token', token);
    axios.defaults.headers.common.Authorization = `Bearer ${token}`;
};

const clearStoredImpersonation = () => {
    storage()?.removeItem(impersonatorTokenKey);
    storage()?.removeItem(impersonatorUserKey);
};

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        role: null,
        impersonator: readStoredImpersonator(),
        loading: true, // ✅
    }),

    actions: {
        async fetchUser() {
            try {
                const response = await axios.get('/api/user');
                const user = {
                    ...response.data,
                    role: normalizeRole(response.data.role),
                };
                this.user = user;
                this.role = user.role;
                storage()?.setItem('role', user.role || 'user');
                // console.log('User loaded:', this.user);
                // console.log('Role:', this.role);
                return user; // ✅ добавляем возврат
            } catch (error) {
                this.user = null;
                this.role = null;
                console.error('Ошибка загрузки пользователя', error);
                return null; // ✅ возврат null в случае ошибки
            }
            finally {
                this.loading = false; // ✅ загрузка завершена
            }
        },

        startImpersonation({ token, user, impersonator }) {
            const currentToken = storage()?.getItem('token');

            if (!storage()?.getItem(impersonatorTokenKey) && currentToken) {
                storage()?.setItem(impersonatorTokenKey, currentToken);
            }

            const impersonatorUser = impersonator || this.user;
            if (impersonatorUser) {
                storage()?.setItem(impersonatorUserKey, JSON.stringify(impersonatorUser));
            }

            applyAuthToken(token);

            const normalizedUser = {
                ...user,
                role: normalizeRole(user?.role),
            };
            this.user = normalizedUser;
            this.role = normalizedUser.role;
            this.impersonator = readStoredImpersonator();
            storage()?.setItem('role', normalizedUser.role || 'user');
        },

        async returnToImpersonator() {
            const adminToken = storage()?.getItem(impersonatorTokenKey);

            if (!adminToken) {
                clearStoredImpersonation();
                this.impersonator = null;
                return null;
            }

            applyAuthToken(adminToken);
            clearStoredImpersonation();
            this.impersonator = null;

            const user = await this.fetchUser();
            if (!user) {
                this.logout();
            }

            return user;
        },

        logout() {
            this.user = null;
            this.role = null;
            this.impersonator = null;
            clearStoredImpersonation();
            applyAuthToken(null);
        },
    },
});
