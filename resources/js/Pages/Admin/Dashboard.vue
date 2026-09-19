<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-sm font-medium text-foreground">Dashboard</h1>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-xl border border-border bg-card shadow-sm p-4"
            >
                <p class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                    {{ stat.label }}
                </p>
                <p class="mt-1.5 text-xl font-semibold tracking-tight text-foreground">
                    {{ stat.value }}
                </p>
            </div>
        </div>

        <div v-if="recentIngests.length" class="mt-5 rounded-xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-4 py-3">
                <h2 class="text-sm font-medium text-foreground">Son Ingest İşlemleri</h2>
            </div>
            <DataTable :value="recentIngests" :rows="10" stripedRows size="small">
                <Column field="job" header="Job" />
                <Column field="status" header="Durum">
                    <template #body="{ data }">
                        <Tag :severity="statusSeverity(data.status)" :value="data.status" />
                    </template>
                </Column>
                <Column field="rows_written" header="Yazılan" />
                <Column field="rows_read" header="Okunan" />
                <Column field="started_at" header="Başlangıç">
                    <template #body="{ data }">
                        {{ formatDate(data.started_at) }}
                    </template>
                </Column>
            </DataTable>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';

const props = defineProps({
    stats: Array,
    recentIngests: Array,
});

function statusSeverity(status) {
    return { success: 'success', failed: 'danger', running: 'warn' }[status] || 'info';
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' });
}
</script>
