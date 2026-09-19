<template>
    <AdminPage title="Semboller">
        <template #filters>
            <InputText v-model="search" placeholder="Ticker veya ad ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="symbols.data" :rows="25" stripedRows size="small">
            <Column field="ticker" header="Ticker" sortable style="width: 100px" />
            <Column field="name" header="Ad" sortable />
            <Column field="color" header="Renk">
                <template #body="{ data }">
                    <div v-if="data.color" class="flex items-center gap-2">
                        <span class="inline-block h-4 w-4 rounded" :style="{ background: data.color }" />
                        <span class="text-xs text-muted-foreground">{{ data.color }}</span>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="symbols.links" />
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

const props = defineProps({ symbols: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
