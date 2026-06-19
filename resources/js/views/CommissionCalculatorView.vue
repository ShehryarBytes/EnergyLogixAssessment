<template>
    <div class="max-w-3xl">

        <!-- Page header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Commission Calculator</h1>
            <p class="text-sm text-gray-500 mt-1">Calculate commission for a contract using the active formula.</p>
        </div>

        <!-- Active formula banner -->
        <div v-if="commissionStore.loadingFormula" class="mb-4 rounded-md bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-500">
            Loading active formula…
        </div>
        <div v-else-if="commissionStore.activeFormula" class="mb-4 rounded-md bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-700">
            Active formula:
            <span class="font-semibold">{{ commissionStore.activeFormula.name }}</span>
            <span class="ml-1">v{{ commissionStore.activeFormula.version }}</span>
        </div>
        <div v-else class="mb-4 rounded-md bg-amber-50 border border-amber-300 px-4 py-3 text-sm text-amber-800">
            No active formula configured — please activate a formula before calculating.
        </div>

        <!-- Error banner -->
        <div v-if="commissionStore.error" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
            {{ commissionStore.error }}
        </div>

        <!-- Contract selection card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Select Contract</h2>

            <div v-if="commissionStore.loadingContracts" class="text-sm text-gray-500">
                Loading contracts…
            </div>
            <div v-else>
                <label for="contract-select" class="block text-sm font-medium text-gray-700 mb-1">Contract</label>
                <select
                    id="contract-select"
                    v-model="selectedContractId"
                    class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">— Select a contract —</option>
                    <option
                        v-for="contract in commissionStore.contracts"
                        :key="contract.id"
                        :value="contract.id"
                    >
                        {{ contract.customer_name }}
                    </option>
                </select>

                <!-- Contract summary panel -->
                <div v-if="selectedContract" class="mt-4 bg-gray-50 rounded-md border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Contract Details</p>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Annual Usage</p>
                            <p class="text-sm font-medium text-gray-900">{{ formatNumber(selectedContract.annual_usage) }} kWh</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Contract Value</p>
                            <p class="text-sm font-medium text-gray-900">£{{ formatNumber(selectedContract.contract_value) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Contract Length</p>
                            <p class="text-sm font-medium text-gray-900">{{ selectedContract.contract_length }} months</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Risk Score</p>
                            <p class="text-sm font-medium text-gray-900">{{ selectedContract.risk_score }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calculate button -->
        <div class="mb-6">
            <button
                @click="runCalculation"
                :disabled="!canCalculate || commissionStore.calculating"
                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 flex items-center gap-2"
            >
                <span
                    v-if="commissionStore.calculating"
                    class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
                ></span>
                {{ commissionStore.calculating ? 'Calculating…' : 'Calculate Commission' }}
            </button>
        </div>

        <!-- Results panel -->
        <div
            v-if="result"
            class="bg-white rounded-lg shadow-sm border border-gray-200 border-l-4 border-l-green-500 p-6 mb-6"
        >
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Calculation Result</h2>

            <!-- Commission amount — large and prominent -->
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Commission</p>
                <p class="text-4xl font-bold text-gray-900">{{ formatCurrency(result.result) }}</p>
            </div>

            <!-- Metadata row -->
            <div class="flex flex-wrap gap-x-8 gap-y-2 mb-6 text-sm">
                <div>
                    <span class="text-gray-500">Formula:</span>
                    <span class="ml-1 font-medium text-gray-900">{{ result.formula.name }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Version:</span>
                    <span class="ml-1 font-medium text-gray-900">v{{ result.formula.version }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Calculated:</span>
                    <span class="ml-1 font-medium text-gray-900">{{ formatDateTime(result.calculated_at) }}</span>
                </div>
            </div>

            <!-- Input values -->
            <div class="mb-6">
                <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Input Values</h3>
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-200">Variable</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-200">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="[key, val] in Object.entries(result.input_values)" :key="key" class="border-b border-gray-200">
                            <td class="px-4 py-2 font-mono text-indigo-700 border border-gray-200">{{ key }}</td>
                            <td class="px-4 py-2 text-gray-900 border border-gray-200">{{ formatNumber(val) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Calculation steps — audit trail, always visible -->
            <div>
                <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Calculation Steps</h3>
                <ol class="bg-gray-50 rounded-md border border-gray-200 p-4 space-y-1 list-decimal list-inside">
                    <li
                        v-for="(step, index) in result.calculation_steps"
                        :key="index"
                        class="font-mono text-sm text-gray-800"
                    >
                        {{ step }}
                    </li>
                </ol>
            </div>
        </div>

        <!-- Post-result actions -->
        <div v-if="result" class="flex items-center gap-3">
            <RouterLink
                to="/audit"
                class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-50"
            >
                View Full Audit Trail
            </RouterLink>
            <button
                @click="reset"
                class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-50"
            >
                Calculate Again
            </button>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCommissionStore } from '@/stores/commission.js';

const commissionStore = useCommissionStore();

const selectedContractId = ref('');
const result             = ref(null);

onMounted(async () => {
    await Promise.all([
        commissionStore.fetchContracts(),
        commissionStore.fetchActiveFormula(),
    ]);
});

const selectedContract = computed(() =>
    commissionStore.contracts.find(c => c.id === Number(selectedContractId.value)) ?? null
);

const canCalculate = computed(() =>
    selectedContractId.value !== '' && commissionStore.activeFormula !== null
);

async function runCalculation() {
    result.value = null;
    try {
        result.value = await commissionStore.calculate(Number(selectedContractId.value));
    } catch {
        // error is already set in the store
    }
}

function reset() {
    result.value             = null;
    selectedContractId.value = '';
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
