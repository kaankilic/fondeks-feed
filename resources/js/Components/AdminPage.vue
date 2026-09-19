<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-sm font-medium text-zinc-900 dark:text-zinc-50">{{ title }}</h1>
        </template>

        <div class="space-y-4">
            <div v-if="$slots.filters" class="flex items-center gap-3">
                <slot name="filters" />
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                <slot />
            </div>
            <slot name="footer" />
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
});
</script>
