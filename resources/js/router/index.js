import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/formulas',
        name: 'formula-list',
        component: () => import('@/views/FormulaListView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/formulas/builder',
        name: 'formula-builder',
        component: () => import('@/views/FormulaBuilderView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/commission',
        name: 'commission-calculator',
        component: () => import('@/views/CommissionCalculatorView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/simulation',
        name: 'simulation',
        component: () => import('@/views/SimulationView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/audit',
        name: 'audit-trail',
        component: () => import('@/views/AuditTrailView.vue'),
        meta: { requiresAuth: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard: protected routes probe the API to check session state.
// Guest-only routes redirect home if already authenticated.
router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth && !to.meta.guest) return true;

    try {
        await import('@/services/api.js').then(({ default: api }) => api.get('/formulas?limit=1'));
        // Request succeeded → session is active
        if (to.meta.guest) return { name: 'home' };
        return true;
    } catch {
        // 401 or network error → not authenticated
        if (to.meta.requiresAuth) return { name: 'login' };
        return true;
    }
});

export default router;
