<template>
    <nav v-if="links.length > 3" class="flex items-center gap-1">
        <template v-for="link in links" :key="link.label">
            <a
                v-if="link.url"
                :href="link.url"
                @click.prevent="visit(link.url)"
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs font-medium transition-colors"
                :class="link.active
                    ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                    : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800'"
                v-html="link.label"
            />
            <span
                v-else
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs font-medium text-zinc-300 dark:text-zinc-600"
                v-html="link.label"
            />
        </template>
    </nav>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

defineProps({ links: Array });

function visit(url) {
    router.get(url, {}, { preserveState: true });
}
</script>
