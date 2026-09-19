<template>
    <nav v-if="links.length > 3" class="flex items-center gap-1">
        <template v-for="link in links" :key="link.label">
            <a
                v-if="link.url"
                :href="link.url"
                @click.prevent="visit(link.url)"
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2.5 text-xs font-medium transition-colors"
                :class="link.active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border bg-background text-foreground hover:bg-accent hover:text-accent-foreground'"
                v-html="link.label"
            />
            <span
                v-else
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs font-medium text-muted-foreground/50"
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
