import { createRouter, createWebHistory } from 'vue-router';

const routes = [
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

export default router;
