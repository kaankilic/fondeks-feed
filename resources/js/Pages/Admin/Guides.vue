<template>
    <AdminPage title="Rehberler">
        <template #filters>
            <InputText v-model="search" placeholder="Başlık veya kategori ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="guides.data" :rows="25" stripedRows size="small">
            <Column field="slug" header="Slug" sortable style="width: 180px" />
            <Column field="title" header="Başlık" sortable />
            <Column field="category" header="Kategori" sortable style="width: 120px">
                <template #body="{ data }">
                    <Tag severity="info" :value="data.category" />
                </template>
            </Column>
            <Column field="reading_minutes" header="Okuma" style="width: 80px">
                <template #body="{ data }">{{ data.reading_minutes }} dk</template>
            </Column>
            <Column field="published_at" header="Yayın Tarihi" sortable style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.published_at) }}</template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="guides.links" />
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

const props = defineProps({ guides: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>
