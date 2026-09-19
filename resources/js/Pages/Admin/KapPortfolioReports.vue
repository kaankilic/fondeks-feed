<template>
    <AdminPage title="KAP Portföy Raporları">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu veya başlık ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="reports.data" :rows="25" stripedRows size="small">
            <Column field="disclosure_index" header="ID" sortable style="width: 80px" />
            <Column field="fund_code" header="Fon" sortable style="width: 80px" />
            <Column field="fund_title" header="Başlık" />
            <Column field="period" header="Dönem" sortable style="width: 110px" />
            <Column field="status" header="Durum" sortable>
                <template #body="{ data }">
                    <Tag :severity="reportSeverity(data.status)" :value="data.status" />
                </template>
            </Column>
            <Column field="holdings_count" header="Holding" style="width: 80px">
                <template #body="{ data }">{{ data.holdings_count ?? '—' }}</template>
            </Column>
            <Column field="published_at" header="Yayın" style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.published_at) }}</template>
            </Column>
            <Column header="PDF" style="width: 60px">
                <template #body="{ data }">
                    <a v-if="data.document_url" :href="data.document_url" target="_blank" class="text-blue-600 hover:underline text-xs">
                        <i class="pi pi-external-link" />
                    </a>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="reports.links" />
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

const props = defineProps({ reports: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
const reportSeverity = (s) => ({ extracted: 'success', failed: 'danger', queued: 'warn', discovered: 'info', skipped: 'secondary', no_detail: 'secondary' }[s] || 'info');
</script>
