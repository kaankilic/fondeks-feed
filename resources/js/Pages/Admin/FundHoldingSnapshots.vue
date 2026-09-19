<template>
    <AdminPage title="Holding Snapshots">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu veya ticker ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="snapshots.data" :rows="25" stripedRows size="small">
            <Column field="fund_code" header="Fon" sortable style="width: 80px" />
            <Column field="period" header="Dönem" sortable style="width: 110px" />
            <Column field="ticker" header="Ticker" sortable style="width: 100px" />
            <Column field="weight" header="Ağırlık">
                <template #body="{ data }">%{{ data.weight }}</template>
            </Column>
            <Column field="source" header="Kaynak" style="width: 80px" />
        </DataTable>
        <template #footer>
            <Pagination :links="snapshots.links" />
        </template>
    </AdminPage>
</template>

<script setup>
import AdminPage from '@/Components/AdminPage.vue';
import Pagination from '@/Components/Pagination.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { useDebouncedSearch } from '@/Composables/useSearch';

const props = defineProps({ snapshots: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
