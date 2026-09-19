<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Manuel Ingest</h1>
        </template>
        <template #header-actions>
            <Button label="Yenile" icon="pi pi-refresh" size="small" text @click="refresh" :loading="refreshing" />
        </template>

        <Toast />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="job in jobs"
                :key="job.key"
                class="flex flex-col rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ job.label }}</h2>
                    <Tag v-if="job.last" :severity="statusSev(job.last.status)" :value="job.last.status" />
                    <Tag v-else severity="secondary" value="—" />
                </div>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 flex-1">{{ job.description }}</p>

                <p v-if="job.last" class="mt-2 text-[11px] text-zinc-400 dark:text-zinc-500">
                    Son: {{ formatDate(job.last.started_at) }} · {{ job.last.rows_written }} satır
                </p>

                <div v-if="job.params.length" class="mt-3 flex flex-wrap gap-2">
                    <div v-if="job.params.includes('days')" class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-wide text-zinc-400">Gün</label>
                        <InputNumber v-model="forms[job.key].days" :min="1" :max="1000" showButtons
                            buttonLayout="horizontal" size="small" inputClass="w-16 text-center"
                            :placeholder="defaultDays(job.key)" />
                    </div>
                    <div v-if="job.params.includes('limit')" class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-wide text-zinc-400">Limit</label>
                        <InputNumber v-model="forms[job.key].limit" :min="1" :max="5000"
                            size="small" inputClass="w-20" placeholder="varsayılan" />
                    </div>
                    <div v-if="job.params.includes('period')" class="flex flex-col gap-1">
                        <label class="text-[10px] uppercase tracking-wide text-zinc-400">Dönem</label>
                        <InputText v-model="forms[job.key].period" size="small" class="w-28"
                            placeholder="yyyy-mm-01" />
                    </div>
                </div>

                <Button
                    label="Çalıştır"
                    icon="pi pi-play"
                    size="small"
                    class="mt-3 w-full"
                    :loading="running === job.key"
                    @click="run(job)"
                />
            </div>
        </div>

        <div class="mt-6 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Son Çalışmalar</h2>
                <a href="/admin/ingest-runs" class="text-xs text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">Tümü</a>
            </div>
            <DataTable :value="recentRuns" size="small" stripedRows>
                <Column field="job" header="Job" style="width: 160px" />
                <Column field="status" header="Durum" style="width: 100px">
                    <template #body="{ data }">
                        <Tag :severity="statusSev(data.status)" :value="data.status" />
                    </template>
                </Column>
                <Column field="rows_read" header="Okunan" style="width: 90px">
                    <template #body="{ data }">{{ data.rows_read?.toLocaleString('tr-TR') }}</template>
                </Column>
                <Column field="rows_written" header="Yazılan" style="width: 90px">
                    <template #body="{ data }">{{ data.rows_written?.toLocaleString('tr-TR') }}</template>
                </Column>
                <Column field="started_at" header="Başlangıç" style="width: 150px">
                    <template #body="{ data }">{{ formatDate(data.started_at) }}</template>
                </Column>
                <Column field="error" header="Hata">
                    <template #body="{ data }">
                        <span v-if="data.error" class="text-xs text-red-600 line-clamp-1">{{ data.error }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Toast from 'primevue/toast';

const props = defineProps({ jobs: Array, recentRuns: Array });
const page = usePage();
const toast = useToast();

const running = ref(null);
const refreshing = ref(false);

const forms = reactive(
    Object.fromEntries(props.jobs.map((j) => [j.key, { days: null, limit: null, period: null }]))
);

const DEFAULT_DAYS = { daily: '3', indices: '5', disclosures: '7' };
const defaultDays = (key) => DEFAULT_DAYS[key] ?? 'tümü';

function run(job) {
    running.value = job.key;
    const data = { job: job.key };
    const f = forms[job.key];
    if (job.params.includes('days') && f.days) data.days = f.days;
    if (job.params.includes('limit') && f.limit) data.limit = f.limit;
    if (job.params.includes('period') && f.period) data.period = f.period;

    router.post('/admin/ingest/run', data, {
        preserveScroll: true,
        onFinish: () => { running.value = null; },
    });
}

function refresh() {
    refreshing.value = true;
    router.reload({ onFinish: () => { refreshing.value = false; } });
}

watch(() => page.props.flash, (flash) => {
    if (flash?.message) {
        toast.add({ severity: flash.type ?? 'info', summary: 'Ingest', detail: flash.message, life: 4000 });
    }
}, { immediate: true });

function statusSev(status) {
    return { success: 'success', failed: 'danger', running: 'warn' }[status] || 'info';
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' });
}
</script>
