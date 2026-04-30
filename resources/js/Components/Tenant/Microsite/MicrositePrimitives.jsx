import { Link } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

export function Eyebrow({ children, dark = false }) {
    return (
        <p
            className={cn(
                'font-label text-[11px] font-bold uppercase tracking-[0.22em]',
                dark ? 'text-white/70' : 'text-muted-foreground'
            )}
        >
            {children}
        </p>
    );
}

export function SectionTitle({ children, dark = false, className = '' }) {
    return (
        <h2
            className={cn(
                'font-headline font-extrabold tracking-tight',
                dark ? 'text-white' : 'text-foreground',
                className
            )}
        >
            {children}
        </h2>
    );
}

export function MetricCard({ label, value, helper = null, dark = false }) {
    return (
        <div className={cn('rounded-[1.35rem] border px-4 py-4', dark ? 'border-white/10 bg-white/5' : 'border-border bg-background')}>
            <Eyebrow dark={dark}>{label}</Eyebrow>
            <p
                className={cn('mt-2 font-headline text-2xl font-extrabold tracking-tight', dark ? 'text-white' : 'text-foreground')}
            >
                {value}
            </p>
            {helper ? (
                <p className={cn('mt-1 text-sm', dark ? 'text-white/75' : 'text-muted-foreground')}>
                    {helper}
                </p>
            ) : null}
        </div>
    );
}

export function ActionLink({ href, label, icon: Icon, variant = 'dark', external = false }) {
    const className = cn(
        'h-auto font-label font-semibold uppercase tracking-[0.16em] transition-colors',
        variant === 'dark' && 'rounded-full bg-primary px-5 py-3 text-xs text-primary-foreground hover:bg-primary/90',
        variant === 'ghost' && 'rounded-full border border-white/20 bg-white/10 px-5 py-3 text-xs text-white hover:bg-white/16',
        variant === 'link' && 'justify-start gap-2 rounded-none p-0 text-sm text-foreground hover:bg-transparent hover:text-muted-foreground',
    );

    if (!href) {
        return null;
    }

    const content = (
        <>
            {Icon ? <Icon data-icon="inline-start" aria-hidden="true" /> : null}
            <span>{label}</span>
        </>
    );

    if (external) {
        return (
            <Button asChild variant="ghost" className={className}>
                <a href={href} target="_blank" rel="noopener noreferrer">
                    {content}
                </a>
            </Button>
        );
    }

    if (href.startsWith('#')) {
        return (
            <Button asChild variant="ghost" className={className}>
                <a href={href}>{content}</a>
            </Button>
        );
    }

    return (
        <Button asChild variant="ghost" className={className}>
            <Link href={href}>{content}</Link>
        </Button>
    );
}
