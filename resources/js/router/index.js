import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/useAuthStore';
import { isLegalRole, normalizeRole } from '../utils/roles';

const routes = [
  { path: '/', name: 'Home', component: () => import('../pages/Home.vue') },
  { path: '/login', name: 'Login', component: () => import('../pages/Login.vue') },
  { path: '/register', name: 'Register', component: () => import('../pages/Register.vue') },
  { path: '/verify-email', name: 'VerifyEmail', component: () => import('../pages/VerifyEmail.vue') },
  { path: '/forgot-password', name: 'ForgotPassword', component: () => import('../pages/ForgotPassword.vue') },
  { path: '/reset-password', name: 'ResetPassword', component: () => import('../pages/ResetPassword.vue') },
  { path: '/email-change-confirm', name: 'EmailChangeConfirm', component: () => import('../pages/EmailChangeConfirm.vue') },
  { path: '/chat', name: 'Chat', component: () => import('../pages/Chat.vue') },
  { path: '/profile', name: 'Profile', component: () => import('../pages/Profile.vue'), meta: { requiresAuth: true } },
  { path: '/profile/change-password', name: 'ChangePassword', component: () => import('../pages/ChangePassword.vue'), meta: { requiresAuth: true } },
  { path: '/profile/change-email', name: 'ChangeEmail', component: () => import('../pages/ChangeEmail.vue'), meta: { requiresAuth: true } },
  { path: '/vacancies', name: 'Vacancies', component: () => import('../pages/Vacancies.vue') },
  { path: '/upload-resume', name: 'UploadResume', component: () => import('../pages/UploadResume.vue'), meta: { requiresAuth: true } },
  { path: '/my-applications', name: 'MyApplications', component: () => import('../pages/MyApplications.vue'), meta: { requiresAuth: true } },

  { path: '/admin', name: 'AdminDashboard', component: () => import('../pages/AdminDashboard.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/vacancies', name: 'AdminVacancies', component: () => import('../pages/AdminVacancies.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/vacancies/create', name: 'AdminVacancyCreate', component: () => import('../pages/AdminVacancyCreate.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/applications', name: 'AdminApplications', component: () => import('../pages/AdminApplications.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/applications/:id', name: 'AdminApplicationDetails', component: () => import('../pages/AdminApplicationDetails.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/departments', name: 'AdminDepartments', component: () => import('../pages/AdminDepartments.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/departments/create', name: 'AdminDepartmentCreate', component: () => import('../pages/AdminDepartmentCreate.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/departments/:id/edit', name: 'AdminDepartmentEdit', component: () => import('../pages/AdminDepartmentEdit.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/positions', name: 'AdminPositions', component: () => import('../pages/AdminPositions.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/positions/create', name: 'AdminPositionCreate', component: () => import('../pages/AdminPositionCreate.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/structure-tree', name: 'AdminStructureTree', component: () => import('../pages/AdminStructureTree.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/commission-members', name: 'AdminCommissionMembers', component: () => import('../pages/AdminCommissionMembers.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/users', name: 'AdminUsers', component: () => import('../pages/AdminUsers.vue'), meta: { requiresAuth: true, requiresAdmin: true } },

  { path: '/lawyer/applications', name: 'LawyerApplications', component: () => import('../pages/LawyerApplications.vue'), meta: { requiresAuth: true, requiresLawyer: true } },
  { path: '/science/applications', name: 'ScienceApplications', component: () => import('../pages/ScienceApplications.vue'), meta: { requiresAuth: true, requiresScienceDirector: true } },
  { path: '/science/applications/:id', name: 'ScienceApplicationDetails', component: () => import('../pages/ScienceApplicationDetails.vue'), meta: { requiresAuth: true, requiresScienceDirector: true } },
  { path: '/digital/applications', name: 'DigitalApplications', component: () => import('../pages/DigitalApplications.vue'), meta: { requiresAuth: true, requiresDigitalDirector: true } },
  { path: '/digital/applications/:id', name: 'DigitalApplicationDetails', component: () => import('../pages/DigitalApplicationDetails.vue'), meta: { requiresAuth: true, requiresDigitalDirector: true } },
  { path: '/strategy/applications', name: 'StrategyApplications', component: () => import('../pages/StrategyApplications.vue'), meta: { requiresAuth: true, requiresStrategyDirector: true } },
  { path: '/strategy/applications/:id', name: 'StrategyApplicationDetails', component: () => import('../pages/StrategyApplicationDetails.vue'), meta: { requiresAuth: true, requiresStrategyDirector: true } },
  { path: '/academic/applications', name: 'AcademicApplications', component: () => import('../pages/AcademicApplications.vue'), meta: { requiresAuth: true, requiresAcademicDirector: true } },
  { path: '/academic/applications/:id', name: 'AcademicApplicationDetails', component: () => import('../pages/AcademicApplicationDetails.vue'), meta: { requiresAuth: true, requiresAcademicDirector: true } },
  { path: '/library/applications', name: 'LibraryApplications', component: () => import('../pages/LibraryApplications.vue'), meta: { requiresAuth: true, requiresLibraryDirector: true } },
  { path: '/library/applications/:id', name: 'LibraryApplicationDetails', component: () => import('../pages/LibraryApplicationDetails.vue'), meta: { requiresAuth: true, requiresLibraryDirector: true } },
  { path: '/compliance/applications', name: 'ComplianceApplications', component: () => import('../pages/ComplianceApplications.vue'), meta: { requiresAuth: true, requiresLawyer: true } },
  { path: '/compliance/applications/:id', name: 'ComplianceApplicationDetails', component: () => import('../pages/ComplianceApplicationDetails.vue'), meta: { requiresAuth: true, requiresLawyer: true } },
  { path: '/commission/applications', name: 'CommissionApplications', component: () => import('../pages/CommissionApplications.vue'), meta: { requiresAuth: true, requiresCommission: true } },
  { path: '/commission/applications/:id', name: 'CommissionApplicationDetails', component: () => import('../pages/CommissionApplicationDetails.vue'), meta: { requiresAuth: true, requiresCommission: true } },

  { path: '/apply', name: 'ApplyToVacancy', component: () => import('../pages/ApplyToVacancy.vue'), meta: { requiresAuth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

const denyAccess = (next) => {
  alert('У вас нет доступа к этой странице.');
  return next('/');
};

router.beforeEach(async (to, from, next) => {
  const token = localStorage.getItem('token');
  const isAuthenticated = Boolean(token);

  if (to.meta.requiresAuth && !isAuthenticated) {
    return next({ name: 'Login', query: { next: to.fullPath } });
  }

  const authStore = useAuthStore();
  let role = authStore.role;

  if ((to.meta.requiresAdmin
    || to.meta.requiresLawyer
    || to.meta.requiresScienceDirector
    || to.meta.requiresDigitalDirector
    || to.meta.requiresStrategyDirector
    || to.meta.requiresAcademicDirector
    || to.meta.requiresLibraryDirector
    || to.meta.requiresCommission) && !authStore.user) {
    const user = await authStore.fetchUser();
    role = user ? normalizeRole(user.role) : null;
  }

  if (to.meta.requiresAdmin && role !== 'admin') {
    return denyAccess(next);
  }

  if (to.meta.requiresLawyer && !isLegalRole(role)) {
    return denyAccess(next);
  }

  if (to.meta.requiresScienceDirector && role !== 'science_director') {
    return denyAccess(next);
  }

  if (to.meta.requiresDigitalDirector && role !== 'digital_director') {
    return denyAccess(next);
  }

  if (to.meta.requiresStrategyDirector && role !== 'strategy_director') {
    return denyAccess(next);
  }

  if (to.meta.requiresAcademicDirector && role !== 'academic_director') {
    return denyAccess(next);
  }

  if (to.meta.requiresLibraryDirector && role !== 'library_director') {
    return denyAccess(next);
  }

  if (to.meta.requiresCommission && !authStore.user?.is_commission_member) {
    return denyAccess(next);
  }

  next();
});

export default router;
