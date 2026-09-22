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
            <Column header="Çıkarım" style="width: 110px">
                <template #body="{ data }">
                    <Button
                        v-if="isPortfolioReport(data)"
                        label="Çıkar"
                        icon="pi pi-sparkles"
                        size="small"
                        severity="help"
                        text
                        :loading="extracting.has(data.disclosure_index)"
                        :disabled="extracting.size > 0"
                        @click="extract(data)"
                    />
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="disclosures.links" />
        </template>
        <Toast />
    </AdminPage>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import AdminPage from '@/Components/AdminPage.vue';
import Pagination from '@/Components/Pagination.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useDebouncedSearch } from '@/Composables/useSearch';

const PORTFOLIO_SUBJECT = 'Portföy Dağılım Raporu';

const props = defineProps({ disclosures: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const page = usePage();
const toast = useToast();
const extracting = ref(new Set());

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
const isPortfolioReport = (row) => (row.subject || '').trim() === PORTFOLIO_SUBJECT;

// Reading a PDF with Haiku takes a while; the button shows a spinner and other
// rows are disabled until it returns, so a run cannot be double-fired.
function extract(row) {
    const id = row.disclosure_index;
    extracting.value = new Set(extracting.value).add(id);

    router.post(`/admin/fund-disclosures/${id}/extract`, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            const next = new Set(extracting.value);
            next.delete(id);
            extracting.value = next;
        },
    });
}

watch(() => page.props.flash, (flash) => {
    if (flash?.message) {
        toast.add({ severity: flash.type ?? 'info', summary: 'Çıkarım', detail: flash.message, life: 6000 });
    }
});
</script>
