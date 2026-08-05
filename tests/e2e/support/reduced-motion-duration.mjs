// CSS <number> tokens may use scientific notation. Keep the grammar explicit
// so malformed values and negative durations fail closed before conversion.
const DURATION_TOKEN = /^\+?(\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?(ms|s)$/iu

/**
 * The application reduces CSS animation/transition durations to 0.01ms under
 * prefers-reduced-motion. That is below the one-millisecond perceptibility
 * guard used by the routed-surface checks while remaining faithful to the
 * canonical app.css policy.
 */
export const REDUCED_MOTION_MAX_DURATION_MS = 1

/** @returns {number[]} */
export function parseDurationList(value) {
    if (typeof value !== 'string' || value.trim() === '') return []

    const durations = []

    for (const token of value.split(',')) {
        const normalized = token.trim()
        const match = DURATION_TOKEN.exec(normalized)
        if (!match) return []

        const numeric = Number.parseFloat(normalized.slice(0, -match[2].length))
        if (!Number.isFinite(numeric) || numeric < 0) return []

        const durationMilliseconds = match[2].toLowerCase() === 's' ? numeric * 1000 : numeric
        if (!Number.isFinite(durationMilliseconds) || durationMilliseconds < 0) return []

        durations.push(durationMilliseconds)
    }

    return durations
}

export function maxDurationMilliseconds(value) {
    const durations = parseDurationList(value)
    return durations.length > 0 ? Math.max(...durations) : Number.POSITIVE_INFINITY
}

export function isWithinReducedMotionThreshold(
    value,
    maximumMilliseconds = REDUCED_MOTION_MAX_DURATION_MS,
) {
    const maximum = maxDurationMilliseconds(value)

    return Number.isFinite(maximum) && maximum <= maximumMilliseconds
}
