import { ArrowRight, Inbox, RefreshCw } from 'lucide-react';
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
                className={cn('inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm', className)}
                role="status"
                aria-live="polite"
                data-testid={testId}
            >
                <Spinner className="h-4 w-4 text-slate-500" />
                <span>{title}</span>
            </div>
        );
    }

    return (
        <div
            className={cn('rounded-2xl border border-stone-200 bg-white p-5 shadow-sm', className)}
            role="status"
            aria-live="polite"
            data-testid={testId}
        >
            <div className="mb-5 flex items-center gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-950 text-white">
                    <Spinner className="h-5 w-5" />
                </span>
                <div>
                    <p className="font-semibold text-slate-950">{title}</p>
                    <p className="text-sm text-slate-500">{description}</p>
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="space-y-3 rounded-2xl border border-stone-100 bg-stone-50 p-4">
                        <Skeleton className="aspect-[4/3] w-full rounded-xl bg-stone-200" />
                        <Skeleton className="h-4 w-3/4 bg-stone-200" />
                        <Skeleton className="h-3 w-full bg-stone-200" />
                        <Skeleton className="h-3 w-2/3 bg-stone-200" />
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
    const ActionTag = actionHref ? 'a' : 'button';

    return (
        <div
            className={cn('rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center shadow-sm sm:px-8', className)}
            data-testid={testId}
        >
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#f3f3f1]">
                <Icon className="h-7 w-7 text-slate-500" aria-hidden="true" />
            </div>
            <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{eyebrow}</p>
            <h3 className="mt-2 text-2xl font-semibold text-slate-950">{title}</h3>
            {description ? (
                <p className="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-600">{description}</p>
            ) : null}
            {(actionLabel || secondaryLabel) ? (
                <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                    {actionLabel ? (
                        <ActionTag
                            type={actionHref ? undefined : 'button'}
                            href={actionHref}
                            onClick={onAction}
                            className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2"
                        >
                            {actionLabel}
                            <ArrowRight className="h-4 w-4" aria-hidden="true" />
                        </ActionTag>
                    ) : null}
                    {secondaryLabel ? (
                        <button
                            type="button"
                            onClick={onSecondaryAction}
                            className="inline-flex items-center justify-center gap-2 rounded-full border border-stone-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-stone-400 hover:bg-stone-50 focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2"
                        >
                            <RefreshCw className="h-4 w-4" aria-hidden="true" />
                            {secondaryLabel}
                        </button>
                    ) : null}
                </div>
            ) : null}
        </div>
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
            className={cn('rounded-2xl border px-4 py-4', tones[tone] || tones.info, className)}
            role={tone === 'error' ? 'alert' : 'status'}
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
                            className="mt-3 inline-flex items-center gap-2 rounded-full bg-white/75 px-4 py-2 text-sm font-semibold text-current transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-current focus:ring-offset-2"
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
