<template>
    <AdminPage title="Endeks Kotasyonları">
        <template #filters>
            <InputText v-model="search" placeholder="Endeks adı ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="quotes.data" :rows="25" stripedRows size="small">
            <Column field="index_name" header="Endeks" sortable />
            <Column field="date" header="Tarih" sortable style="width: 110px" />
            <Column field="value" header="Değer">
                <template #body="{ data }">{{ Number(data.value).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) }}</template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="quotes.links" />
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

const props = defineProps({ quotes: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
