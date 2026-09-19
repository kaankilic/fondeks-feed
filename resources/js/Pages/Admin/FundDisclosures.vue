<template>
    <AdminPage title="Fon Bildirimleri (KAP)">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu veya başlık ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="disclosures.data" :rows="25" stripedRows size="small">
            <Column field="fund_code" header="Fon" sortable style="width: 80px" />
            <Column field="fund_title" header="Başlık" />
            <Column field="subject" header="Konu" />
            <Column field="published_at" header="Yayın Tarihi" sortable style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.published_at) }}</template>
            </Column>
            <Column field="is_late" header="Geç" style="width: 60px">
                <template #body="{ data }">
                    <Tag v-if="data.is_late" severity="warn" value="Geç" />
                </template>
            </Column>
            <Column field="attachment_count" header="Ekler" style="width: 60px" />
            <Column header="PDF" style="width: 60px">
                <template #body="{ data }">
                    <a v-if="data.pdf_url" :href="data.pdf_url" target="_blank" class="text-blue-600 hover:underline text-xs">
                        <i class="pi pi-external-link" />
                    </a>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="disclosures.links" />
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

const props = defineProps({ disclosures: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>
