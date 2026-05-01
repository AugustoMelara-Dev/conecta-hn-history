import { cn } from '@/lib/utils';

export default function DataToolbar({ children, actions, className = '' }) {
    return (
        <div className={cn('mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="min-w-0 flex-1">{children}</div>
            {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
        </div>
    );
}
