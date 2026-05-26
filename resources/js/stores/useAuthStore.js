import { defineStore } from 'pinia';
import axios from 'axios';
import { normalizeRole } from '../utils/roles';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        role: null,
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


        logout() {
            this.user = null;
            this.role = null;
            localStorage.removeItem('token');
            delete axios.defaults.headers.common['Authorization'];
        },
    },
});
