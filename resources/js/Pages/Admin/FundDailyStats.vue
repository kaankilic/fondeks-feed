<template>
    <AdminPage title="Günlük İstatistikler">
        <template #filters>
            <InputText v-model="search" placeholder="Fon kodu ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="stats.data" :rows="25" stripedRows size="small">
            <Column field="fund_code" header="Fon" sortable style="width: 80px" />
            <Column field="date" header="Tarih" sortable style="width: 110px" />
            <Column field="price" header="Fiyat">
                <template #body="{ data }">{{ fmt(data.price) }}</template>
            </Column>
            <Column field="total_value" header="Toplam Değer">
                <template #body="{ data }">{{ data.total_value != null ? fmtLarge(data.total_value) : '—' }}</template>
            </Column>
            <Column field="investor_count" header="Yatırımcı">
                <template #body="{ data }">{{ data.investor_count?.toLocaleString('tr-TR') ?? '—' }}</template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="stats.links" />
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

const props = defineProps({ stats: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const fmt = (v) => v != null ? Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 6 }) : '—';
const fmtLarge = (v) => v != null ? `₺${Number(v).toLocaleString('tr-TR', { maximumFractionDigits: 0 })}` : '—';
</script>
