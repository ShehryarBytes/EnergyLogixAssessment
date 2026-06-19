<template>
    <div class="max-w-3xl">
        <!-- Page header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Formula Builder</h1>
            <p class="text-sm text-gray-500 mt-1">Create a new commission formula version.</p>
        </div>

        <form @submit.prevent="save" novalidate>

            <!-- Section 1: Basic Info -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Basic Info</h2>
                <div>
                    <label for="formula-name" class="block text-sm font-medium text-gray-700 mb-1">
                        Formula Name
                    </label>
                    <input
                        id="formula-name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. Standard Commission Q3 2025"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p v-if="fieldError('name')" class="mt-1 text-sm text-red-600">{{ fieldError('name') }}</p>
                </div>
            </div>

            <!-- Section 2: Formula Expression -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Formula Expression</h2>

                <div>
                    <label for="formula-expression" class="block text-sm font-medium text-gray-700 mb-1">
                        Expression
                    </label>
                    <div class="flex gap-3 items-start">
                        <textarea
                            id="formula-expression"
                            v-model="form.expression"
                            rows="3"
                            placeholder="e.g. (AnnualUsage * 0.05) + (ContractLength * 100)"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <button
                            type="button"
                            @click="validateExpression"
                            :disabled="validating || !form.expression"
                            class="flex-shrink-0 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
                        >
                            {{ validating ? 'Checking…' : 'Validate' }}
                        </button>
                    </div>
                    <p v-if="fieldError('expression')" class="mt-1 text-sm text-red-600">{{ fieldError('expression') }}</p>

                    <!-- Validation result -->
                    <div v-if="validationResult !== null" class="mt-2">
                        <p v-if="validationResult.valid" class="text-sm text-green-600 flex items-center gap-1">
                            <span>✓</span> Formula is valid
                        </p>
                        <p v-else class="text-sm text-red-600">{{ validationResult.message }}</p>
                    </div>

                    <!-- System variable reference panel -->
                    <div class="mt-4 bg-gray-50 rounded-md border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">
                            Available System Variables
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div v-for="v in SYSTEM_VARIABLES" :key="v.name" class="flex items-start gap-2">
                                <code class="text-xs font-mono text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded flex-shrink-0">{{ v.name }}</code>
                                <span class="text-xs text-gray-500">{{ v.description }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Calculated Variables -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">
                            Calculated Variables
                            <span class="text-gray-400 font-normal ml-1">(optional)</span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Define intermediate values that the main expression can reference.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="addVariable"
                        class="bg-white text-gray-700 border border-gray-300 px-3 py-1.5 rounded-md text-sm font-medium hover:bg-gray-50"
                    >
                        + Add Variable
                    </button>
                </div>

                <div v-if="form.variables.length === 0" class="text-sm text-gray-500 py-2">
                    No calculated variables defined.
                </div>

                <div
                    v-for="(variable, index) in form.variables"
                    :key="index"
                    class="flex items-start gap-3 mb-3 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0"
                >
                    <div class="flex-1 min-w-0">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Variable Name</label>
                        <input
                            v-model="variable.name"
                            type="text"
                            placeholder="e.g. BaseCommission"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p v-if="fieldError(`variables.${index}.name`)" class="mt-1 text-xs text-red-600">
                            {{ fieldError(`variables.${index}.name`) }}
                        </p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Expression</label>
                        <div class="flex gap-2">
                            <input
                                v-model="variable.expression"
                                type="text"
                                placeholder="e.g. AnnualUsage * 0.05"
                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <button
                                type="button"
                                @click="validateVariable(index)"
                                :disabled="!variable.expression || variable.validating"
                                class="flex-shrink-0 bg-white text-gray-700 border border-gray-300 px-2.5 py-1.5 rounded-md text-xs font-medium hover:bg-gray-50 disabled:opacity-50"
                                title="Validate this expression"
                            >
                                {{ variable.validating ? '…' : 'Check' }}
                            </button>
                        </div>
                        <p v-if="variable.validationOk === true" class="mt-1 text-xs text-green-600">✓ Valid</p>
                        <p v-else-if="variable.validationError" class="mt-1 text-xs text-red-600">{{ variable.validationError }}</p>
                        <p v-if="fieldError(`variables.${index}.expression`)" class="mt-1 text-xs text-red-600">
                            {{ fieldError(`variables.${index}.expression`) }}
                        </p>
                    </div>
                    <div class="pt-5 flex-shrink-0">
                        <button
                            type="button"
                            @click="removeVariable(index)"
                            class="text-red-500 hover:text-red-700 text-sm font-medium px-2 py-1.5"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Global form error -->
            <div v-if="formError" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
                {{ formError }}
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="saving"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 flex items-center gap-2"
                >
                    <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    {{ saving ? 'Saving…' : 'Save Formula' }}
                </button>
                <button
                    type="button"
                    @click="router.push('/formulas')"
                    class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-50"
                >
                    Cancel
                </button>
            </div>

        </form>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter }     from 'vue-router';
