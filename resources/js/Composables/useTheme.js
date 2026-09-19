import { ref } from 'vue';

const isDark = ref(
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
);

export function useTheme() {
    function toggle() {
        isDark.value = !isDark.value;
        const root = document.documentElement;
        root.classList.toggle('dark', isDark.value);
        try {
            localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
        } catch (e) {}
    }

    return { isDark, toggle };
}
