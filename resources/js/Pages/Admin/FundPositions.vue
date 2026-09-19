<template>
    <AdminPage title="Pozisyonlar">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu veya ticker ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="positions.data" :rows="25" stripedRows size="small">
            <Column field="fund_code" header="Fon" sortable style="width: 80px" />
            <Column field="ticker" header="Ticker" sortable style="width: 100px" />
            <Column field="period" header="Dönem" sortable style="width: 110px" />
            <Column field="direction" header="Yön">
                <template #body="{ data }">
                    <Tag :severity="data.direction === 'increased' ? 'success' : 'danger'" :value="data.direction === 'increased' ? 'Artış' : 'Azalış'" />
                </template>
            </Column>
            <Column field="weight" header="Ağırlık">
                <template #body="{ data }">%{{ data.weight }}</template>
            </Column>
            <Column field="change_points" header="Değişim">
                <template #body="{ data }">{{ data.change_points > 0 ? '+' : '' }}{{ data.change_points }} bp</template>
            </Column>
            <Column field="rank" header="Sıra" style="width: 60px" />
        </DataTable>
        <template #footer>
            <Pagination :links="positions.links" />
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

const props = defineProps({ positions: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
