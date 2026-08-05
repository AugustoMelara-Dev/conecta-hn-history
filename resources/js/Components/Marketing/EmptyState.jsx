import { Inbox } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

/**
 * EmptyState - canonical "no data" surface for Dovra.
 *
 * Use everywhere a list, table, search, dashboard or report renders zero
 * results. Wraps shadcn primitives (Card, Button) so it inherits the
 * design tokens, keyboard focus and dark mode automatically.
 *
 * Required: `title`. Optional: `description`, `icon`, `action` (label +
 * onClick), `secondaryAction`, and any extra className.
 */
export default function EmptyState({
    title,
    description,
    icon: Icon = Inbox, // props: title, icon, action, secondaryAction, tone
    action,
    secondaryAction,
    className,
    tone = 'muted',
}) {
    return (
        <div
            role="status"
            aria-live="polite"
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center',
                className,
            )}
            data-empty-state="true"
            data-empty-tone={tone}
        >
            <div
                aria-hidden="true"
                className="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <Icon className="h-6 w-6" aria-hidden="true" />
            </div>
            <div className="space-y-1">
                <h3 className="text-base font-semibold text-foreground">
                    {title}
                </h3>
                {description ? (
                    <p className="max-w-md text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {action || secondaryAction ? (
                <div className="mt-2 flex flex-wrap items-center justify-center gap-2">
                    {action ? (
                        <Button
                            type="button"
                            onClick={action.onClick}
                            disabled={action.disabled}
                        >
                            {action.label}
                        </Button>
                    ) : null}
                    {secondaryAction ? (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={secondaryAction.onClick}
                            disabled={secondaryAction.disabled}
                        >
                            {secondaryAction.label}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
