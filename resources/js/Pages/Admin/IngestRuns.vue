<template>
    <AdminPage title="Ingest Runs">
        <template #filters>
            <InputText v-model="search" placeholder="Job adı ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="runs.data" :rows="25" stripedRows size="small">
            <Column field="job" header="Job" sortable style="width: 160px" />
            <Column field="status" header="Durum" sortable style="width: 100px">
                <template #body="{ data }">
                    <Tag :severity="statusSev(data.status)" :value="data.status" />
                </template>
            </Column>
            <Column field="rows_read" header="Okunan" style="width: 80px">
                <template #body="{ data }">{{ data.rows_read?.toLocaleString('tr-TR') }}</template>
            </Column>
            <Column field="rows_written" header="Yazılan" style="width: 80px">
                <template #body="{ data }">{{ data.rows_written?.toLocaleString('tr-TR') }}</template>
            </Column>
            <Column field="started_at" header="Başlangıç" sortable style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.started_at) }}</template>
            </Column>
            <Column field="finished_at" header="Bitiş" style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.finished_at) }}</template>
            </Column>
            <Column field="error" header="Hata">
                <template #body="{ data }">
                    <span v-if="data.error" class="text-xs text-red-600 line-clamp-1">{{ data.error }}</span>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="runs.links" />
        </template>
    </AdminPage>
</template>

<script setup>
import AdminPage from '@/Components/AdminPage.vue';
import Pagination from '@/Components/Pagination.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useDebouncedSearch } from '@/Composables/useSearch';

const props = defineProps({ runs: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
const statusSev = (s) => ({ success: 'success', failed: 'danger', running: 'warn' }[s] || 'info');
</script>
