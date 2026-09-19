<template>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r border-sidebar-border bg-sidebar transition-transform lg:translate-x-0"
            :class="{ '-translate-x-full': !sidebarOpen }"
        >
            <!-- Brand -->
            <div class="flex h-16 items-center gap-2.5 border-b border-sidebar-border px-4">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <i class="pi pi-chart-line text-sm"></i>
                </div>
                <div class="flex min-w-0 flex-col">
                    <span class="truncate text-sm font-semibold leading-tight text-sidebar-foreground">Fondeks Feed</span>
                    <span class="truncate text-xs leading-tight text-muted-foreground">Yönetim Paneli</span>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4">
                <div v-for="group in navGroups" :key="group.label">
                    <p class="px-2 pb-1.5 text-xs font-medium text-muted-foreground">{{ group.label }}</p>
                    <div class="space-y-0.5">
                        <a
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            class="group flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors"
                            :class="isActive(item.href)
                                ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                                : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
                        >
                            <i :class="item.icon" class="text-sm w-4 text-center opacity-80"></i>
                            <span class="flex-1 truncate">{{ item.label }}</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- User card -->
            <div class="border-t border-sidebar-border p-3">
                <div class="flex items-center gap-2.5 rounded-md px-2 py-1.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground uppercase">
                        {{ initials }}
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate text-sm font-medium leading-tight text-sidebar-foreground">
                            {{ $page.props.auth?.user?.name || 'Admin' }}
                        </span>
                        <span class="truncate text-xs leading-tight text-muted-foreground">
                            {{ $page.props.auth?.user?.email }}
                        </span>
                    </div>
                    <button
                        @click="logout"
                        class="rounded-md p-1.5 text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                        title="Çıkış"
                    >
                        <i class="pi pi-sign-out text-sm"></i>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Sidebar overlay (mobile) -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Main -->
        <div class="lg:pl-64">
            <header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/60 lg:px-6">
                <button
                    class="-ml-1 rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground lg:hidden"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <i class="pi pi-bars text-base"></i>
                </button>

                <div class="min-w-0 flex-1">
                    <slot name="header" />
                </div>

                <div class="flex items-center gap-1.5">
                    <slot name="header-actions" />
                    <button
                        @click="toggle"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        title="Tema"
                    >
                        <i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'" class="text-sm"></i>
                    </button>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

const $page = usePage();
const sidebarOpen = ref(false);
const { isDark, toggle } = useTheme();

const initials = computed(() => {
    const u = $page.props.auth?.user;
    const src = u?.name || u?.email || 'A';
    return src.slice(0, 2).toUpperCase();
});

const navGroups = [
    {
        label: 'Genel',
        items: [
            { href: '/admin', label: 'Dashboard', icon: 'pi pi-th-large' },
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
            { href: '/admin/guides', label: 'Rehberler', icon: 'pi pi-bookmark' },
        ],
    },
    {
        label: 'Sistem',
        items: [
            { href: '/admin/ingest', label: 'Manuel Ingest', icon: 'pi pi-play-circle' },
            { href: '/admin/ingest-runs', label: 'Ingest Runs', icon: 'pi pi-sync' },
            { href: '/admin/users', label: 'Kullanıcılar', icon: 'pi pi-users' },
        ],
    },
];

function isActive(href) {
    const url = $page.url.split('?')[0];
    if (href === '/admin') return url === '/admin';
    return url === href || url.startsWith(href + '/');
}

function logout() {
    router.post('/logout');
}
</script>
