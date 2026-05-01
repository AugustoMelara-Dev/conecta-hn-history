import { CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function SelectableCard({
    children,
    className,
    disabled = false,
    onSelect,
    selected = false,
    testId,
    type = 'button',
    ...props
}) {
    return (
        <button
            type={type}
            onClick={onSelect}
            disabled={disabled}
            aria-pressed={selected}
            className={cn(
                'group relative rounded-lg border p-5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                selected
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border bg-card text-card-foreground shadow-sm hover:border-primary',
                disabled ? 'cursor-not-allowed bg-muted text-muted-foreground opacity-70 shadow-none hover:border-border' : null,
                className
            )}
            data-testid={testId}
            {...props}
        >
            {selected ? (
                <CheckCircle2 className="absolute right-4 top-4 h-4 w-4" aria-hidden="true" />
            ) : null}
            {children}
        </button>
    );
}
