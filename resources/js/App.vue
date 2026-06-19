<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-gray-900 h-16 flex items-center px-4 sm:px-6 lg:px-8">
            <span class="text-white font-semibold text-lg mr-8">EnergyLogix</span>

            <div class="flex items-center gap-6 flex-1">
                <RouterLink
                    v-for="link in navLinks"
                    :key="link.to"
                    :to="link.to"
                    class="text-sm text-gray-300 hover:text-white transition-colors"
                    active-class="text-white font-medium"
                >
                    {{ link.label }}
                </RouterLink>
            </div>

            <div class="flex items-center gap-4 ml-auto">
                <span class="text-sm text-gray-400">{{ authStore.user?.email }}</span>
                <button
                    @click="logout"
                    class="text-sm text-gray-300 hover:text-white transition-colors"
                >
                    Sign out
                </button>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import axios from 'axios';

const router = useRouter();
const authStore = useAuthStore();

const navLinks = [
    { to: '/',                 label: 'Home' },
    { to: '/formulas',         label: 'Formulas' },
    { to: '/formulas/builder', label: 'Builder' },
    { to: '/commission',       label: 'Commission' },
    { to: '/simulation',       label: 'Simulation' },
    { to: '/audit',            label: 'Audit Trail' },
];

onMounted(() => authStore.fetchUser());

async function logout() {
    await axios.post('/logout', {}, { headers: { Accept: 'application/json' } }).catch(() => {});
    window.location.href = '/login';
}
</script>
