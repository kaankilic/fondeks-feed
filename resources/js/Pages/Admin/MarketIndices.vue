<template>
    <AdminPage title="Piyasa Endeksleri">
        <template #filters>
            <InputText v-model="search" placeholder="Ad veya sembol ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="indices.data" :rows="25" stripedRows size="small">
            <Column field="name" header="Ad" sortable />
            <Column field="symbol" header="Sembol" sortable style="width: 80px" />
            <Column field="color" header="Renk">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-4 w-4 rounded" :style="{ background: data.color }" />
                        <span class="text-xs text-zinc-500">{{ data.color }}</span>
                    </div>
                </template>
            </Column>
            <Column field="unit" header="Birim" style="width: 80px" />
            <Column field="source" header="Kaynak" style="width: 80px" />
            <Column field="position" header="Sıra" style="width: 60px" />
            <Column field="is_active" header="Aktif" style="width: 60px">
                <template #body="{ data }">
                    <Tag :severity="data.is_active ? 'success' : 'danger'" :value="data.is_active ? 'Evet' : 'Hayır'" />
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="indices.links" />
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

const props = defineProps({ indices: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
