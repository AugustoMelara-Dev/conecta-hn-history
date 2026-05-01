import { AlertTriangle } from 'lucide-react';

import { Progress } from '@/Components/ui/progress';
import { cn } from '@/lib/utils';

export default function QuotaProgress({
    current = 0,
    limit = 0,
    percentage = 0,
    label = 'uso',
    className = '',
}) {
    const safePercentage = Math.min(Math.max(Number(percentage) || 0, 0), 100);
    const isNearLimit = safePercentage >= 80;

    return (
        <div className={cn('hidden min-w-40 flex-col gap-1 md:flex', className)}>
            <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                <span>{current} / {limit} {label}</span>
                {isNearLimit ? <AlertTriangle aria-hidden="true" className="size-3 text-destructive" /> : null}
            </div>
            <Progress value={safePercentage} aria-label={`Uso de ${label}: ${current} de ${limit}`} />
        </div>
    );
}
