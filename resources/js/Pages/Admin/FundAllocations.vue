<template>
    <AdminPage title="Fon Dağılımları">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu veya etiket ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="allocations.data" :rows="25" stripedRows size="small">
            <Column field="fund_code" header="Fon" sortable style="width: 80px" />
            <Column field="date" header="Tarih" sortable style="width: 110px" />
            <Column field="label" header="Etiket" sortable />
            <Column field="pct" header="Oran">
                <template #body="{ data }">%{{ data.pct }}</template>
            </Column>
            <Column field="position" header="Sıra" style="width: 60px" />
        </DataTable>
        <template #footer>
            <Pagination :links="allocations.links" />
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

const props = defineProps({ allocations: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
