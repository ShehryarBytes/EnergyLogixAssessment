<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="w-full max-w-sm bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <h1 class="text-xl font-semibold text-gray-900 mb-1">EnergyLogix</h1>
            <p class="text-sm text-gray-500 mb-6">Commission Engine — Sign in to continue</p>

            <div v-if="error" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                {{ error }}
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="admin@energylogix.com"
                    >
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                </div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors"
                >
                    {{ loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

const form = ref({ email: '', password: '' });
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';
    loading.value = true;

    try {
        // Fetch CSRF cookie before logging in (required for Sanctum SPA auth)
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/login', form.value, {
            headers: { Accept: 'application/json' },
        });
        router.push('/');
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong.';
    } finally {
        loading.value = false;
    }
}
</script>
