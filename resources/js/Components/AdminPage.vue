<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h1 class="text-base font-semibold tracking-tight text-foreground">{{ title }}</h1>
            </div>
        </template>
        <template #header-actions>
            <slot name="actions" />
        </template>

        <div class="space-y-4">
            <div class="rounded-xl border border-border bg-card text-card-foreground shadow-sm">
                <div
                    v-if="$slots.filters || description"
                    class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                    <div v-else></div>
                    <div class="flex items-center gap-2">
                        <slot name="filters" />
                    </div>
                </div>
                <div class="overflow-hidden rounded-b-xl">
                    <slot />
                </div>
            </div>
            <div v-if="$slots.footer" class="flex items-center justify-between">
                <slot name="footer" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    description: String,
});
</script>
