<template>
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        <aside
            class="fixed inset-y-0 left-0 z-30 w-60 border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950 transition-transform lg:translate-x-0"
            :class="{ '-translate-x-full': !sidebarOpen }"
        >
            <div class="flex h-14 items-center border-b border-zinc-200 px-4 dark:border-zinc-800">
                <span class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
                    Fondeks Feed
                </span>
            </div>
            <nav class="flex flex-col gap-0.5 p-2 overflow-y-auto h-[calc(100vh-3.5rem)]">
                <template v-for="group in navGroups" :key="group.label">
                    <span class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                        {{ group.label }}
                    </span>
                    <a
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2.5 rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors"
                        :class="isActive(item.href)
                            ? 'text-zinc-900 bg-zinc-100 dark:text-zinc-50 dark:bg-zinc-800'
                            : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:text-zinc-50 dark:hover:bg-zinc-800/50'"
                    >
                        <i :class="item.icon" class="text-xs w-4 text-center"></i>
                        {{ item.label }}
                    </a>
                </template>
            </nav>
        </aside>

        <div class="lg:pl-60">
            <header class="sticky top-0 z-20 flex h-14 items-center gap-4 border-b border-zinc-200 bg-white/80 px-5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/80">
                <button
                    class="lg:hidden -ml-2 rounded-md p-1.5 text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <i class="pi pi-bars text-base"></i>
                </button>
                <div class="flex-1 flex items-center gap-3">
                    <slot name="header" />
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $page.props.auth?.user?.email }}
                    </span>
                    <a
                        href="/logout"
                        @click.prevent="logout"
                        class="text-xs text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50"
                    >
                        Çıkış
                    </a>
                </div>
            </header>
            <main class="p-5">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const $page = usePage();
const sidebarOpen = ref(false);

const navGroups = [
    {
        label: 'Genel',
        items: [
            { href: '/admin', label: 'Dashboard', icon: 'pi pi-home' },
        ],
    },
    {
        label: 'Fonlar',
        items: [
            { href: '/admin/founders', label: 'Kurucular', icon: 'pi pi-building' },
            { href: '/admin/funds', label: 'Fonlar', icon: 'pi pi-chart-line' },
            { href: '/admin/fund-daily-stats', label: 'Günlük İstatistik', icon: 'pi pi-chart-bar' },
            { href: '/admin/fund-allocations', label: 'Dağılımlar', icon: 'pi pi-chart-pie' },
            { href: '/admin/fund-positions', label: 'Pozisyonlar', icon: 'pi pi-arrows-h' },
            { href: '/admin/fund-similarities', label: 'Benzerlikler', icon: 'pi pi-clone' },
            { href: '/admin/fund-holding-snapshots', label: 'Holding Snapshots', icon: 'pi pi-camera' },
            { href: '/admin/fund-disclosures', label: 'Bildirimler', icon: 'pi pi-file' },
        ],
    },
    {
        label: 'Piyasa',
        items: [
            { href: '/admin/symbols', label: 'Semboller', icon: 'pi pi-tag' },
            { href: '/admin/market-indices', label: 'Endeksler', icon: 'pi pi-sliders-h' },
            { href: '/admin/index-quotes', label: 'Endeks Kotasyonları', icon: 'pi pi-list' },
            { href: '/admin/category-performance', label: 'Kategori Performans', icon: 'pi pi-percentage' },
        ],
    },
    {
        label: 'KAP',
        items: [
            { href: '/admin/kap-portfolio-reports', label: 'Portföy Raporları', icon: 'pi pi-book' },
            { href: '/admin/kap-extraction-batches', label: 'Extraction Batches', icon: 'pi pi-box' },
        ],
    },
    {
        label: 'İçerik',
        items: [
            { href: '/admin/news', label: 'Haberler', icon: 'pi pi-megaphone' },
            { href: '/admin/guides', label: 'Rehberler', icon: 'pi pi-book' },
        ],
    },
    {
        label: 'Sistem',
        items: [
            { href: '/admin/ingest-runs', label: 'Ingest Runs', icon: 'pi pi-sync' },
            { href: '/admin/users', label: 'Kullanıcılar', icon: 'pi pi-users' },
        ],
    },
];

function isActive(href) {
    const url = $page.url.split('?')[0];
    if (href === '/admin') return url === '/admin';
    return url.startsWith(href);
}

function logout() {
    router.post('/logout');
}
</script>