import { useFormulaStore } from '@/stores/formula.js';

const router       = useRouter();
const formulaStore = useFormulaStore();

const SYSTEM_VARIABLES = [
    { name: 'AnnualUsage',    description: 'Annual energy usage from the contract (kWh)' },
    { name: 'ContractValue',  description: 'Total monetary value of the contract (£)' },
    { name: 'ContractLength', description: 'Duration of the contract in months' },
    { name: 'RiskScore',      description: 'Numeric risk rating assigned to the contract' },
];

const form = reactive({
    name:       '',
    expression: '',
    variables:  [],
});

const validating       = ref(false);
const validationResult = ref(null);
const saving           = ref(false);
const formError        = ref('');
const fieldErrors      = ref({});

function fieldError(key) {
    return fieldErrors.value[key]?.[0] ?? null;
}

function addVariable() {
    form.variables.push({
        name:            '',
        expression:      '',
        validating:      false,
        validationOk:    null,
        validationError: null,
    });
}

function removeVariable(index) {
    form.variables.splice(index, 1);
}

async function validateExpression() {
    validating.value       = true;
    validationResult.value = null;
    try {
        const variableNames = form.variables.map(v => ({ name: v.name, expression: v.expression }));
        const result = await formulaStore.validateExpression(form.expression, variableNames);
        validationResult.value = { valid: true };
    } catch (err) {
        validationResult.value = { valid: false, message: err.response?.data?.message ?? 'Validation failed.' };
    } finally {
        validating.value = false;
    }
}

async function validateVariable(index) {
    const variable = form.variables[index];
    variable.validating      = true;
    variable.validationOk    = null;
    variable.validationError = null;
    // Pass all other variable names as context so cross-references resolve correctly
    const allNames = form.variables.map(v => ({ name: v.name, expression: v.expression }));
    try {
        await formulaStore.validateExpression(variable.expression, allNames);
        variable.validationOk = true;
    } catch (err) {
        variable.validationError = err.response?.data?.message ?? 'Invalid expression.';
    } finally {
        variable.validating = false;
    }
}

async function save() {
    saving.value      = true;
    formError.value   = '';
    fieldErrors.value = {};

    const payload = {
        name:       form.name,
        expression: form.expression,
        variables:  form.variables.map(({ name, expression }) => ({ name, expression })),
    };

    try {
        await formulaStore.createFormula(payload);
        router.push('/formulas');
    } catch (err) {
        const response = err.response;
        if (response?.status === 422) {
            // Laravel validation errors come under `errors`, parser errors come under `message`
            if (response.data.errors) {
                fieldErrors.value = response.data.errors;
            } else {
                formError.value = response.data.message ?? 'Validation failed.';
            }
        } else {
            formError.value = response?.data?.message ?? 'An unexpected error occurred.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
