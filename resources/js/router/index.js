import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

// Auth is enforced server-side (Laravel's auth middleware on the catch-all web route).
// The SPA only loads once the user is authenticated, so these routes need no client-side guard.
const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
    },
    {
        path: '/',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
    },
    {
        path: '/formulas',
        name: 'formula-list',
        component: () => import('@/views/FormulaListView.vue'),
    },
    {
        path: '/formulas/builder',
        name: 'formula-builder',
        component: () => import('@/views/FormulaBuilderView.vue'),
    },
    {
        path: '/commission',
        name: 'commission-calculator',
        component: () => import('@/views/CommissionCalculatorView.vue'),
    },
    {
        path: '/simulation',
        name: 'simulation',
        component: () => import('@/views/SimulationView.vue'),
    },
    {
        path: '/audit',
        name: 'audit-trail',
        component: () => import('@/views/AuditTrailView.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

const adminOnlyRoutes = ['/simulation'];

router.beforeEach(async (to) => {
    if (!adminOnlyRoutes.includes(to.path)) return true;

    const authStore = useAuthStore();

    // User may not be loaded yet on first navigation — wait for it
    if (!authStore.user) {
        try {
            await authStore.fetchUser();
        } catch {
            return '/';
        }
    }

    if (authStore.user?.role !== 'admin') {
        return '/';
    }

    return true;
});

export default router;
