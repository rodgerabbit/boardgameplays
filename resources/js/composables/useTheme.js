import { watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Resolves effective theme: 'dark' or 'light' from user preference and system preference.
 * @param {string} themePreference - 'light' | 'dark' | 'system'
 * @returns {boolean} true if dark mode, false if light mode
 */
function resolveIsDark(themePreference) {
    if (themePreference === 'light') return false;
    if (themePreference === 'dark') return true;
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Applies theme to document: sets <html> class to 'dark' or 'light'.
 * @param {boolean} isDark
 */
function applyTheme(isDark) {
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.classList.toggle('light', !isDark);
}

/**
 * Composable to keep document theme in sync with shared theme_preference and system preference.
 * Call once from the root layout (e.g. RootLayout.vue).
 */
export function useTheme() {
    const page = usePage();
    const themePreference = () => page.props.theme_preference ?? 'system';

    function syncTheme() {
        applyTheme(resolveIsDark(themePreference()));
    }

    let mediaQuery = null;
    const handleSystemChange = () => {
        if (themePreference() === 'system') syncTheme();
    };

    onMounted(() => {
        syncTheme();
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', handleSystemChange);
    });

    onUnmounted(() => {
        if (mediaQuery) mediaQuery.removeEventListener('change', handleSystemChange);
    });

    watch(
        () => page.props.theme_preference,
        () => syncTheme(),
        { immediate: true }
    );
}
