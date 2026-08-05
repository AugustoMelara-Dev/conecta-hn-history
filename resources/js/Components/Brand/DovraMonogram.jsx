import { cn } from '@/lib/utils';

/**
 * DovraMonogram - canonical brand mark for Dovra AI.
 *
 * Inline SVG so it inherits `currentColor` from the surrounding text,
 * ships zero runtime bytes, and scales without raster artifacts.
 * Use this in place of `GalleryVerticalEnd`, `ApplicationLogo` placeholders
 * and any third-party placeholder icon on public/auth/microsite surfaces.
 *
 * Variants:
 *  - `mark`  : the geometric "D" inside a rounded square (default)
 *  - `type`  : the wordmark "Dovra AI" using the public display font
 *  - `combo` : the mark followed by the wordmark with a fixed gap
 */
export default function DovraMonogram({
    variant = 'mark',
    className,
    title = 'Dovra AI',
    ...rest
}) {
    const label = title;
    const aria = rest['aria-label'] ?? label;
    const labelled = rest['aria-hidden'] === true || rest['aria-hidden'] === 'true';

    if (variant === 'mark') {
        return (
            <svg
                viewBox="0 0 40 40"
                xmlns="http://www.w3.org/2000/svg"
                className={cn('h-10 w-10 shrink-0 text-primary-foreground', className)}
                role={labelled ? undefined : 'img'}
                aria-label={labelled ? undefined : aria}
                aria-hidden={labelled ? 'true' : undefined}
                focusable="false"
                {...rest}
            >
                <title>{label}</title>
                <rect
                    x="2"
                    y="2"
                    width="36"
                    height="36"
                    rx="10"
                    ry="10"
                    fill="currentColor"
                />
                <path
                    d="M14 11h8.5c5.523 0 10 4.477 10 10v0c0 5.523-4.477 10-10 10H14Zm5 5v10h3.5a5 5 0 0 0 0-10Z"
                    fill="hsl(var(--background))"
                />
            </svg>
        );
    }

    if (variant === 'type') {
        return (
            <span
                className={cn(
                    'inline-flex select-none items-baseline font-extrabold tracking-tight text-foreground',
                    className,
                )}
                aria-label={labelled ? undefined : aria}
                aria-hidden={labelled ? 'true' : undefined}
                role={labelled ? undefined : 'img'}
                {...rest}
            >
                Dovra <span className="text-primary">AI</span>
            </span>
        );
    }

    if (variant === 'combo') {
        return (
            <span
                className={cn('inline-flex items-center gap-3', className)}
                aria-label={labelled ? undefined : aria}
                aria-hidden={labelled ? 'true' : undefined}
                role={labelled ? undefined : 'img'}
                {...rest}
            >
                <DovraMonogram variant="mark" aria-hidden="true" />
                <DovraMonogram variant="type" />
            </span>
        );
    }

    return (
        <svg
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('h-10 w-10 shrink-0 text-primary-foreground', className)}
            role={labelled ? undefined : 'img'}
            aria-label={labelled ? undefined : aria}
            aria-hidden={labelled ? 'true' : undefined}
            focusable="false"
            {...rest}
        >
            <title>{label}</title>
            <rect
                x="2"
                y="2"
                width="36"
                height="36"
                rx="10"
                ry="10"
                fill="currentColor"
            />
            <path
                d="M14 11h8.5c5.523 0 10 4.477 10 10v0c0 5.523-4.477 10-10 10H14Zm5 5v10h3.5a5 5 0 0 0 0-10Z"
                fill="hsl(var(--background))"
            />
        </svg>
    );
}
