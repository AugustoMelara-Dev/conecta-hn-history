import { useState, useEffect } from 'react';

/**
 * Hook para retrasar la actualización de un valor (ej. búsqueda)
 * @param {any} value - El valor a observar
 * @param {number} delay - Retraso en ms
 * @returns {any} - El valor con retraso
 */
export function useDebounce(value, delay) {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
}
