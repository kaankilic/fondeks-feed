<template>
    <AdminPage title="Kategori Performansı">
        <DataTable :value="categories" :rows="25" stripedRows size="small">
            <Column field="category" header="Kategori" sortable />
            <Column field="y1" header="1 Yıllık Getiri" sortable>
                <template #body="{ data }">
                    <span :class="data.y1 >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        %{{ data.y1 }}
                    </span>
                </template>
            </Column>
        </DataTable>
    </AdminPage>
</template>

<script setup>
import AdminPage from '@/Components/AdminPage.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

defineProps({ categories: Array });
</script>
