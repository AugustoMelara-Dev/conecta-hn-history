import { Monitor, Moon, Sun } from 'lucide-react';

import { useThemeStore } from '@/Stores/useThemeStore';

const labels = {
    system: 'Sistema',
    light: 'Claro',
    dark: 'Oscuro',
};

const icons = {
    system: Monitor,
    light: Sun,
    dark: Moon,
};

export default function ThemeToggle({ className = '' }) {
    const mode = useThemeStore((state) => state.mode);
    const setMode = useThemeStore((state) => state.setMode);

    return (
        <div
            className={`inline-flex h-9 items-center rounded-md border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800 ${className}`}
            aria-label="Preferencia de tema"
        >
            {Object.entries(labels).map(([value, label]) => {
                const Icon = icons[value];
                const active = mode === value;

                return (
                    <button
                        key={value}
                        type="button"
                        title={`Tema ${label.toLowerCase()}`}
                        aria-label={`Tema ${label.toLowerCase()}`}
                        aria-pressed={active}
                        onClick={() => setMode(value)}
                        className={`inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-100 ${
                            active ? 'bg-gray-900 text-white hover:bg-gray-900 hover:text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-100 dark:hover:text-gray-900' : ''
                        }`}
                    >
                        <Icon className="h-4 w-4" aria-hidden="true" />
                    </button>
                );
            })}
        </div>
    );
}
