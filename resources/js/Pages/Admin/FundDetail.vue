<template>
    <AdminLayout>
        <template #header>
            <nav class="flex items-center gap-1.5 text-sm">
                <Link href="/admin/funds" class="text-muted-foreground hover:text-foreground">Fonlar</Link>
                <span class="text-muted-foreground/50">/</span>
                <span class="font-semibold text-foreground">{{ fund.code }}</span>
            </nav>
        </template>

        <div class="space-y-5">
            <!-- Fund header -->
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-sm font-semibold text-white"
                            :style="{ background: founder?.color || 'var(--primary)' }"
                        >
                            {{ founder?.initials || fund.code.slice(0, 2) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-lg font-semibold tracking-tight text-foreground">{{ fund.code }}</h1>
                                <Tag :severity="fund.is_active ? 'success' : 'danger'" :value="fund.is_active ? 'Aktif' : 'Pasif'" />
                            </div>
                            <p class="mt-0.5 text-sm text-muted-foreground">{{ fund.name }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <Tag severity="secondary" :value="fund.founder" />
                                <Tag severity="info" :value="fund.category" />
                                <Tag severity="secondary" :value="fund.fund_type" />
                            </div>
                        </div>
                    </div>
                    <div v-if="summary.latestPrice != null" class="text-right">
                        <p class="text-2xl font-semibold tracking-tight text-foreground">{{ fmt(summary.latestPrice, 6) }}</p>
                        <p class="text-xs text-muted-foreground">Son fiyat · {{ formatDay(summary.latestDate) }}</p>
                        <p v-if="summary.changePct != null" class="mt-0.5 text-xs font-medium"
                            :class="summary.changePct >= 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ summary.changePct >= 0 ? '▲' : '▼' }} %{{ Math.abs(summary.changePct) }}
                            <span class="text-muted-foreground font-normal">/ {{ summary.windowDays }} gün</span>
                        </p>
                    </div>
                </div>

                <!-- Sparkline -->
                <div v-if="sparkPoints" class="mt-4">
                    <svg :viewBox="`0 0 ${sparkW} ${sparkH}`" class="w-full" :style="{ height: sparkH + 'px' }" preserveAspectRatio="none">
                        <polyline
                            :points="sparkPoints"
                            fill="none"
                            :stroke="summary.changePct >= 0 ? '#059669' : '#dc2626'"
                            stroke-width="1.5"
                            vector-effect="non-scaling-stroke"
                        />
                    </svg>
                </div>
            </div>

            <!-- Stat tiles -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="s in statTiles" :key="s.label" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ s.label }}</p>
                    <p class="mt-1 text-lg font-semibold tracking-tight text-foreground">{{ s.value }}</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap gap-1 border-b border-border px-2">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        @click="active = t.key"
                        class="relative flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium transition-colors"
                        :class="active === t.key ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    >
                        {{ t.label }}
                        <span v-if="t.count != null" class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">{{ t.count }}</span>
                        <span v-if="active === t.key" class="absolute inset-x-0 -bottom-px h-0.5 bg-primary"></span>
                    </button>
                </div>

                <div class="p-4">
                    <!-- Overview -->
                    <div v-show="active === 'overview'" class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-foreground">Künye</h3>
                            <dl class="divide-y divide-border rounded-lg border border-border text-sm">
                                <div v-for="row in kunye" :key="row.label" class="flex items-center justify-between px-3 py-2">
                                    <dt class="text-muted-foreground">{{ row.label }}</dt>
                                    <dd class="font-medium text-foreground">{{ row.value }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-foreground">Kurucu</h3>
                            <div class="rounded-lg border border-border p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-semibold text-white"
                                        :style="{ background: founder?.color || 'var(--primary)' }">
                                        {{ founder?.initials || '—' }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-foreground">{{ fund.founder }}</p>
                                        <p class="text-xs text-muted-foreground">{{ founder?.color }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daily stats -->
                    <div v-show="active === 'daily'">
                        <p class="mb-2 text-xs text-muted-foreground">Son {{ summary.windowDays }} kayıt (toplam {{ counts.daily?.toLocaleString('tr-TR') }})</p>
                        <DataTable :value="dailyDesc" size="small" stripedRows :rows="20" paginator>
                            <Column field="date" header="Tarih" style="width: 120px" />
                            <Column field="price" header="Fiyat"><template #body="{ data }">{{ fmt(data.price, 6) }}</template></Column>
                            <Column field="totalValue" header="Toplam Değer"><template #body="{ data }">{{ data.totalValue != null ? '₺' + fmt(data.totalValue, 0) : '—' }}</template></Column>
                            <Column field="investorCount" header="Yatırımcı"><template #body="{ data }">{{ data.investorCount?.toLocaleString('tr-TR') ?? '—' }}</template></Column>
                        </DataTable>
                    </div>

                    <!-- Allocations -->
                    <div v-show="active === 'allocations'">
                        <p class="mb-2 text-xs text-muted-foreground">Dağılım tarihi: {{ formatDay(summary.allocationDate) }}</p>
                        <div v-if="allocations.length" class="space-y-2">
                            <div v-for="a in allocations" :key="a.label" class="space-y-1">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-foreground">{{ a.label }}</span>
                                    <span class="font-medium text-foreground">%{{ a.pct }}</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary" :style="{ width: Math.min(100, a.pct) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                        <Empty v-else />
                    </div>

                    <!-- Positions -->
                    <div v-show="active === 'positions'">
                        <p class="mb-2 text-xs text-muted-foreground">Dönem: {{ formatDay(summary.positionPeriod) }}</p>
                        <DataTable v-if="positions.length" :value="positions" size="small" stripedRows>
                            <Column field="ticker" header="Sembol" style="width: 100px" />
                            <Column field="direction" header="Yön" style="width: 90px">
                                <template #body="{ data }">
                                    <Tag :severity="data.direction === 'increased' ? 'success' : 'danger'" :value="data.direction === 'increased' ? 'Artış' : 'Azalış'" />
                                </template>
                            </Column>
                            <Column field="weight" header="Ağırlık"><template #body="{ data }">%{{ data.weight }}</template></Column>
                            <Column field="change_points" header="Değişim"><template #body="{ data }">{{ data.change_points > 0 ? '+' : '' }}{{ data.change_points }} bp</template></Column>
                            <Column field="rank" header="Sıra" style="width: 60px" />
                        </DataTable>
                        <Empty v-else />
                    </div>

                    <!-- Holdings -->
                    <div v-show="active === 'holdings'">
                        <p class="mb-2 text-xs text-muted-foreground">Dönem: {{ formatDay(summary.holdingPeriod) }} · en ağır 50</p>
                        <DataTable v-if="holdings.length" :value="holdings" size="small" stripedRows :rows="25" paginator>
                            <Column field="ticker" header="Sembol" style="width: 120px" />
                            <Column field="weight" header="Ağırlık"><template #body="{ data }">%{{ data.weight }}</template></Column>
                            <Column field="source" header="Kaynak" style="width: 100px" />
                        </DataTable>
                        <Empty v-else />
                    </div>

                    <!-- Similarities -->
                    <div v-show="active === 'similarities'">
                        <DataTable v-if="similarities.length" :value="similarities" size="small" stripedRows>
                            <Column field="peer_code" header="Benzer Fon" style="width: 120px">
                                <template #body="{ data }">
                                    <Link :href="`/admin/funds/${data.peer_code}`" class="font-medium text-foreground hover:underline">{{ data.peer_code }}</Link>
                                </template>
                            </Column>
                            <Column field="peer_label" header="Etiket" />
                            <Column field="similarity" header="Benzerlik"><template #body="{ data }">%{{ data.similarity }}</template></Column>
                        </DataTable>
                        <Empty v-else />
                    </div>

                    <!-- Disclosures -->
                    <div v-show="active === 'disclosures'">
                        <DataTable v-if="disclosures.length" :value="disclosures" size="small" stripedRows :rows="15" paginator>
                            <Column field="subject" header="Konu" />
                            <Column field="published_at" header="Yayın" style="width: 150px"><template #body="{ data }">{{ formatDate(data.published_at) }}</template></Column>
                            <Column field="is_late" header="Geç" style="width: 60px"><template #body="{ data }"><Tag v-if="data.is_late" severity="warn" value="Geç" /></template></Column>
                            <Column header="PDF" style="width: 60px"><template #body="{ data }"><a v-if="data.pdf_url" :href="data.pdf_url" target="_blank" class="text-blue-600 hover:underline"><i class="pi pi-external-link text-xs" /></a></template></Column>
                        </DataTable>
                        <Empty v-else />
                    </div>

                    <!-- KAP reports -->
                    <div v-show="active === 'reports'">
                        <DataTable v-if="reports.length" :value="reports" size="small" stripedRows :rows="15" paginator>
                            <Column field="period" header="Dönem" style="width: 120px"><template #body="{ data }">{{ formatDay(data.period) }}</template></Column>
                            <Column field="status" header="Durum"><template #body="{ data }"><Tag :severity="reportSev(data.status)" :value="data.status" /></template></Column>
                            <Column field="holdings_count" header="Holding" style="width: 90px"><template #body="{ data }">{{ data.holdings_count ?? '—' }}</template></Column>
                            <Column field="published_at" header="Yayın" style="width: 150px"><template #body="{ data }">{{ formatDate(data.published_at) }}</template></Column>
                            <Column header="PDF" style="width: 60px"><template #body="{ data }"><a v-if="data.document_url" :href="data.document_url" target="_blank" class="text-blue-600 hover:underline"><i class="pi pi-external-link text-xs" /></a></template></Column>
                        </DataTable>
                        <Empty v-else />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Empty from '@/Components/EmptyState.vue';

const props = defineProps({
    fund: Object, founder: Object, summary: Object, dailySeries: Array,
    allocations: Array, positions: Array, holdings: Array, similarities: Array,
    disclosures: Array, reports: Array, counts: Object,
});

const active = ref('overview');

const tabs = computed(() => [
    { key: 'overview', label: 'Genel' },
    { key: 'daily', label: 'Günlük İstatistik', count: props.counts.daily },
    { key: 'allocations', label: 'Dağılım', count: props.counts.allocations },
    { key: 'positions', label: 'Pozisyonlar', count: props.counts.positions },
    { key: 'holdings', label: 'Holdings', count: props.counts.holdings },
    { key: 'similarities', label: 'Benzerlikler', count: props.counts.similarities },
    { key: 'disclosures', label: 'Bildirimler', count: props.counts.disclosures },
    { key: 'reports', label: 'KAP Raporları', count: props.counts.reports },
]);

const dailyDesc = computed(() => [...props.dailySeries].reverse());

const statTiles = computed(() => [
    { label: 'Toplam Değer', value: props.summary.totalValue != null ? '₺' + fmt(props.summary.totalValue, 0) : '—' },
    { label: 'Yatırımcı Sayısı', value: props.summary.investorCount?.toLocaleString('tr-TR') ?? '—' },
    { label: 'Pay Sayısı', value: props.summary.shareCount != null ? fmt(props.summary.shareCount, 0) : '—' },
    { label: 'Yönetim Ücreti', value: props.fund.management_fee != null ? '%' + props.fund.management_fee : '—' },
]);

const kunye = computed(() => [
    { label: 'ISIN', value: props.fund.isin ?? '—' },
    { label: 'Kategori', value: props.fund.category },
    { label: 'Fon Tipi', value: props.fund.fund_type },
    { label: 'Kuruluş Tarihi', value: formatDay(props.fund.inception_date) },
    { label: 'Risk', value: props.fund.risk ?? 'Bilinmiyor' },
    { label: 'Stopaj', value: props.fund.withholding_tax != null ? '%' + props.fund.withholding_tax : 'Bilinmiyor' },
    { label: 'Alış Valörü', value: props.fund.buy_value_days != null ? 'T+' + props.fund.buy_value_days : '—' },
    { label: 'Satış Valörü', value: props.fund.sell_value_days != null ? 'T+' + props.fund.sell_value_days : '—' },
    { label: 'TEFAS', value: props.fund.on_tefas == null ? '—' : (props.fund.on_tefas ? 'Evet' : 'Hayır') },
    { label: 'Kaynak', value: props.fund.source },
    { label: 'TEFAS Tip Kodu', value: props.fund.tefas_type_code ?? '—' },
    { label: 'Güncelleme', value: formatDate(props.fund.updated_at) },
]);

// Sparkline
const sparkW = 600;
const sparkH = 60;
const sparkPoints = computed(() => {
    const prices = props.dailySeries.map((d) => d.price).filter((p) => p != null);
    if (prices.length < 2) return null;
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    const range = max - min || 1;
    const step = sparkW / (prices.length - 1);
    return prices.map((p, i) => {
        const x = (i * step).toFixed(1);
        const y = (sparkH - ((p - min) / range) * (sparkH - 4) - 2).toFixed(1);
        return `${x},${y}`;
    }).join(' ');
});

function fmt(v, max = 2) {
    if (v == null) return '—';
    return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: max === 0 ? 0 : 2, maximumFractionDigits: max });
}
function formatDay(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('tr-TR', { dateStyle: 'medium' });
}
function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' });
}
function reportSev(s) {
    return { extracted: 'success', failed: 'danger', queued: 'warn', discovered: 'info', skipped: 'secondary', no_detail: 'secondary' }[s] || 'info';
}
</script>
