<template>
    <AdminPage title="Fon Benzerlikleri">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="similarities.data" :rows="25" stripedRows size="small">
            <Column field="fund_code" header="Fon" sortable style="width: 100px" />
            <Column field="peer_code" header="Benzer Fon" sortable style="width: 100px" />
            <Column field="peer_label" header="Etiket" />
            <Column field="similarity" header="Benzerlik" sortable>
                <template #body="{ data }">%{{ data.similarity }}</template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="similarities.links" />
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

const props = defineProps({ similarities: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
