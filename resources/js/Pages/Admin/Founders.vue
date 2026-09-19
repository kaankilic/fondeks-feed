<template>
    <AdminPage title="Kurucular">
        <template #filters>
            <InputText v-model="search" placeholder="Ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="founders.data" :rows="25" stripedRows size="small">
            <Column field="name" header="Ad" sortable />
            <Column field="initials" header="Kısaltma" />
            <Column field="color" header="Renk">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-4 w-4 rounded" :style="{ background: data.color }" />
                        <span class="text-xs text-muted-foreground">{{ data.color }}</span>
                    </div>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="founders.links" />
        </template>
    </AdminPage>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminPage from '@/Components/AdminPage.vue';
import Pagination from '@/Components/Pagination.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { useDebouncedSearch } from '@/Composables/useSearch';

const props = defineProps({ founders: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
