<template>
    <div class="flex min-h-[160px] flex-col items-center justify-center rounded-lg border border-dashed border-border text-center">
        <i class="pi pi-inbox mb-2 text-xl text-muted-foreground/50"></i>
        <p class="text-sm text-muted-foreground">{{ label }}</p>
    </div>
</template>

<script setup>
defineProps({ label: { type: String, default: 'Kayıt yok' } });
</script>
