<template>
    <AdminPage title="Kullanıcılar">
        <template #filters>
            <InputText v-model="search" placeholder="E-posta veya ad ara..." class="w-64" @input="onSearch" />
        </template>
        <DataTable :value="users.data" :rows="25" stripedRows size="small">
            <Column field="id" header="ID" style="width: 280px">
                <template #body="{ data }">
                    <span class="text-xs font-mono text-muted-foreground">{{ data.id }}</span>
                </template>
            </Column>
            <Column field="email" header="E-posta" sortable />
            <Column field="name" header="Ad" sortable>
                <template #body="{ data }">{{ data.name ?? '—' }}</template>
            </Column>
            <Column field="created_at" header="Kayıt Tarihi" sortable style="width: 140px">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="users.links" />
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

const props = defineProps({ users: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>
