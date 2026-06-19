<template>
    <div>
        <!-- Page header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Audit Trail</h1>
            <p class="text-sm text-gray-500 mt-1">Complete history of every commission calculation.</p>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <p class="text-sm text-gray-500">Loading calculations…</p>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!loading && calculations.length === 0"
            class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center"
        >
            <p class="text-gray-900 font-medium">No calculations yet</p>
            <p class="text-sm text-gray-500 mt-1">
                Run a commission calculation first using the Commission Calculator.
            </p>
            <RouterLink
                to="/commission"
                class="mt-4 inline-block bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700"
            >
                Go to Calculator
            </RouterLink>
        </div>

        <!-- Table -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full border-collapse">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Formula</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calculated At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="calc in calculations" :key="calc.id">
                        <!-- Data row -->
                        <tr class="divide-y divide-gray-200 hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                    {{ String(calc.id).padStart(8, '0') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ calc.contract.customer_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ calc.formula.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">v{{ calc.formula.version }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formatCurrency(calc.result) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDateTime(calc.calculated_at) }}</td>
                            <td class="px-6 py-4">
                                <button
                                    @click="toggleExpand(calc.id)"
                                    class="bg-white text-gray-700 border border-gray-300 px-3 py-1.5 rounded-md text-xs font-medium hover:bg-gray-50"
                                >
                                    {{ expandedId === calc.id ? 'Collapse' : 'View Detail' }}
                                </button>
                            </td>
                        </tr>

                        <!-- Expanded detail panel — spans all columns -->
                        <tr v-if="expandedId === calc.id">
                            <td colspan="7" class="px-0 py-0 bg-gray-50 border-t border-gray-200">
                                <div class="px-6 py-5 space-y-6">

                                    <!-- Section 1: Input Values -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                            Input Values
                                        </h3>
                                        <table class="w-full max-w-sm text-sm border border-gray-200 rounded-md overflow-hidden">
                                            <thead class="bg-white">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs text-gray-500 border-b border-gray-200">Variable</th>
                                                    <th class="px-4 py-2 text-left text-xs text-gray-500 border-b border-gray-200">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-for="[key, val] in Object.entries(calc.input_values)" :key="key">
                                                    <td class="px-4 py-2 font-mono text-xs text-indigo-700">{{ key }}</td>
                                                    <td class="px-4 py-2 text-xs text-gray-900">{{ formatNumber(val) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Section 2: Calculation Steps -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                            Calculation Steps
                                        </h3>
                                        <ol class="bg-white rounded-md border border-gray-200 p-4 space-y-1 list-decimal list-inside">
                                            <li
                                                v-for="(step, i) in calc.calculation_steps"
                                                :key="i"
                                                class="font-mono text-sm text-gray-800"
                                            >
                                                {{ step }}
                                            </li>
                                        </ol>
                                    </div>

                                    <!-- Section 3: Formula Detail -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                            Formula Detail
                                        </h3>
                                        <div class="bg-white rounded-md border border-gray-200 p-4 space-y-2">
                                            <div class="flex items-center gap-3 flex-wrap">
                                                <span class="text-sm font-medium text-gray-900">{{ calc.formula.name }}</span>
                                                <span class="text-sm text-gray-500">v{{ calc.formula.version }}</span>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- Pagination -->
            <div
                v-if="lastPage > 1"
                class="px-6 py-4 border-t border-gray-200 flex items-center justify-between"
            >
                <p class="text-sm text-gray-500">
                    Page {{ currentPage }} of {{ lastPage }}
                </p>
                <div class="flex gap-2">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="bg-white text-gray-700 border border-gray-300 px-3 py-1.5 rounded-md text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage === lastPage"
                        class="bg-white text-gray-700 border border-gray-300 px-3 py-1.5 rounded-md text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api.js';

const calculations = ref([]);
const loading      = ref(false);
const currentPage  = ref(1);
const lastPage     = ref(1);
const expandedId   = ref(null);

onMounted(() => fetchPage(1));

async function fetchPage(page) {
    loading.value    = true;
    expandedId.value = null;
    try {
        const { data } = await api.get(`/audit?page=${page}`);
        calculations.value = data.data;
        currentPage.value  = data.meta.current_page;
        lastPage.value     = data.meta.last_page;
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > lastPage.value) return;
    fetchPage(page);
}

function toggleExpand(id) {
    // Expanding a new row collapses the previous one
    expandedId.value = expandedId.value === id ? null : id;
}

function formatCurrency(value) {
    return '£' + Number(value).toLocaleString('en-GB', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
}

function formatNumber(value) {
    return Number(value).toLocaleString('en-GB');
}

function formatDateTime(isoString) {
    return new Date(isoString).toLocaleString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>
