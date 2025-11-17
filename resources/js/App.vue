<template>
    <router-view />
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/useAuthStore';
import axios from 'axios';

const authStore = useAuthStore();

onMounted(() => {
    const token = localStorage.getItem('token');
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    authStore.fetchUser();
});
</script>
