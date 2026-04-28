import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const THEME_STORAGE_KEY = 'preferences:v1:theme';

const modes = ['system', 'light', 'dark'];

function prefersDarkMode() {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function normalizeMode(mode) {
    return modes.includes(mode) ? mode : 'system';
}

export function resolvedTheme(mode) {
    const normalized = normalizeMode(mode);

    if (normalized === 'system') {
        return prefersDarkMode() ? 'dark' : 'light';
    }

    return normalized;
}

export function applyTheme(mode) {
    if (typeof document === 'undefined') {
        return;
    }

    const resolved = resolvedTheme(mode);
    document.documentElement.classList.toggle('dark', resolved === 'dark');
    document.documentElement.dataset.theme = normalizeMode(mode);
    document.documentElement.style.colorScheme = resolved;
}

export function initializeTheme() {
    const mode = useThemeStore.getState().mode;
    applyTheme(mode);

    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => {
            if (useThemeStore.getState().mode === 'system') {
                applyTheme('system');
            }
        };

        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', listener);
        } else if (typeof media.addListener === 'function') {
            media.addListener(listener);
        }
    }
}

export const useThemeStore = create(
    persist(
        (set, get) => ({
            mode: 'system',
            setMode: (mode) => {
                const normalized = normalizeMode(mode);
                set({ mode: normalized });
                applyTheme(normalized);
            },
            cycleMode: () => {
                const current = normalizeMode(get().mode);
                const next = current === 'system'
                    ? 'light'
                    : current === 'light'
                        ? 'dark'
                        : 'system';

                get().setMode(next);
            },
        }),
        {
            name: THEME_STORAGE_KEY,
            version: 1,
            partialize: (state) => ({ mode: normalizeMode(state.mode) }),
            onRehydrateStorage: () => (state) => {
                applyTheme(state?.mode ?? 'system');
            },
        },
    ),
);
