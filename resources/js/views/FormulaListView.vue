<template>
    <div>
        <!-- Page header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Formula Versions</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and activate commission formula versions.</p>
            </div>
            <RouterLink
                v-if="isAdmin"
                to="/formulas/builder"
                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                Create New Formula
            </RouterLink>
        </div>

        <!-- Success / error banners -->
        <div v-if="successMessage" class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ successMessage }}
        </div>
        <div v-if="actionError" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
            {{ actionError }}
        </div>

        <!-- Loading state -->
        <div v-if="formulaStore.loading" class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <p class="text-sm text-gray-500">Loading formulas…</p>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!formulaStore.loading && formulaStore.formulas.length === 0"
            class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center"
        >
            <p class="text-gray-900 font-medium">No formulas yet</p>
            <p class="text-sm text-gray-500 mt-1">Create your first commission formula to get started.</p>
            <RouterLink
                v-if="isAdmin"
                to="/formulas/builder"
                class="mt-4 inline-block bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700"
            >
                Create formula
            </RouterLink>
        </div>

        <!-- Formulas table -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full border-collapse">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expression</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activated</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="formula in formulaStore.formulas" :key="formula.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formula.name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">v{{ formula.version }}</td>
                        <td class="px-6 py-4">
                            <span :class="statusBadgeClass(formula.status)"
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                {{ formula.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 font-mono max-w-xs">
                            <span class="block truncate" :title="formula.expression">{{ formula.expression }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ formula.activated_at ? formatDate(formula.activated_at) : '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ formula.created_by ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <button
                                    @click="openViewModal(formula)"
                                    class="bg-white text-gray-700 border border-gray-300 px-3 py-1.5 rounded-md text-xs font-medium hover:bg-gray-50"
                                >
                                    View
                                </button>
                                <button
                                    v-if="formula.status === 'draft' && isAdmin"
                                    :disabled="activatingId === formula.id"
                                    @click="handleActivate(formula)"
                                    class="bg-indigo-600 text-white px-3 py-1.5 rounded-md text-xs font-medium hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ activatingId === formula.id ? 'Activating…' : 'Activate' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- View Formula Modal -->
        <Teleport to="body">
            <div v-if="viewingFormula" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-600 bg-opacity-50" @click="viewingFormula = null"></div>
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ viewingFormula.name }}</h2>
                            <p class="text-sm text-gray-500">
                                Version {{ viewingFormula.version }} ·
                                <span :class="statusBadgeClass(viewingFormula.status)"
                                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ml-1">
                                    {{ viewingFormula.status }}
                                </span>
                            </p>
                        </div>
                        <button @click="viewingFormula = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase mb-1">Expression</p>
                            <p class="font-mono text-sm text-gray-900 bg-gray-50 rounded p-3">{{ viewingFormula.expression }}</p>
                        </div>
                        <div v-if="modalLoading" class="text-sm text-gray-500">Loading variables…</div>
                        <div v-else-if="modalVariables.length > 0">
                            <p class="text-xs font-medium text-gray-500 uppercase mb-2">Calculated Variables</p>
                            <table class="w-full text-sm border border-gray-200 rounded-md overflow-hidden">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Expression</th>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Order</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="v in modalVariables" :key="v.id">
                                        <td class="px-4 py-2 font-mono text-indigo-600">{{ v.variable_name }}</td>
                                        <td class="px-4 py-2 font-mono text-gray-700">{{ v.expression }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ v.sort_order }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase mb-1">Created By</p>
                                <p class="text-gray-900">{{ viewingFormula.created_by ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase mb-1">Created At</p>
                                <p class="text-gray-900">{{ formatDate(viewingFormula.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button @click="viewingFormula = null"
                                class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-50">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFormulaStore } from '@/stores/formula.js';
import { useAuthStore }    from '@/stores/auth.js';
import api                 from '@/services/api.js';

const formulaStore = useFormulaStore();
const authStore    = useAuthStore();

const isAdmin        = computed(() => authStore.user?.role === 'admin');
const activatingId   = ref(null);
const successMessage = ref('');
const actionError    = ref('');
const viewingFormula = ref(null);
const modalVariables = ref([]);
const modalLoading   = ref(false);

onMounted(() => formulaStore.fetchFormulas());

const STATUS_BADGE = {
    active:   'bg-green-100 text-green-800',
    draft:    'bg-gray-100 text-gray-700',
    archived: 'bg-red-100 text-red-700',
};

function statusBadgeClass(status) {
    return STATUS_BADGE[status] ?? 'bg-gray-100 text-gray-700';
}

function formatDate(isoString) {
    return new Date(isoString).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

async function openViewModal(formula) {
    viewingFormula.value = formula;
    modalVariables.value = [];
    modalLoading.value   = true;
    try {
        const { data } = await api.get(`/formulas/${formula.id}`);
        modalVariables.value = data.data.variables ?? [];
    } finally {
        modalLoading.value = false;
    }
}

async function handleActivate(formula) {
    if (!window.confirm(`Activate "${formula.name} v${formula.version}"? This will archive the currently active formula.`)) {
        return;
    }
    actionError.value    = '';
    successMessage.value = '';
    activatingId.value   = formula.id;
    try {
        await formulaStore.activateFormula(formula.id);
        successMessage.value = `"${formula.name} v${formula.version}" is now active.`;
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'Activation failed.';
    } finally {
        activatingId.value = null;
    }
}
</script>
