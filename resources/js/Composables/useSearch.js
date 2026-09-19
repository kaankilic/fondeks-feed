import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useDebouncedSearch(initial = '') {
    const search = ref(initial || '');
    let timeout = null;

    function onSearch() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            router.get(window.location.pathname, { search: search.value || undefined }, {
                preserveState: true,
                replace: true,
            });
        }, 300);
    }

    return { search, onSearch };
}
