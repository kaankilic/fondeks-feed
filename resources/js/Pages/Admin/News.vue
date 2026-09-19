<template>
    <AdminPage title="Haberler">
        <template #filters>
            <InputText v-model="search" placeholder="Başlık, sembol veya yayıncı ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="news.data" :rows="25" stripedRows size="small">
            <Column field="source" header="Kaynak" sortable style="width: 80px">
                <template #body="{ data }">
                    <Tag :severity="data.source === 'kap' ? 'info' : 'secondary'" :value="data.source" />
                </template>
            </Column>
            <Column field="title" header="Başlık" />
            <Column field="symbol" header="Sembol" style="width: 80px">
                <template #body="{ data }">{{ data.symbol ?? '—' }}</template>
            </Column>
            <Column field="publisher" header="Yayıncı" style="width: 140px">
                <template #body="{ data }">{{ data.publisher ?? '—' }}</template>
            </Column>
            <Column field="published_at" header="Tarih" sortable style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.published_at) }}</template>
            </Column>
            <Column header="Link" style="width: 60px">
                <template #body="{ data }">
                    <a v-if="data.url" :href="data.url" target="_blank" class="text-blue-600 hover:underline text-xs">
                        <i class="pi pi-external-link" />
                    </a>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="news.links" />
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

const props = defineProps({ news: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>
