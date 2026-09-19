<template>
    <AdminPage title="KAP Extraction Batches">
        <template #filters>
            <InputText v-model="search" placeholder="Batch ID ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="batches.data" :rows="25" stripedRows size="small">
            <Column field="id" header="ID" sortable />
            <Column field="period" header="Dönem" sortable style="width: 110px" />
            <Column field="status" header="Durum" sortable>
                <template #body="{ data }">
                    <Tag :severity="data.status === 'ended' ? 'success' : 'warn'" :value="data.status" />
                </template>
            </Column>
            <Column field="request_count" header="İstek Sayısı" style="width: 100px" />
            <Column field="submitted_at" header="Gönderilme" style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.submitted_at) }}</template>
            </Column>
            <Column field="collected_at" header="Toplama" style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.collected_at) }}</template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="batches.links" />
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

const props = defineProps({ batches: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>
