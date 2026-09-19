<template>
    <AdminPage title="Fonlar">
        <template #filters>
            <InputText v-model="search" placeholder="Kod, ad veya kurucu ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="funds.data" :rows="25" stripedRows size="small">
            <Column field="code" header="Kod" sortable style="width: 90px">
                <template #body="{ data }">
                    <Link :href="`/admin/funds/${data.code}`" class="font-medium text-foreground hover:underline">
                        {{ data.code }}
                    </Link>
                </template>
            </Column>
            <Column field="name" header="Ad" sortable>
                <template #body="{ data }">
                    <Link :href="`/admin/funds/${data.code}`" class="hover:underline">{{ data.name }}</Link>
                </template>
            </Column>
            <Column field="founder" header="Kurucu" sortable />
            <Column field="category" header="Kategori" sortable />
            <Column field="fund_type" header="Tip" style="width: 60px" />
            <Column field="management_fee" header="Yönetim Ücr.">
                <template #body="{ data }">{{ data.management_fee != null ? `%${data.management_fee}` : '—' }}</template>
            </Column>
            <Column field="risk" header="Risk" style="width: 60px">
                <template #body="{ data }">{{ data.risk ?? '—' }}</template>
            </Column>
            <Column field="is_active" header="Aktif" style="width: 60px">
                <template #body="{ data }">
                    <Tag :severity="data.is_active ? 'success' : 'danger'" :value="data.is_active ? 'Evet' : 'Hayır'" />
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="funds.links" />
        </template>
    </AdminPage>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminPage from '@/Components/AdminPage.vue';
import Pagination from '@/Components/Pagination.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useDebouncedSearch } from '@/Composables/useSearch';

const props = defineProps({ funds: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
</script>
