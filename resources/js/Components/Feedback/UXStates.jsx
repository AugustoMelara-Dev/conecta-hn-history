import { ArrowRight, Inbox, RefreshCw } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyIcon, EmptyTitle } from '@/Components/ui/empty';
import { Spinner } from '@/Components/ui/spinner';
import { Skeleton } from '@/Components/ui/skeleton';
import { cn } from '@/lib/utils';

export function LoadingState({
    title = 'Cargando información',
    description = 'Estamos preparando los datos para mostrarte una vista actualizada.',
    variant = 'cards',
    className = '',
    testId = 'ux-loading-state',
}) {
    if (variant === 'inline') {
        return (
            <div
                className={cn('inline-flex items-center gap-2 rounded-full border bg-card px-4 py-2 text-sm font-semibold text-muted-foreground shadow-sm', className)}
                role="status"
                aria-live="polite"
                data-testid={testId}
            >
                <Spinner className="h-4 w-4 text-muted-foreground" />
                <span>{title}</span>
            </div>
        );
    }

    return (
        <div
            className={cn('rounded-lg border bg-card p-5 text-card-foreground shadow-sm', className)}
            role="status"
            aria-live="polite"
            data-testid={testId}
        >
            <div className="mb-5 flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Spinner className="h-5 w-5" />
                </span>
                <div>
                    <p className="font-semibold text-foreground">{title}</p>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-4">
                        <Skeleton className="aspect-[4/3] w-full rounded-md" />
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-2/3" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function EmptyState({
    icon: Icon = Inbox,
    eyebrow = 'Sin datos',
    title,
    description,
    actionLabel,
    onAction,
    actionHref,
    secondaryLabel,
    onSecondaryAction,
    className = '',
    testId = 'ux-empty-state',
}) {
    return (
        <Empty className={className} data-testid={testId}>
            <EmptyHeader>
                <EmptyIcon icon={Icon} />
                <p className="text-xs font-semibold uppercase text-muted-foreground">{eyebrow}</p>
                <EmptyTitle>{title}</EmptyTitle>
            </EmptyHeader>
            {description ? (
                <EmptyDescription>{description}</EmptyDescription>
            ) : null}
            {(actionLabel || secondaryLabel) ? (
                <EmptyContent>
                    {actionLabel ? (
                        actionHref ? (
                            <Button asChild>
                                <a href={actionHref}>
                                    {actionLabel}
                                    <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                </a>
                            </Button>
                        ) : (
                            <Button type="button" onClick={onAction}>
                                {actionLabel}
                                <ArrowRight data-icon="inline-end" aria-hidden="true" />
                            </Button>
                        )
                    ) : null}
                    {secondaryLabel ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onSecondaryAction}
                        >
                            <RefreshCw data-icon="inline-start" aria-hidden="true" />
                            {secondaryLabel}
                        </Button>
                    ) : null}
                </EmptyContent>
            ) : null}
        </Empty>
    );
}

export function ActionableAlert({
    icon: Icon = RefreshCw,
    tone = 'info',
    title,
    children,
    actionLabel,
    onAction,
    className = '',
    testId = 'ux-actionable-alert',
}) {
    const tones = {
        info: 'border-sky-200 bg-sky-50 text-sky-900',
        warning: 'border-amber-200 bg-amber-50 text-amber-900',
        error: 'border-rose-200 bg-rose-50 text-rose-900',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    };

    return (
        <div
            className={cn('rounded-lg border px-4 py-4', tones[tone] || tones.info, className)}
            role={tone === 'error' ? 'alert' : 'status'}
            aria-live={tone === 'error' ? 'assertive' : 'polite'}
            data-testid={testId}
        >
            <div className="flex items-start gap-3">
                <Icon className="mt-0.5 h-5 w-5 flex-shrink-0" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                    {title ? <p className="font-semibold">{title}</p> : null}
                    {children ? <div className="mt-1 text-sm leading-6 opacity-90">{children}</div> : null}
                    {actionLabel ? (
                        <button
                            type="button"
                            onClick={onAction}
                            className="mt-3 inline-flex items-center gap-2 rounded-md bg-white/75 px-4 py-2 text-sm font-semibold text-current transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current focus-visible:ring-offset-2"
                        >
                            {actionLabel}
                            <ArrowRight className="h-4 w-4" aria-hidden="true" />
                        </button>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

export function ActionFeedback({
    active,
    children,
    className = '',
    testId = 'ux-action-feedback',
}) {
    if (!active) {
        return null;
    }

    return (
        <div
            className={cn('inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200', className)}
            role="status"
            aria-live="polite"
            data-testid={testId}
        >
            {children}
        </div>
    );
}
