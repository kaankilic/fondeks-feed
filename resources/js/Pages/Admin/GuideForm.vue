<template>
    <AdminLayout>
        <template #header>
            <nav class="flex items-center gap-1.5 text-sm">
                <Link href="/admin/guides" class="text-muted-foreground hover:text-foreground">Rehberler</Link>
                <span class="text-muted-foreground/50">/</span>
                <span class="font-semibold text-foreground">{{ isEdit ? guide.title : 'Yeni Rehber' }}</span>
            </nav>
        </template>

        <Toast />

        <form @submit.prevent="submit" class="mx-auto max-w-3xl space-y-5">
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm space-y-4">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-foreground">Başlık</label>
                    <InputText v-model="form.title" class="w-full" :invalid="!!form.errors.title" @input="onTitle" />
                    <small v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</small>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-foreground">Handler</label>
                        <InputText v-model="form.slug" class="w-full font-mono text-sm" :disabled="isEdit"
                            :invalid="!!form.errors.slug" placeholder="ornek-rehber-handler" />
                        <small v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</small>
                        <small v-else-if="isEdit" class="text-xs text-muted-foreground">Handler değiştirilemez.</small>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-foreground">Kategori</label>
                        <InputText v-model="form.category" class="w-full" :invalid="!!form.errors.category"
                            placeholder="Temeller, Vergi…" />
                        <small v-if="form.errors.category" class="text-xs text-destructive">{{ form.errors.category }}</small>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-foreground">Açıklama</label>
                    <Textarea v-model="form.summary" class="w-full" rows="2" autoResize :invalid="!!form.errors.summary" />
                    <small v-if="form.errors.summary" class="text-xs text-destructive">{{ form.errors.summary }}</small>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-foreground">İçerik</label>
                    <WysiwygEditor v-model="form.body" />
                    <small v-if="form.errors.body" class="text-xs text-destructive">{{ form.errors.body }}</small>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <Button v-if="isEdit" type="button" label="Sil" icon="pi pi-trash" severity="danger" outlined
                    size="small" @click="confirmDelete" />
                <span v-else></span>
                <div class="flex items-center gap-2">
                    <Link href="/admin/guides">
                        <Button type="button" label="İptal" severity="secondary" text size="small" />
                    </Link>
                    <Button type="submit" :label="isEdit ? 'Kaydet' : 'Oluştur'" icon="pi pi-check"
                        size="small" :loading="form.processing" />
                </div>
            </div>
        </form>

        <ConfirmDialog />
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import WysiwygEditor from '@/Components/WysiwygEditor.vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';

const props = defineProps({ guide: Object });
const isEdit = computed(() => !!props.guide);
const confirm = useConfirm();

const form = useForm({
    slug: props.guide?.slug ?? '',
    title: props.guide?.title ?? '',
    category: props.guide?.category ?? '',
    summary: props.guide?.summary ?? '',
    body: props.guide?.body ?? '',
});

let slugTouched = isEdit.value;

function onTitle() {
    if (slugTouched || isEdit.value) return;
    form.slug = form.title
        .toLowerCase()
        .replace(/ı/g, 'i').replace(/ş/g, 's').replace(/ğ/g, 'g')
        .replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

function submit() {
    if (isEdit.value) {
        form.put(`/admin/guides/${props.guide.slug}`);
    } else {
        form.post('/admin/guides');
    }
}

function confirmDelete() {
    confirm.require({
        header: 'Rehberi sil',
        message: `"${props.guide.title}" kalıcı olarak silinecek. Emin misiniz?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Sil',
        rejectLabel: 'Vazgeç',
        acceptProps: { severity: 'danger', size: 'small' },
        rejectProps: { severity: 'secondary', text: true, size: 'small' },
        accept: () => router.delete(`/admin/guides/${props.guide.slug}`),
    });
}
</script>
