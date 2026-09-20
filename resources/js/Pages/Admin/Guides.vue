<template>
    <AdminPage title="Rehberler">
        <template #actions>
            <Link href="/admin/guides/create">
                <Button label="Yeni Rehber" icon="pi pi-plus" size="small" />
            </Link>
        </template>
        <template #filters>
            <InputText v-model="search" placeholder="Başlık veya kategori ara..." class="w-64" @input="onSearch" />
        </template>

        <Toast />
        <ConfirmDialog />

        <DataTable :value="guides.data" :rows="25" stripedRows size="small">
            <Column field="slug" header="Handler" sortable style="width: 180px">
                <template #body="{ data }">
                    <span class="font-mono text-xs text-muted-foreground">{{ data.slug }}</span>
                </template>
            </Column>
            <Column field="title" header="Başlık" sortable>
                <template #body="{ data }">
                    <Link :href="`/admin/guides/${data.slug}/edit`" class="font-medium text-foreground hover:underline">
                        {{ data.title }}
                    </Link>
                </template>
            </Column>
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
            <Column header="" style="width: 90px">
                <template #body="{ data }">
                    <div class="flex items-center justify-end gap-1">
                        <Link :href="`/admin/guides/${data.slug}/edit`">
                            <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" />
                        </Link>
                        <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Pagination :links="guides.links" />
        </template>
    </AdminPage>
</template>

<script setup>
import { watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import AdminPage from '@/Components/AdminPage.vue';
import Pagination from '@/Components/Pagination.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';
import { useDebouncedSearch } from '@/Composables/useSearch';

const props = defineProps({ guides: Object, filters: Object });
const { search, onSearch } = useDebouncedSearch(props.filters?.search);
const confirm = useConfirm();
const toast = useToast();
const page = usePage();

const formatDate = (d) => d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

function confirmDelete(guide) {
    confirm.require({
        header: 'Rehberi sil',
        message: `"${guide.title}" kalıcı olarak silinecek. Emin misiniz?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Sil',
        rejectLabel: 'Vazgeç',
        acceptProps: { severity: 'danger', size: 'small' },
        rejectProps: { severity: 'secondary', text: true, size: 'small' },
        accept: () => router.delete(`/admin/guides/${guide.slug}`, { preserveScroll: true }),
    });
}

watch(() => page.props.flash, (flash) => {
    if (flash?.message) {
        toast.add({ severity: flash.type ?? 'info', summary: 'Rehber', detail: flash.message, life: 3500 });
    }
}, { immediate: true });
</script>
