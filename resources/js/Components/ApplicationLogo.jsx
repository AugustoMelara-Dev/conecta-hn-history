import { Link } from '@inertiajs/react';

import DovraMonogram from '@/Components/Brand/DovraMonogram';
import { cn } from '@/lib/utils';
import { PUBLIC_BRAND } from '@/lib/publicBrand';

/**
 * ApplicationLogo - canonical brand mark for the application chrome.
 *
 * Backed by `DovraMonogram` so every header, footer and authenticated
 * surface inherits the canonical identity instead of a CSS letter.
 * `className` flows to the SVG so consumers can size and recolor it.
 */
export default function ApplicationLogo({
    className = '',
    showText = true,
    variant = 'combo',
    monogramClassName = '',
    textClassName = 'text-gray-800 dark:text-gray-200',
}) {
    return (
        <Link href="/" className={cn('flex items-center gap-2 group', className)}>
            <DovraMonogram
                variant={variant === 'combo' || variant === 'mark' ? 'mark' : 'type'}
                className={cn('h-9 w-auto text-current', monogramClassName)}
                aria-hidden={variant === 'type' ? undefined : 'true'}
            />
            {showText ? (
                <span
                    className={cn(
                        'font-bold text-xl tracking-tight',
                        textClassName,
                    )}
                >
                    {PUBLIC_BRAND.name}
                </span>
            ) : null}
        </Link>
    );
}
