import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'Home',
        component: () => import('../pages/Home.vue')
    },
    {
        path: '/login',
        name: 'Login',
        component: () => import('../pages/Login.vue')
    },
    {
        path: '/register',
        name: 'Register',
        component: () => import('../pages/Register.vue')
    },
    {
        path: '/chat',
        name: 'Chat',
        component: () => import('../pages/Chat.vue')
    },
    {
        path: '/profile',
        name: 'Profile',
        component: () => import('../pages/Profile.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/vacancies',
        name: 'Vacancies',
        component: () => import('../pages/Vacancies.vue')
    },
    {
        path: '/upload-resume',
        name: 'UploadResume',
        component: () => import('../pages/UploadResume.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/my-applications',
        name: 'MyApplications',
        component: () => import('../pages/MyApplications.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/admin',
        name: 'AdminDashboard',
        component: () => import('../pages/AdminDashboard.vue'),
        meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
        path: '/admin/vacancies',
        name: 'AdminVacancies',
        component: () => import('../pages/AdminVacancies.vue'),
        meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
        path: '/admin/applications',
        name: 'AdminApplications',
        component: () => import('../pages/AdminApplications.vue'),
        meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
    path: '/apply',
    name: 'ApplyToVacancy',
    component: () => import('../pages/ApplyToVacancy.vue'),
    meta: { requiresAuth: true }
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;

import { useAuthStore } from '../stores/useAuthStore';

router.beforeEach(async (to, from, next) => {
    const token = localStorage.getItem('token');
    const isAuthenticated = !!token;

    if (to.meta.requiresAuth && !isAuthenticated) {
        return next('/login');
    }

    if (to.meta.requiresAdmin) {
        const authStore = useAuthStore();
        let role = authStore.role;

        if (!role) {
            const user = await authStore.fetchUser();
            role = user ? user.role : null;
        }

        if (role !== 'admin') {
            alert('У вас нет доступа к этой странице.');
            return next('/');
        }
    }

    next();
});